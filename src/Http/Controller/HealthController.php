<?php

declare(strict_types=1);

namespace Htzone\Http\Controller;

use Htzone\Domain\Catalog\ProductRepository;
use Htzone\Http\Controller;
use Htzone\Http\Request;
use Htzone\Http\Response;

/**
 * GET /health — 200 with the product count when the catalog can be read and isn't empty, otherwise 503
 * (AC-7.1). Render's health check and the keep-awake ping call it.
 */
final readonly class HealthController implements Controller
{
    public function __construct(private ProductRepository $products)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            $count = $this->products->count();
        } catch (\Throwable $e) {
            return Response::json(['status' => 'unavailable'], 503)->withLog(['exception' => $e::class, 'error' => $e->getMessage()]);
        }

        return $count > 0
            ? Response::json(['status' => 'ok', 'products' => $count])
            : Response::json(['status' => 'unavailable'], 503)->withLog(['error' => 'catalog is empty']);
    }
}
