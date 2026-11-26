<?php

declare(strict_types=1);

namespace Htzone\Tests\Integration\Import;

use Htzone\Infrastructure\Import\CatalogImporter;
use Htzone\Infrastructure\Import\CsvCatalogReader;
use Htzone\Infrastructure\Import\EnrichmentReader;
use Htzone\Infrastructure\Import\PriceGenerator;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Tests\Support\TempDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The committed catalog, enrichment and price ranges import cleanly (AC-6.2).
 */
final class RealCatalogImportTest extends TestCase
{
    use TempDirectory;

    private const ROOT = __DIR__ . '/../../..';

    public function testTheRealCatalogImports(): void
    {
        $pdo = SqliteConnection::readWrite($this->tempDir() . '/catalog.sqlite');
        $importer = new CatalogImporter(
            $pdo,
            new MigrationRunner($pdo, self::ROOT . '/migrations'),
            new CsvCatalogReader(),
            new EnrichmentReader(),
            PriceGenerator::fromFile(self::ROOT . '/data/price-ranges.json'),
        );

        $report = $importer->import(self::ROOT . '/assets/catalog_no_prices.csv', self::ROOT . '/data/enrichment.jsonl');

        self::assertSame(1000, $report->products);
        self::assertSame(1000, $report->tagged);
        self::assertSame(['P0014: description contains letters outside Hebrew/Latin: ی د'], $report->warnings, 'the known data problem, and only that one');

        $stats = $pdo->query('SELECT count(*) AS n, sum(price_minor < 1900) AS below_min, sum(brand IS NULL) AS no_brand, count(DISTINCT category) AS categories FROM products');
        self::assertNotFalse($stats);
        self::assertSame(['n' => 1000, 'below_min' => 0, 'no_brand' => 52, 'categories' => 13], $stats->fetch(), 'no price under ₪19, the lowest range minimum');

        $fts = $pdo->query("SELECT p.id FROM products_fts JOIN products p ON p.rowid = products_fts.rowid WHERE products_fts MATCH 'tags:אייפון' ORDER BY p.id LIMIT 3");
        self::assertNotFalse($fts);
        self::assertSame(['P0401', 'P0407', 'P0413'], $fts->fetchAll(\PDO::FETCH_COLUMN), 'enrichment tags are searchable');
    }
}
