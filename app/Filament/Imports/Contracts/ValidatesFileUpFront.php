<?php

namespace App\Filament\Imports\Contracts;

use App\Filament\Actions\ImportAction;
use App\Filament\Imports\Concerns\ValidatesRowsUpFront;
use Illuminate\Validation\ValidationException;

/**
 * An importer whose rows can be checked before the first one is written.
 *
 * Filament hands a file to a batch of queue jobs a hundred rows at a time, and a
 * database transaction cannot span jobs — a half-imported file cannot be rolled
 * back. What can be done is to refuse it before it starts: run every row through
 * the importer's own lookups and rules, save nothing, and send the file back with
 * the failing rows named. {@see ImportAction} checks for this interface before it
 * offers the choice; {@see ValidatesRowsUpFront} is the implementation.
 */
interface ValidatesFileUpFront
{
    /**
     * The option the checkbox in the import modal writes to, and which the action
     * reads to decide whether to run the check.
     */
    public const OPTION = 'allOrNothing';

    /**
     * Put one row through everything the import does short of writing it.
     *
     * @param  array<string, mixed>  $data  The row as read from the file, keyed by its CSV header.
     *
     * @throws ValidationException When the row would have failed the import.
     */
    public function validateRow(array $data): void;
}
