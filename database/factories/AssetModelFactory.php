<?php

namespace Database\Factories;

use App\Models\AssetModel;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetModel>
 */
class AssetModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->unique()->bothify('Model-###'),
            'model_number' => fake()->bothify('MN-####'),
            'is_active' => true,
        ];
    }
}
