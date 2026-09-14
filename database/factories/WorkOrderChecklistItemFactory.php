<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderChecklistItem>
 */
class WorkOrderChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'sort_order' => 1,
            'task' => fake()->sentence(4),
            'is_done' => false,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'is_done' => true,
            'done_at' => now(),
        ]);
    }
}
