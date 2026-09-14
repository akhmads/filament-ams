<?php

namespace Database\Factories;

use App\Enums\DepreciationBook;
use App\Models\Asset;
use App\Models\DepreciationEntry;
use App\Models\DepreciationPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepreciationEntry>
 */
class DepreciationEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'depreciation_period_id' => DepreciationPeriod::factory(),
            'asset_id' => Asset::factory(),
            'book' => DepreciationBook::Commercial,
            'period' => now()->startOfMonth()->toDateString(),
            'opening_book_value' => '1000000.00',
            'amount' => '100000.00',
            'accumulated' => '100000.00',
            'closing_book_value' => '900000.00',
            'is_final' => false,
        ];
    }
}
