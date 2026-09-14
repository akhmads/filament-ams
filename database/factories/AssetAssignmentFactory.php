<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\PlacementType;
use App\Models\AssetAssignment;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAssignment>
 */
class AssetAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'BAST/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'type' => AssignmentType::Checkout,
            'assignment_date' => now(),
            'branch_id' => Branch::factory(),
            'to_placement_type' => PlacementType::Employee,
            'status' => AssignmentStatus::Draft,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => AssignmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
