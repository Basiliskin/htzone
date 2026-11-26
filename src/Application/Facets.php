<?php

declare(strict_types=1);

namespace Htzone\Application;

use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\SearchFilter;

/**
 * What the filter controls offer for one search (D44, D45), counted over the strong matches (D46), not
 * only the 24 shown. Each control ignores its own filter: picking a category keeps the other chips, and the
 * price placeholders show what the chosen category costs whatever bounds are typed.
 */
final readonly class Facets
{
    /**
     * @param array<string, int> $categories name → number of results, most first, then by name
     */
    public function __construct(
        public array $categories,
        public ?Money $minPrice,
        public ?Money $maxPrice,
    ) {
    }

    public static function none(): self
    {
        return new self([], null, null);
    }

    /**
     * @param list<RankedProduct> $ranked
     */
    public static function from(array $ranked, SearchFilter $filter): self
    {
        $byPrice = $filter->withoutCategory();
        $categories = [];
        foreach ($ranked as $result) {
            if ($byPrice->matches($result->product)) {
                $name = $result->product->category;
                $categories[$name] = ($categories[$name] ?? 0) + 1;
            }
        }
        uksort($categories, static fn (string $a, string $b): int => [$categories[$b], $a] <=> [$categories[$a], $b]);

        $byCategory = $filter->withoutPrice();
        $prices = [];
        foreach ($ranked as $result) {
            if ($byCategory->matches($result->product)) {
                $prices[] = $result->product->price->minor;
            }
        }

        return $prices === []
            ? new self($categories, null, null)
            : new self($categories, Money::ils(min($prices)), Money::ils(max($prices)));
    }
}
