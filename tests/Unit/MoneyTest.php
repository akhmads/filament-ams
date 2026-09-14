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

    public function test_an_amount_with_more_than_two_decimals_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toSen('12.345');
    }
}
