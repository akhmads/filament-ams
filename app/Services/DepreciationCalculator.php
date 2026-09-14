<?php

namespace App\Services;

use App\Enums\DepreciationMethod;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Builds a month-by-month depreciation schedule. Pure arithmetic with no database
 * access, so each rule can be checked against hand-worked figures.
 *
 * Money is handled in integer sen. Rounding is cumulative: each month books the
 * difference between two rounded running totals, so a schedule never gains or
 * loses a sen overall.
 *
 * Conventions shared by the commercial and fiscal books:
 * - The start month counts in full (UU PPh Pasal 11 ayat 3). PMK 72/2023's worked
 *   example depreciates a boat bought in October by 3/12 in that year.
 * - Straight line spreads cost less residual evenly over the useful life.
 * - Declining balance applies twice the straight-line rate to the book value at
 *   the start of each fiscal year, prorated by month, stops at the residual, and
 *   writes whatever remains off in the last month of the useful life
 *   (Pasal 11 ayat 2: "pada akhir masa manfaat nilai sisa buku disusutkan sekaligus").
 * - Sum-of-years-digits weights each 12-month asset year; commercial book only.
 */
final class DepreciationCalculator
{
    /**
     * @return list<array{period: string, opening: int, amount: int, accumulated: int, closing: int, is_final: bool}>
     */
    public function schedule(
        int $costSen,
        int $residualSen,
        DepreciationMethod $method,
        int $usefulLifeMonths,
        CarbonImmutable $start,
        int $fiscalYearStartMonth = 1,
    ): array {
        if ($fiscalYearStartMonth < 1 || $fiscalYearStartMonth > 12) {
            throw new InvalidArgumentException("Fiscal year start month must be 1–12, got [{$fiscalYearStartMonth}].");
        }

        $residualSen = max(0, $residualSen);
        $start = $start->startOfMonth();

        if ($method === DepreciationMethod::None || $usefulLifeMonths < 1 || $costSen <= $residualSen) {
            return [];
        }

        $amounts = match ($method) {
            DepreciationMethod::StraightLine => $this->straightLine($costSen - $residualSen, $usefulLifeMonths),
            DepreciationMethod::DoubleDeclining => $this->decliningBalance($costSen, $residualSen, $usefulLifeMonths, $start, $fiscalYearStartMonth),
            DepreciationMethod::SumOfYearsDigits => $this->sumOfYearsDigits($costSen - $residualSen, $usefulLifeMonths),
        };

        return $this->rows($costSen, $amounts, $start);
    }

    /**
     * @return list<int>
     */
    private function straightLine(int $depreciable, int $life): array
    {
        $amounts = [];
        $booked = 0;

        for ($month = 1; $month <= $life; $month++) {
            $target = $this->roundDiv($depreciable * $month, $life);
            $amounts[] = $target - $booked;
            $booked = $target;
        }

        return $amounts;
    }

    /**
     * @return list<int>
     */
    private function decliningBalance(int $cost, int $residual, int $life, CarbonImmutable $start, int $fiscalYearStartMonth): array
    {
        $amounts = [];
        $bookValue = $cost;
        $yearOpening = $cost;
        $monthsIntoYear = 0;
        $bookedThisYear = 0;
        $month = $start;

        for ($index = 1; $index <= $life; $index++) {
            if ($index > 1 && $month->month === $fiscalYearStartMonth) {
                $yearOpening = $bookValue;
                $monthsIntoYear = 0;
                $bookedThisYear = 0;
            }

            $monthsIntoYear++;

            // Annual charge = opening × (24 / life); booked through k months of the
            // year = annual × k / 12 = opening × 2k / life.
            $target = $this->roundDiv($yearOpening * 2 * $monthsIntoYear, $life);
            $amount = $index === $life
                ? $bookValue - $residual
                : min($target - $bookedThisYear, $bookValue - $residual);

            $amounts[] = $amount;
            $bookValue -= $amount;
            $bookedThisYear = $target;

            if ($bookValue <= $residual) {
                break;
            }

            $month = $month->addMonth();
        }

        return $amounts;
    }

    /**
     * @return list<int>
     */
    private function sumOfYearsDigits(int $depreciable, int $life): array
    {
        $years = intdiv($life + 11, 12);
        $digits = intdiv($years * ($years + 1), 2);
        $amounts = [];
        $weight = 0;
        $booked = 0;

        for ($month = 1; $month <= $life; $month++) {
            $assetYear = intdiv($month - 1, 12) + 1;
            $weight += $years - $assetYear + 1;

            $target = $month === $life
                ? $depreciable
                : $this->roundDiv($depreciable * $weight, 12 * $digits);

            $amounts[] = $target - $booked;
            $booked = $target;
        }

        return $amounts;
    }

    /**
     * @param  list<int>  $amounts
     * @return list<array{period: string, opening: int, amount: int, accumulated: int, closing: int, is_final: bool}>
     */
    private function rows(int $cost, array $amounts, CarbonImmutable $start): array
    {
        $rows = [];
        $accumulated = 0;
        $opening = $cost;
        $month = $start;
        $last = array_key_last($amounts);

        foreach ($amounts as $index => $amount) {
            $accumulated += $amount;
            $closing = $cost - $accumulated;

            $rows[] = [
                'period' => $month->format('Y-m-d'),
                'opening' => $opening,
                'amount' => $amount,
                'accumulated' => $accumulated,
                'closing' => $closing,
                'is_final' => $index === $last,
            ];

            $opening = $closing;
            $month = $month->addMonth();
        }

        return $rows;
    }

    /** Integer division rounded half up, for non-negative operands. */
    private function roundDiv(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
