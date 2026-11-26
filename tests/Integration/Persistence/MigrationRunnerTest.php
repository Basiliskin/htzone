<?php

declare(strict_types=1);

namespace Htzone\Tests\Integration\Persistence;

use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Tests\Support\TempDirectory;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    use TempDirectory;

    private const MIGRATIONS = __DIR__ . '/../../../migrations';

    public function testAppliesAllMigrationsInOrderAndRecordsThem(): void
    {
        $pdo = SqliteConnection::readWrite($this->tempDir() . '/db.sqlite');

        $applied = (new MigrationRunner($pdo, self::MIGRATIONS))->run();

        self::assertSame(['0001_create_products', '0002_create_fts', '0003_create_trigram'], $applied);
        self::assertSame($applied, $this->column($pdo, 'SELECT version FROM schema_migrations ORDER BY version'));
        self::assertSame(
            ['products', 'products_fts', 'products_trigram', 'schema_migrations'],
            $this->column($pdo, "SELECT name FROM sqlite_master WHERE name IN ('products', 'products_fts', 'products_trigram', 'schema_migrations') ORDER BY name"),
        );
    }

    public function testSecondRunChangesNothing(): void
    {
        $pdo = SqliteConnection::readWrite($this->tempDir() . '/db.sqlite');
        $runner = new MigrationRunner($pdo, self::MIGRATIONS);
        $runner->run();
        $before = $this->column($pdo, 'SELECT version || applied_at FROM schema_migrations ORDER BY version');

        self::assertSame([], $runner->run());
        self::assertSame($before, $this->column($pdo, 'SELECT version || applied_at FROM schema_migrations ORDER BY version'));
    }

    public function testFailedMigrationIsRolledBackAndNotRecorded(): void
    {
        $dir = $this->tempDir() . '/migrations';
        mkdir($dir);
        file_put_contents($dir . '/0001_ok.sql', 'CREATE TABLE a (x INTEGER) STRICT;');
        file_put_contents($dir . '/0002_broken.sql', 'CREATE TABLE b (x INTEGER); THIS IS NOT SQL;');
        $pdo = SqliteConnection::readWrite($this->tempDir() . '/db.sqlite');

        try {
            (new MigrationRunner($pdo, $dir))->run();
            self::fail('Expected the broken migration to throw');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('0002_broken', $e->getMessage());
        }

        self::assertSame(['0001_ok'], $this->column($pdo, 'SELECT version FROM schema_migrations'));
        self::assertSame([], $this->column($pdo, "SELECT name FROM sqlite_master WHERE name = 'b'"), 'half-applied table must be rolled back');
    }

    public function testReadOnlyConnectionRefusesWrites(): void
    {
        $path = $this->tempDir() . '/db.sqlite';
        (new MigrationRunner(SqliteConnection::readWrite($path), self::MIGRATIONS))->run();

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('readonly');

        SqliteConnection::readOnly($path)->exec("INSERT INTO schema_migrations VALUES ('x', 'y')");
    }

    public function testReadOnlyConnectionNeedsAnExistingFile(): void
    {
        $this->expectException(\PDOException::class);

        SqliteConnection::readOnly($this->tempDir() . '/missing.sqlite');
    }

    /**
     * @return list<string>
     */
    private function column(\PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        self::assertNotFalse($statement);

        return array_values(array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            $statement->fetchAll(\PDO::FETCH_COLUMN),
        ));
    }
}
