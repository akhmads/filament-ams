<?php

namespace Database\Factories;

use App\Enums\AssetDisposalStatus;
use App\Models\AssetDisposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetDisposal>
 */
class AssetDisposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'DSP/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'status' => AssetDisposalStatus::Proposed,
            'disposal_date' => now()->toDateString(),
            'recipient_name' => null,
            'reference_number' => null,
            'reason' => fake()->sentence(),
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => AssetDisposalStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => AssetDisposalStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
