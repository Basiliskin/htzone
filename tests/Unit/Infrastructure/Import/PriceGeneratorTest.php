<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Infrastructure\Import;

use Htzone\Infrastructure\Import\PriceGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceGeneratorTest extends TestCase
{
    private const KITCHEN = 'מטבח ובישול > מכשירי חשמל קטנים למטבח';

    /**
     * Golden values: the same numbers the /data step previewed (docs/data.md §3).
     * If this test changes, every price on the site changes. That should be a deliberate decision.
     */
    #[DataProvider('golden')]
    public function testKnownPricesNeverDrift(string $id, string $key, string $expected): void
    {
        self::assertSame($expected, self::realGenerator()->priceFor($id, $key)->format());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function golden(): iterable
    {
        yield 'air fryer' => ['P0001', self::KITCHEN, '₪1,899'];
        yield 'dyson vacuum' => ['P0201', 'מוצרי חשמל לבית > שואבי אבק ורובוטים', '₪3,899'];
        yield 'macbook pro' => ['P0306', 'מחשבים ועבודה מהבית > מחשבים ניידים וטאבלטים', '₪9,299'];
        yield 'pet brush' => ['P0950', 'חיות מחמד > כללי לחיות מחמד', '₪59'];
    }

    public function testEveryPriceIsInRangeWithAStoreEnding(): void
    {
        $generator = new PriceGenerator([self::KITCHEN => [199, 1999], 'cheap' => [19, 149]]);

        for ($n = 1; $n <= 1000; $n++) {
            $id = sprintf('P%04d', $n);
            foreach ([self::KITCHEN => [199, 1999], 'cheap' => [19, 149]] as $key => [$min, $max]) {
                $shekels = intdiv($generator->priceFor($id, $key)->minor, 100);
                self::assertGreaterThanOrEqual($min, $shekels, $id);
                self::assertLessThanOrEqual($max, $shekels, $id);
                self::assertSame($shekels < 1000 ? 9 : 99, $shekels % ($shekels < 1000 ? 10 : 100), "{$id} → {$shekels}");
            }
        }
    }

    public function testSameIdAlwaysGetsTheSamePrice(): void
    {
        $a = new PriceGenerator([self::KITCHEN => [199, 1999]]);
        $b = new PriceGenerator([self::KITCHEN => [199, 1999]]);

        self::assertEquals($a->priceFor('P0042', self::KITCHEN), $b->priceFor('P0042', self::KITCHEN));
    }

    public function testUnknownSubcategoryFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no price range');

        (new PriceGenerator([self::KITCHEN => [199, 1999]]))->priceFor('P0001', 'nope > nope');
    }

    /**
     * @param array<array-key, mixed> $ranges
     */
    #[DataProvider('badRanges')]
    public function testRejectsBadRanges(array $ranges): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PriceGenerator($ranges);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function badRanges(): iterable
    {
        yield 'min above max' => [['a' => [500, 100]]];
        yield 'zero min' => [['a' => [0, 100]]];
        yield 'not a pair' => [['a' => [100]]];
        yield 'empty' => [[]];
    }

    public function testLoadsTheCommittedRangesFile(): void
    {
        self::assertSame('₪1,899', self::realGenerator()->priceFor('P0001', self::KITCHEN)->format());
    }

    private static function realGenerator(): PriceGenerator
    {
        return PriceGenerator::fromFile(__DIR__ . '/../../../../data/price-ranges.json');
    }
}
