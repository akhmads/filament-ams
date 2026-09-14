<?php

namespace Database\Factories;

use App\Enums\DepreciationBook;
use App\Enums\DepreciationPeriodStatus;
use App\Models\DepreciationPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepreciationPeriod>
 */
class DepreciationPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book' => DepreciationBook::Commercial,
            'period' => now()->startOfMonth()->toDateString(),
            'status' => DepreciationPeriodStatus::Draft,
            'asset_count' => 0,
            'total_amount' => 0,
            'calculated_at' => null,
            'posted_at' => null,
            'posted_by' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (): array => [
            'status' => DepreciationPeriodStatus::Posted,
            'posted_at' => now(),
        ]);
    }
}
