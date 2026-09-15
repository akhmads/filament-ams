<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AuditResult;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetAuditLine>
 */
class AssetAuditLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_audit_id' => AssetAudit::factory(),
            'asset_id' => Asset::factory(),
            'is_expected' => true,
            'expected_location_id' => null,
            'expected_condition' => AssetCondition::Good,
            'result' => AuditResult::Pending,
        ];
    }

    /**
     * Scanned in a room other than the one on record.
     */
    public function misplaced(Location $recordedIn, Location $foundIn): static
    {
        return $this->state(fn (): array => [
            'expected_location_id' => $recordedIn->id,
            'scanned_location_id' => $foundIn->id,
            'scanned_at' => now(),
            'observed_condition' => AssetCondition::Good,
            'result' => AuditResult::Misplaced,
        ]);
    }
}
