<?php

namespace App\Services;

use App\Enums\AssetAuditStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AuditFollowUp;
use App\Enums\AuditResult;
use App\Enums\MovementType;
use App\Exceptions\AssetAuditException;
use App\Exceptions\AssetTransitionException;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Models\User;
use App\Support\Placement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs an asset audit: counting → under review → completed, or cancelled.
 *
 * Counting compares what is scanned with the register. Nothing on the register
 * changes until a reviewer holding the close permission picks corrections for
 * the findings and closes the audit; each correction is then an audit adjustment
 * in the movement ledger.
 */
class AssetAuditService
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AssetMovementRecorder $recorder,
    ) {}

    /**
     * An audit number shaped AUD/YYMM/SEQ.
     */
    public function nextNumber(): string
    {
        return $this->numbers->document('asset_audit', 'AUD');
    }

    /**
     * Lists the assets the audit expects to find: every asset still in service in
     * the location (with everything under it), department and category chosen,
     * as the register stands now.
     */
    public function listExpectedAssets(AssetAudit $audit): int
    {
        $this->ensureStatus($audit, 'listed', AssetAuditStatus::InProgress);

        $query = Asset::query()
            ->active()
            ->when($audit->location_id !== null, fn ($query) => $query->whereIn(
                'current_location_id',
                Location::query()->whereDescendantOrSelf($audit->location_id)->pluck('id'),
            ))
            ->when($audit->department_id !== null, fn ($query) => $query->where('department_id', $audit->department_id))
            ->when($audit->asset_category_id !== null, fn ($query) => $query->whereIn(
                'asset_category_id',
                AssetCategory::query()->whereDescendantOrSelf($audit->asset_category_id)->pluck('id'),
            ));

        $listed = 0;

        DB::transaction(function () use ($audit, $query, &$listed): void {
            $query->chunkById(self::CHUNK_SIZE, function (Collection $assets) use ($audit, &$listed): void {
                $now = now();

                AssetAuditLine::query()->insertOrIgnore($assets->map(fn (Asset $asset): array => [
                    'asset_audit_id' => $audit->id,
                    'asset_id' => $asset->id,
                    'is_expected' => true,
                    'expected_location_id' => $asset->current_location_id,
                    'expected_condition' => $asset->condition->value,
                    'result' => AuditResult::Pending->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());

                $listed += $assets->count();
            });

            $audit->forceFill(['started_at' => $audit->started_at ?? now()])->save();
        });

        return $listed;
    }

    /**
     * Records that an asset was seen in a room. Scanning it again replaces the
     * earlier scan; an asset not on the list is added to it.
     */
    public function scan(AssetAudit $audit, string $code, Location $foundIn, User $scannedBy, ?AssetCondition $condition = null): AssetAuditLine
    {
        $this->ensureStatus($audit, 'scanned', AssetAuditStatus::InProgress);

        if (! $foundIn->type->canHoldAssets()) {
            throw AssetAuditException::notARoom($foundIn);
        }

        $code = trim($code);
        $asset = Asset::query()->where('code', $code)->first() ?? throw AssetAuditException::unknownCode($code);

        if ($asset->status === AssetStatus::Disposed) {
            throw AssetAuditException::disposedAsset($asset);
        }

        return DB::transaction(function () use ($audit, $asset, $foundIn, $scannedBy, $condition): AssetAuditLine {
            $line = AssetAuditLine::query()
                ->where('asset_audit_id', $audit->id)
                ->where('asset_id', $asset->id)
                ->lockForUpdate()
                ->first()
                ?? new AssetAuditLine([
                    'asset_audit_id' => $audit->id,
                    'asset_id' => $asset->id,
                    'is_expected' => false,
                    'expected_location_id' => $asset->current_location_id,
                    'expected_condition' => $asset->condition,
                ]);

            // An asset held by an employee has no room on record, so any room counts as found.
            $isMisplaced = $line->expected_location_id !== null && (int) $line->expected_location_id !== $foundIn->id;

            $line->forceFill([
                'scanned_at' => now(),
                'scanned_by' => $scannedBy->id,
                'scanned_location_id' => $foundIn->id,
                'observed_condition' => $condition ?? $line->expected_condition,
                'result' => $isMisplaced ? AuditResult::Misplaced : AuditResult::Found,
            ])->save();

            return $line->setRelation('asset', $asset)->setRelation('scannedLocation', $foundIn);
        });
    }

    /**
     * Ends scanning. Assets never scanned are missing; misplaced assets and changed
     * conditions are proposed for correction, while marking an asset lost is left
     * as a deliberate choice.
     */
    public function finishCounting(AssetAudit $audit, User $countedBy): AssetAudit
    {
        $this->ensureStatus($audit, 'finished', AssetAuditStatus::InProgress);

        return DB::transaction(function () use ($audit, $countedBy): AssetAudit {
            $audit->lines()->where('result', AuditResult::Pending->value)->update(['result' => AuditResult::Missing->value]);

            $audit->lines()->whereNotNull('scanned_at')->eachById(function (AssetAuditLine $line): void {
                $line->forceFill([
                    'apply_relocation' => $line->result === AuditResult::Misplaced,
                    'apply_condition' => $line->hasConditionChanged(),
                ])->save();
            }, self::CHUNK_SIZE);

            $audit->forceFill([
                'status' => AssetAuditStatus::UnderReview,
                'counted_at' => now(),
                'counted_by' => $countedBy->id,
            ])->save();

            return $audit;
        });
    }

    public function setFollowUp(AssetAuditLine $line, AuditFollowUp $followUp, bool $isChosen): AssetAuditLine
    {
        $audit = $line->assetAudit()->firstOrFail();

        $this->ensureStatus($audit, 'reviewed', AssetAuditStatus::UnderReview);

        if ($isChosen && ! $followUp->isApplicableTo($line)) {
            throw AssetAuditException::followUpNotApplicable($line, $followUp);
        }

        $line->forceFill([$followUp->value => $isChosen])->save();

        return $line;
    }

    /**
     * Applies every chosen correction and closes the audit — all of them, or none
     * when any asset cannot be corrected.
     */
    public function close(AssetAudit $audit, User $closedBy): AssetAudit
    {
        $this->ensureStatus($audit, 'closed', AssetAuditStatus::UnderReview);

        return DB::transaction(function () use ($audit, $closedBy): AssetAudit {
            $lines = $audit->lines()
                ->where(fn ($query) => $query->where('apply_relocation', true)->orWhere('apply_condition', true)->orWhere('mark_lost', true))
                ->with(['asset', 'scannedLocation'])
                ->orderBy('id')
                ->get();

            foreach ($lines as $line) {
                $this->applyFollowUps($audit, $line);
            }

            $audit->forceFill([
                'status' => AssetAuditStatus::Completed,
                'closed_at' => now(),
                'closed_by' => $closedBy->id,
            ])->save();

            return $audit;
        });
    }

    public function cancel(AssetAudit $audit, string $reason): AssetAudit
    {
        $this->ensureStatus($audit, 'cancelled', AssetAuditStatus::InProgress, AssetAuditStatus::UnderReview);

        $audit->forceFill([
            'status' => AssetAuditStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $audit;
    }

    private function applyFollowUps(AssetAudit $audit, AssetAuditLine $line): void
    {
        $asset = $line->asset;

        // A correction based on a stale observation would undo a real move made since.
        $observedAt = $line->scanned_at ?? $audit->started_at;
        $hasMovedSince = AssetMovement::query()
            ->where('asset_id', $asset->id)
            ->where('created_at', '>', $observedAt)
            ->exists();

        if ($hasMovedSince) {
            throw AssetAuditException::movedSinceAudit($audit, $asset);
        }

        try {
            $movement = $line->mark_lost
                ? $this->recorder->record(
                    asset: $asset,
                    to: Placement::fromAsset($asset),
                    movementType: MovementType::AuditAdjustment,
                    status: AssetStatus::Lost,
                    reference: $audit,
                    notes: "Not found in audit {$audit->number}",
                )
                : $this->recordCorrection($audit, $line, $asset);
        } catch (AssetTransitionException $exception) {
            throw AssetAuditException::cannotAdjust($audit, $exception);
        }

        $line->forceFill([
            'applied_at' => now(),
            'adjustment_movement_id' => $movement->id,
        ])->save();
    }

    private function recordCorrection(AssetAudit $audit, AssetAuditLine $line, Asset $asset): AssetMovement
    {
        $to = $line->apply_relocation && $line->scannedLocation !== null
            ? Placement::toLocation($line->scannedLocation)
            : Placement::fromAsset($asset);

        return $this->recorder->record(
            asset: $asset,
            to: $to,
            movementType: MovementType::AuditAdjustment,
            // Keep the status unless the asset moves between a room and a warehouse.
            status: $to->type === $asset->placement_type ? $asset->status : null,
            condition: $line->apply_condition ? $line->observed_condition : null,
            reference: $audit,
            notes: $line->apply_relocation
                ? "Found in {$line->scannedLocation?->name} during audit {$audit->number}"
                : "Condition checked in audit {$audit->number}",
        );
    }

    private function ensureStatus(AssetAudit $audit, string $action, AssetAuditStatus ...$allowed): void
    {
        if (! in_array($audit->status, $allowed, strict: true)) {
            throw AssetAuditException::notInStatus($audit, $action, ...$allowed);
        }
    }
}
