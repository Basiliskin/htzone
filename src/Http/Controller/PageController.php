<?php

declare(strict_types=1);

namespace Htzone\Http\Controller;

use Htzone\Http\Controller;
use Htzone\Http\Request;
use Htzone\Http\Response;

/**
 * GET / (and any non-API path) — the HTML shell from templates/page.php. The template gets the raw
 * query as `$q` and must escape it (AC-4.4); the page then searches through the API.
 */
final readonly class PageController implements Controller
{
    public function __construct(private string $template)
    {
    }

    public function handle(Request $request): Response
    {
        return Response::html(self::render($this->template, $request->queryString('q') ?? ''));
    }

    private static function render(string $template, string $q): string
    {
        ob_start();
        try {
            require $template;

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
