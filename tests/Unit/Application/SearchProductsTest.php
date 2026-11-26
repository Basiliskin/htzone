<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Application;

use Htzone\Application\NoticeType;
use Htzone\Application\SearchProducts;
use Htzone\Application\SearchResult;
use Htzone\Domain\Catalog\Money;
use Htzone\Domain\Catalog\Product;
use Htzone\Domain\Search\Candidate;
use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\Ranker;
use Htzone\Domain\Search\SearchFilter;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Tests\Support\FakeProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * The use case's own decisions (architecture §6 steps 4–5), with a fake repository.
 */
final class SearchProductsTest extends TestCase
{
    private static function search(string $query, FakeProductRepository $repo, SearchFilter $filter = new SearchFilter()): SearchResult
    {
        return (new SearchProducts($repo, new QueryNormalizer(), new Ranker()))->execute(SearchQuery::fromRaw($query), $filter);
    }

    /**
     * @param list<string> $tags
     */
    private static function candidate(string $id, string $name, string $attributes = '', array $tags = [], string $category = 'קטגוריה', int $shekels = 10): Candidate
    {
        return new Candidate(new Product($id, $name, null, $category, 'תת', 'תיאור', $attributes, 'https://example.com/x.jpg', Money::fromShekels($shekels), $tags), -1.0);
    }

    /**
     * 30 sofas: odd ids in "בית ועיצוב" at ₪100 + id, even ids in "ריהוט חוץ".
     *
     * @return list<Candidate>
     */
    private static function thirtySofas(): array
    {
        $candidates = [];
        for ($i = 1; $i <= 30; ++$i) {
            $candidates[] = self::candidate(sprintf('P%04d', $i), 'ספה', category: $i % 2 === 1 ? 'בית ועיצוב' : 'ריהוט חוץ', shekels: 100 + $i);
        }

        return $candidates;
    }

    public function testShowsAtMost24ButCountsFacetsOverEveryRankedResult(): void
    {
        $result = self::search('ספה', new FakeProductRepository(self::thirtySofas(), []));

        self::assertCount(24, $result->results);
        self::assertSame(['בית ועיצוב' => 15, 'ריהוט חוץ' => 15], $result->facets->categories);
        self::assertSame(10_100, $result->facets->minPrice?->minor);
        self::assertSame(13_000, $result->facets->maxPrice?->minor);
    }

    public function testAFilterNarrowsTheResultsAndKeepsTheirOrder(): void
    {
        $all = self::ids(self::search('ספה', new FakeProductRepository(self::thirtySofas(), [])));
        $result = self::search('ספה', new FakeProductRepository(self::thirtySofas(), []), SearchFilter::fromRaw('בית ועיצוב', null, '120'));

        // Odd ids (בית ועיצוב) up to ₪120, in the same relative order as the unfiltered results.
        $expected = array_values(array_filter($all, static fn (string $id): bool => (int) substr($id, 1) % 2 === 1 && (int) substr($id, 1) <= 20));
        self::assertCount(10, $expected);
        self::assertSame($expected, self::ids($result));
    }

    public function testAFilterReachesProductsBelowTheFirst24(): void
    {
        // Ranked by id on equal scores, so P0025–P0030 are never among the first 24 without a filter.
        $result = self::search('ספה', new FakeProductRepository(self::thirtySofas(), []), SearchFilter::fromRaw(null, '125', null));

        self::assertSame(['P0025', 'P0026', 'P0027', 'P0028', 'P0029', 'P0030'], self::ids($result));
    }

