<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'employee_number' => fake()->unique()->bothify('EMP-####'),
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'status' => EmployeeStatus::Active,
            'joined_at' => now()->subYear(),
        ];
    }

    public function resigned(): static
    {
        return $this->state(fn (): array => [
            'status' => EmployeeStatus::Resigned,
            'resigned_at' => now(),
        ]);
    }
}
