<?php

namespace Database\Factories;

use App\Enums\MaintenanceIntervalUnit;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Quarterly service',
            'asset_id' => Asset::factory(),
            'asset_category_id' => null,
            'include_subcategories' => true,
            'interval_value' => 3,
            'interval_unit' => MaintenanceIntervalUnit::Month,
            'start_date' => now()->startOfMonth()->toDateString(),
            'lead_days' => 7,
            'assigned_to' => null,
            'supplier_id' => null,
            'estimated_cost' => 0,
            'estimated_minutes' => null,
            'checklist' => ['Clean the unit', 'Check for damage'],
            'is_active' => true,
        ];
    }

    public function forCategory(AssetCategory $category): static
    {
        return $this->state(fn (): array => [
            'asset_id' => null,
            'asset_category_id' => $category->id,
        ]);
    }

    public function every(int $value, MaintenanceIntervalUnit $unit): static
    {
        return $this->state(fn (): array => [
            'interval_value' => $value,
            'interval_unit' => $unit,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
