<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DepreciationMethod: string implements HasLabel
{
    case None = 'none';
    case StraightLine = 'straight_line';
    case DoubleDeclining = 'double_declining';
    case SumOfYearsDigits = 'sum_of_years_digits';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'No Depreciation',
            self::StraightLine => 'Straight Line',
            self::DoubleDeclining => 'Double Declining Balance',
            self::SumOfYearsDigits => 'Sum of Years Digits',
        };
    }
}
