<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The two books an asset is depreciated in: the company's own accounts, and the
 * tax computation under UU PPh Pasal 11.
 */
enum DepreciationBook: string implements HasColor, HasLabel
{
    case Commercial = 'commercial';
    case Fiscal = 'fiscal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Commercial => 'Commercial',
            self::Fiscal => 'Fiscal (Tax)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Commercial => 'primary',
            self::Fiscal => 'warning',
        };
    }
}
