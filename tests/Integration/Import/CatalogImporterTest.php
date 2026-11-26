<?php

declare(strict_types=1);

namespace Htzone\Tests\Integration\Import;

use Htzone\Infrastructure\Import\CatalogImporter;
use Htzone\Infrastructure\Import\CsvCatalogReader;
use Htzone\Infrastructure\Import\EnrichmentReader;
use Htzone\Infrastructure\Import\ImportFailed;
use Htzone\Infrastructure\Import\PriceGenerator;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Tests\Support\TempDirectory;
use PHPUnit\Framework\TestCase;

final class CatalogImporterTest extends TestCase
{
    use TempDirectory;

    private const ROOT = __DIR__ . '/../../..';
    private const CSV = self::ROOT . '/tests/Fixtures/catalog-small.csv';
    private const ENRICHMENT = self::ROOT . '/tests/Fixtures/enrichment-small.jsonl';
    private const HEADER = "id,name,brand,category,subcategory,description,attributes,image_url\n";
    private const KITCHEN = 'מטבח ובישול,מכשירי חשמל קטנים למטבח';

    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = SqliteConnection::readWrite($this->tempDir() . '/catalog.sqlite');
    }

    public function testImportsRowsWithPricesTagsAndSearchIndexes(): void
    {
        $report = $this->importer()->import(self::CSV, self::ENRICHMENT);

        self::assertSame(3, $report->products);
        self::assertSame(3, $report->tagged);
        self::assertSame([], $report->warnings);

        $p0001 = $this->row("SELECT * FROM products WHERE id = 'P0001'");
        self::assertSame('Philips', $p0001['brand']);
        self::assertSame(189900, $p0001['price_minor'], 'same golden price as PriceGeneratorTest');
        self::assertSame('ILS', $p0001['currency']);
        self::assertIsString($p0001['tags']);
        self::assertStringContainsString('מטגן אוויר | ', $p0001['tags']);

        self::assertNull($this->row("SELECT brand FROM products WHERE id = 'P0070'")['brand'], 'empty CSV brand is stored as NULL');

        // Both indexes are filled and searchable, and the FTS category column holds category + subcategory.
        self::assertSame(['P0201'], $this->ids("SELECT p.id FROM products_fts f JOIN products p ON p.rowid = f.rowid WHERE products_fts MATCH 'brand:dyson'"));
        self::assertSame(['P0001'], $this->ids("SELECT p.id FROM products_fts f JOIN products p ON p.rowid = f.rowid WHERE products_fts MATCH 'category:\"קטנים\"'"));
        self::assertSame(['P0201'], $this->ids("SELECT p.id FROM products_trigram t JOIN products p ON p.rowid = t.rowid WHERE products_trigram MATCH 'דייס'"));
    }

    public function testReimportGivesIdenticalContents(): void
    {
        $importer = $this->importer();
        $importer->import(self::CSV, self::ENRICHMENT);
        $first = $this->dump();

        $importer->import(self::CSV, self::ENRICHMENT);

        self::assertSame($first, $this->dump());
        self::assertSame(3, $this->row('SELECT count(*) AS n FROM products_fts')['n'], 'index is rebuilt, not appended');
    }

    public function testMissingEnrichmentImportsWithEmptyTagsAndAWarning(): void
    {
        $report = $this->importer()->import(self::CSV, $this->tempDir() . '/nope.jsonl');

        self::assertSame(3, $report->products);
        self::assertSame(0, $report->tagged);
        self::assertCount(1, $report->warnings);
        self::assertStringContainsString('enrichment file not found', $report->warnings[0]);
    }

    public function testInvalidRowsAreAllReportedAndNothingIsWritten(): void
    {
        $csv = $this->csv(
            'P0001,מטגן,Philips,' . self::KITCHEN . ',תיאור,,https://placehold.co/a.png',
            'P0002,,Philips,' . self::KITCHEN . ',תיאור,,https://placehold.co/a.png',
            'P0003,קומקום,,' . self::KITCHEN . ',תיאור,,http://placehold.co/a.png',
            'X4,קומקום,,' . self::KITCHEN . ',תיאור,,https://placehold.co/a.png',
            'P0001,כפול,,' . self::KITCHEN . ',תיאור,,https://placehold.co/a.png',
            'P0006,קצר',
        );

        $problems = $this->expectFailure(fn () => $this->importer()->import($csv, null));

        self::assertSame([
            'row 2 (P0002): name is empty',
            'row 3 (P0003): image_url must be an https URL',
            'row 4 (X4): id must look like P0001',
            'row 5 (P0001): duplicate id',
            'row 6 (P0006): expected 8 columns, got 2',
        ], $problems);
        self::assertSame(0, $this->row("SELECT count(*) AS n FROM sqlite_master WHERE name = 'products'")['n'], 'inputs are validated before the DB is touched');
    }

    public function testWrongHeaderIsRejected(): void
    {
        $path = $this->tempDir() . '/bad-header.csv';
        file_put_contents($path, "id,title,brand\nP0001,x,y\n");

        $problems = $this->expectFailure(fn () => $this->importer()->import($path, null));

        self::assertStringContainsString('header must be', $problems[0]);
    }

    public function testEnrichmentMustMatchTheCatalogInBothDirections(): void
    {
        $lines = file(self::ENRICHMENT, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $path = $this->tempDir() . '/enrichment.jsonl';
        // drop P0070, add an unknown id
        file_put_contents($path, $lines[0] . "\n" . $lines[2] . "\n" . '{"id":"P0999","tags_he":["א"],"tags_en":["a"],"issues":[]}' . "\n");

        $problems = $this->expectFailure(fn () => $this->importer()->import(self::CSV, $path));

        self::assertSame([
            'enrichment has unknown id P0999',
            'enrichment is missing catalog id P0070',
        ], $problems);
    }

    public function testMalformedEnrichmentLinesAreReported(): void
    {
        $path = $this->tempDir() . '/enrichment.jsonl';
        file_put_contents($path, "{not json}\n{\"id\":\"P0001\",\"tags_he\":\"oops\",\"tags_en\":[]}\n");

        $problems = $this->expectFailure(fn () => $this->importer()->import(self::CSV, $path));

        self::assertStringStartsWith('enrichment line 1: invalid JSON', $problems[0]);
        self::assertSame('enrichment line 2 (P0001): tags_he and tags_en must be lists of strings', $problems[1]);
    }

    public function testUnknownSubcategoryFailsWithTheProductId(): void
    {
        $csv = $this->csv('P0001,מטגן,Philips,קטגוריה,לא קיימת,תיאור,,https://placehold.co/a.png');

        $problems = $this->expectFailure(fn () => $this->importer()->import($csv, null));

        self::assertSame(['P0001: no price range for "קטגוריה > לא קיימת"'], $problems);
    }

    public function testWarnsAboutLettersFromOtherScriptsButKeepsTheText(): void
    {
        // D30: the real P0014 case, Hebrew "סו-וו" followed by Persian yeh (U+06CC) and dal (U+062F).
        $word = "סו-וו\u{06CC}\u{062F}";
        $csv = $this->csv('P0014,מכשיר סו ויד,Anova,' . self::KITCHEN . ",תיאור {$word},,https://placehold.co/a.png");

        $report = $this->importer()->import($csv, null);

        self::assertSame("תיאור {$word}", $this->row("SELECT description FROM products WHERE id = 'P0014'")['description']);
        self::assertContains("P0014: description contains letters outside Hebrew/Latin: \u{06CC} \u{062F}", $report->warnings);
    }

    private function importer(): CatalogImporter
    {
        return new CatalogImporter(
            $this->pdo,
            new MigrationRunner($this->pdo, self::ROOT . '/migrations'),
            new CsvCatalogReader(),
            new EnrichmentReader(),
            PriceGenerator::fromFile(self::ROOT . '/data/price-ranges.json'),
        );
    }

    private function csv(string ...$rows): string
    {
        $path = $this->tempDir() . '/catalog-' . bin2hex(random_bytes(3)) . '.csv';
        file_put_contents($path, "\u{FEFF}" . self::HEADER . implode("\n", $rows) . "\n");

        return $path;
    }

    /**
     * @param callable(): mixed $import
     * @return list<string>
     */
    private function expectFailure(callable $import): array
    {
        try {
            $import();
        } catch (ImportFailed $e) {
            return $e->problems;
        }
        self::fail('Expected ImportFailed');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);
        $row = $statement->fetch();
        self::assertIsArray($row);

        /** @var array<string, mixed> $row */
        return $row;
    }

    /**
     * @return list<string>
     */
    private function ids(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return array_values(array_filter($statement->fetchAll(\PDO::FETCH_COLUMN), is_string(...)));
    }

    private function dump(): string
    {
        $statement = $this->pdo->query('SELECT * FROM products ORDER BY id');
        self::assertNotFalse($statement);

        return hash('sha256', serialize($statement->fetchAll()));
    }
}
