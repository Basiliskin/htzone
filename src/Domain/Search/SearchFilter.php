<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;

/**
 * Narrows ranked results to one category and/or a price range (D43–D45). Applied after ranking, so it
 * never changes the order, only which products are shown. An empty filter matches everything.
 */
final readonly class SearchFilter
{
    public const MAX_CATEGORY_LENGTH = 100;
    private const SHEKELS = '/^\d{1,7}$/'; // whole shekels, up to ₪9,999,999

    public function __construct(
        public ?string $category = null,
        public ?Money $min = null,
        public ?Money $max = null,
    ) {
        if ($min !== null && $max !== null && $min->minor > $max->minor) {
            throw new InvalidFilter('min price is above max price');
        }
    }

    /** From URL values: blank means "no filter"; prices are whole shekels. */
    public static function fromRaw(?string $category, ?string $min, ?string $max): self
    {
        $category = trim((string) $category);
        if (mb_strlen($category) > self::MAX_CATEGORY_LENGTH) {
            throw new InvalidFilter('category is too long');
        }

        return new self($category === '' ? null : $category, self::shekels($min), self::shekels($max));
    }

    public function isEmpty(): bool
    {
        return $this->category === null && $this->min === null && $this->max === null;
    }

    public function matches(Product $product): bool
    {
        $price = $product->price->minor;

        return ($this->category === null || $product->category === $this->category)
            && ($this->min === null || $price >= $this->min->minor)
            && ($this->max === null || $price <= $this->max->minor);
    }

    public function withoutCategory(): self
    {
        return new self(null, $this->min, $this->max);
    }

    public function withoutPrice(): self
    {
        return new self($this->category);
    }

    private static function shekels(?string $raw): ?Money
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match(self::SHEKELS, $raw) !== 1) {
            throw new InvalidFilter('price must be whole shekels');
        }

        return Money::fromShekels((int) $raw);
    }
}
