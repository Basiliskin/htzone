<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Logging;

/**
 * One JSON object per line (architecture §8, AC-7.4), written to a stream: php://stdout in production,
 * so `docker logs` and Render show it; php://memory in tests. Events below the configured level are dropped.
 */
final readonly class JsonLogger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param resource $stream
     */
    public function __construct(private mixed $stream, private string $minLevel)
    {
    }

    /**
     * @param 'debug'|'info'|'warning'|'error' $level
     * @param array<string, scalar|null>       $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (self::LEVELS[$level] < (self::LEVELS[$this->minLevel] ?? self::LEVELS['info'])) {
            return;
        }
        $line = ['ts' => gmdate('Y-m-d\TH:i:s') . sprintf('.%03dZ', (int) (microtime(true) * 1000) % 1000), 'level' => $level, 'msg' => $message] + $context;

        fwrite($this->stream, json_encode($line, self::FLAGS | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
    }
}
