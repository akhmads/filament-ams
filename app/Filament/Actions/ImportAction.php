<?php

namespace App\Filament\Actions;

use App\Filament\Imports\Contracts\ValidatesFileUpFront;
use App\Support\ImportPreflight;
use App\Support\Spreadsheet;
use Filament\Actions\ImportAction as BaseImportAction;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use League\Csv\Info;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Filament's import, taught three things (the same as in the lastmile project).
 *
 * It only considers the comma and the semicolon as delimiters: Filament guesses by
 * counting several candidates, and a character that merely appears inside fields
 * can win. Our exports write commas; Excel on an Indonesian PC writes semicolons.
 *
 * It takes an Excel workbook, flattened to CSV as it is read {@see Spreadsheet}.
 *
 * And for importers that offer it, it refuses a file that would only partly import
 * {@see rejectFileIfAnyRowFails}.
 */
class ImportAction extends BaseImportAction
{
    /**
     * The largest file the up-front check reads. The check runs in the request the
     * user is waiting in, so past this size it says so instead of timing out.
     */
    public const PREFLIGHT_MAX_ROWS = 5000;

    /** How many failing rows the notification names before it starts counting. */
    private const PREFLIGHT_ROWS_LISTED = 10;

    /**
     * The converted workbook, kept for the length of the request: Filament reads
     * the upload several times over one import.
     *
     * @var array<string, string>
     */
    private array $convertedWorkbooks = [];

    /**
     * Filament funnels every read of the uploaded file through here, so a stream of
     * CSV handed back is indistinguishable from a CSV having been uploaded.
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();

        if (! Spreadsheet::isWorkbook($extension)) {
            return parent::getUploadedFileStream($file);
        }

        $key = $file->getRealPath();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->convertedWorkbooks[$key] ??= $this->convertWorkbook($file, $extension));
        rewind($stream);

        return $stream;
    }

    /**
     * The workbook's first sheet as CSV text. Copied to a local file first: the
     * readers unzip the workbook, so they need a path on this machine.
     */
    private function convertWorkbook(TemporaryUploadedFile $file, string $extension): string
    {
        $local = tempnam(storage_path('app/private'), 'workbook-');

        try {
            $source = $file->readStream();
            $target = fopen($local, 'w');
            stream_copy_to_stream($source, $target);
            fclose($target);
            fclose($source);

            $csv = Spreadsheet::toCsvStream($local, $extension);
            $contents = stream_get_contents($csv);
            fclose($csv);

            return $contents;
        } finally {
            File::delete($local);
        }
    }

