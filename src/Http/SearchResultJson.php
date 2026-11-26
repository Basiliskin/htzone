<?php

declare(strict_types=1);

namespace Htzone\Http;

use Htzone\Application\Facets;
use Htzone\Application\Notice;
use Htzone\Application\NoticeType;
use Htzone\Application\SearchResult;
use Htzone\Domain\Search\RankedProduct;

/**
 * SearchResult → the JSON body of GET /api/search (architecture §8). The notice wording lives here,
 * because what the visitor reads is the HTTP layer's concern (the Application only says what happened).
 */
final class SearchResultJson
{
    /**
     * @return array<string, mixed>
     */
    public static function from(SearchResult $result): array
    {
        return [
            'query' => $result->query->text,
            'total' => count($result->results),
            'results' => array_map(self::product(...), $result->results),
            'notice' => $result->notice === null ? null : self::notice($result->notice),
            'facets' => self::facets($result->facets),
        ];
    }

    /**
     * Category chips with counts and the price range in agorot, like `price.amount` (D44, D45).
     *
     * @return array<string, mixed>
     */
    private static function facets(Facets $facets): array
    {
        $categories = [];
        foreach ($facets->categories as $name => $count) {
            $categories[] = ['name' => $name, 'count' => $count];
        }

        return [
            'categories' => $categories,
            'price' => $facets->minPrice === null || $facets->maxPrice === null
                ? null
                : ['min' => $facets->minPrice->minor, 'max' => $facets->maxPrice->minor],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function product(RankedProduct $ranked): array
    {
        $product = $ranked->product;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'price' => [
                'amount' => $product->price->minor,
                'currency' => $product->price->currency,
                'formatted' => $product->price->format(),
            ],
            'image' => $product->imageUrl,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function notice(Notice $notice): array
    {
        return match ($notice->type) {
            NoticeType::MissingModel => ['type' => $notice->type->value, 'message' => 'לא נמצא דגם מדויק, הנה המוצרים הקרובים'],
            NoticeType::DidYouMean => [
                'type' => $notice->type->value,
                'message' => "האם התכוונת: {$notice->suggestion}?",
                'suggestion' => (string) $notice->suggestion,
            ],
        };
    }
}
