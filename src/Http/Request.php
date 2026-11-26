<?php

declare(strict_types=1);

namespace Htzone\Http;

/**
 * The parts of an HTTP request this app uses. Built from PHP's globals once, in public/index.php;
 * tests build it directly (D19).
 */
final readonly class Request
{
    /**
     * @param array<array-key, mixed> $query the parsed query string ($_GET)
     */
    public function __construct(
        public string $method,
        public string $path,
        private array $query,
    ) {
    }

    public static function fromGlobals(): self
    {
        return self::fromArrays($_SERVER, $_GET);
    }

    /**
     * @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $get
     */
    public static function fromArrays(array $server, array $get): self
    {
        $method = $server['REQUEST_METHOD'] ?? null;
        $uri = $server['REQUEST_URI'] ?? null;
        $path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : null;

        return new self(
            is_string($method) ? strtoupper($method) : 'GET',
            is_string($path) && $path !== '' ? $path : '/',
            $get,
        );
    }

    /** The parameter as text, or null when it's absent or not a string (`?q[]=x`). */
    public function queryString(string $name): ?string
    {
        $value = $this->query[$name] ?? null;

        return is_string($value) ? $value : null;
    }
}
