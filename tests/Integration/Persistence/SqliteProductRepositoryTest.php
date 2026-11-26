<?php

declare(strict_types=1);

namespace Htzone\Tests\Integration\Persistence;

use Htzone\Domain\Search\Candidate;
use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Infrastructure\Import\CatalogImporter;
use Htzone\Infrastructure\Import\CsvCatalogReader;
use Htzone\Infrastructure\Import\EnrichmentReader;
use Htzone\Infrastructure\Import\PriceGenerator;
use Htzone\Infrastructure\Persistence\FtsQueryBuilder;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Infrastructure\Persistence\SqliteProductRepository;
use Htzone\Tests\Support\TempDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The repository against a real SQLite file holding the 3-row fixture, opened read-only like production.
 */
final class SqliteProductRepositoryTest extends TestCase
{
    use TempDirectory;

    private const ROOT = __DIR__ . '/../../..';

    private SqliteProductRepository $repository;

    protected function setUp(): void
    {
        $path = $this->tempDir() . '/catalog.sqlite';
        $pdo = SqliteConnection::readWrite($path);
        (new CatalogImporter($pdo, new MigrationRunner($pdo, self::ROOT . '/migrations'), new CsvCatalogReader(), new EnrichmentReader(), PriceGenerator::fromFile(self::ROOT . '/data/price-ranges.json')))
            ->import(self::ROOT . '/tests/Fixtures/catalog-small.csv', self::ROOT . '/tests/Fixtures/enrichment-small.jsonl');

        $this->repository = new SqliteProductRepository(SqliteConnection::readOnly($path), new FtsQueryBuilder());
    }

    /**
     * @return list<Candidate>
     */
    private function find(string $query, int $limit = 100): array
    {
        return $this->repository->findCandidates((new QueryNormalizer())->normalize(SearchQuery::fromRaw($query)), $limit);
    }

    /**
     * @param list<Candidate> $candidates
     * @return list<string>
     */
    private static function ids(array $candidates): array
    {
        return array_map(static fn (Candidate $c): string => $c->product->id, $candidates);
    }

    public function testFindsAndRebuildsTheWholeProduct(): void
    {
        $found = $this->find('דייסון');

        self::assertSame(['P0201'], self::ids($found));
        $product = $found[0]->product;
        self::assertSame('Dyson', $product->brand);
        self::assertSame('מוצרי חשמל לבית', $product->category);
        self::assertSame('שואבי אבק ורובוטים', $product->subcategory);
        self::assertSame('₪3,899', $product->price->format(), 'same golden price as PriceGeneratorTest');
        self::assertContains('שואבי אבק', $product->tags);
        self::assertContains('dyson', array_map(mb_strtolower(...), $product->tags));
        self::assertLessThan(0.0, $found[0]->bm25, 'bm25: more negative is better');
    }

    public function testAnEmptyBrandComesBackAsNull(): void
    {
        self::assertNull($this->find('סלסלה')[0]->product->brand);
    }

    public function testBestMatchFirstAndLimited(): void
    {
        $both = $this->find('מטבח');   // the category of P0001 and P0070

        self::assertEqualsCanonicalizing(['P0001', 'P0070'], self::ids($both));
        self::assertCount(1, $this->find('מטבח', limit: 1));
        self::assertGreaterThanOrEqual($both[0]->bm25, $both[1]->bm25, 'ordered by bm25');
    }

    public function testHostileInputIsSearchedAsText(): void
    {
        self::assertSame([], $this->find('" OR 1=1 --'));
        self::assertSame([], $this->find("'; DROP TABLE products; --"));
        self::assertSame([], $this->find('NEAR(a b) ^name:* AND NOT'));
        self::assertSame(3, $this->repository->count(), 'the table is still there');
        self::assertSame([], $this->repository->findCandidates([], 100));
    }

    public function testTrigramsFindAMisspelling(): void
    {
        self::assertSame(['P0201'], self::ids($this->repository->findByTrigrams(['דייסן'], 100)));
        self::assertSame([], $this->repository->findByTrigrams(['ab'], 100), 'too short for trigrams');
    }
}
