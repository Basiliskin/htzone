<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

/**
 * One validated CSV line, before price and tags are added. Values are trimmed; brand may be ''.
 */
final readonly class CatalogRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $brand,
        public string $category,
        public string $subcategory,
        public string $description,
        public string $attributes,
        public string $imageUrl,
    ) {
    }

    /** The key used by data/price-ranges.json, e.g. "מטבח ובישול > אפייה". */
    public function categoryKey(): string
    {
        return $this->category . ' > ' . $this->subcategory;
    }
}
