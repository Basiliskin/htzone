<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

use Htzone\Domain\Catalog\Product;

/**
 * A result in its final order. `suggestion` is set by the typo fallback: the words this product actually
 * contains (מקרוגל → מיקרוגל), for the "did you mean" note.
 */
final readonly class RankedProduct
{
    public function __construct(public Product $product, public float $score, public ?string $suggestion = null)
    {
    }
}
