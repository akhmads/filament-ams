<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tax asset groups, with the useful lives and rates set by UU PPh Pasal 11
 * ayat (6) and restated in PMK 72/2023.
 *
 * Kept as an enum rather than editable data: the figures are set by statute,
 * and an operator changing them would silently misstate the tax computation.
 */
enum FiscalAssetGroup: string implements HasLabel
{
    case GroupOne = 'group_1';
    case GroupTwo = 'group_2';
    case GroupThree = 'group_3';
    case GroupFour = 'group_4';
    case PermanentBuilding = 'building_permanent';
    case NonPermanentBuilding = 'building_non_permanent';

    public function getLabel(): string
    {
        return match ($this) {
            self::GroupOne => 'Group 1 — 4 years',
            self::GroupTwo => 'Group 2 — 8 years',
            self::GroupThree => 'Group 3 — 16 years',
            self::GroupFour => 'Group 4 — 20 years',
            self::PermanentBuilding => 'Permanent Building — 20 years',
            self::NonPermanentBuilding => 'Non-permanent Building — 10 years',
        };
    }

    public function usefulLifeMonths(): int
    {
        return match ($this) {
            self::GroupOne => 48,
            self::GroupTwo => 96,
            self::GroupThree => 192,
            self::GroupFour, self::PermanentBuilding => 240,
            self::NonPermanentBuilding => 120,
        };
    }

    public function isBuilding(): bool
    {
        return in_array($this, [self::PermanentBuilding, self::NonPermanentBuilding], strict: true);
    }

    /**
     * Pasal 11 ayat (2) allows declining balance for tangible assets other than
     * buildings only.
     */
    public function allowsDecliningBalance(): bool
    {
        return ! $this->isBuilding();
    }

    /** The statutory straight-line rate, for display. */
    public function straightLineRate(): string
    {
        return match ($this) {
            self::GroupOne => '25%',
            self::GroupTwo => '12.5%',
            self::GroupThree => '6.25%',
            self::GroupFour, self::PermanentBuilding => '5%',
            self::NonPermanentBuilding => '10%',
        };
    }

    /** The statutory declining-balance rate, or null where it is not allowed. */
    public function decliningBalanceRate(): ?string
    {
        return match ($this) {
            self::GroupOne => '50%',
            self::GroupTwo => '25%',
            self::GroupThree => '12.5%',
            self::GroupFour => '10%',
            self::PermanentBuilding, self::NonPermanentBuilding => null,
        };
    }

    /**
     * PMK 72/2023: an asset type not listed in the regulation's appendix uses the
     * Group 3 useful life.
     */
    public static function forUnlistedAssets(): self
    {
        return self::GroupThree;
    }
}
