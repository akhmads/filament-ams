<?php

namespace Database\Factories;

use App\Enums\StockItemType;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('STK-####')),
            'name' => fake()->words(2, true),
            'item_type' => StockItemType::Consumable,
            'unit' => 'pcs',
            'minimum_quantity' => 0,
            'reorder_quantity' => null,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function sparePart(): static
    {
        return $this->state(fn (): array => ['item_type' => StockItemType::SparePart]);
    }

    public function minimum(string|int $quantity, string|int|null $reorderQuantity = null): static
    {
        return $this->state(fn (): array => [
            'minimum_quantity' => $quantity,
            'reorder_quantity' => $reorderQuantity,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
