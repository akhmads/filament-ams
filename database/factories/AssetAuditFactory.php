<?php

namespace Database\Factories;

use App\Enums\AssetAuditStatus;
use App\Models\AssetAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAudit>
 */
class AssetAuditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'AUD/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'title' => 'Stock take '.fake()->words(2, true),
            'status' => AssetAuditStatus::InProgress,
            'location_id' => null,
            'department_id' => null,
            'asset_category_id' => null,
            'notes' => null,
            'started_at' => now(),
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => AssetAuditStatus::UnderReview,
            'counted_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->underReview()->state(fn (): array => [
            'status' => AssetAuditStatus::Completed,
            'closed_at' => now(),
        ]);
    }
}
