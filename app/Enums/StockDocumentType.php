<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StockDocumentType: string implements HasColor, HasIcon, HasLabel
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Count = 'count';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Goods Receipt',
            self::Issue => 'Goods Issue',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Adjustment',
            self::Count => 'Stock Count',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Issue => 'warning',
            self::Transfer => 'info',
            self::Adjustment => 'gray',
            self::Count => 'primary',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Receipt => 'heroicon-o-arrow-down-tray',
            self::Issue => 'heroicon-o-arrow-up-tray',
            self::Transfer => 'heroicon-o-arrows-right-left',
            self::Adjustment => 'heroicon-o-adjustments-horizontal',
            self::Count => 'heroicon-o-clipboard-document-check',
        };
    }

    /**
     * The document number prefix, e.g. RCV/2609/0001.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Receipt => 'RCV',
            self::Issue => 'ISS',
            self::Transfer => 'TRF',
            self::Adjustment => 'ADJ',
            self::Count => 'OPN',
        };
    }

    /**
     * Writing stock up or down without goods changing hands is where losses get
     * hidden, so posting these needs its own permission.
     */
    public function correctsStock(): bool
    {
        return in_array($this, [self::Adjustment, self::Count], strict: true);
    }

    public function quantityLabel(): string
    {
        return match ($this) {
            self::Adjustment => 'Change (+/−)',
            self::Count => 'Counted Quantity',
            default => 'Quantity',
        };
    }
}