    public function testChipsAndFiltersUseOnlyStrongMatchesButTheUnfilteredListKeepsTheTail(): void
    {
        // D46: P0099 is found by the index but its name doesn't say "ספה": a weak match, far below 50% of the top.
        $candidates = [...self::thirtySofas(), self::candidate('P0099', 'שטיח לסלון', category: 'שטיחים', shekels: 50)];
        $weakFirst = [self::candidate('P0099', 'שטיח לסלון', category: 'שטיחים', shekels: 50), self::candidate('P0001', 'ספה', shekels: 900)];

        self::assertContains('P0099', self::ids(self::search('ספה', new FakeProductRepository($weakFirst, []))), 'no filter: the list is unchanged');
        self::assertArrayNotHasKey('שטיחים', self::search('ספה', new FakeProductRepository($candidates, []))->facets->categories);
        self::assertSame(10_100, self::search('ספה', new FakeProductRepository($candidates, []))->facets->minPrice?->minor, 'the ₪50 rug is not the cheapest "sofa"');
        self::assertSame([], self::search('ספה', new FakeProductRepository($weakFirst, []), SearchFilter::fromRaw(null, null, '100'))->results);
    }

    public function testAFilterThatMatchesNothingKeepsTheFacetsAndSkipsTheTypoFallback(): void
    {
        $repo = new FakeProductRepository(self::thirtySofas(), []);
        $result = self::search('ספה', $repo, SearchFilter::fromRaw('אין כזו', null, null));

        self::assertSame([], $result->results);
        self::assertNull($result->notice);
        self::assertSame(['בית ועיצוב' => 15, 'ריהוט חוץ' => 15], $result->facets->categories);
        self::assertSame(0, $repo->trigramCalls, 'the search did find products; only the filter removed them');
    }

    /**
     * @return list<string>
     */
    private static function ids(SearchResult $result): array
    {
        return array_map(static fn (RankedProduct $r): string => $r->product->id, $result->results);
    }

    public function testAMatchHasNoNotice(): void
    {
        $repo = new FakeProductRepository([self::candidate('P0001', 'ספה תלת מושבית')], []);
        $result = self::search('ספה', $repo);

        self::assertSame(['P0001'], self::ids($result));
        self::assertNull($result->notice);
        self::assertFalse($result->usedFallback);
        self::assertSame(0, $repo->trigramCalls, 'the typo index is only asked when the main search finds nothing');
    }

    public function testNoMatchFallsBackToCloseSpellings(): void
    {
        $repo = new FakeProductRepository([], [self::candidate('P0279', 'מיקרוגל דיגיטלי'), self::candidate('P0500', 'מקרר')]);
        $result = self::search('מקרוגל', $repo);

        self::assertSame(['P0279'], self::ids($result));
        self::assertTrue($result->usedFallback);
        self::assertSame(NoticeType::DidYouMean, $result->notice?->type);
        self::assertSame('מיקרוגל', $result->notice->suggestion);
        self::assertSame([['מקרוגל']], $repo->trigramWords);
    }

    public function testTheFallbackCorrectsOnlyRealWordsOf3LettersOrMore(): void
    {
        $repo = new FakeProductRepository([], []);
        self::search('xyzzy 16 טל', $repo);

        self::assertSame([['xyzzy']], $repo->trigramWords);
    }

    public function testNothingAnywhereIsAnEmptyResult(): void
    {
        $result = self::search('xyzzy', new FakeProductRepository([], [self::candidate('P0001', 'ספה')]));

        self::assertSame([], $result->results);
        self::assertNull($result->notice);
    }

    public function testANumberNoTopResultContainsGivesTheMissingModelNotice(): void
    {
        $result = self::search('iPhone 16', new FakeProductRepository([self::candidate('P0401', 'סמארטפון 128GB', 'זיכרון: 128GB', ['iphone'])], []));

        self::assertSame(['P0401'], self::ids($result));
        self::assertSame(NoticeType::MissingModel, $result->notice?->type);
    }

    public function testANumberATopResultContainsGivesNoNotice(): void
    {
        $result = self::search('מחבת 28', new FakeProductRepository([self::candidate('P0001', 'מחבת', 'קוטר: 28 ס״מ')], []));

        self::assertNull($result->notice);
    }

    public function testAQueryOfOnlyFillerWordsSearchesNothing(): void
    {
        $repo = new FakeProductRepository([self::candidate('P0001', 'ספה')], [self::candidate('P0001', 'ספה')]);
        $result = self::search('של את עם', $repo);

        self::assertSame([], $result->results);
        self::assertSame(0, $repo->candidateCalls + $repo->trigramCalls);
    }
}
