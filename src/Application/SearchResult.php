<?php

declare(strict_types=1);

namespace Htzone\Application;

use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\SearchQuery;

/**
 * What a search returns to the HTTP layer: the results in order, an optional notice,
 * and whether the typo fallback produced them (logged as `fallback`).
 */
final readonly class SearchResult
{
    /**
     * @param list<RankedProduct> $results at most 24
     */
    public function __construct(
        public SearchQuery $query,
        public array $results,
        public ?Notice $notice,
        public bool $usedFallback,
    ) {
    }
}