    /**
     * Accept workbooks alongside CSVs. The old .xls format is let through the
     * extension rule on purpose, so the failure is a sentence rather than a list
     * of extensions.
     *
     * @return array<mixed>
     */
    public function getFileValidationRules(): array
    {
        $inherited = array_values(array_filter(
            parent::getFileValidationRules(),
            fn (mixed $rule): bool => ! (is_string($rule) && str_starts_with($rule, 'extensions:')),
        ));

        return [
            'extensions:csv,txt,'.implode(',', Spreadsheet::EXTENSIONS).','.Spreadsheet::LEGACY_EXTENSION,
            fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                if (mb_strtolower($value->getClientOriginalExtension()) === Spreadsheet::LEGACY_EXTENSION) {
                    $fail('This is the old Excel format (.xls), which cannot be read. Open it in Excel and use Save As to make an .xlsx or a CSV.');
                }
            },
            ...$inherited,
        ];
    }

    /**
     * Let the file picker offer workbooks. The upload field is reached after the
     * parent builds its schema, rather than restating Filament's schema here.
     */
    public function getSchema(Schema $schema): ?Schema
    {
        $built = parent::getSchema($schema);

        foreach ($built?->getComponents() ?? [] as $component) {
            if ($component instanceof FileUpload && $component->getName() === 'file') {
                $component
                    ->acceptedFileTypes([
                        ...$component->getAcceptedFileTypes() ?? [],
                        ...Spreadsheet::MIME_TYPES,
                    ])
                    ->placeholder('Upload a CSV or Excel file')
                    ->helperText('CSV, XLSX or ODS. Only the first sheet of a workbook is read.');
            }
        }

        return $built;
    }

    protected function guessCsvDelimiter(?CsvReader $reader = null): ?string
    {
        if (! $reader) {
            return null;
        }

        $counts = Info::getDelimiterStats($reader, delimiters: [',', ';'], limit: 10);

        // A single-column file counts zero of both; let the reader use its default.
        if (max($counts) === 0) {
            return null;
        }

        return array_search(max($counts), $counts);
    }

    /**
     * After the modal's form validates and before the import record is created and
     * the batch dispatched: the last point at which refusing the file costs nothing.
     */
    public function callBefore(): mixed
    {
        $this->rejectFileIfAnyRowFails();

        return parent::callBefore();
    }

    /**
     * Put the whole file through the importer's checks, and stop the import if any
     * row would have failed. Skipped unless the importer offers the option and the
     * user left it ticked.
     */
    private function rejectFileIfAnyRowFails(): void
    {
        $importer = $this->getImporter();
        $data = $this->getData();

        if (! is_a($importer, ValidatesFileUpFront::class, true)) {
            return;
        }

        if (! ($data[ValidatesFileUpFront::OPTION] ?? false)) {
            return;
        }

        $file = $data['file'] ?? null;

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $stream = $this->getUploadedFileStream($file);

        if (! $stream) {
            return;
        }

        $reader = CsvReader::from($stream);

        if (filled($delimiter = $this->getCsvDelimiter($reader))) {
            $reader->setDelimiter($delimiter);
        }

        $headerOffset = $this->getHeaderOffset() ?? 0;
        $reader->setHeaderOffset($headerOffset);

        $rows = (new Statement)->process($reader);

        if ($rows->count() > static::PREFLIGHT_MAX_ROWS) {
            $this->sendPreflightNotification(
                'File too large to check first',
                new HtmlString(
                    'This file has '.Number::format($rows->count()).' rows, and the whole-file check reads up to '
                    .Number::format(static::PREFLIGHT_MAX_ROWS).'. Split the file, or untick "Reject the whole file '
                    .'if any row fails" to import it row by row.',
                ),
            );

            $this->halt();
        }

        $failures = (new ImportPreflight($this->preflightImporter($file, $data, $rows->count())))
            ->run($rows->getRecords(), $headerOffset);

        if ($failures === []) {
            return;
        }

        $this->sendPreflightNotification(
            'Nothing imported — '.Number::format(count($failures)).' of '.Number::format($rows->count()).' rows would have failed',
            $this->preflightFailureList($failures),
        );

        $this->halt();
    }

    /**
     * An importer to check the rows with, built against an import record that is
     * deliberately never saved — no import has happened.
     *
     * @param  array<string, mixed>  $data
     */
    private function preflightImporter(TemporaryUploadedFile $file, array $data, int $totalRows): ValidatesFileUpFront
    {
        $import = app(Import::class);
        $import->user()->associate(auth($this->getAuthGuard())->user());
        $import->file_name = $file->getClientOriginalName();
        $import->file_path = $file->getRealPath();
        $import->importer = $this->getImporter();
        $import->total_rows = $totalRows;

        /** @var ValidatesFileUpFront $importer */
        $importer = $import->getImporter(
            columnMap: $data['columnMap'] ?? [],
            options: array_merge($this->getOptions(), Arr::except($data, ['file', 'columnMap'])),
        );

        return $importer;
    }

    /**
     * The failing rows, as many as are worth reading at once.
     *
     * @param  array<int, string>  $failures
     */
    private function preflightFailureList(array $failures): HtmlString
    {
        $listed = array_slice($failures, 0, self::PREFLIGHT_ROWS_LISTED, preserve_keys: true);

        $lines = [];

        foreach ($listed as $line => $message) {
            $lines[] = '<strong>Row '.$line.':</strong> '.e($message);
        }

        if (($remaining = count($failures) - count($listed)) > 0) {
            $lines[] = 'and '.Number::format($remaining).' more.';
        }

        $lines[] = 'Nothing was imported. Fix these rows and upload the file again.';

        return new HtmlString(implode('<br>', $lines));
    }

    private function sendPreflightNotification(string $title, HtmlString $body): void
    {
        Notification::make()
            ->danger()
            ->title($title)
            ->body($body)
            // A list of rows has to stay on screen long enough to go and find them.
            ->persistent()
            ->send();
    }
}
