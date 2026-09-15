<?php

namespace App\Support;

use App\Filament\Actions\ImportAction;
use League\Csv\Writer;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Reading an Excel workbook as if it were the CSV the importers already speak.
 *
 * Filament imports CSV only, while the files people actually keep their asset
 * lists in are workbooks. So a workbook is flattened to a CSV on the way in
 * {@see ImportAction} and the importers never learn it happened. Only the first
 * sheet is read: guessing which sheet was meant is worse than being predictable.
 *
 * openspout is already required by Filament and streams rather than loading the
 * workbook into memory, which matters for a first migration of thousands of assets.
 */
final class Spreadsheet
{
    /** What a workbook can arrive as. Anything else is left to the CSV reader. */
    public const EXTENSIONS = ['xlsx', 'ods'];

    /** The mime types a browser sends for those, so the file picker offers them. */
    public const MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /**
     * The pre-2007 Excel format, which openspout cannot read. Named so the upload
     * can say so in words.
     */
    public const LEGACY_EXTENSION = 'xls';

    public static function isWorkbook(?string $extension): bool
    {
        return in_array(mb_strtolower((string) $extension), self::EXTENSIONS, true);
    }

    /**
     * The first sheet of a workbook, rewritten as a UTF-8 CSV.
     *
     * @param  string  $path  A readable local path — the readers unzip, so a stream is not enough.
     * @return resource An in-memory stream, rewound and ready to read.
     */
    public static function toCsvStream(string $path, string $extension)
    {
        $stream = fopen('php://temp/maxmemory:'.(8 * 1024 * 1024), 'r+');

        if ($stream === false) {
            throw new RuntimeException('Could not open a temporary stream for the workbook.');
        }

        $csv = Writer::createFromStream($stream);

        $reader = mb_strtolower($extension) === 'ods' ? new OdsReader : new XlsxReader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $width = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    if ($row->isEmpty()) {
                        continue;
                    }

                    $cells = array_map(self::readable(...), $row->toArray());

                    // Excel trims the trailing empty cells off each row, so a row
                    // that leaves the last columns blank arrives shorter than the
                    // header and every later column would shift. The header sets
                    // the width and the rest are padded to it.
                    $width = $width === 0 ? count($cells) : $width;
                    $cells = array_slice(array_pad($cells, $width, ''), 0, $width);

                    $csv->insertOne($cells);
                }

                // First sheet only.
                break;
            }
        } finally {
            $reader->close();
        }

        rewind($stream);

        return $stream;
    }

    /**
     * One cell as the CSV should carry it. The importers validate strings a person
     * typed, and a workbook hands back typed values instead: a cost of 12500000 as
     * a float would be written "1.25E+7", and a date cell as an object.
     */
    private static function readable(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            $value instanceof \DateTimeInterface => $value->format(
                $value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s',
            ),
            $value instanceof \DateInterval => $value->format('%H:%I:%S'),
            is_float($value) => self::number($value),
            default => self::text((string) $value),
        };
    }

    /**
     * A cell's text with the spreadsheet's own artefacts taken back out: Excel's
     * `_xHHHH_` escapes for line breaks, control and zero-width characters that
     * silently break a code lookup, and runs of (non-breaking) spaces.
     */
    private static function text(string $value): string
    {
        $value = (string) preg_replace_callback(
            '/(_x005F)?_x([0-9A-Fa-f]{4})_/',
            fn (array $matches): string => $matches[1] !== ''
                ? '_x'.$matches[2].'_'
                : (string) mb_chr((int) hexdec($matches[2]), 'UTF-8'),
            $value,
        );

        $value = (string) preg_replace('/\p{Cc}/u', ' ', $value);
        $value = (string) preg_replace('/\p{Cf}/u', '', $value);
        $value = (string) preg_replace('/[\p{Z}\s]+/u', ' ', $value);

        return trim($value);
    }

    /**
     * A number without the exponent Excel's storage would otherwise leak: whole
     * numbers lose their decimal point, the rest keep every digit they had.
     */
    private static function number(float $value): string
    {
        if ($value === floor($value) && abs($value) < PHP_INT_MAX) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
