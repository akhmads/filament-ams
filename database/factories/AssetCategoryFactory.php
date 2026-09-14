<?php

namespace Database\Factories;

use App\Enums\DepreciationMethod;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCategory>
 */
class AssetCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CAT-###')),
            'prefix' => strtoupper(fake()->lexify('???')),
            'name' => fake()->words(2, true),
            'depreciation_method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 48,
            'residual_percent' => 0,
            'fiscal_group' => null,
            'fiscal_method' => DepreciationMethod::StraightLine,
            'expense_account_code' => null,
            'expense_account_name' => null,
            'accumulated_account_code' => null,
            'accumulated_account_name' => null,
            'is_depreciable' => true,
            'is_active' => true,
        ];
    }
}
