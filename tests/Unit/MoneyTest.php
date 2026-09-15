<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string|int, int}>
     */
    public static function rupiahAmounts(): array
    {
        return [
            'decimal column value' => ['600000000.00', 60_000_000_000],
            'one decimal place' => ['0.5', 50],
            'no decimals' => ['10', 1_000],
            'integer' => [10, 1_000],
            'negative' => ['-12.34', -1_234],
        ];
    }

    #[DataProvider('rupiahAmounts')]
    public function test_a_rupiah_amount_converts_to_sen(string|int $rupiah, int $sen): void
    {
        $this->assertSame($sen, Money::toSen($rupiah));
    }

    public function test_sen_convert_back_to_a_two_decimal_rupiah_string(): void
    {
        $this->assertSame('1234.56', Money::toRupiah(123_456));
        $this->assertSame('0.05', Money::toRupiah(5));
        $this->assertSame('-12.34', Money::toRupiah(-1_234));
    }

    public function test_a_share_rounds_half_away_from_zero(): void
    {
        $this->assertSame(33_333, Money::share(99_999, 100, 300));
        $this->assertSame(5, Money::share(10, 1, 2));
        $this->assertSame(-5, Money::share(-10, 1, 2));
        $this->assertSame(3, Money::share(10, 1, 3));
    }

    public function test_the_whole_share_is_the_whole_amount(): void
    {
        $this->assertSame(99_999, Money::share(99_999, 300, 300));
    }

    public function test_a_share_of_a_large_stock_value_does_not_overflow(): void
    {
        // Rp 90 billion of stock across 1,000,000.00 units, taking 999,999.99 of them.
        $this->assertSame(8_999_999_910_000, Money::share(9_000_000_000_000, 99_999_999, 100_000_000));
    }

    public function test_an_amount_with_more_than_two_decimals_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toSen('12.345');
    }
}
