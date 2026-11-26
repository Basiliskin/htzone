<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * Every ranking number in one place (architecture §6), tuned against the relevance tests.
 * Coverage dominates: matching more of what was asked matters more than anything else.
 */
final readonly class RankingWeights
{
    /**
     * @param list<float> $fieldWeights bm25 weight per FTS column: name, brand, tags, category, description
     */
    public function __construct(
        public array $fieldWeights = [10.0, 8.0, 6.0, 3.0, 1.0],
        public float $base = 1.0,          // the index's bm25, scaled 0..1 across the candidates
        public float $coverage = 4.0,      // share of query words found in name, brand, tags or category
        public float $exact = 1.0,         // share of words found as typed (or as an alias), not as a guess
        public float $brand = 2.0,         // a word is the product's brand
        public float $name = 1.5,          // share of words found in the name itself
        public float $category = 0.5,      // share of words found in the category/subcategory: the query names the product type
        public float $qualifier = 0.5,     // share of numbers/units found (only next to a word match)
        public float $minSimilarity = 0.4, // typo fallback: below this a spelling is not "close"
        public int $limit = 24,
    ) {
    }
}
