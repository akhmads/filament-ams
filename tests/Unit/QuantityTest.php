<?php

namespace Tests\Unit;

use App\Support\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    /**
     * @return array<string, array{string|int, int}>
     */
    public static function quantities(): array
    {
        return [
            'decimal column value' => ['12.50', 1_250],
            'one decimal place' => ['0.5', 50],
            'no decimals' => ['3', 300],
            'integer' => [3, 300],
            'negative change' => ['-2.25', -225],
        ];
    }

    #[DataProvider('quantities')]
    public function test_a_quantity_converts_to_hundredths(string|int $quantity, int $hundredths): void
    {
        $this->assertSame($hundredths, Quantity::toHundredths($quantity));
    }

    public function test_hundredths_convert_back_to_a_two_decimal_string(): void
    {
        $this->assertSame('12.50', Quantity::toDecimal(1_250));
        $this->assertSame('0.05', Quantity::toDecimal(5));
        $this->assertSame('-2.25', Quantity::toDecimal(-225));
    }

    public function test_a_quantity_with_more_than_two_decimals_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Quantity::toHundredths('1.255');
    }
}
