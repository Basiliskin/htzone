<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Search;

use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;
use Htzone\Domain\Search\InvalidFilter;
use Htzone\Domain\Search\SearchFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Category + price filter (D43–D45): parsing the URL values and matching a product.
 */
final class SearchFilterTest extends TestCase
{
    private static function product(string $category, int $agorot): Product
    {
        return new Product('P0001', 'מוצר', null, $category, 'תת', 'תיאור', '', 'https://example.com/x.jpg', Money::ils($agorot), []);
    }

    public function testNoValuesMeansNoFilter(): void
    {
        $filter = SearchFilter::fromRaw(null, '', '  ');

        self::assertTrue($filter->isEmpty());
        self::assertTrue($filter->matches(self::product('מטבח ובישול', 1)));
    }

    public function testCategoryMustMatchExactly(): void
    {
        $filter = SearchFilter::fromRaw(' מטבח ובישול ', null, null);

        self::assertSame('מטבח ובישול', $filter->category);
        self::assertTrue($filter->matches(self::product('מטבח ובישול', 100)));
        self::assertFalse($filter->matches(self::product('בית ועיצוב', 100)));
    }

    public function testPriceBoundsAreWholeShekelsAndInclusive(): void
    {
        $filter = SearchFilter::fromRaw(null, '100', '500');

        self::assertFalse($filter->matches(self::product('x', 9_990)));   // ₪99.90
        self::assertTrue($filter->matches(self::product('x', 10_000)));   // ₪100
        self::assertTrue($filter->matches(self::product('x', 50_000)));   // ₪500
        self::assertFalse($filter->matches(self::product('x', 50_090))); // ₪500.90
    }

    public function testOnlyOneBoundIsFine(): void
    {
        self::assertTrue(SearchFilter::fromRaw(null, null, '200')->matches(self::product('x', 5_000)));
        self::assertFalse(SearchFilter::fromRaw(null, '200', null)->matches(self::product('x', 5_000)));
    }

    public function testEachFacetIgnoresItsOwnPart(): void
    {
        $filter = SearchFilter::fromRaw('מטבח ובישול', '100', '500');

        self::assertNull($filter->withoutCategory()->category);
        self::assertSame(10_000, $filter->withoutCategory()->min?->minor);
        self::assertNull($filter->withoutPrice()->min);
        self::assertNull($filter->withoutPrice()->max);
        self::assertSame('מטבח ובישול', $filter->withoutPrice()->category);
    }

    #[DataProvider('badValues')]
    public function testBadValuesAreRejected(?string $category, ?string $min, ?string $max): void
    {
        $this->expectException(InvalidFilter::class);
        SearchFilter::fromRaw($category, $min, $max);
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function badValues(): iterable
    {
        yield 'negative' => [null, '-5', null];
        yield 'decimal' => [null, '9.5', null];
        yield 'not a number' => [null, null, 'abc'];
        yield 'over ₪9,999,999' => [null, '10000000', null];
        yield 'min above max' => [null, '500', '100'];
        yield 'category over 100 characters' => [str_repeat('א', 101), null, null];
    }
}
