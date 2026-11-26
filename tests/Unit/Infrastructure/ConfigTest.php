<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Infrastructure;

use Htzone\Infrastructure\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultsAreSafeForProduction(): void
    {
        $config = Config::fromEnv([]);

        self::assertSame('prod', $config->appEnv);
        self::assertSame('/app/var/catalog.sqlite', $config->dbPath);
        self::assertSame('info', $config->logLevel);
        self::assertTrue($config->isProd());
    }

    public function testReadsValuesAndNormalizesCase(): void
    {
        $config = Config::fromEnv(['APP_ENV' => ' Dev ', 'LOG_LEVEL' => 'DEBUG', 'DB_PATH' => '/tmp/Test.sqlite']);

        self::assertSame('dev', $config->appEnv);
        self::assertSame('debug', $config->logLevel);
        self::assertSame('/tmp/Test.sqlite', $config->dbPath, 'paths keep their case');
        self::assertFalse($config->isProd());
    }

    public function testBlankValuesFallBackToDefaults(): void
    {
        $config = Config::fromEnv(['APP_ENV' => '', 'DB_PATH' => '  ']);

        self::assertSame('prod', $config->appEnv);
        self::assertSame('/app/var/catalog.sqlite', $config->dbPath);
    }

    /**
     * @param array<string, string> $env
     */
    #[DataProvider('invalidEnvironments')]
    public function testRejectsUnknownValues(array $env, string $expectedKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedKey);

        Config::fromEnv($env);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidEnvironments(): iterable
    {
        yield 'unknown APP_ENV' => [['APP_ENV' => 'staging'], 'APP_ENV'];
        yield 'unknown LOG_LEVEL' => [['LOG_LEVEL' => 'verbose'], 'LOG_LEVEL'];
    }
}
