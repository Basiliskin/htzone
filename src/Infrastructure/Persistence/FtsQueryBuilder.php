<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Persistence;

use Htzone\Domain\Search\SearchTerm;
use Htzone\Domain\Search\Trigrams;

/**
 * Search terms → an FTS5 MATCH string. The safety boundary for full-text syntax (AC-4.3):
 * every piece of user text is a quoted string with " doubled, so AND/OR/NOT/NEAR ^ : * ( ) are plain text.
 * The result is bound as a PDO parameter, never concatenated into SQL.
 */
final class FtsQueryBuilder
{
    /**
     * ("לכלב"* OR "כלב"*) OR ("16"): a word matches the start of an indexed word; a number must match a whole word.
     *
     * @param list<SearchTerm> $terms
     */
    public function forTerms(array $terms): string
    {
        $groups = [];
        foreach ($terms as $term) {
            $variants = array_map(
                static fn (string $text): string => self::quote($text) . ($term->isQualifier || mb_strlen($text) < 2 ? '' : '*'),
                $term->texts(),
            );
            $groups[] = '(' . implode(' OR ', $variants) . ')';
        }

        return implode(' OR ', $groups);
    }

    /**
     * "מקר" OR "קרו" OR …: the trigram index matches any 3-letter chunk; shorter words can't be matched.
     *
     * @param list<string> $words
     */
    public function forTrigrams(array $words): string
    {
        $chunks = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3) {
                array_push($chunks, ...Trigrams::split($word));
            }
        }

        return implode(' OR ', array_map(self::quote(...), array_values(array_unique($chunks))));
    }

    private static function quote(string $text): string
    {
        return '"' . str_replace('"', '""', $text) . '"';
    }
}
