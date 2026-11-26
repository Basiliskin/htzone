<?php

declare(strict_types=1);

namespace Htzone\Domain\Catalog;

/**
 * A catalog item. Immutable; the constructor enforces the invariants (architecture §4).
 */
final readonly class Product
{
    public ?string $brand;

    /**
     * @param list<string> $tags Claude-written HE + EN search tags (may be empty)
     */
    public function __construct(
        public string $id,
        public string $name,
        ?string $brand,
        public string $category,
        public string $subcategory,
        public string $description,
        public string $attributes,
        public string $imageUrl,
        public Money $price,
        public array $tags,
    ) {
        if (preg_match('/^P\d{4}$/', $id) !== 1) {
            throw new \InvalidArgumentException("Product id must look like P0001, got \"{$id}\"");
        }
        if (trim($name) === '') {
            throw new \InvalidArgumentException("Product {$id}: name is empty");
        }
        $brand = $brand === null ? null : trim($brand);
        $this->brand = $brand === '' ? null : $brand;
    }
}
