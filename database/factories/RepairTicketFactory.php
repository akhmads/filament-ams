<?php

namespace Database\Factories;

use App\Enums\RepairPriority;
use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Models\Asset;
use App\Models\RepairTicket;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairTicket>
 */
class RepairTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'RPR/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'asset_id' => Asset::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'priority' => RepairPriority::Normal,
            'status' => RepairTicketStatus::Reported,
            'reported_at' => now(),
            'is_under_warranty' => false,
            'estimated_cost' => 0,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RepairTicketStatus::Verified,
            'repair_type' => $attributes['repair_type'] ?? RepairType::Internal,
            'verified_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->verified()->state(fn (): array => [
            'status' => RepairTicketStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function atVendor(): static
    {
        return $this->state(fn (): array => [
            'repair_type' => RepairType::Vendor,
            'supplier_id' => Supplier::factory(),
        ]);
    }

    public function inRepair(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => RepairTicketStatus::InRepair,
            'started_at' => now(),
        ]);
    }

    public function repaired(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => RepairTicketStatus::Repaired,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => RepairTicketStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => 'Not a fault',
        ]);
    }
}
