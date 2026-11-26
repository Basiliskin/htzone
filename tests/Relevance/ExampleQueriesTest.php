<?php

declare(strict_types=1);

namespace Htzone\Tests\Relevance;

use Htzone\Application\NoticeType;
use Htzone\Application\SearchProducts;
use Htzone\Application\SearchResult;
use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\RankedProduct;
use Htzone\Domain\Search\Ranker;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Infrastructure\Persistence\FtsQueryBuilder;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Infrastructure\Persistence\SqliteProductRepository;
use Htzone\Tests\Support\RealCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The example-query table (docs/requirements.md §4) against the real imported catalog.
 * A row that can't pass is changed only by an explicit decision, never quietly.
 */
final class ExampleQueriesTest extends TestCase
{
    private const VACUUMS = 'שואבי אבק ורובוטים';
    private const APPLE_PHONES = ['P0401', 'P0407', 'P0413'];

    private static ?SearchProducts $useCase = null;

    private static function useCase(): SearchProducts
    {
        return self::$useCase ??= new SearchProducts(
            new SqliteProductRepository(SqliteConnection::readOnly(RealCatalog::path()), new FtsQueryBuilder()),
            new QueryNormalizer(),
            new Ranker(),
        );
    }

    private static function search(string $query): SearchResult
    {
        return self::useCase()->execute(SearchQuery::fromRaw($query));
    }

    /**
     * @return list<string>
     */
    private static function top(SearchResult $result, int $n): array
    {
        return array_map(static fn (RankedProduct $r): string => $r->product->id, array_slice($result->results, 0, $n));
    }

    private static function describe(SearchResult $result): string
    {
        return "\n" . implode("\n", array_map(
            static fn (RankedProduct $r): string => sprintf('  %s %.2f %s | %s', $r->product->id, $r->score, $r->product->name, $r->product->subcategory),
            array_slice($result->results, 0, 6),
        ));
    }

