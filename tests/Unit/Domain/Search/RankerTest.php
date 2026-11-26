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

    public function testTheProductTypeInTheSubcategoryBreaksATie(): void
    {
        $vacuum = new Candidate(new Product('P0002', 'שואב אבק אלחוטי', null, 'מוצרי חשמל לבית', 'שואבי אבק ורובוטים', '', '', 'https://example.com/x.jpg', Money::fromShekels(100), []), -1.0);
        $ids = self::rank('שואב אבק', [self::candidate('P0001', 'שואב אבק אלחוטי לרכב'), $vacuum]);

        self::assertSame(['P0002', 'P0001'], $ids, 'without the bonus the id tie-break would put P0001 first (example row 1: a car vacuum filed under "רכב")');
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

    public function testAnEnglishWholeWordBeatsAWordItOnlyStarts(): void
    {
        $ids = self::rank('kettle', [
            self::candidate('P0001', 'קטלבל', bm25: -12.0, tags: ['kettlebell']),
            self::candidate('P0002', 'אופניים', bm25: -12.0, brand: 'Kettler'),
            self::candidate('P0003', 'קומקום', bm25: -1.0, tags: ['kettle']),
        ]);

        self::assertSame('P0003', $ids[0], 'kettle is a whole word only in P0003 (D41)');
    }

    public function testAHalfTypedEnglishWordStillCountsAsAMatch(): void
    {
        // Search runs while typing: "macb" must still beat a product that matched only in its description.
        $ids = self::rank('macb', [
            self::candidate('P0001', 'כרית', bm25: -15.0),
            self::candidate('P0002', 'מחשב נייד', bm25: -1.0, tags: ['macbook']),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids);
    }

    public function testHebrewPrefixMatchesKeepFullStrength(): void
    {
        // Hebrew relies on prefixes for plurals and construct forms (D3), so D41 is Latin only:
        // both products match "כלב" fully and the better bm25 still wins.
        $ids = self::rank('כלב', [
            self::candidate('P0001', 'מיטה', bm25: -1.0, tags: ['כלב']),
            self::candidate('P0002', 'מיטה', bm25: -9.0, tags: ['כלבים']),
        ]);

        self::assertSame(['P0002', 'P0001'], $ids);
    }

    public function testStrongMatchesScoreAtLeastHalfOfTheBest(): void
    {
        $ranker = new Ranker();
        $ranked = $ranker->rank([self::candidate('P0001', 'ספה'), self::candidate('P0002', 'שטיח')], (new QueryNormalizer())->normalize(SearchQuery::fromRaw('ספה')));

        self::assertCount(2, $ranked);
        self::assertSame(['P0001'], array_map(static fn (RankedProduct $r): string => $r->product->id, $ranker->strong($ranked)));
        self::assertSame([], $ranker->strong([]));
    }

    public function testRanksEveryCandidateSoFiltersCanReachPastTheFirst24(): void
    {
        // The 24-result page size is the use case's job (SearchProducts, D43), after filtering.
        $candidates = [];
        for ($i = 1; $i <= 30; ++$i) {
            $candidates[] = self::candidate(sprintf('P%04d', $i), 'ספה');
        }

        self::assertCount(30, self::rank('ספה', $candidates));
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
