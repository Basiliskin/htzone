<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Persistence;

use Htzone\Domain\Catalog\ProductRepository;

/**
 * Opens the real repository (and so the database) on first use, not when the app is wired (D33).
 * A missing or broken DB then fails inside a request, where the Kernel turns it into 503/500 JSON.
 */
final class LazyProductRepository implements ProductRepository
{
    private ?ProductRepository $inner = null;

    /**
     * @param \Closure(): ProductRepository $open
     */
    public function __construct(private readonly \Closure $open)
    {
    }

    public function findCandidates(array $terms, int $limit): array
    {
        return $this->inner()->findCandidates($terms, $limit);
    }

    public function findByTrigrams(array $words, int $limit): array
    {
        return $this->inner()->findByTrigrams($words, $limit);
    }

    public function count(): int
    {
        return $this->inner()->count();
    }

    private function inner(): ProductRepository
    {
        return $this->inner ??= ($this->open)();
    }
}
