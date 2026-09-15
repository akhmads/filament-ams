<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\HasNumberExportColumns;
use Filament\Actions\Exports\ExportColumn;

/**
 * A column exported as a number rather than as text that looks like one.
 *
 * Filament formats every exported value to a string, so an amount arrives in
 * Excel as text that cannot be summed — and a PC set to Indonesian reads the dot
 * in 1234.56 as a thousands separator. The workbook therefore carries the number
 * itself and tells Excel how many places to show {@see HasNumberExportColumns};
 * the CSV keeps a plain dot and no separators, which the importer reads back.
 */
class NumberExportColumn extends ExportColumn
{
    public const DEFAULT_PLACES = 2;

    protected int $places = self::DEFAULT_PLACES;

    public function places(int $places): static
    {
        $this->places = max(0, $places);

        return $this;
    }

    public function getPlaces(): int
    {
        return $this->places;
    }

    public function getXlsxFormat(): string
    {
        return $this->places === 0 ? '0' : '#,##0.'.str_repeat('0', $this->places);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reads $this->places when the value is formatted, so places() may be chained afterwards.
        $this->formatStateUsing(fn (mixed $state): ?string => blank($state)
            ? null
            : number_format((float) $state, $this->places, '.', ''));
    }
}