    /**
     * Row → [query, check]. Each check gets the result and the top-6 listing for failure messages.
     *
     * @return iterable<string, array{string, \Closure(SearchResult, string): void}>
     */
    public static function rows(): iterable
    {
        $topFiveAreVacuums = static function (SearchResult $r, string $why): void {
            self::assertCount(5, self::top($r, 5), $why);
            foreach (array_slice($r->results, 0, 5) as $ranked) {
                self::assertSame(self::VACUUMS, $ranked->product->subcategory, $why);
            }
            self::assertSame([], array_intersect(['P0077', 'P0678'], self::top($r, 5)), 'vacuum bags are not vacuums' . $why);
        };

        yield '1 🅱 שואב אבק' => ['שואב אבק', $topFiveAreVacuums];
        yield '2 🅱 iPhone 16' => ['iPhone 16', static function (SearchResult $r, string $why): void {
            self::assertEqualsCanonicalizing(self::APPLE_PHONES, self::top($r, 3), $why);
            self::assertSame(NoticeType::MissingModel, $r->notice?->type, $why);
        }];
        yield '3 🅱 אייפון' => ['אייפון', static function (SearchResult $r, string $why): void {
            self::assertEqualsCanonicalizing(self::APPLE_PHONES, self::top($r, 3), $why);
            self::assertNull($r->notice, $why);
        }];
        yield '4 🅱 מתנה למישהו שאוהב לבשל' => ['מתנה למישהו שאוהב לבשל', static function (SearchResult $r, string $why): void {
            self::assertGreaterThanOrEqual(4, self::countInCategory($r, 'מטבח ובישול'), $why);
        }];
        yield '5 🅱 משהו שיעזור לנקות שערות של כלב מהספה' => ['משהו שיעזור לנקות שערות של כלב מהספה', static function (SearchResult $r, string $why): void {
            self::assertContains('P0950', self::top($r, 5), $why);
            self::assertNotSame([], array_intersect(['P0207', 'P0225'], self::top($r, 5)), $why);
        }];
        yield '6 שואבי אבק' => ['שואבי אבק', $topFiveAreVacuums];
        yield '7 לכלב' => ['לכלב', static function (SearchResult $r, string $why): void {
            self::assertCount(5, self::top($r, 5), $why);
            self::assertSame([], array_diff(self::top($r, 5), ['P0936', 'P0937', 'P0938', 'P0939', 'P0940', 'P0941']), $why);
        }];
        yield '8 Dyson' => ['Dyson', static function (SearchResult $r, string $why): void {
            self::assertEqualsCanonicalizing(['P0201', 'P0701', 'P0707'], self::top($r, 3), $why);
        }];
        yield '9 דייסון' => ['דייסון', static function (SearchResult $r, string $why): void {
            self::assertSame('P0201', self::top($r, 1)[0] ?? null, $why);
            self::assertSame([], array_diff(['P0701', 'P0707'], self::top($r, 5)), $why);
        }];
        yield '10 שואב Dyson' => ['שואב Dyson', static function (SearchResult $r, string $why): void {
            self::assertSame('P0201', self::top($r, 1)[0] ?? null, $why);
        }];
        yield '11 robot vacuum' => ['robot vacuum', static function (SearchResult $r, string $why): void {
            self::assertCount(5, self::top($r, 5), $why);
            self::assertSame([], array_diff(self::top($r, 5), ['P0202', 'P0203', 'P0204', 'P0205', 'P0213']), $why);
        }];
        yield '12 מכונת קפה' => ['מכונת קפה', static function (SearchResult $r, string $why): void {
            self::assertSame([], array_diff(self::top($r, 3), ['P0101', 'P0102', 'P0103', 'P0104', 'P0108', 'P0109', 'P0111', 'P0115']), $why);
            self::assertNotSame('P0129', self::top($r, 1)[0] ?? null, $why);
        }];
        yield '13 Samsung' => ['Samsung', static function (SearchResult $r, string $why): void {
            self::assertCount(5, self::top($r, 5), $why);
            foreach (array_slice($r->results, 0, 5) as $ranked) {
                self::assertSame('Samsung', $ranked->product->brand, $why);
            }
        }];
        yield '14 מתנה לחובב קפה' => ['מתנה לחובב קפה', static function (SearchResult $r, string $why): void {
            self::assertGreaterThanOrEqual(4, self::countInCategory($r, 'קפה, משקאות וגורמה'), $why);
        }];
        yield '15 מקרוגל (typo)' => ['מקרוגל', static function (SearchResult $r, string $why): void {
            self::assertTrue($r->usedFallback, $why);
            self::assertEqualsCanonicalizing(['P0279', 'P0285'], self::top($r, 2), $why);
            self::assertSame(NoticeType::DidYouMean, $r->notice?->type, $why);
            self::assertSame('מיקרוגל', $r->notice->suggestion, $why);
        }];
        yield '16 macbok (typo)' => ['macbok', static function (SearchResult $r, string $why): void {
            self::assertTrue($r->usedFallback, $why);
            self::assertSame([], array_diff(['P0305', 'P0306'], self::top($r, 3)), $why);
        }];
        foreach (['קשדגכ', 'xyzzy'] as $nonsense) {
            yield "17 {$nonsense} (nonsense)" => [$nonsense, static function (SearchResult $r, string $why): void {
                self::assertSame([], $r->results, $why);
            }];
        }
        yield '18 " OR 1=1 -- (hostile)' => ['" OR 1=1 --', static function (SearchResult $r, string $why): void {
            self::assertLessThanOrEqual(24, count($r->results), $why);   // reaching here means no exception
        }];
        yield '19 iPhone 16: nothing matched only on "16"' => ['iPhone 16', static function (SearchResult $r, string $why): void {
            self::assertSame([], array_intersect(['P0027', 'P0301', 'P0306'], self::top($r, 5)), $why);
        }];
        // QA F1 / D41: an English word that only starts a product word ("Kettler", "kettlebell") is a weaker match.
        yield '20 kettle (English whole word)' => ['kettle', static function (SearchResult $r, string $why): void {
            self::assertEqualsCanonicalizing(['P0162', 'P0009'], self::top($r, 2), $why);
            self::assertSame([], array_intersect(['P0802', 'P0805'], self::top($r, 3)), 'spinning bike / kettlebell' . $why);
        }];
        // D42: the catalog has only two air fryers; everything after them matches "air" alone.
        yield '21 Air Fryer (both words first)' => ['Air Fryer', static function (SearchResult $r, string $why): void {
            self::assertEqualsCanonicalizing(['P0001', 'P0002'], self::top($r, 2), $why);
        }];
    }

    /**
     * @param \Closure(SearchResult, string): void $check
     */
    #[DataProvider('rows')]
    public function testExampleQuery(string $query, \Closure $check): void
    {
        $result = self::search($query);

        $check($result, self::describe($result));
    }

    public function testEveryExampleQueryAnswersWithin100MsAtP95(): void
    {
        $queries = array_unique(array_map(static fn (array $row): string => $row[0], iterator_to_array(self::rows())));
        self::search('warm-up');

        $durations = [];
        for ($round = 0; $round < 5; ++$round) {
            foreach ($queries as $query) {
                $started = hrtime(true);
                self::search($query);
                $durations[] = (hrtime(true) - $started) / 1e6;
            }
        }
        sort($durations);
        $p95 = $durations[(int) ceil(0.95 * count($durations)) - 1];

        self::assertLessThan(100.0, $p95, sprintf('AC-1.6: p95 %.1f ms over %d searches', $p95, count($durations)));
    }

    private static function countInCategory(SearchResult $result, string $category): int
    {
        return count(array_filter(array_slice($result->results, 0, 5), static fn (RankedProduct $r): bool => $r->product->category === $category));
    }
}
