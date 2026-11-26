<?php

declare(strict_types=1);

namespace Htzone;

use Htzone\Application\SearchProducts;
use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\Ranker;
use Htzone\Http\Controller\HealthController;
use Htzone\Http\Controller\PageController;
use Htzone\Http\Controller\SearchController;
use Htzone\Http\Kernel;
use Htzone\Http\Router;
use Htzone\Infrastructure\Config;
use Htzone\Infrastructure\Import\CatalogImporter;
use Htzone\Infrastructure\Import\CsvCatalogReader;
use Htzone\Infrastructure\Import\EnrichmentReader;
use Htzone\Infrastructure\Import\PriceGenerator;
use Htzone\Infrastructure\Logging\JsonLogger;
use Htzone\Infrastructure\Persistence\FtsQueryBuilder;
use Htzone\Infrastructure\Persistence\LazyProductRepository;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Infrastructure\Persistence\SqliteProductRepository;

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
    private const PAGE_TEMPLATE = self::ROOT . '/templates/page.php';

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

    /**
     * The web app (architecture §10). The DB is opened read-only and only when a request first needs it (D33).
     *
     * @param resource|null $logStream defaults to stdout; tests pass php://memory
     */
    public function kernel(mixed $logStream = null): Kernel
    {
        $products = new LazyProductRepository(
            fn (): SqliteProductRepository => new SqliteProductRepository(SqliteConnection::readOnly($this->config->dbPath), new FtsQueryBuilder()),
        );
        $search = new SearchProducts($products, new QueryNormalizer(), new Ranker());

        return new Kernel(
            new Router(
                ['/api/search' => new SearchController($search), '/health' => new HealthController($products)],
                new PageController(self::PAGE_TEMPLATE),
            ),
            new JsonLogger($logStream ?? self::stdout(), $this->config->logLevel),
        );
    }

    /**
     * @return resource
     */
    private static function stdout(): mixed
    {
        $stream = fopen('php://stdout', 'w');

        return $stream !== false ? $stream : throw new \RuntimeException('Cannot open php://stdout');
    }
}
