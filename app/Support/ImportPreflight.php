<?php

namespace App\Support;

use App\Filament\Imports\Contracts\ValidatesFileUpFront;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs a whole file through an importer's checks without writing any of it.
 *
 * Failures come back keyed by the line the row sits on in the file, counting the
 * header, which is what a spreadsheet shows in its gutter. The catch blocks
 * mirror {@see ImportCsv::handle}, so a row reported here reads as it would have
 * read in the failed-rows file.
 */
final class ImportPreflight
{
    public function __construct(private readonly ValidatesFileUpFront $importer) {}

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  int  $headerOffset  Which line the header sits on, zero-based.
     * @return array<int, string> Line number => what went wrong. Empty means the file is good.
     */
    public function run(iterable $rows, int $headerOffset = 0): array
    {
        $failures = [];
        $line = $headerOffset + 2;

        foreach ($rows as $row) {
            try {
                $this->importer->validateRow($this->utf8Encode($row));
            } catch (RowImportFailedException $exception) {
                $failures[$line] = $exception->getMessage();
            } catch (ValidationException $exception) {
                $failures[$line] = collect($exception->errors())->flatten()->implode(' ');
            } catch (Throwable $exception) {
                report($exception);

                $failures[$line] = 'This row could not be read.';
            }

            $line++;
        }

        return $failures;
    }

    /**
     * The import job drops invalid UTF-8 before handing a row to the importer, so
     * the check does the same to measure the same string.
     */
    private function utf8Encode(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->utf8Encode(...), $value);
        }

        if (is_string($value)) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $value;
    }
}
