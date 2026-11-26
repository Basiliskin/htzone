<?php

declare(strict_types=1);

namespace Htzone\Http;

/**
 * Exact-path routing (architecture §8): known paths → their controller, any other /api/* → none (404),
 * every other path → the page, so the front end owns those URLs.
 */
final readonly class Router
{
    /**
     * @param array<string, Controller> $routes path → controller, e.g. '/health'
     */
    public function __construct(private array $routes, private Controller $page)
    {
    }

    public function match(string $path): ?Controller
    {
        if (isset($this->routes[$path])) {
            return $this->routes[$path];
        }

        return $path === '/api' || str_starts_with($path, '/api/') ? null : $this->page;
    }
}
