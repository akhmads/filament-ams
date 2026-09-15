<?php

namespace App\Filament\Imports\Concerns;

use App\Filament\Imports\Contracts\ValidatesFileUpFront;
use Filament\Actions\Imports\Importer;
use Filament\Forms\Components\Checkbox;
use Illuminate\Validation\ValidationException;

/**
 * The dry run behind {@see ValidatesFileUpFront}.
 *
 * Filament's own {@see Importer::__invoke} stopped where it would start writing:
 * remap, cast, resolve the record, check the mapping, validate. Written as the
 * framework's sequence rather than a validation of our own, so the check cannot
 * quietly drift from what the import does. What it cannot catch is anything only
 * the write discovers, such as a unique index.
 */
trait ValidatesRowsUpFront
{
    /**
     * The checkbox that turns the check on, for an importer's options form.
     */
    protected static function allOrNothingOption(): Checkbox
    {
        return Checkbox::make(ValidatesFileUpFront::OPTION)
            ->label('Reject the whole file if any row fails')
            ->helperText('Semua baris diperiksa lebih dulu: kalau ada satu saja yang gagal, tidak ada yang masuk dan file dikembalikan dengan daftar barisnya. Dilepas, baris yang baik tetap masuk dan yang gagal bisa diunduh sebagai CSV setelah import selesai.')
            ->default(true);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function validateRow(array $data): void
    {
        $this->originalData = $this->data = $data;
        $this->record = null;

        $this->remapData();
        $this->castData();

        $this->record = $this->resolveRecord();

        // A record the importer declines to build is a row it would have skipped.
        if (! $this->record) {
            return;
        }

        if (! $this->record->exists) {
            $this->checkColumnMappingRequirementsForNewRecords();
        }

        $this->validateData();
    }
}
