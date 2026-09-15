<?php

namespace App\Support;

use App\Enums\CalendarEntryKind;
use Carbon\CarbonImmutable;

/**
 * One item on the maintenance calendar.
 */
final readonly class CalendarEntry
{
    /**
     * @param  string  $color  A Filament colour name: primary, info, success, warning, danger or gray.
     */
    public function __construct(
        public CalendarEntryKind $kind,
        public CarbonImmutable $date,
        public string $title,
        public ?string $detail,
        public string $color,
        public string $statusLabel,
        public ?string $url = null,
    ) {}
}
