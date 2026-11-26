<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Persistence;

/**
 * PDO factory for the catalog database.
 * Runtime opens it read-only (AC-6.4); only migrations and the importer open it read-write.
 */
final class SqliteConnection
{
    private const OPTIONS = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /** Opens an existing database file; refuses to create one and refuses every write. */
    public static function readOnly(string $path): \PDO
    {
        return new \PDO('sqlite:' . $path, options: self::OPTIONS + [
            \PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY,
        ]);
    }

    /** Opens (and creates, with its directory, if missing) a database for migrations and import. */
    public static function readWrite(string $path): \PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }
        $pdo = new \PDO('sqlite:' . $path, options: self::OPTIONS);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
