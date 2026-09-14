<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => strtoupper(fake()->unique()->bothify('LOC-###')),
            'name' => 'Ruang '.fake()->word(),
            'type' => LocationType::Room,
            'is_active' => true,
        ];
    }

    public function warehouse(): static
    {
        return $this->state(fn (): array => [
            'type' => LocationType::Warehouse,
            'name' => 'Gudang '.fake()->word(),
        ]);
    }

    /**
     * A floor may not hold assets — useful for testing the rejection.
     */
    public function floor(): static
    {
        return $this->state(fn (): array => ['type' => LocationType::Floor]);
    }
}
