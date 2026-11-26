<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Persistence;

use Htzone\Domain\Catalog\ProductRepository;
use Htzone\Domain\Search\Candidate;
use Htzone\Domain\Search\RankingWeights;

/**
 * ProductRepository over the SQLite FTS5 indexes (architecture §5). Each index shares the products rowid,
 * so a match joins straight back to its row. bm25 is negative: lower = better, so ORDER BY ascending.
 */
final readonly class SqliteProductRepository implements ProductRepository
{
    public function __construct(
        private \PDO $pdo,
        private FtsQueryBuilder $queries,
        private RankingWeights $weights = new RankingWeights(),
    ) {
    }

    public function findCandidates(array $terms, int $limit): array
    {
        $weights = implode(', ', array_map(static fn (float $w): string => sprintf('%.1F', $w), $this->weights->fieldWeights));

        return $this->find('products_fts', "bm25(products_fts, {$weights})", $this->queries->forTerms($terms), $limit);
    }

    public function findByTrigrams(array $words, int $limit): array
    {
        return $this->find('products_trigram', 'bm25(products_trigram)', $this->queries->forTrigrams($words), $limit);
    }

    public function count(): int
    {
        $statement = $this->pdo->query('SELECT count(*) FROM products');
        $count = $statement === false ? null : $statement->fetchColumn();

        return is_int($count) ? $count : throw new \UnexpectedValueException('count(*) is not an integer');
    }

    /**
     * @param 'products_fts'|'products_trigram' $index
     * @return list<Candidate>
     */
    private function find(string $index, string $score, string $match, int $limit): array
    {
        if ($match === '') {
            return [];
        }
        // Only fixed identifiers are interpolated; the user's text travels as the :match parameter.
        $statement = $this->pdo->prepare(
            "SELECT p.*, {$score} AS score
               FROM {$index} JOIN products p ON p.rowid = {$index}.rowid
              WHERE {$index} MATCH :match
              ORDER BY score, p.id
              LIMIT :limit",
        );
        $statement->bindValue('match', $match);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        $candidates = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row) || !is_float($row['score'] ?? null)) {
                throw new \UnexpectedValueException("{$index}: unexpected row");
            }
            $candidates[] = new Candidate(ProductRowMapper::fromRow($row), $row['score']);
        }

        return $candidates;
    }
}
