<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LabelCodeType: string implements HasLabel
{
    case Qr = 'qr';
    case Barcode = 'barcode';
    case Both = 'both';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qr => 'QR Code',
            self::Barcode => 'Barcode (Code 128)',
            self::Both => 'QR Code + Barcode',
        };
    }

    public function includesQr(): bool
    {
        return in_array($this, [self::Qr, self::Both], strict: true);
    }

    public function includesBarcode(): bool
    {
        return in_array($this, [self::Barcode, self::Both], strict: true);
    }
}
