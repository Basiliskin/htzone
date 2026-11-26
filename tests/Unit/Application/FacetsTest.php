<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Application;

use Htzone\Application\Facets;
use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;
use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\SearchFilter;
use PHPUnit\Framework\TestCase;

/**
 * What the filter controls show (D44, D45): category counts and the price range of the ranked results.
 */
final class FacetsTest extends TestCase
{
    private static function ranked(string $id, string $category, int $shekels): RankedProduct
    {
        return new RankedProduct(new Product($id, 'מוצר', null, $category, 'תת', 'תיאור', '', 'https://example.com/x.jpg', Money::fromShekels($shekels), []), 1.0);
    }

    /**
     * @return list<RankedProduct>
     */
    private static function results(): array
    {
        return [
            self::ranked('P0001', 'מטבח ובישול', 50),
            self::ranked('P0002', 'בית ועיצוב', 400),
            self::ranked('P0003', 'מטבח ובישול', 900),
            self::ranked('P0004', 'תינוקות וילדים', 120),
            self::ranked('P0005', 'בית ועיצוב', 80),
            self::ranked('P0006', 'מטבח ובישול', 300),
        ];
    }

    public function testCountsCategoriesMostFirstThenByName(): void
    {
        $facets = Facets::from(self::results(), new SearchFilter());

        self::assertSame(['מטבח ובישול' => 3, 'בית ועיצוב' => 2, 'תינוקות וילדים' => 1], $facets->categories);
        self::assertSame(5_000, $facets->minPrice?->minor);
        self::assertSame(90_000, $facets->maxPrice?->minor);
    }

    public function testCategoryCountsFollowThePriceFilterButNotTheCategoryFilter(): void
    {
        // Choosing a category must not hide the other chips; a budget must update their counts.
        $facets = Facets::from(self::results(), SearchFilter::fromRaw('מטבח ובישול', null, '400'));

        self::assertSame(['בית ועיצוב' => 2, 'מטבח ובישול' => 2, 'תינוקות וילדים' => 1], $facets->categories);
    }

    public function testThePriceRangeFollowsTheCategoryButNotThePriceFilter(): void
    {
        // The placeholders show what the chosen category costs, whatever the current bounds are.
        $facets = Facets::from(self::results(), SearchFilter::fromRaw('מטבח ובישול', '100', '200'));

        self::assertSame(5_000, $facets->minPrice?->minor);
        self::assertSame(90_000, $facets->maxPrice?->minor);
    }

    public function testNoResultsMeansNoFacets(): void
    {
        $facets = Facets::from([], new SearchFilter());

        self::assertSame([], $facets->categories);
        self::assertNull($facets->minPrice);
        self::assertNull($facets->maxPrice);
    }
}
