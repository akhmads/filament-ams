<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use Database\Factories\AssetMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The asset movement ledger. Rows are append-only — a correction is a new
 * movement, never an edit of an existing row.
 */
class AssetMovement extends Model
{
    /** @use HasFactory<AssetMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_id', 'movement_type', 'moved_at',
        'from_placement_type', 'from_branch_id', 'from_location_id', 'from_employee_id',
        'to_placement_type', 'to_branch_id', 'to_location_id', 'to_employee_id',
        'condition_before', 'condition_after', 'status_before', 'status_after',
        'reference_type', 'reference_id', 'notes', 'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'moved_at' => 'datetime',
            'from_placement_type' => PlacementType::class,
            'to_placement_type' => PlacementType::class,
            'condition_before' => AssetCondition::class,
            'condition_after' => AssetCondition::class,
            'status_before' => AssetStatus::class,
            'status_after' => AssetStatus::class,
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFromDescriptionAttribute(): string
    {
        return $this->describe($this->from_placement_type, $this->fromEmployee, $this->fromLocation);
    }

    public function getToDescriptionAttribute(): string
    {
        return $this->describe($this->to_placement_type, $this->toEmployee, $this->toLocation);
    }

    private function describe(?PlacementType $type, ?Employee $employee, ?Location $location): string
    {
        return match ($type) {
            PlacementType::Employee => $employee?->name ?? '—',
            PlacementType::Location, PlacementType::Warehouse => $location?->name ?? '—',
            PlacementType::InTransit => 'Dalam perjalanan',
            PlacementType::Vendor => 'Vendor',
            null => '—',
        };
    }
}
