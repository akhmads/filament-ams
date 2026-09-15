<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AuditResult;
use Database\Factories\AssetAuditLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAuditLine extends Model
{
    /** @use HasFactory<AssetAuditLineFactory> */
    use HasFactory;

    /**
     * Mirrored from the column defaults so a new line reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_expected' => true,
        'result' => 'pending',
        'apply_relocation' => false,
        'apply_condition' => false,
        'mark_lost' => false,
    ];

    protected $fillable = [
        'asset_audit_id', 'asset_id', 'is_expected', 'expected_location_id', 'expected_condition', 'result',
        'scanned_at', 'scanned_by', 'scanned_location_id', 'observed_condition',
        'apply_relocation', 'apply_condition', 'mark_lost', 'applied_at', 'adjustment_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'is_expected' => 'boolean',
            'expected_condition' => AssetCondition::class,
            'result' => AuditResult::class,
            'scanned_at' => 'datetime',
            'observed_condition' => AssetCondition::class,
            'apply_relocation' => 'boolean',
            'apply_condition' => 'boolean',
            'mark_lost' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AssetAudit, $this> */
    public function assetAudit(): BelongsTo
    {
        return $this->belongsTo(AssetAudit::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function expectedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'expected_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function scannedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'scanned_location_id');
    }

    /** @return BelongsTo<User, $this> */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /** @return BelongsTo<AssetMovement, $this> */
    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(AssetMovement::class, 'adjustment_movement_id');
    }

    public function hasConditionChanged(): bool
    {
        return $this->scanned_at !== null
            && $this->observed_condition !== null
            && $this->observed_condition !== $this->expected_condition;
    }

    public function hasFollowUp(): bool
    {
        return $this->apply_relocation || $this->apply_condition || $this->mark_lost;
    }

    /**
     * A one-line account of a scan, for the person scanning.
     */
    public function scanSummary(): string
    {
        $foundIn = $this->scannedLocation?->name ?? 'an unknown place';

        $summary = match (true) {
            ! $this->is_expected => "Not on the audit list — found in {$foundIn}.",
            $this->result === AuditResult::Misplaced => "Found in {$foundIn}, but recorded in {$this->expectedLocation?->name}.",
            default => 'Found where it is recorded.',
        };

        return $this->hasConditionChanged()
            ? "{$summary} Condition now {$this->observed_condition->getLabel()}."
            : $summary;
    }
}
