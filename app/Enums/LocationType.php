<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LocationType: string implements HasIcon, HasLabel
{
    case Site = 'site';
    case Building = 'building';
    case Floor = 'floor';
    case Room = 'room';
    case Warehouse = 'warehouse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Site => 'Site / Area',
            self::Building => 'Building',
            self::Floor => 'Floor',
            self::Room => 'Room',
            self::Warehouse => 'Warehouse',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Site => 'heroicon-o-globe-asia-australia',
            self::Building => 'heroicon-o-building-office-2',
            self::Floor => 'heroicon-o-bars-3-bottom-left',
            self::Room => 'heroicon-o-home',
            self::Warehouse => 'heroicon-o-building-storefront',
        };
    }

    /**
     * Only these types may hold assets directly.
     */
    public function canHoldAssets(): bool
    {
        return in_array($this, [self::Room, self::Warehouse], strict: true);
    }
}
