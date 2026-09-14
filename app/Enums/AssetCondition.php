<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssetCondition: string implements HasColor, HasLabel
{
    case Good = 'good';
    case MinorDamage = 'minor_damage';
    case MajorDamage = 'major_damage';
    case Broken = 'broken';

    public function getLabel(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::MinorDamage => 'Minor Damage',
            self::MajorDamage => 'Major Damage',
            self::Broken => 'Not Working',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::MinorDamage => 'warning',
            self::MajorDamage => 'danger',
            self::Broken => 'gray',
        };
    }

    /**
     * Wording for printed documents, which stay in Indonesian because they are
     * signed and filed on paper in Indonesia — kept apart from the interface
     * labels, which follow the application language.
     */
    public function printedLabel(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::MinorDamage => 'Rusak Ringan',
            self::MajorDamage => 'Rusak Berat',
            self::Broken => 'Tidak Berfungsi',
        };
    }
}
