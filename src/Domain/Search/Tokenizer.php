<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * Splits text into lowercase words the same way the SQLite index does (FTS5 "unicode61"):
 * only letters and digits form words, so a geresh or apostrophe splits (דולצ׳ה → דולצ + ה).
 * Used for queries (QueryNormalizer) and for product fields (Ranker), so both sides agree.
 */
final class Tokenizer
{
    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $text = preg_replace('/\p{Mn}+/u', '', $text) ?? '';   // Mn = niqqud, cantillation, combining accents
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }
}
