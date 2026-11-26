<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

use Htzone\Domain\Catalog\Product;
use Htzone\Infrastructure\Persistence\MigrationRunner;

/**
 * CSV + enrichment + generated prices → SQLite (architecture §7).
 * Every input is validated before the DB is touched; the write is one transaction,
 * so a failed import never leaves a half-filled catalog. Re-running gives identical contents.
 */
final readonly class CatalogImporter
{
    private const TAG_SEPARATOR = ' | ';

    public function __construct(
        private \PDO $pdo,
        private MigrationRunner $migrations,
        private CsvCatalogReader $csvReader,
        private EnrichmentReader $enrichmentReader,
        private PriceGenerator $prices,
    ) {
    }

    /**
     * @throws ImportFailed when any input is invalid (nothing is written then)
     */
    public function import(string $csvPath, ?string $enrichmentPath): ImportReport
    {
        $started = hrtime(true);
        $warnings = [];

        $rows = $this->csvReader->read($csvPath);
        if ($enrichmentPath !== null && is_file($enrichmentPath)) {
            $tags = $this->enrichmentReader->read($enrichmentPath);
            $this->assertSameIds($rows, $tags);
        } else {
            $tags = [];
            $warnings[] = 'enrichment file not found (' . ($enrichmentPath ?? 'none given') . '): importing without tags, search quality will be poor';
        }

        $products = $this->toProducts($rows, $tags);
        foreach ($rows as $row) {
            array_push($warnings, ...self::foreignLetters($row));
        }

        $this->migrations->run();
        $this->write($products);

        return new ImportReport(
            products: count($products),
            tagged: count(array_filter($products, static fn (Product $p): bool => $p->tags !== [])),
            warnings: $warnings,
            durationMs: intdiv(hrtime(true) - $started, 1_000_000),
        );
    }

    /**
     * AC-6.3: enrichment must cover exactly the catalog ids.
     *
     * @param list<CatalogRow>            $rows
     * @param array<string, list<string>> $tags
     */
    private function assertSameIds(array $rows, array $tags): void
    {
        $catalogIds = array_map(static fn (CatalogRow $row): string => $row->id, $rows);
        $enrichmentIds = array_map(strval(...), array_keys($tags));

        $problems = [
            ...array_map(static fn (string $id): string => "enrichment has unknown id {$id}", array_values(array_diff($enrichmentIds, $catalogIds))),
            ...array_map(static fn (string $id): string => "enrichment is missing catalog id {$id}", array_values(array_diff($catalogIds, $enrichmentIds))),
        ];
        if ($problems !== []) {
            throw new ImportFailed($problems);
        }
    }

    /**
     * @param list<CatalogRow>            $rows
     * @param array<string, list<string>> $tags
     * @return list<Product>
     */
    private function toProducts(array $rows, array $tags): array
    {
        $products = [];
        $problems = [];
        foreach ($rows as $row) {
            try {
                $products[] = new Product(
                    id: $row->id,
                    name: $row->name,
                    brand: $row->brand,
                    category: $row->category,
                    subcategory: $row->subcategory,
                    description: $row->description,
                    attributes: $row->attributes,
                    imageUrl: $row->imageUrl,
                    price: $this->prices->priceFor($row->id, $row->categoryKey()),
                    tags: $tags[$row->id] ?? [],
                );
            } catch (\InvalidArgumentException $e) {
                $problems[] = $e->getMessage();
            }
        }
        if ($problems !== []) {
            throw new ImportFailed($problems);
        }

        return $products;
    }

    /**
     * D30: letters that aren't Hebrew or Latin (e.g. Persian ی that looks like Hebrew י) can never match a query.
     * The client's text is kept as-is; we only warn.
     *
     * @return list<string>
     */
    private static function foreignLetters(CatalogRow $row): array
    {
        $warnings = [];
        $fields = ['name' => $row->name, 'brand' => $row->brand, 'description' => $row->description, 'attributes' => $row->attributes];
        foreach ($fields as $field => $value) {
            if (preg_match_all('/[^\P{L}\p{Hebrew}\p{Latin}]/u', $value, $matches) > 0) {
                $letters = implode(' ', array_values(array_unique($matches[0])));
                $warnings[] = "{$row->id}: {$field} contains letters outside Hebrew/Latin: {$letters}";
            }
        }

        return $warnings;
    }

    /**
     * @param list<Product> $products
     */
    private function write(array $products): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM products_fts; DELETE FROM products_trigram; DELETE FROM products;');
            $insert = $this->pdo->prepare(
                'INSERT INTO products (id, name, brand, category, subcategory, description, attributes, image_url, price_minor, currency, tags)
                 VALUES (:id, :name, :brand, :category, :subcategory, :description, :attributes, :image_url, :price_minor, :currency, :tags)',
            );
            foreach ($products as $product) {
                $insert->execute([
                    'id' => $product->id,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'category' => $product->category,
                    'subcategory' => $product->subcategory,
                    'description' => $product->description,
                    'attributes' => $product->attributes,
                    'image_url' => $product->imageUrl,
                    'price_minor' => $product->price->minor,
                    'currency' => $product->price->currency,
                    'tags' => implode(self::TAG_SEPARATOR, $product->tags),
                ]);
            }
            // Search indexes share the products rowid, so a match joins straight back to its product.
            $this->pdo->exec(
                "INSERT INTO products_fts (rowid, name, brand, tags, category, description)
                 SELECT rowid, name, coalesce(brand, ''), tags, category || ' ' || subcategory, description FROM products ORDER BY rowid;
                 INSERT INTO products_trigram (rowid, name, brand, tags)
                 SELECT rowid, name, coalesce(brand, ''), tags FROM products ORDER BY rowid;",
            );
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->pdo->exec('PRAGMA optimize');
    }
}
