<?php

declare(strict_types=1);

namespace Htzone\Http;

/**
 * An immutable HTTP response. Nothing is sent until send(), so the Kernel and tests can inspect it.
 * `log` holds extra fields for the request log line (D34); it is never sent to the client.
 */
final readonly class Response
{
    /** JSON_HEX_TAG / _AMP turn < > & into < … so user text can never form HTML (AC-4.4). */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR;

    /**
     * @param array<string, string>      $headers
     * @param array<string, scalar|null> $log
     */
    private function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public array $log = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200, string $cacheControl = 'no-store'): self
    {
        return new self($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => $cacheControl,
        ], json_encode($data, self::JSON_FLAGS));
    }

    public static function error(ApiError $error): self
    {
        return self::json(['error' => ['code' => $error->value, 'message' => $error->message()]], $error->status());
    }

    public static function html(string $body): self
    {
        return new self(200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache'], $body);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [...$this->headers, $name => $value], $this->body, $this->log);
    }

    /**
     * @param array<string, scalar|null> $fields
     */
    public function withLog(array $fields): self
    {
        return new self($this->status, $this->headers, $this->body, [...$this->log, ...$fields]);
    }

    public function withoutBody(): self
    {
        return new self($this->status, $this->headers, '', $this->log);
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
