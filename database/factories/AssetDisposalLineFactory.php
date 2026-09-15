<?php

namespace Database\Factories;

use App\Enums\DisposalMethod;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetDisposalLine>
 */
class AssetDisposalLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_disposal_id' => AssetDisposal::factory(),
            'asset_id' => Asset::factory(),
            'repair_ticket_id' => null,
            'method' => DisposalMethod::Scrapped,
            'proceeds' => 0,
            'notes' => null,
        ];
    }

    public function sold(string|int $proceeds): static
    {
        return $this->state(fn (): array => [
            'method' => DisposalMethod::Sale,
            'proceeds' => $proceeds,
        ]);
    }
}
