<?php

namespace App\Services;

use App\Enums\ExportFormat;
use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Writes a report to CSV or XLSX and hands it to the browser.
 *
 * Values keep their type in XLSX: floats become amounts with thousand separators
 * and dates become real date cells, so the sheet can be summed and sorted. CSV
 * gets plain numbers and ISO dates, which accounting imports read reliably.
 */
class SpreadsheetExporter
{
    private const DATE_FORMAT = 'dd/mm/yyyy';

    private const AMOUNT_FORMAT = '#,##0.00';

    /**
     * @param  list<string>  $headings
     * @param  iterable<list<string|int|float|DateTimeInterface|null>>  $rows
     */
    public function download(string $filename, ExportFormat $format, array $headings, iterable $rows): StreamedResponse
    {
        $path = $this->write($format, $headings, $rows);

        return response()->streamDownload(
            function () use ($path): void {
                readfile($path);
                File::delete($path);
            },
            "{$filename}.{$format->extension()}",
            ['Content-Type' => $format->contentType()],
        );
    }

    /**
     * Writes the file into private storage and returns its path. The caller owns
     * the file afterwards.
     *
     * @param  list<string>  $headings
     * @param  iterable<list<string|int|float|DateTimeInterface|null>>  $rows
     */
    public function write(ExportFormat $format, array $headings, iterable $rows): string
    {
        // Private storage rather than the system temp folder, which other processes
        // on a shared server can read and clean up mid-request. Group-writable so
        // the web server and the CLI user can both write here whichever creates it.
        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory, 0775);

        $path = $directory.'/'.Str::uuid()->toString().'.'.$format->extension();
        $writer = $this->writerFor($format, $directory);

        try {
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues($headings, $format === ExportFormat::Xlsx ? (new Style)->setFontBold() : null));

            foreach ($rows as $values) {
                $writer->addRow(new Row(array_map(
                    fn (string|int|float|DateTimeInterface|null $value): Cell => $this->cell($format, $value),
                    $values,
                )));
            }

            $writer->close();
        } catch (Throwable $exception) {
            $writer->close();
            File::delete($path);

            throw $exception;
        }

        return $path;
    }

    private function writerFor(ExportFormat $format, string $directory): WriterInterface
    {
        if ($format === ExportFormat::Csv) {
            return new CsvWriter;
        }

        $options = new XlsxOptions;
        $options->setTempFolder($directory);

        return new XlsxWriter($options);
    }

    private function cell(ExportFormat $format, string|int|float|DateTimeInterface|null $value): Cell
    {
        if ($format === ExportFormat::Csv) {
            return Cell::fromValue($value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value);
        }

        return match (true) {
            $value instanceof DateTimeInterface => Cell::fromValue($value, (new Style)->setFormat(self::DATE_FORMAT)),
            is_float($value) => Cell::fromValue($value, (new Style)->setFormat(self::AMOUNT_FORMAT)),
            default => Cell::fromValue($value),
        };
    }
}
