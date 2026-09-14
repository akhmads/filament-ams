<?php

namespace App\Models;

use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Models\Concerns\HasTreePath;
use Database\Factories\AssetCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AssetCategory extends Model
{
    /** @use HasFactory<AssetCategoryFactory> */
    use HasFactory, HasTreePath, LogsActivity, NodeTrait, SoftDeletes;

    protected $fillable = [
        'parent_id', 'code', 'prefix', 'name', 'description',
        'depreciation_method', 'useful_life_months', 'residual_percent',
        'fiscal_group', 'fiscal_method',
        'expense_account_code', 'expense_account_name',
        'accumulated_account_code', 'accumulated_account_name',
        'is_depreciable', 'requires_maintenance', 'spec_fields', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'depreciation_method' => DepreciationMethod::class,
            'fiscal_group' => FiscalAssetGroup::class,
            'fiscal_method' => DepreciationMethod::class,
            'residual_percent' => 'decimal:2',
            'is_depreciable' => 'boolean',
            'requires_maintenance' => 'boolean',
            'is_active' => 'boolean',
            'spec_fields' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * The specification field definitions, inherited from the parent when empty.
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function resolvedSpecFields(): array
    {
        if (filled($this->spec_fields)) {
            return $this->spec_fields;
        }

        foreach ($this->ancestors()->defaultOrder('desc')->get() as $ancestor) {
            if (filled($ancestor->spec_fields)) {
                return $ancestor->spec_fields;
            }
        }

        return [];
    }
}
