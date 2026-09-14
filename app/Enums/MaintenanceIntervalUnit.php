<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceIntervalUnit: string implements HasLabel
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function getLabel(): string
    {
        return match ($this) {
            self::Day => 'Days',
            self::Week => 'Weeks',
            self::Month => 'Months',
            self::Year => 'Years',
        };
    }

    /**
     * Months and years never overflow into the next month, so a plan due on the
     * 31st falls on the last day of shorter months instead of skipping one.
     */
    public function addTo(CarbonImmutable $date, int $count): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->addDays($count),
            self::Week => $date->addWeeks($count),
            self::Month => $date->addMonthsNoOverflow($count),
            self::Year => $date->addYearsNoOverflow($count),
        };
    }

    public function describe(int $count): string
    {
        $unit = strtolower($this->getLabel());

        return $count === 1
            ? 'Every '.rtrim($unit, 's')
            : "Every {$count} {$unit}";
    }
}
