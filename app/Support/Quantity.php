<?php

namespace App\Support;

use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * Converts between stock quantities as stored (decimal strings with two places)
 * and integer hundredths for arithmetic, the same way {@see Money} handles
 * rupiah. Two places are enough for litres, metres and kilograms.
 */
final class Quantity
{
    /**
     * @param  string|int  $quantity  e.g. "12.50", "0.5" or 3
     */
    public static function toHundredths(string|int $quantity): int
    {
        if (is_int($quantity)) {
            return $quantity * 100;
        }

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', trim($quantity), $matches)) {
            throw new InvalidArgumentException("[{$quantity}] is not a quantity with at most two decimals.");
        }

        $hundredths = ((int) $matches[2]) * 100 + (int) str_pad($matches[3] ?? '0', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$hundredths : $hundredths;
    }

    /**
     * A decimal string ready for a decimal(18,2) column, e.g. 1250 → "12.50".
     */
    public static function toDecimal(int $hundredths): string
    {
        $sign = $hundredths < 0 ? '-' : '';
        $hundredths = abs($hundredths);

        return sprintf('%s%d.%02d', $sign, intdiv($hundredths, 100), $hundredths % 100);
    }

    /**
     * For display only: "12.50" → "12.5", "3.00" → "3".
     */
    public static function format(string|int|float|null $quantity): string
    {
        return Number::format((float) ($quantity ?? 0), maxPrecision: 2);
    }
}
