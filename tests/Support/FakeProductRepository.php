<?php

declare(strict_types=1);

namespace Htzone\Tests\Support;

use Htzone\Domain\Catalog\ProductRepository;
use Htzone\Domain\Search\Candidate;

/**
 * In-memory ProductRepository: returns fixed candidates and records what the use case asked for.
 */
final class FakeProductRepository implements ProductRepository
{
    public int $candidateCalls = 0;
    public int $trigramCalls = 0;
    /** @var list<list<string>> */
    public array $trigramWords = [];

    /**
     * @param list<Candidate> $candidates
     * @param list<Candidate> $trigramCandidates
     */
    public function __construct(private readonly array $candidates, private readonly array $trigramCandidates)
    {
    }

    public function findCandidates(array $terms, int $limit): array
    {
        ++$this->candidateCalls;

        return $this->candidates;
    }

    public function findByTrigrams(array $words, int $limit): array
    {
        ++$this->trigramCalls;
        $this->trigramWords[] = $words;

        return $this->trigramCandidates;
    }

    public function count(): int
    {
        return count($this->candidates);
    }
}
