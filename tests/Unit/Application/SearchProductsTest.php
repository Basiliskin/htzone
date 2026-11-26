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
use Htzone\Domain\Search\SearchQuery;
use Htzone\Tests\Support\FakeProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * The use case's own decisions (architecture §6 steps 4–5), with a fake repository.
 */
final class SearchProductsTest extends TestCase
{
    private static function search(string $query, FakeProductRepository $repo): SearchResult
    {
        return (new SearchProducts($repo, new QueryNormalizer(), new Ranker()))->execute(SearchQuery::fromRaw($query));
    }

    /**
     * @param list<string> $tags
     */
    private static function candidate(string $id, string $name, string $attributes = '', array $tags = []): Candidate
    {
        return new Candidate(new Product($id, $name, null, 'קטגוריה', 'תת', 'תיאור', $attributes, 'https://example.com/x.jpg', Money::fromShekels(10), $tags), -1.0);
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
