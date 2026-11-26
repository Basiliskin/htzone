<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Infrastructure\Logging;

use Htzone\Infrastructure\Logging\JsonLogger;
use PHPUnit\Framework\TestCase;

final class JsonLoggerTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $this->stream = $stream;
    }

    public function testWritesOneJsonLinePerEventWithTimestampLevelAndMessage(): void
    {
        $logger = new JsonLogger($this->stream, 'info');
        $logger->log('info', 'request', ['q' => "שואב\nאבק", 'n' => 3]);
        $logger->log('error', 'boom');

        $lines = $this->lines();
        self::assertCount(2, $lines);
        $first = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($first);
        self::assertSame(['ts', 'level', 'msg', 'q', 'n'], array_keys($first));
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', self::string($first['ts']));
        self::assertSame("שואב\nאבק", $first['q'], 'a newline in user text stays inside one JSON line');
        self::assertStringContainsString('שואב', $lines[0], 'Hebrew is written readable, not as \\u escapes');
    }

    public function testDropsEventsBelowTheConfiguredLevel(): void
    {
        $logger = new JsonLogger($this->stream, 'warning');
        $logger->log('debug', 'a');
        $logger->log('info', 'b');
        $logger->log('warning', 'c');
        $logger->log('error', 'd');

        self::assertCount(2, $this->lines());
    }

    public function testInvalidUtf8NeverBreaksTheLine(): void
    {
        (new JsonLogger($this->stream, 'info'))->log('info', 'request', ['q' => "\xC3\x28"]);

        self::assertIsArray(json_decode($this->lines()[0], true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        rewind($this->stream);

        return array_values(array_filter(explode("\n", (string) stream_get_contents($this->stream)), static fn (string $l): bool => $l !== ''));
    }

    private static function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
