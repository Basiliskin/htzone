<?php

declare(strict_types=1);

namespace Htzone\Application;

use Htzone\Domain\Catalog\ProductRepository;
use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\Ranker;
use Htzone\Domain\Search\SearchFilter;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Domain\Search\SearchTerm;
use Htzone\Domain\Search\Tokenizer;

/**
 * The search use case (architecture §6): normalize → candidates → rank → filter → top 24, then the typo
 * fallback when nothing matched, or the missing-model note when a number isn't in the top results.
 * The filter (D43) runs after ranking over the strong matches (D46), so it never changes the order and can
 * reach products below the first 24. Without a filter the list is exactly the ranked top 24.
 */
final readonly class SearchProducts
{
    private const CANDIDATES = 100;
    private const PAGE_SIZE = 24;
    private const MODEL_CHECK_TOP = 3;
    private const MIN_FUZZY_LENGTH = 3;

    public function __construct(
        private ProductRepository $products,
        private QueryNormalizer $normalizer,
        private Ranker $ranker,
    ) {
    }

    public function execute(SearchQuery $query, SearchFilter $filter = new SearchFilter()): SearchResult
    {
        $terms = $this->normalizer->normalize($query);
        if ($terms === []) {
            return new SearchResult($query, [], null, false);
        }

        $ranked = $this->ranker->rank($this->products->findCandidates($terms, self::CANDIDATES), $terms);
        if ($ranked !== []) {
            $shown = $this->shown($ranked, $filter);
            $notice = $shown !== [] && self::hasMissingModel($terms, $shown) ? Notice::missingModel() : null;

            return new SearchResult($query, $shown, $notice, false, Facets::from($this->ranker->strong($ranked), $filter));
        }

        return $this->typoFallback($query, $terms, $filter);
    }

    /**
     * @param list<RankedProduct> $ranked
     * @return list<RankedProduct>
     */
    private function shown(array $ranked, SearchFilter $filter): array
    {
        if (!$filter->isEmpty()) {
            $ranked = array_values(array_filter($this->ranker->strong($ranked), static fn (RankedProduct $r): bool => $filter->matches($r->product)));
        }

        return array_slice($ranked, 0, self::PAGE_SIZE);
    }

    /**
     * @param list<SearchTerm> $terms
     */
    private function typoFallback(SearchQuery $query, array $terms, SearchFilter $filter): SearchResult
    {
        $words = [];
        foreach ($terms as $term) {
            if (!$term->isQualifier && mb_strlen($term->original) >= self::MIN_FUZZY_LENGTH) {
                $words[] = $term->original;
            }
        }
        if ($words === []) {
            return new SearchResult($query, [], null, false);
        }

        $ranked = $this->ranker->rankFuzzy($this->products->findByTrigrams($words, self::CANDIDATES), $words);
        $suggestion = $ranked === [] ? null : $ranked[0]->suggestion;

        return new SearchResult($query, $this->shown($ranked, $filter), $suggestion === null ? null : Notice::didYouMean($suggestion), $ranked !== [], Facets::from($this->ranker->strong($ranked), $filter));
    }

    /**
     * D14: the query names a number (16) alongside real words, and none of the top results has it
     * in its name or attributes ("iPhone 16" when the catalog has no iPhone 16).
     *
     * @param list<SearchTerm>    $terms
     * @param list<RankedProduct> $ranked
     */
    private static function hasMissingModel(array $terms, array $ranked): bool
    {
        $qualifiers = array_filter($terms, static fn (SearchTerm $t): bool => $t->isQualifier);
        if ($qualifiers === [] || count($qualifiers) === count($terms)) {
            return false;
        }

        $words = [];
        foreach (array_slice($ranked, 0, self::MODEL_CHECK_TOP) as $result) {
            array_push($words, ...Tokenizer::words($result->product->name . ' ' . $result->product->attributes));
        }
        foreach ($qualifiers as $qualifier) {
            if (!in_array($qualifier->original, $words, true)) {
                return true;
            }
        }

        return false;
    }
}
