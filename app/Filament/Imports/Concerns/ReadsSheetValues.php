<?php

namespace App\Filament\Imports\Concerns;

use App\Support\Spreadsheet;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Validation\ValidationException;

/**
 * Values as a sheet writes them, read strictly.
 *
 * Dates are read in one spelling only, YYYY-MM-DD. PHP reads a slashed date in
 * American order, so 03/04/2026 — the third of April to whoever typed it — would
 * import as the fourth of March, and nothing about that row would look wrong. Our
 * exports write YYYY-MM-DD, and a workbook's date cell is flattened to it too
 * {@see Spreadsheet}, so an exported file edited and sent back always reads.
 */
trait ReadsSheetValues
{
    private const DATE_EXAMPLE = 'YYYY-MM-DD (for example 2026-01-15)';

    /**
     * @throws ValidationException When the value is not a real date in that spelling.
     */
    protected static function sheetDate(mixed $state, string $column): ?string
    {
        if (blank($state)) {
            return null;
        }

        if ($state instanceof DateTimeInterface) {
            return $state->format('Y-m-d');
        }

        $value = trim((string) $state);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        // Comparing the result with the input refuses dates PHP would roll over,
        // such as 2026-02-31.
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([
                $column => "'{$value}' is not a date this import reads. Write it as ".self::DATE_EXAMPLE.'.',
            ]);
        }

        return $value;
    }

    /**
     * An enum case named by its value or its label, in any case.
     *
     * @param  class-string<BackedEnum&HasLabel>  $enum
     *
     * @throws ValidationException When nothing matches.
     */
    protected static function sheetEnum(string $enum, mixed $state, string $column): ?string
    {
        if (blank($state)) {
            return null;
        }

        $value = mb_strtolower(trim((string) $state));

        foreach ($enum::cases() as $case) {
            if ($value === mb_strtolower((string) $case->value) || $value === mb_strtolower((string) $case->getLabel())) {
                return (string) $case->value;
            }
        }

        $allowed = implode(', ', array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases()));

        throw ValidationException::withMessages([$column => "'{$state}' is not a valid {$column}. Use one of: {$allowed}."]);
    }

    /**
     * A yes/no cell: 1/0, yes/no, y/n, ya/tidak, true/false.
     *
     * @throws ValidationException When the value is none of those.
     */
    protected static function sheetBoolean(mixed $state, string $column): ?bool
    {
        if (blank($state)) {
            return null;
        }

        return match (mb_strtolower(trim((string) $state))) {
            '1', 'y', 'yes', 'ya', 'true' => true,
            '0', 'n', 'no', 'tidak', 'false' => false,
            default => throw ValidationException::withMessages([$column => "'{$state}' is not a yes or no. Write 1 or 0."]),
        };
    }
}
