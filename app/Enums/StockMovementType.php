<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';
    case Count = 'count';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Issue => 'Issue',
            self::TransferOut => 'Transfer Out',
            self::TransferIn => 'Transfer In',
            self::Adjustment => 'Adjustment',
            self::Count => 'Stock Count',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Receipt, self::TransferIn => 'success',
            self::Issue, self::TransferOut => 'warning',
            self::Adjustment => 'gray',
            self::Count => 'primary',
        };
    }
}
