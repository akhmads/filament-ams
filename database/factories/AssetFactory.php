<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\PlacementType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('AST-####')),
            'name' => fake()->words(3, true),
            'asset_category_id' => AssetCategory::factory(),
            'branch_id' => Branch::factory(),
            'serial_number' => strtoupper(fake()->bothify('SN######')),
            'acquisition_date' => now()->subMonths(6),
            'acquisition_cost' => fake()->numberBetween(1_000_000, 50_000_000),
            'is_depreciable' => true,
            'depreciation_method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 48,
            'residual_value' => 0,
            'placement_type' => PlacementType::Warehouse,
            'status' => AssetStatus::Available,
            'condition' => AssetCondition::Good,
        ];
    }

    public function inWarehouse(Location $location): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $location->branch_id,
            'placement_type' => PlacementType::Warehouse,
            'current_location_id' => $location->id,
            'current_employee_id' => null,
            'status' => AssetStatus::Available,
        ]);
    }

    public function heldBy(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $employee->branch_id,
            'placement_type' => PlacementType::Employee,
            'current_employee_id' => $employee->id,
            'current_location_id' => null,
            'status' => AssetStatus::InUse,
        ]);
    }

    public function status(AssetStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function warrantyUntil(string $date): static
    {
        return $this->state(fn (): array => [
            'warranty_start' => now()->subYear(),
            'warranty_end' => $date,
        ]);
    }
}
