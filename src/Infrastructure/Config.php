<?php

declare(strict_types=1);

namespace Htzone\Infrastructure;

/**
 * Runtime configuration read from environment variables (architecture §10).
 * No secrets exist in this app; every value has a safe default.
 */
final readonly class Config
{
    private const APP_ENVS = ['prod', 'dev', 'test'];
    private const LOG_LEVELS = ['debug', 'info', 'warning', 'error'];

    private function __construct(
        public string $appEnv,
        public string $dbPath,
        public string $logLevel,
    ) {
    }

    /**
     * @param array<string, string> $env usually getenv()
     */
    public static function fromEnv(array $env): self
    {
        $appEnv = strtolower(self::read($env, 'APP_ENV', 'prod'));
        $logLevel = strtolower(self::read($env, 'LOG_LEVEL', 'info'));
        $dbPath = self::read($env, 'DB_PATH', '/app/var/catalog.sqlite');

        if (!in_array($appEnv, self::APP_ENVS, true)) {
            throw new \InvalidArgumentException(sprintf('APP_ENV must be one of %s, got "%s"', implode('|', self::APP_ENVS), $appEnv));
        }
        if (!in_array($logLevel, self::LOG_LEVELS, true)) {
            throw new \InvalidArgumentException(sprintf('LOG_LEVEL must be one of %s, got "%s"', implode('|', self::LOG_LEVELS), $logLevel));
        }

        return new self($appEnv, $dbPath, $logLevel);
    }

    public function isProd(): bool
    {
        return $this->appEnv === 'prod';
    }

    /**
     * @param array<string, string> $env
     */
    private static function read(array $env, string $key, string $default): string
    {
        $value = trim($env[$key] ?? '');

        return $value === '' ? $default : $value;
    }
}
