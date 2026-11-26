<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Search;

use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;
use Htzone\Domain\Search\Candidate;
use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\Ranker;
use Htzone\Domain\Search\SearchQuery;
use PHPUnit\Framework\TestCase;

/**
 * Each ranking rule of architecture §6, proven with hand-made candidates and real normalized terms.
 */
final class RankerTest extends TestCase
{
    /**
     * @param list<string> $tags
     */
    private static function candidate(string $id, string $name, float $bm25 = -1.0, ?string $brand = null, array $tags = [], string $attributes = ''): Candidate
    {
        return new Candidate(
            new Product($id, $name, $brand, 'קטגוריה', 'תת קטגוריה', 'תיאור', $attributes, 'https://example.com/x.jpg', Money::fromShekels(100), $tags),
            $bm25,
        );
    }

    /**
     * @param list<Candidate> $candidates
     * @return list<string>
     */
    private static function rank(string $query, array $candidates): array
    {
        $terms = (new QueryNormalizer())->normalize(SearchQuery::fromRaw($query));

        return array_map(static fn (RankedProduct $r): string => $r->product->id, (new Ranker())->rank($candidates, $terms));
    }

    public function testMatchingMoreOfTheQueryBeatsABetterBm25(): void
    {
        $ids = self::rank('שואב אבק', [
            self::candidate('P0001', 'מברשת ניקוי', bm25: -12.0, tags: ['אבק']),
            self::candidate('P0002', 'שואב אבק אלחוטי', bm25: -2.0),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids);
    }

    public function testTheWordAsTypedBeatsAStrippedForm(): void
    {
        $ids = self::rank('כלבים', [
            self::candidate('P0001', 'רצועה לכלב', tags: ['כלב']),
            self::candidate('P0002', 'רצועה לכלבים', tags: ['כלבים']),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids);
    }

    public function testTheBrandItselfBeatsAMention(): void
    {
        $ids = self::rank('דייסון', [
            self::candidate('P0001', 'מסנן תואם', bm25: -9.0, brand: 'Generic', tags: ['dyson']),
            self::candidate('P0002', 'שואב אבק', bm25: -1.0, brand: 'Dyson', tags: ['dyson']),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids, 'the Hebrew alias reaches the Latin brand');
    }

    public function testANameMatchBeatsATagOnlyMatch(): void
    {
        $ids = self::rank('קומקום', [
            self::candidate('P0001', 'מכשיר חימום מים', tags: ['קומקום']),
            self::candidate('P0002', 'קומקום חשמלי'),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids);
    }

    public function testProductsMatchingOnlyANumberAreDropped(): void
    {
        $ids = self::rank('iPhone 16', [
            self::candidate('P0001', 'מחבת 16 ס״מ', bm25: -20.0),
            self::candidate('P0002', 'מגן מסך', tags: ['iphone']),
        ]);

        self::assertSame(['P0002'], $ids, 'ADR 0003: "16" alone must not pull in a pan');
    }

    public function testNumberOnlyMatchesStayWhenNothingElseMatches(): void
    {
        self::assertSame(['P0001'], self::rank('iPhone 16', [self::candidate('P0001', 'מחבת 16 ס״מ')]));
    }

    public function testAMatchingNumberBreaksATieBetweenWordMatches(): void
    {
        $ids = self::rank('מחבת 28', [
            self::candidate('P0001', 'מחבת 24 ס״מ'),
            self::candidate('P0002', 'מחבת נון סטיק', attributes: 'קוטר: 28'),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids, 'qualifiers also look in the attributes');
    }

    public function testTiesAreOrderedByIdSoResultsAreStable(): void
    {
        $ids = self::rank('ספה', [
            self::candidate('P0003', 'ספה'),
            self::candidate('P0001', 'ספה'),
            self::candidate('P0002', 'ספה'),
        ]);

        self::assertSame(['P0001', 'P0002', 'P0003'], $ids);
    }

    public function testDescriptionOnlyMatchesAreKeptBelowFieldMatches(): void
    {
        $ids = self::rank('ספה', [
            self::candidate('P0001', 'כרית', bm25: -15.0),
            self::candidate('P0002', 'ספה תלת מושבית', bm25: -1.0),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids);
    }

    public function testReturnsAtMost24(): void
    {
        $candidates = [];
        for ($i = 1; $i <= 30; ++$i) {
            $candidates[] = self::candidate(sprintf('P%04d', $i), 'ספה');
        }

        self::assertCount(24, self::rank('ספה', $candidates));
        self::assertSame([], self::rank('ספה', []));
    }

    public function testTypoFallbackKeepsCloseSpellingsAndNamesTheCorrection(): void
    {
        $ranked = (new Ranker())->rankFuzzy([
            self::candidate('P0001', 'מקרר מקפיא'),
            self::candidate('P0002', 'מיקרוגל דיגיטלי 25 ליטר'),
            self::candidate('P0003', 'מיקרוגל משולב גריל', tags: ['מיקרוגל']),
        ], ['מקרוגל']);

        self::assertSame(['P0002', 'P0003'], array_map(static fn (RankedProduct $r): string => $r->product->id, $ranked), 'similarity 0.2 (מקרר) is below 0.4');
        self::assertSame('מיקרוגל', $ranked[0]->suggestion);
    }
}
