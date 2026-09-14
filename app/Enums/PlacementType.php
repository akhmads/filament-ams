<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PlacementType: string implements HasColor, HasIcon, HasLabel
{
    case Employee = 'employee';
    case Location = 'location';
    case Warehouse = 'warehouse';
    case InTransit = 'in_transit';
    case Vendor = 'vendor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Employee => 'With Employee',
            self::Location => 'In a Room',
            self::Warehouse => 'In a Warehouse',
            self::InTransit => 'In Transit',
            self::Vendor => 'At Vendor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Employee => 'info',
            self::Location => 'primary',
            self::Warehouse => 'gray',
            self::InTransit => 'warning',
            self::Vendor => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Employee => 'heroicon-o-user',
            self::Location => 'heroicon-o-map-pin',
            self::Warehouse => 'heroicon-o-building-storefront',
            self::InTransit => 'heroicon-o-truck',
            self::Vendor => 'heroicon-o-briefcase',
        };
    }

    public function requiresEmployee(): bool
    {
        return $this === self::Employee;
    }

    public function requiresLocation(): bool
    {
        return in_array($this, [self::Location, self::Warehouse], strict: true);
    }

    /**
     * Wording for printed documents, which stay in Indonesian because they are
     * signed and filed on paper in Indonesia — kept apart from the interface
     * labels, which follow the application language.
     */
    public function printedLabel(): string
    {
        return match ($this) {
            self::Employee => 'Dipegang Karyawan',
            self::Location => 'Ditempatkan di Ruangan',
            self::Warehouse => 'Disimpan di Gudang',
            self::InTransit => 'Dalam Perjalanan',
            self::Vendor => 'Di Vendor',
        };
    }
}
