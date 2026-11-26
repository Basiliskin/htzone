<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Catalog;

use Htzone\Domain\Catalog\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('formats')]
    public function testFormatsShekelsTheIsraeliWay(int $agorot, string $expected): void
    {
        self::assertSame($expected, Money::ils($agorot)->format());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function formats(): iterable
    {
        yield 'zero' => [0, '₪0'];
        yield 'under a thousand' => [77900, '₪779'];
        yield 'thousands separator' => [129900, '₪1,299'];
        yield 'agorot shown only when not zero' => [129990, '₪1,299.90'];
        yield 'single agora' => [5, '₪0.05'];
        yield 'large' => [999999900, '₪9,999,999'];
    }

    public function testFromShekelsStoresAgorot(): void
    {
        $money = Money::fromShekels(1299);

        self::assertSame(129900, $money->minor);
        self::assertSame('ILS', $money->currency);
    }

    public function testRejectsNegativeAmounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::ils(-1);
    }
}
