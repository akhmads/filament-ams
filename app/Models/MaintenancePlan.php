<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceIntervalUnit;
use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Recurring preventive maintenance for one asset or a whole category.
 */
class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column defaults so a new plan reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'include_subcategories' => true,
        'lead_days' => 7,
        'estimated_cost' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'name', 'asset_id', 'asset_category_id', 'include_subcategories',
        'interval_value', 'interval_unit', 'start_date', 'lead_days',
        'assigned_to', 'supplier_id', 'estimated_cost', 'estimated_minutes', 'checklist',
        'is_active', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'include_subcategories' => 'boolean',
            'interval_value' => 'integer',
            'interval_unit' => MaintenanceIntervalUnit::class,
            'start_date' => 'date',
            'lead_days' => 'integer',
            'estimated_cost' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'checklist' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'asset_id', 'asset_category_id', 'interval_value', 'interval_unit', 'start_date', 'lead_days', 'assigned_to', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('maintenance');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<WorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isForCategory(): bool
    {
        return $this->asset_category_id !== null;
    }

    public function getScheduleLabelAttribute(): string
    {
        return $this->interval_unit->describe($this->interval_value);
    }

    /**
     * The assets this plan maintains. Disposed, lost and retired assets are left
     * out: nobody services an asset that is gone or out of use.
     *
     * @return Builder<Asset>
     */
    public function targetAssets(): Builder
    {
        $query = Asset::query()->whereNotIn('status', [
            AssetStatus::Disposed->value,
            AssetStatus::Lost->value,
            AssetStatus::Retired->value,
        ]);

        if (! $this->isForCategory()) {
            return $query->whereKey($this->asset_id);
        }

        $categoryIds = $this->include_subcategories
            ? AssetCategory::query()->whereDescendantOrSelf($this->asset_category_id)->pluck('id')->all()
            : [$this->asset_category_id];

        return $query->whereIn('asset_category_id', $categoryIds);
    }
}
