<?php

declare(strict_types=1);

namespace Htzone\Http;

use Htzone\Infrastructure\Logging\JsonLogger;

/**
 * Turns every Request into a Response (architecture §8): method check, routing, the catch-all that
 * hides errors behind `internal_error`, security headers and one request log line.
 */
final readonly class Kernel
{
    /** The same values docker/apache.conf sets on static files. */
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' https://placehold.co data:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];
    private const ALLOWED_METHODS = ['GET', 'HEAD'];
    private const MAX_LOGGED_PATH = 200;
    /** PHP 8's `@` lowers error_reporting to these fatal levels, so "nothing else left" means silenced. */
    private const FATAL_LEVELS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    public function __construct(private Router $router, private JsonLogger $logger)
    {
    }

    public function handle(Request $request): Response
    {
        $started = hrtime(true);
        // A PHP warning or notice is a bug: turn it into an exception so it ends as a clean 500, not stray output.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & ~self::FATAL_LEVELS) === 0) {
                return false; // silenced with @: let PHP's default handling apply
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $response = $this->dispatch($request);
        } catch (\Throwable $e) {
            // Details go to the log only; the client gets the generic error (no stack trace, AC-7.2).
            $response = Response::error(ApiError::Internal)->withLog(['exception' => $e::class, 'error' => $e->getMessage()]);
        } finally {
            restore_error_handler();
        }

        foreach (self::SECURITY_HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        if ($request->method === 'HEAD') {
            $response = $response->withoutBody();
        }

        $this->logger->log($response->status >= 500 ? 'error' : 'info', 'request', [
            'method' => $request->method,
            'path' => mb_substr(mb_scrub($request->path, 'UTF-8'), 0, self::MAX_LOGGED_PATH),
            'status' => $response->status,
            'duration_ms' => round((hrtime(true) - $started) / 1e6, 2),
            ...$response->log,
        ]);

        return $response;
    }

    private function dispatch(Request $request): Response
    {
        if (!in_array($request->method, self::ALLOWED_METHODS, true)) {
            return Response::error(ApiError::MethodNotAllowed)->withHeader('Allow', implode(', ', self::ALLOWED_METHODS));
        }
        $controller = $this->router->match($request->path);

        return $controller === null ? Response::error(ApiError::NotFound) : $controller->handle($request);
    }
}
