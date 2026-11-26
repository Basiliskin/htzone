<?php

declare(strict_types=1);

namespace Htzone\Domain\Catalog;

use Htzone\Domain\Search\Candidate;
use Htzone\Domain\Search\SearchTerm;

/**
 * The only port out of the Domain (architecture §4). SQLite implements it; unit tests use a fake.
 */
interface ProductRepository
{
    /**
     * Products where any variant of any term starts a word, best full-text score first.
     *
     * @param list<SearchTerm> $terms
     * @return list<Candidate>
     */
    public function findCandidates(array $terms, int $limit): array;

    /**
     * Products sharing 3-letter chunks with the words, for the typo fallback.
     *
     * @param list<string> $words
     * @return list<Candidate>
     */
    public function findByTrigrams(array $words, int $limit): array;

    public function count(): int;
}
