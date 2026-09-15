<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditResult: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Found = 'found';
    case Misplaced = 'misplaced';
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Not Scanned Yet',
            self::Found => 'Found',
            self::Misplaced => 'Wrong Location',
            self::Missing => 'Missing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Found => 'success',
            self::Misplaced => 'warning',
            self::Missing => 'danger',
        };
    }

    /**
     * Wording for the printed audit record, which stays in Indonesian because it is
     * signed and filed on paper.
     */
    public function printedLabel(): string
    {
        return match ($this) {
            self::Pending => 'Belum Diperiksa',
            self::Found => 'Ditemukan',
            self::Misplaced => 'Salah Lokasi',
            self::Missing => 'Tidak Ditemukan',
        };
    }
}
