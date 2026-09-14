<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Models\Concerns\HasTreePath;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, HasTreePath, LogsActivity, NodeTrait, SoftDeletes;

    protected $fillable = [
        'branch_id', 'parent_id', 'code', 'name', 'type', 'pic_name', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_location_id');
    }

    /** @param Builder<Location> $query */
    public function scopeHoldsAssets(Builder $query): void
    {
        $query->whereIn('type', [LocationType::Room->value, LocationType::Warehouse->value]);
    }

    /** @param Builder<Location> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
