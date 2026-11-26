<?php

declare(strict_types=1);

namespace Htzone\Tests\Support;

use Htzone\Infrastructure\Import\CatalogImporter;
use Htzone\Infrastructure\Import\CsvCatalogReader;
use Htzone\Infrastructure\Import\EnrichmentReader;
use Htzone\Infrastructure\Import\PriceGenerator;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;

/**
 * The committed catalog, enrichment and price ranges, imported once per test run into a temp file
 * (the same steps as bin/import), so relevance tests search exactly what production serves.
 */
final class RealCatalog
{
    private const ROOT = __DIR__ . '/../..';
    private static ?string $path = null;

    public static function path(): string
    {
        if (self::$path === null) {
            $path = sys_get_temp_dir() . '/htzone-relevance-' . bin2hex(random_bytes(6)) . '.sqlite';
            $pdo = SqliteConnection::readWrite($path);
            (new CatalogImporter(
                $pdo,
                new MigrationRunner($pdo, self::ROOT . '/migrations'),
                new CsvCatalogReader(),
                new EnrichmentReader(),
                PriceGenerator::fromFile(self::ROOT . '/data/price-ranges.json'),
            ))->import(self::ROOT . '/assets/catalog_no_prices.csv', self::ROOT . '/data/enrichment.jsonl');
            register_shutdown_function(static fn (): bool => @unlink($path));
            self::$path = $path;
        }

        return self::$path;
    }
}
