<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMovement>
 */
class AssetMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'movement_type' => MovementType::Initial,
            'moved_at' => now(),
            'to_placement_type' => PlacementType::Warehouse,
        ];
    }
}
