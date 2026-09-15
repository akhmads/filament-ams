<?php

namespace App\Services;

use App\Enums\AssetDisposalStatus;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Exceptions\AssetDisposalException;
use App\Exceptions\AssetTransitionException;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalLine;
use App\Models\User;
use App\Support\Money;
use App\Support\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves a disposal through proposed → approved → completed. A proposal can be
 * rejected, and an open disposal cancelled.
 *
 * Completing it fixes each asset's book value and gain or loss in both books,
 * then records a disposal movement that makes the asset Disposed. From then on
 * the asset is no longer depreciated.
 */
class AssetDisposalService
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AssetMovementRecorder $recorder,
        private readonly DisposalValuation $valuation,
        private readonly DisposalNotifier $notifier,
    ) {}

    /**
     * A disposal number shaped DSP/YYMM/SEQ.
     */
    public function nextNumber(): string
    {
        return $this->numbers->document('asset_disposal', 'DSP');
    }

    public function approve(AssetDisposal $disposal, User $approvedBy): AssetDisposal
    {
        $this->ensureStatus($disposal, 'approved', AssetDisposalStatus::Proposed);

        foreach ($this->linesOf($disposal) as $line) {
            $this->ensureDisposable($line->asset, $disposal);
        }

        $disposal->forceFill([
            'status' => AssetDisposalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approvedBy->id,
        ])->save();

        $this->notifier->disposalDecided($disposal, $approvedBy);

        return $disposal;
    }

    public function reject(AssetDisposal $disposal, string $reason, User $rejectedBy): AssetDisposal
    {
        $this->ensureStatus($disposal, 'rejected', AssetDisposalStatus::Proposed);

        $disposal->forceFill([
            'status' => AssetDisposalStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejectedBy->id,
            'rejection_reason' => $reason,
        ])->save();

        $this->notifier->disposalDecided($disposal, $rejectedBy);

        return $disposal;
    }

    public function cancel(AssetDisposal $disposal, string $reason): AssetDisposal
    {
        $this->ensureStatus($disposal, 'cancelled', AssetDisposalStatus::Proposed, AssetDisposalStatus::Approved);

        $disposal->forceFill([
            'status' => AssetDisposalStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $disposal;
    }

    /**
     * Writes every asset on the disposal off, or none of them.
     */
    public function complete(AssetDisposal $disposal, User $completedBy): AssetDisposal
    {
        $this->ensureStatus($disposal, 'completed', AssetDisposalStatus::Approved);

        if ($disposal->disposal_date->isAfter(today())) {
            throw AssetDisposalException::dateInFuture($disposal);
        }

        $lines = $this->linesOf($disposal);
        $disposalDate = CarbonImmutable::parse($disposal->disposal_date);

        return DB::transaction(function () use ($disposal, $completedBy, $lines, $disposalDate): AssetDisposal {
            foreach ($lines as $line) {
                $this->writeOff($disposal, $line, $disposalDate);
            }

            $disposal->forceFill([
                'status' => AssetDisposalStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $completedBy->id,
            ])->save();

            return $disposal;
        });
    }

    private function writeOff(AssetDisposal $disposal, AssetDisposalLine $line, CarbonImmutable $disposalDate): void
    {
        $asset = $line->asset;

        $this->ensureDisposable($asset, $disposal);

        $proceeds = Money::toSen($line->proceeds);

        if ($proceeds > 0 && ! $line->method->hasProceeds()) {
            throw AssetDisposalException::unexpectedProceeds($asset, $line->method);
        }

        $value = $this->valuation->value($asset, $disposalDate);

        try {
            $movement = $this->recorder->record(
                asset: $asset,
                to: Placement::fromAsset($asset),
                movementType: MovementType::Disposal,
                status: AssetStatus::Disposed,
                reference: $disposal,
                notes: "{$line->method->getLabel()} — {$disposal->number}",
                movedAt: $disposalDate,
            );
        } catch (AssetTransitionException $exception) {
            throw AssetDisposalException::assetCannotMove($disposal, $exception);
        }

        $line->forceFill([
            'acquisition_cost' => Money::toRupiah($value['cost']),
            'commercial_accumulated' => Money::toRupiah($value['commercial']['accumulated']),
            'commercial_book_value' => Money::toRupiah($value['commercial']['book_value']),
            'commercial_gain_loss' => Money::toRupiah($proceeds - $value['commercial']['book_value']),
            'fiscal_accumulated' => Money::toRupiah($value['fiscal']['accumulated']),
            'fiscal_book_value' => Money::toRupiah($value['fiscal']['book_value']),
            'fiscal_gain_loss' => Money::toRupiah($proceeds - $value['fiscal']['book_value']),
            'disposal_movement_id' => $movement->id,
        ])->save();
    }

    private function ensureDisposable(Asset $asset, AssetDisposal $disposal): void
    {
        if (! $asset->status->isDisposable()) {
            throw AssetDisposalException::notDisposable($asset);
        }

        if ($asset->current_employee_id !== null) {
            throw AssetDisposalException::stillHeld($asset);
        }

        $other = AssetDisposal::query()
            ->open()
            ->whereKeyNot($disposal->getKey())
            ->whereHas('lines', fn (Builder $query) => $query->where('asset_id', $asset->id))
            ->first();

        if ($other !== null) {
            throw AssetDisposalException::alreadyProposed($asset, $other);
        }
    }

    /**
     * @return Collection<int, AssetDisposalLine>
     */
    private function linesOf(AssetDisposal $disposal): Collection
    {
        $lines = $disposal->lines()->with('asset')->orderBy('id')->get();

        if ($lines->isEmpty()) {
            throw AssetDisposalException::noLines($disposal);
        }

        return $lines;
    }

    private function ensureStatus(AssetDisposal $disposal, string $action, AssetDisposalStatus ...$allowed): void
    {
        if (! in_array($disposal->status, $allowed, strict: true)) {
            throw AssetDisposalException::notInStatus($disposal, $action, ...$allowed);
        }
    }
}
