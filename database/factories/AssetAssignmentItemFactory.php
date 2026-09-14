<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAssignmentItem>
 */
class AssetAssignmentItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_assignment_id' => AssetAssignment::factory(),
            'asset_id' => Asset::factory(),
            'condition' => AssetCondition::Good,
        ];
    }
}
