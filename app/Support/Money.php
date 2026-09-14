<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Converts between rupiah amounts as stored (decimal strings with two places)
 * and integer sen for arithmetic. Floats are never used: they drift, and the CLI
 * PHP on this machine has no bcmath to fall back on.
 */
final class Money
{
    /**
     * @param  string|int  $rupiah  e.g. "600000000.00", "0.5" or 10
     */
    public static function toSen(string|int $rupiah): int
    {
        if (is_int($rupiah)) {
            return $rupiah * 100;
        }

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', trim($rupiah), $matches)) {
            throw new InvalidArgumentException("[{$rupiah}] is not a rupiah amount with at most two decimals.");
        }

        $sen = ((int) $matches[2]) * 100 + (int) str_pad($matches[3] ?? '0', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$sen : $sen;
    }

    /**
     * A decimal string ready for a decimal(18,2) column, e.g. 123456 → "1234.56".
     */
    public static function toRupiah(int $sen): string
    {
        $sign = $sen < 0 ? '-' : '';
        $sen = abs($sen);

        return sprintf('%s%d.%02d', $sign, intdiv($sen, 100), $sen % 100);
    }
}
