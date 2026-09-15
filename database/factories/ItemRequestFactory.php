<?php

namespace Database\Factories;

use App\Enums\ItemRequestStatus;
use App\Models\Employee;
use App\Models\ItemRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemRequest>
 */
class ItemRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'REQ/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'status' => ItemRequestStatus::Submitted,
            'employee_id' => Employee::factory(),
            'department_id' => null,
            'request_date' => now()->toDateString(),
            'needed_by' => null,
            'purpose' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ItemRequestStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ItemRequestStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => 'Not needed',
        ]);
    }
}
