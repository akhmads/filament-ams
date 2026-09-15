<?php

namespace App\Filament\Imports\Concerns;

use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Master data named rather than numbered.
 *
 * A row says "IT-LAP" or "Head Office", never an id: whoever fills in the sheet
 * has no ids. So a lookup takes a code or a name and ignores case. Codes are tried
 * first because they are unique; a name shared by two records fails the row and
 * asks for the code, rather than silently picking one.
 *
 * A master that does not exist fails the row rather than being created: an
 * importer that invents masters turns one typo into a permanent duplicate.
 */
trait ResolvesNamedMasters
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  Already scoped, e.g. to the asset's branch.
     * @param  list<string>  $columns  Tried in order.
     * @return TModel|null
     *
     * @throws ValidationException When nothing matches, the match is ambiguous, or a required value is blank.
     */
    protected static function namedMaster(Builder $query, array $columns, mixed $value, string $label, bool $required = false): ?Model
    {
        $key = trim((string) $value);

        if ($key === '') {
            if ($required) {
                throw ValidationException::withMessages([$label => "A {$label} is required."]);
            }

            return null;
        }

        foreach ($columns as $column) {
            $matches = Search::whereSame(clone $query, $column, $key)->limit(2)->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }

            if ($matches->count() > 1) {
                throw ValidationException::withMessages([$label => "'{$key}' matches more than one {$label}. Use its code instead."]);
            }
        }

        throw ValidationException::withMessages([$label => "No {$label} matches '{$key}'."]);
    }
}
