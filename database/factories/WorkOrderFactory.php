<?php

namespace Database\Factories;

use App\Enums\WorkOrderResult;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'WO/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'maintenance_plan_id' => null,
            'asset_id' => Asset::factory(),
            'title' => fake()->sentence(3),
            'due_date' => now()->toDateString(),
            'status' => WorkOrderStatus::Open,
            'estimated_cost' => 0,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkOrderStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkOrderStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'result' => WorkOrderResult::Ok,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'No longer needed',
        ]);
    }
}
