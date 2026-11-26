<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Persistence;

use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;

/**
 * Product ↔ `products` row, in one place for the importer (write) and the repository (read).
 */
final class ProductRowMapper
{
    public const TAG_SEPARATOR = ' | ';

    /**
     * @return array<string, string|int|null>
     */
    public static function toRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'subcategory' => $product->subcategory,
            'description' => $product->description,
            'attributes' => $product->attributes,
            'image_url' => $product->imageUrl,
            'price_minor' => $product->price->minor,
            'currency' => $product->price->currency,
            'tags' => implode(self::TAG_SEPARATOR, $product->tags),
        ];
    }

    /**
     * @param array<mixed> $row
     */
    public static function fromRow(array $row): Product
    {
        $tags = self::string($row, 'tags');
        $brand = $row['brand'] ?? null;
        $price = $row['price_minor'] ?? null;
        if (!is_int($price) || $price < 0 || self::string($row, 'currency') !== 'ILS' || ($brand !== null && !is_string($brand))) {
            throw new \UnexpectedValueException('Malformed products row ' . self::string($row, 'id'));
        }

        return new Product(
            id: self::string($row, 'id'),
            name: self::string($row, 'name'),
            brand: $brand,
            category: self::string($row, 'category'),
            subcategory: self::string($row, 'subcategory'),
            description: self::string($row, 'description'),
            attributes: self::string($row, 'attributes'),
            imageUrl: self::string($row, 'image_url'),
            price: Money::ils($price),
            tags: $tags === '' ? [] : explode(self::TAG_SEPARATOR, $tags),
        );
    }

    /**
     * @param array<mixed> $row
     */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException("products.{$column} is not text");
        }

        return $value;
    }
}
