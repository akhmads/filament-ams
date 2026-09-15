<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalendarEntryKind: string implements HasLabel
{
    case WorkOrder = 'work_order';
    case Repair = 'repair';
    case Planned = 'planned';

    public function getLabel(): string
    {
        return match ($this) {
            self::WorkOrder => 'Work Orders',
            self::Repair => 'Repair Tickets',
            self::Planned => 'Planned Maintenance',
        };
    }

    /**
     * What the calendar legend says about the entries of this kind.
     */
    public function description(): string
    {
        return match ($this) {
            self::WorkOrder => 'on their due date',
            self::Repair => 'on the day they were reported',
            self::Planned => 'dates a plan falls due that have no work order yet',
        };
    }
}
