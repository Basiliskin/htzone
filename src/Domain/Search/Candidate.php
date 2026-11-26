<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

use Htzone\Domain\Catalog\Product;

/**
 * A product the index found, with its full-text score (SQLite bm25: more negative = better match).
 */
final readonly class Candidate
{
    public function __construct(public Product $product, public float $bm25)
    {
    }
}
