<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Lookups keyed on a value a human typed, matched without regard to case.
 *
 * MySQL's default collation ignores case, but SQLite — which the test suite runs
 * on — compares byte for byte, so a lookup that worked in the browser could fail
 * in a test, or the other way round. Both sides are lowered instead: `lower()`
 * means the same on every driver, so one query shape covers all of them.
 */
class Search
{
    /** Match a column against a value, ignoring case. */
    public static function whereSame(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        string $value,
        string $boolean = 'and',
    ): EloquentBuilder|QueryBuilder {
        return $query->whereRaw(
            'lower('.static::wrap($query, $column).') = ?',
            [mb_strtolower($value)],
            $boolean,
        );
    }

    /** The column, quoted the way the current driver expects. */
    private static function wrap(EloquentBuilder|QueryBuilder $query, string $column): string
    {
        return ($query instanceof EloquentBuilder ? $query->getQuery() : $query)
            ->getGrammar()
            ->wrap($column);
    }
}
