<?php

namespace App\Filament\Exports\Concerns;

use App\Filament\Exports\NumberExportColumn;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * The other half of {@see NumberExportColumn}: putting those columns into the
 * workbook as numbers. The columns are recognised by their class, so there is no
 * second list to keep in step.
 */
trait HasNumberExportColumns
{
    /**
     * Headings are words, and the numeric pass below has no business seeing them.
     *
     * @param  array<mixed>  $values
     */
    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        return Row::fromValues($values, $style);
    }

    /**
     * @param  array<mixed>  $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $columns = $this->getCachedColumns();

        // Filament writes the CSV first and reads it back to build the workbook, so
        // the values arrive positional, in the order of the column map.
        $names = array_keys($this->columnMap);

        $styles = [];
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $column = $columns[$names[$index] ?? ''] ?? null;

            // A blank stays blank: an unknown residual value must not become zero.
            if (! $column instanceof NumberExportColumn || ! is_numeric($value)) {
                $cells[] = Cell::fromValue($value);

                continue;
            }

            $format = $column->getXlsxFormat();
            $styles[$format] ??= (new Style)->setFormat($format);

            $cells[] = Cell::fromValue(
                $column->getPlaces() === 0 ? (int) $value : (float) $value,
                $styles[$format],
            );
        }

        return new Row($cells, $style);
    }
}
