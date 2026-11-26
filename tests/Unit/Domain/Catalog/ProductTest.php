<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Catalog;

use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testKeepsItsData(): void
    {
        $product = self::make(brand: 'Dyson', tags: ['שואב אבק', 'dyson']);

        self::assertSame('P0201', $product->id);
        self::assertSame('Dyson', $product->brand);
        self::assertSame(['שואב אבק', 'dyson'], $product->tags);
        self::assertSame('₪3,899', $product->price->format());
    }

    public function testEmptyBrandBecomesNull(): void
    {
        self::assertNull(self::make(brand: '  ')->brand);
    }

    #[DataProvider('invalid')]
    public function testRejectsBrokenInvariants(string $id, string $name, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        self::make(id: $id, name: $name);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'id format' => ['X1', 'שואב', 'id'];
        yield 'id too long' => ['P00011', 'שואב', 'id'];
        yield 'empty name' => ['P0001', ' ', 'name'];
    }

    /**
     * @param list<string> $tags
     */
    private static function make(string $id = 'P0201', string $name = 'שואב אבק Dyson', ?string $brand = 'Dyson', array $tags = []): Product
    {
        return new Product(
            id: $id,
            name: $name,
            brand: $brand,
            category: 'מוצרי חשמל לבית',
            subcategory: 'שואבי אבק ורובוטים',
            description: 'שואב אבק אלחוטי',
            attributes: 'סוג: אלחוטי',
            imageUrl: 'https://placehold.co/600x600.png',
            price: Money::fromShekels(3899),
            tags: $tags,
        );
    }
}
