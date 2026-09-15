<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Enums\PlacementType;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Asset extends Model implements HasMedia
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'available',
        'condition' => 'good',
        'placement_type' => 'warehouse',
    ];

    protected $fillable = [
        'uuid', 'code', 'name',
        'asset_category_id', 'brand_id', 'asset_model_id', 'supplier_id', 'parent_id',
        'serial_number', 'manufacture_year',
        'acquisition_date', 'acquisition_cost', 'po_number', 'invoice_number', 'funding_source',
        'is_depreciable', 'depreciation_method', 'useful_life_months', 'residual_value', 'depreciation_start_date',
        'fiscal_group', 'fiscal_method',
        'warranty_start', 'warranty_end', 'warranty_vendor',
        'branch_id', 'department_id', 'placement_type', 'current_location_id', 'current_employee_id',
        'status', 'condition', 'specs', 'notes',
        'label_printed_count', 'label_printed_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'placement_type' => PlacementType::class,
            'depreciation_method' => DepreciationMethod::class,
            'fiscal_group' => FiscalAssetGroup::class,
            'fiscal_method' => DepreciationMethod::class,
            'acquisition_date' => 'date',
            'depreciation_start_date' => 'date',
            'warranty_start' => 'date',
            'warranty_end' => 'date',
            'label_printed_at' => 'datetime',
            'acquisition_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'is_depreciable' => 'boolean',
            'specs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            $asset->uuid ??= (string) Str::uuid7();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('asset');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('documents');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(240)
            ->height(240)
            ->nonQueued();
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<AssetModel, $this> */
    public function assetModel(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function currentEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_employee_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    /** @return HasMany<Asset, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }

    /** @return HasMany<AssetMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('moved_at');
    }

    /** @return HasMany<DepreciationEntry, $this> */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    /** @return HasMany<WorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** @return HasMany<RepairTicket, $this> */
    public function repairTickets(): HasMany
    {
        return $this->hasMany(RepairTicket::class);
    }

    /** @return HasMany<AssetAssignmentItem, $this> */
    public function assignmentItems(): HasMany
    {
        return $this->hasMany(AssetAssignmentItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A one-line summary of where the asset is, for tables and reports.
     */
    public function getCurrentHolderAttribute(): string
    {
        return match ($this->placement_type) {
            PlacementType::Employee => $this->currentEmployee?->name ?? '—',
            PlacementType::Location, PlacementType::Warehouse => $this->currentLocation?->name ?? '—',
            PlacementType::InTransit => 'Dalam perjalanan',
            PlacementType::Vendor => $this->warranty_vendor ?: 'Vendor',
        };
    }

    public function getQrUrlAttribute(): string
    {
        return route('asset.lookup', ['code' => $this->code]);
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_end !== null && $this->warranty_end->isFuture();
    }

    /**
     * An asset can only be handed over when its status allows it and nobody
     * else is holding it.
     */
    public function isAssignable(): bool
    {
        return $this->status->isAssignable() && $this->current_employee_id === null;
    }

    /** @param Builder<Asset> $query */
    public function scopeAssignable(Builder $query): void
    {
        $query->whereIn('status', [AssetStatus::Available->value, AssetStatus::InStorage->value])
            ->whereNull('current_employee_id');
    }

    /** @param Builder<Asset> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', [AssetStatus::Disposed->value, AssetStatus::Lost->value]);
    }

    /** @param Builder<Asset> $query */
    public function scopeWarrantyExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('warranty_end')
            ->whereBetween('warranty_end', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
