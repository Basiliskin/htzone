<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Search;

use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Domain\Search\SearchTerm;
use Htzone\Domain\Search\Variant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryNormalizerTest extends TestCase
{
    /**
     * @return list<SearchTerm>
     */
    private static function normalize(string $query): array
    {
        return (new QueryNormalizer())->normalize(SearchQuery::fromRaw($query));
    }

    /**
     * Architecture §4 steps, one row each: query → the single term's variants as [text, strength] (D31), original first.
     *
     * @return iterable<string, array{string, list<array{string, float}>}>
     */
    public static function singleWord(): iterable
    {
        yield 'two prefixes stripped one by one (מהספה)' => ['מהספה', [['מהספה', 1.0], ['הספה', 0.85], ['ספה', 0.7]]];
        yield 'Hebrew plural ות (שערות)' => ['שערות', [['שערות', 1.0], ['ערות', 0.85], ['שער', 0.9]]];
        yield 'Hebrew plural ים (כלבים)' => ['כלבים', [['כלבים', 1.0], ['לבים', 0.85], ['בים', 0.7], ['כלב', 0.9]]];
        yield 'attached ל (לכלב)' => ['לכלב', [['לכלב', 1.0], ['כלב', 0.85]]];
        yield 'construct plural ־י (שואבי, AC-1.5)' => ['שואבי', [['שואבי', 1.0], ['ואבי', 0.85], ['אבי', 0.7], ['שואב', 0.8], ['ואב', 0.65]]];
        yield 'D31: a stripped form keeps at least 3 letters (no "של" from לבשל)' => ['לבשל', [['לבשל', 1.0], ['בשל', 0.85]]];
        yield 'D32: final letter restored after stripping a plural (ארונות → ארון)' => ['ארונות', [['ארונות', 1.0], ['ארונ', 0.9], ['ארון', 0.9]]];
        yield 'D32: מדפים → מדף' => ['מדפים', [['מדפים', 1.0], ['דפים', 0.85], ['מדפ', 0.9], ['מדף', 0.9]]];
        yield '3-letter Hebrew words are left alone' => ['ספה', [['ספה', 1.0]]];
        yield 'short tokens are kept as typed' => ['טל', [['טל', 1.0]]];
        yield 'English plural -s' => ['vacuums', [['vacuums', 1.0], ['vacuum', 0.9]]];
        yield 'English plural -ies' => ['batteries', [['batteries', 1.0], ['battery', 0.9]]];
        yield 'English plural -es after ss/x/ch/sh' => ['glasses', [['glasses', 1.0], ['glass', 0.9]]];
        yield 'English: no stripping after ss' => ['glass', [['glass', 1.0]]];
        yield 'lowercased, alias English → Hebrew' => ['DYSON', [['dyson', 1.0], ['דייסון', 1.0]]];
        yield 'alias, Hebrew → English (אייפון)' => ['אייפון', [['אייפון', 1.0], ['iphone', 1.0]]];
        yield 'alias found under a prefix (לדייסון)' => ['לדייסון', [['לדייסון', 1.0], ['דייסון', 0.85], ['dyson', 0.85]]];
        yield 'niqqud is removed (AC-4.7)' => ['סַפָּה', [['ספה', 1.0]]];
        yield 'qualifier: number' => ['16', [['16', 1.0]]];
        yield 'qualifier: number with unit' => ['128GB', [['128gb', 1.0]]];
    }

    /**
     * @param list<array{string, float}> $expected
     */
    #[DataProvider('singleWord')]
    public function testBuildsVariants(string $query, array $expected): void
    {
        $terms = self::normalize($query);

        self::assertCount(1, $terms);
        $actual = array_map(static fn (Variant $v): array => [$v->text, round($v->strength, 2)], $terms[0]->variants);
        self::assertSame($expected, $actual);
    }

    public function testQualifiersAreNumbersOptionallyWithAUnit(): void
    {
        $qualifiers = [];
        foreach (self::normalize('iphone 16 128gb s23 1.5 ליטר') as $term) {
            $qualifiers[$term->original] = $term->isQualifier;
        }

        self::assertSame(['iphone' => false, '16' => true, '128gb' => true, 's23' => false, '1' => true, '5' => true, 'ליטר' => false], $qualifiers);
    }

    public function testDropsStopWordsAndSingleLetters(): void
    {
        $originals = array_map(static fn (SearchTerm $t): string => $t->original, self::normalize('מתנה למישהו שאוהב לבשל'));
        self::assertSame(['מתנה', 'שאוהב', 'לבשל'], $originals, '"למישהו" is a stop-word once its ל is stripped');

        $originals = array_map(static fn (SearchTerm $t): string => $t->original, self::normalize('משהו שיעזור לנקות שערות של כלב מהספה'));
        self::assertSame(['לנקות', 'שערות', 'כלב', 'מהספה'], $originals);

        $originals = array_map(static fn (SearchTerm $t): string => $t->original, self::normalize('a gift for the dog & cat'));
        self::assertSame(['gift', 'dog', 'cat'], $originals);
    }

    public function testSplitsLikeTheSearchIndex(): void
    {
        // SQLite's unicode61 tokenizer splits at geresh/apostrophes and any punctuation; the query must split the same way.
        $originals = array_map(static fn (SearchTerm $t): string => $t->original, self::normalize("דולצ׳ה גוסטו, TP-Link; סייג' \"OR\" 1=1 --"));

        self::assertSame(['דולצ', 'גוסטו', 'tp', 'link', 'סייג', '1'], $originals, '"or" is an English stop-word; FTS operators are made inert later by quoting');
    }

    public function testRepeatedWordsBecomeOneTerm(): void
    {
        self::assertCount(1, self::normalize('dyson Dyson DYSON'));
    }

    public function testAQueryOfOnlyStopWordsOrSymbolsHasNoTerms(): void
    {
        self::assertSame([], self::normalize('של את עם'));
        self::assertSame([], self::normalize('🐶 *** ^^'));
    }
}
