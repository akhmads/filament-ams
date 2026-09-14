<?php

namespace Tests\Unit;

use App\Enums\DepreciationMethod;
use App\Services\DepreciationCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DepreciationCalculatorTest extends TestCase
{
    public function test_straight_line_matches_the_pmk_72_boat_example(): void
    {
        // PMK 72/2023 worked example: a Group 2 boat (8 years) with a Rp600,000,000
        // basis, acquired October 2023 — Rp18,750,000 in 2023 (3/12), Rp75,000,000
        // each full year, the last 9/12 in 2031, ending at zero.
        $rows = (new DepreciationCalculator)->schedule(
            costSen: 60_000_000_000,
            residualSen: 0,
            method: DepreciationMethod::StraightLine,
            usefulLifeMonths: 96,
            start: CarbonImmutable::parse('2023-10-01'),
        );

        $byYear = $this->sumByYear($rows);

        $this->assertCount(96, $rows);
        $this->assertSame(1_875_000_000, $byYear['2023']);
        $this->assertSame(7_500_000_000, $byYear['2024']);
        $this->assertSame(7_500_000_000, $byYear['2030']);
        $this->assertSame(5_625_000_000, $byYear['2031']);
        $this->assertSame('2031-09-01', end($rows)['period']);
        $this->assertSame(0, end($rows)['closing']);
        $this->assertTrue(end($rows)['is_final']);
    }

    public function test_straight_line_stops_at_the_residual_value(): void
    {
        $rows = (new DepreciationCalculator)->schedule(1_200_000_000, 200_000_000, DepreciationMethod::StraightLine, 10, CarbonImmutable::parse('2024-01-01'));

        $this->assertCount(10, $rows);
        $this->assertSame(100_000_000, $rows[0]['amount']);
        $this->assertSame(200_000_000, end($rows)['closing']);
    }

    public function test_cumulative_rounding_never_gains_or_loses_a_sen(): void
    {
        $rows = (new DepreciationCalculator)->schedule(100_000_000, 0, DepreciationMethod::StraightLine, 3, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame([33_333_333, 33_333_334, 33_333_333], array_column($rows, 'amount'));
        $this->assertSame(100_000_000, end($rows)['accumulated']);
    }

    public function test_the_start_month_counts_in_full_whatever_the_day(): void
    {
        $calculator = new DepreciationCalculator;

        $firstDay = $calculator->schedule(1_200_000_000, 0, DepreciationMethod::StraightLine, 12, CarbonImmutable::parse('2024-03-01'));
        $lastDay = $calculator->schedule(1_200_000_000, 0, DepreciationMethod::StraightLine, 12, CarbonImmutable::parse('2024-03-31'));

        $this->assertSame($firstDay, $lastDay);
        $this->assertSame('2024-03-01', $lastDay[0]['period']);
    }

    public function test_declining_balance_applies_the_rate_to_the_book_value_at_each_fiscal_year(): void
    {
        // Group 2 at 25%: 2023 = 600,000,000 × 25% × 3/12; 2024 = 562,500,000 × 25%;
        // 2025 = 421,875,000 × 25%.
        $rows = (new DepreciationCalculator)->schedule(60_000_000_000, 0, DepreciationMethod::DoubleDeclining, 96, CarbonImmutable::parse('2023-10-01'));

        $byYear = $this->sumByYear($rows);

        $this->assertSame(3_750_000_000, $byYear['2023']);
        $this->assertSame(14_062_500_000, $byYear['2024']);
        $this->assertSame(10_546_875_000, $byYear['2025']);
    }

    public function test_declining_balance_writes_off_the_remaining_book_value_in_the_last_month(): void
    {
        $rows = (new DepreciationCalculator)->schedule(60_000_000_000, 0, DepreciationMethod::DoubleDeclining, 96, CarbonImmutable::parse('2023-10-01'));

        $last = end($rows);
        $beforeLast = $rows[count($rows) - 2];

        $this->assertCount(96, $rows);
        $this->assertSame('2031-09-01', $last['period']);
        $this->assertSame(0, $last['closing']);
        $this->assertSame(60_000_000_000, $last['accumulated']);
        $this->assertGreaterThan($beforeLast['amount'], $last['amount']);
    }

    public function test_commercial_declining_balance_stops_once_it_reaches_the_residual(): void
    {
        // Rp10,000,000 cost, Rp1,000,000 residual, 4 years at 50%: book value falls to
        // 5,000,000 / 2,500,000 / 1,250,000 by end-2026, then reaches the residual in
        // May 2027 after booking only the Rp41,666.67 that remains.
        $rows = (new DepreciationCalculator)->schedule(1_000_000_000, 100_000_000, DepreciationMethod::DoubleDeclining, 48, CarbonImmutable::parse('2024-01-01'));

        $last = end($rows);

        $this->assertCount(41, $rows);
        $this->assertSame('2027-05-01', $last['period']);
        $this->assertSame(4_166_667, $last['amount']);
        $this->assertSame(100_000_000, $last['closing']);
    }

    public function test_the_declining_balance_year_follows_the_fiscal_year_start(): void
    {
        // Fiscal year from April: January–March 2024 close FY 2023/24 on the cost
        // (Rp12,000,000 × 50% ÷ 12 = Rp500,000 a month); April opens FY 2024/25 on the
        // Rp10,500,000 book value (× 50% ÷ 12 = Rp437,500).
        $rows = (new DepreciationCalculator)->schedule(1_200_000_000, 0, DepreciationMethod::DoubleDeclining, 48, CarbonImmutable::parse('2024-01-01'), fiscalYearStartMonth: 4);

        $this->assertSame(50_000_000, $rows[0]['amount']);
        $this->assertSame(1_050_000_000, $rows[2]['closing']);
        $this->assertSame(43_750_000, $rows[3]['amount']);
    }

    public function test_sum_of_years_digits_weights_earlier_years_more(): void
    {
        $rows = (new DepreciationCalculator)->schedule(150_000_000, 0, DepreciationMethod::SumOfYearsDigits, 36, CarbonImmutable::parse('2024-01-01'));

        $byYear = $this->sumByYear($rows);

        $this->assertCount(36, $rows);
        $this->assertSame(75_000_000, $byYear['2024']);
        $this->assertSame(50_000_000, $byYear['2025']);
        $this->assertSame(25_000_000, $byYear['2026']);
        $this->assertSame(6_250_000, $rows[0]['amount']);
        $this->assertSame(0, end($rows)['closing']);
    }

    /**
     * @return array<string, array{int, int, DepreciationMethod, int}>
     */
    public static function nothingToDepreciate(): array
    {
        return [
            'no depreciation method' => [1_000_000, 0, DepreciationMethod::None, 48],
            'no useful life' => [1_000_000, 0, DepreciationMethod::StraightLine, 0],
            'cost equals residual' => [1_000_000, 1_000_000, DepreciationMethod::StraightLine, 48],
            'cost below residual' => [1_000_000, 2_000_000, DepreciationMethod::DoubleDeclining, 48],
        ];
    }

    #[DataProvider('nothingToDepreciate')]
    public function test_there_is_no_schedule_when_nothing_is_depreciable(int $cost, int $residual, DepreciationMethod $method, int $life): void
    {
        $rows = (new DepreciationCalculator)->schedule($cost, $residual, $method, $life, CarbonImmutable::parse('2024-01-01'));

        $this->assertSame([], $rows);
    }

    public function test_an_impossible_fiscal_year_start_month_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DepreciationCalculator)->schedule(1_000_000, 0, DepreciationMethod::StraightLine, 12, CarbonImmutable::parse('2024-01-01'), fiscalYearStartMonth: 13);
    }

    /**
     * @param  list<array{period: string, amount: int}>  $rows
     * @return array<string, int>
     */
    private function sumByYear(array $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $year = substr($row['period'], 0, 4);
            $totals[$year] = ($totals[$year] ?? 0) + $row['amount'];
        }

        return $totals;
    }
}
