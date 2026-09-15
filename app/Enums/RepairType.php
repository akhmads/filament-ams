<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RepairType: string implements HasLabel
{
    case Internal = 'internal';
    case Vendor = 'vendor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Internal => 'In-house',
            self::Vendor => 'Service Vendor',
        };
    }
}
