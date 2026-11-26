<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Persistence;

/**
 * Applies migrations/NNNN_name.sql files that aren't recorded in schema_migrations yet,
 * in filename order, each in its own transaction. A second run does nothing (AC-6.1).
 */
final readonly class MigrationRunner
{
    public function __construct(
        private \PDO $pdo,
        private string $directory,
    ) {
    }

    /**
     * @return list<string> versions applied by this run
     */
    public function run(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL) STRICT');
        $done = $this->appliedVersions();
        $record = $this->pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)');

        $applied = [];
        foreach ($this->files() as $version => $file) {
            if (isset($done[$version])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration {$file}");
            }
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $record->execute(['version' => $version, 'applied_at' => gmdate('Y-m-d\TH:i:s\Z')]);
                $this->pdo->commit();
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                throw new \RuntimeException("Migration {$version} failed: {$e->getMessage()}", previous: $e);
            }
            $applied[] = $version;
        }

        return $applied;
    }

    /**
     * @return array<string, string> version → path, sorted by version
     */
    private function files(): array
    {
        $paths = glob($this->directory . '/*.sql');
        if ($paths === false || $paths === []) {
            throw new \RuntimeException("No migrations found in {$this->directory}");
        }
        $files = [];
        foreach ($paths as $path) {
            $files[basename($path, '.sql')] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array<string, true>
     */
    private function appliedVersions(): array
    {
        $statement = $this->pdo->query('SELECT version FROM schema_migrations');
        $versions = [];
        foreach ($statement === false ? [] : $statement->fetchAll(\PDO::FETCH_COLUMN) as $version) {
            if (is_string($version)) {
                $versions[$version] = true;
            }
        }

        return $versions;
    }
}
