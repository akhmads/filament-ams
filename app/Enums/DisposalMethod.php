<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DisposalMethod: string implements HasColor, HasLabel
{
    case Sale = 'sale';
    case TradeIn = 'trade_in';
    case Donation = 'donation';
    case Scrapped = 'scrapped';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sale => 'Sold',
            self::TradeIn => 'Traded In',
            self::Donation => 'Donated',
            self::Scrapped => 'Scrapped',
            self::Lost => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sale => 'success',
            self::TradeIn => 'info',
            self::Donation => 'primary',
            self::Scrapped => 'gray',
            self::Lost => 'danger',
        };
    }

    /**
     * Only a sale or trade-in brings money or value in return.
     */
    public function hasProceeds(): bool
    {
        return in_array($this, [self::Sale, self::TradeIn], strict: true);
    }

    /**
     * Wording for the printed disposal record, which stays in Indonesian because it
     * is signed and filed on paper.
     */
    public function printedLabel(): string
    {
        return match ($this) {
            self::Sale => 'Dijual',
            self::TradeIn => 'Tukar Tambah',
            self::Donation => 'Dihibahkan',
            self::Scrapped => 'Dimusnahkan',
            self::Lost => 'Hilang',
        };
    }
}
