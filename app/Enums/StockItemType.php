<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockItemType: string implements HasColor, HasLabel
{
    case Consumable = 'consumable';
    case SparePart = 'spare_part';

    public function getLabel(): string
    {
        return match ($this) {
            self::Consumable => 'Consumable',
            self::SparePart => 'Spare Part',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Consumable => 'gray',
            self::SparePart => 'info',
        };
    }
}
