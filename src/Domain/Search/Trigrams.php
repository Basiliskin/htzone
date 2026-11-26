<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * Close-spelling comparison for the typo fallback (D15): a word is cut into overlapping 3-letter chunks,
 * and two words are similar when they share many chunks (Jaccard: shared ÷ all distinct).
 */
final class Trigrams
{
    /**
     * @return list<string> never empty: a word shorter than 3 letters is its own chunk
     */
    public static function split(string $word): array
    {
        $letters = mb_str_split(mb_strtolower($word));
        if (count($letters) < 3) {
            return [implode('', $letters)];
        }
        $chunks = [];
        for ($i = 0, $last = count($letters) - 3; $i <= $last; ++$i) {
            $chunks[] = $letters[$i] . $letters[$i + 1] . $letters[$i + 2];
        }

        return array_values(array_unique($chunks));
    }

    public static function similarity(string $a, string $b): float
    {
        $chunksA = self::split($a);
        $chunksB = self::split($b);
        $shared = count(array_intersect($chunksA, $chunksB));

        return $shared / count(array_unique([...$chunksA, ...$chunksB]));
    }

    /**
     * @param list<string> $words
     * @return array{string, float}|null the most similar word (first one on a tie) and its similarity
     */
    public static function closest(string $word, array $words): ?array
    {
        $best = null;
        foreach ($words as $candidate) {
            $similarity = self::similarity($word, $candidate);
            if ($best === null || $similarity > $best[1]) {
                $best = [$candidate, $similarity];
            }
        }

        return $best;
    }
}
