<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockDocumentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Posted => 'success',
        };
    }
}
