<?php

declare(strict_types=1);

namespace Htzone;

use Htzone\Infrastructure\Config;
use Htzone\Infrastructure\Import\CatalogImporter;
use Htzone\Infrastructure\Import\CsvCatalogReader;
use Htzone\Infrastructure\Import\EnrichmentReader;
use Htzone\Infrastructure\Import\PriceGenerator;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;

/**
 * The composition root (architecture §10): the only place that wires concrete classes together.
 * Entry points (public/index.php, bin/*) ask it for what they need; tests build their own graphs.
 */
final readonly class Services
{
    public const ROOT = __DIR__ . '/..';
    public const CATALOG_CSV = self::ROOT . '/assets/catalog_no_prices.csv';
    public const ENRICHMENT = self::ROOT . '/data/enrichment.jsonl';
    public const PRICE_RANGES = self::ROOT . '/data/price-ranges.json';
    private const MIGRATIONS = self::ROOT . '/migrations';

    public function __construct(public Config $config)
    {
    }

    public function migrationRunner(): MigrationRunner
    {
        return new MigrationRunner(SqliteConnection::readWrite($this->config->dbPath), self::MIGRATIONS);
    }

    public function catalogImporter(): CatalogImporter
    {
        $pdo = SqliteConnection::readWrite($this->config->dbPath);

        return new CatalogImporter(
            $pdo,
            new MigrationRunner($pdo, self::MIGRATIONS),
            new CsvCatalogReader(),
            new EnrichmentReader(),
            PriceGenerator::fromFile(self::PRICE_RANGES),
        );
    }
}
