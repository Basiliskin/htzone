<?php

declare(strict_types=1);

namespace Htzone\Tests\Integration\Http;

use Htzone\Http\Controller;
use Htzone\Http\Kernel;
use Htzone\Http\Request;
use Htzone\Http\Response;
use Htzone\Http\Router;
use Htzone\Infrastructure\Config;
use Htzone\Infrastructure\Logging\JsonLogger;
use Htzone\Infrastructure\Persistence\MigrationRunner;
use Htzone\Infrastructure\Persistence\SqliteConnection;
use Htzone\Services;
use Htzone\Tests\Support\RealCatalog;
use Htzone\Tests\Support\TempDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The HTTP contract (architecture §8) through the real composition root and the real catalog.
 * The Kernel is called directly: Request in, Response out, no web server.
 */
final class KernelTest extends TestCase
{
    use TempDirectory;

    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' https://placehold.co data:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    /** @var resource */
    private $log;

    protected function setUp(): void
    {
        $this->log = self::memoryStream();
    }

    public function testSearchReturnsTheContractShape(): void
    {
        $response = $this->get('/api/search', ['q' => 'שואב אבק']);

        self::assertSame(200, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->header('Content-Type'));
        self::assertSame('public, max-age=60', $response->header('Cache-Control'));

        $body = self::json($response);
        self::assertSame(['query', 'total', 'results', 'notice'], array_keys($body));
        self::assertSame('שואב אבק', $body['query']);
        self::assertNull($body['notice']);
        self::assertIsArray($body['results']);
        self::assertSame(count($body['results']), $body['total']);
        self::assertGreaterThan(0, $body['total']);
        self::assertLessThanOrEqual(24, $body['total']);

        $first = $body['results'][0];
        self::assertIsArray($first);
        self::assertSame(['id', 'name', 'brand', 'category', 'price', 'image'], array_keys($first));
        self::assertMatchesRegularExpression('/^P\d{4}$/', self::string($first['id']));
        self::assertIsArray($first['price']);
        self::assertSame(['amount', 'currency', 'formatted'], array_keys($first['price']));
        self::assertIsInt($first['price']['amount']);
        self::assertSame('ILS', $first['price']['currency']);
        self::assertMatchesRegularExpression('/^₪[\d,]+(\.\d\d)?$/u', self::string($first['price']['formatted']));
        self::assertStringStartsWith('https://', self::string($first['image']));
    }

    public function testMissingModelNoticeCarriesTheHebrewMessage(): void
    {
        $body = self::json($this->get('/api/search', ['q' => 'iPhone 16']));

        self::assertSame(['type' => 'missing_model', 'message' => 'לא נמצא דגם מדויק, הנה המוצרים הקרובים'], $body['notice']);
    }

    public function testTypoFallbackNoticeCarriesTheSuggestion(): void
    {
        $body = self::json($this->get('/api/search', ['q' => 'מקרוגל']));

        self::assertIsArray($body['notice']);
        self::assertSame('did_you_mean', $body['notice']['type']);
        $suggestion = self::string($body['notice']['suggestion']);
        self::assertSame("האם התכוונת: {$suggestion}?", $body['notice']['message']);
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('badQueries')]
    public function testBadQueriesAnswer400WithTheErrorShape(array $query, string $code): void
    {
        $response = $this->get('/api/search', $query);

        self::assertSame(400, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        $body = self::json($response);
        self::assertSame(['error'], array_keys($body));
        self::assertIsArray($body['error']);
        self::assertSame(['code', 'message'], array_keys($body['error']));
        self::assertSame($code, $body['error']['code']);
        self::assertNotSame('', $body['error']['message']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badQueries(): iterable
    {
        yield 'no q' => [[], 'query_missing'];
        yield 'q is an array' => [['q' => ['x']], 'query_missing'];
        yield 'empty q' => [['q' => ''], 'query_too_short'];
        yield 'one letter' => [['q' => 'א'], 'query_too_short'];
        yield 'only spaces and RLM' => [['q' => "  \u{200F} "], 'query_too_short'];
    }

    public function testSearchEngineAndSqlSyntaxIsPlainText(): void
    {
        foreach (['" OR 1=1 --', '\'; DROP TABLE products; --', '* ( ) : ^ - NEAR'] as $q) {
            self::assertSame(200, $this->get('/api/search', ['q' => $q])->status, $q);
        }
    }

    public function testScriptInTheQueryNeverAppearsRawInJson(): void
    {
        $response = $this->get('/api/search', ['q' => '<script>alert(1)</script>']);

        self::assertSame(200, $response->status);
        self::assertStringNotContainsString('<', $response->body);
        self::assertStringContainsString('\\u003Cscript\\u003E', $response->body);
        self::assertSame('<script>alert(1)</script>', self::json($response)['query']);
    }

    public function testLongQueryIsCutNotRejected(): void
    {
        $response = $this->get('/api/search', ['q' => str_repeat('מטען ', 40)]);

        self::assertSame(200, $response->status);
        self::assertSame(99, mb_strlen(self::string(self::json($response)['query']))); // cut to 100, then the trailing space trimmed
    }

    public function testUnknownApiPathIs404Json(): void
    {
        $response = $this->get('/api/nope');

        self::assertSame(404, $response->status);
        self::assertSame(['error' => ['code' => 'not_found', 'message' => 'הכתובת לא נמצאה']], self::json($response));
    }

    public function testOtherMethodsAre405WithAllow(): void
    {
        $response = $this->kernel()->handle(new Request('POST', '/api/search', ['q' => 'שואב']));

        self::assertSame(405, $response->status);
        self::assertSame('GET, HEAD', $response->header('Allow'));
        self::assertSame('method_not_allowed', self::errorCode($response));
    }

    public function testHeadIsGetWithoutBody(): void
    {
        $response = $this->kernel()->handle(new Request('HEAD', '/api/search', ['q' => 'שואב']));

        self::assertSame(200, $response->status);
        self::assertSame('', $response->body);
        self::assertSame('application/json; charset=utf-8', $response->header('Content-Type'));
    }

    #[DataProvider('everyKindOfResponse')]
    public function testSecurityHeadersAreOnEveryResponse(string $method, string $path): void
    {
        $response = $this->kernel()->handle(new Request($method, $path, ['q' => 'שואב']));

        foreach (self::SECURITY_HEADERS as $name => $value) {
            self::assertSame($value, $response->header($name), "{$name} on {$method} {$path}");
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function everyKindOfResponse(): iterable
    {
        yield 'search 200' => ['GET', '/api/search'];
        yield 'page' => ['GET', '/'];
        yield 'health' => ['GET', '/health'];
        yield '404' => ['GET', '/api/nope'];
        yield '405' => ['DELETE', '/'];
    }

    public function testHealthReportsTheProductCount(): void
    {
        $response = $this->get('/health');

        self::assertSame(200, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame(['status' => 'ok', 'products' => 1000], self::json($response));
    }

    public function testMissingDatabaseGives503HealthAnd500Search(): void
    {
        $kernel = $this->kernel($this->tempDir() . '/missing.sqlite');

        $health = $kernel->handle(new Request('GET', '/health', []));
        self::assertSame(503, $health->status);
        self::assertSame(['status' => 'unavailable'], self::json($health));

        $search = $kernel->handle(new Request('GET', '/api/search', ['q' => 'שואב']));
        self::assertSame(500, $search->status);
        self::assertSame(['error' => ['code' => 'internal_error', 'message' => 'משהו השתבש. נסו שוב בעוד רגע.']], self::json($search));
        self::assertFileDoesNotExist($this->tempDir() . '/missing.sqlite', 'read-only open must not create the file');
    }

    public function testCorruptDatabaseGives503Health(): void
    {
        $path = $this->tempDir() . '/garbage.sqlite';
        file_put_contents($path, str_repeat('not a database ', 100));

        self::assertSame(503, $this->kernel($path)->handle(new Request('GET', '/health', []))->status);
    }

    public function testEmptyCatalogGives503Health(): void
    {
        $path = $this->tempDir() . '/empty.sqlite';
        (new MigrationRunner(SqliteConnection::readWrite($path), __DIR__ . '/../../../migrations'))->run();

        $response = $this->kernel($path)->handle(new Request('GET', '/health', []));

        self::assertSame(503, $response->status);
        self::assertSame('catalog is empty', $this->logLines()[0]['error']);
    }

    public function testUncaughtErrorHidesDetailsFromTheClientButLogsThem(): void
    {
        $response = $this->kernelWith(new class () implements Controller {
            public function handle(Request $request): Response
            {
                throw new \LogicException('secret detail at /app/src/Foo.php');
            }
        })->handle(new Request('GET', '/boom', []));

        self::assertSame(500, $response->status);
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertSame('internal_error', self::errorCode($response));
        self::assertStringNotContainsString('secret', $response->body);
        self::assertStringNotContainsString('LogicException', $response->body);

        $line = $this->logLines()[0];
        self::assertSame('error', $line['level']);
        self::assertSame(\LogicException::class, $line['exception']);
        self::assertSame('secret detail at /app/src/Foo.php', $line['error']);
    }

    public function testPhpWarningsBecome500(): void
    {
        $response = $this->kernelWith(new class () implements Controller {
            public function handle(Request $request): Response
            {
                trigger_error('a warning', E_USER_WARNING);

                return Response::json(['unreachable' => true]);
            }
        })->handle(new Request('GET', '/warn', []));

        self::assertSame(500, $response->status);
        self::assertSame(\ErrorException::class, $this->logLines()[0]['exception']);
    }

    public function testEachRequestLogsOneJsonLineWithSearchDetails(): void
    {
        $this->get('/api/search', ['q' => 'iPhone 16']);

        $lines = $this->logLines();
        self::assertCount(1, $lines);
        $line = $lines[0];
        self::assertSame(['ts', 'level', 'msg', 'method', 'path', 'status', 'duration_ms', 'q', 'results', 'notice', 'fallback'], array_keys($line));
        self::assertSame('info', $line['level']);
        self::assertSame('request', $line['msg']);
        self::assertSame('GET', $line['method']);
        self::assertSame('/api/search', $line['path']);
        self::assertSame(200, $line['status']);
        self::assertIsNumeric($line['duration_ms']);
        self::assertSame('iPhone 16', $line['q']);
        self::assertIsInt($line['results']);
        self::assertSame('missing_model', $line['notice']);
        self::assertFalse($line['fallback']);
    }

    public function testRejectedQueryIsLoggedToo(): void
    {
        $this->get('/api/search', ['q' => 'א']);
        self::assertSame('א', $this->logLines()[0]['q']);
    }

    public function testLoggedQueryIsCutTo100Characters(): void
    {
        $this->get('/api/search', ['q' => str_repeat('ab', 300)]);
        self::assertSame(100, mb_strlen(self::string($this->logLines()[0]['q'])));
    }

    public function testPageEscapesTheQuery(): void
    {
        $response = $this->get('/', ['q' => '"><script>alert(1)</script>']);

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
        self::assertSame('no-cache', $response->header('Cache-Control'));
        self::assertStringContainsString('<html lang="he" dir="rtl">', $response->body);
        self::assertStringNotContainsString('<script>alert', $response->body);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', $response->body);
    }

    public function testPageHasNoInlineScriptOrStyleSoTheCspHolds(): void
    {
        $body = $this->get('/')->body;

        self::assertSame(1, preg_match_all('/<script\b/i', $body), 'exactly one script tag');
        self::assertStringContainsString('<script type="module" src="/assets/app.js"></script>', $body);
        self::assertStringNotContainsString('<style', $body);
        self::assertDoesNotMatchRegularExpression('/\sstyle=|\son[a-z]+=/i', $body, 'no style="" or onclick="" attributes');
    }

    public function testPageWorksWithoutJavaScript(): void
    {
        $body = $this->get('/', ['q' => 'שואב אבק'])->body;

        // the form round-trips the query (D35) …
        self::assertStringContainsString('action="/" method="get"', $body);
        self::assertStringContainsString('name="q"', $body);
        self::assertStringContainsString('value="שואב אבק"', $body);
        self::assertStringContainsString('<noscript>', $body);
        // … and the suggestion chips are real links (landing + no-results panel)
        self::assertSame(2, substr_count($body, 'href="/?q=%D7%A9%D7%95%D7%90%D7%91+%D7%90%D7%91%D7%A7"'));
    }

    public function testPageHasEverySectionTheScriptNeeds(): void
    {
        $body = $this->get('/')->body;

        // ids looked up by public/assets/app.js through byId() — a rename must break a test, not the page
        foreach (['search-form', 'q', 'clear', 'live', 'grid', 'count', 'notice', 'empty-q', 'retry', 'results-title', 'empty-title', 'error-title', 'short-title'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $body, "#$id");
        }
        self::assertStringContainsString('<body data-state="idle">', $body);
        self::assertStringContainsString('aria-live="polite"', $body);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function get(string $path, array $query = []): Response
    {
        return $this->kernel()->handle(new Request('GET', $path, $query));
    }

    private function kernel(?string $dbPath = null): Kernel
    {
        $config = Config::fromEnv(['APP_ENV' => 'test', 'DB_PATH' => $dbPath ?? RealCatalog::path(), 'LOG_LEVEL' => 'info']);

        return (new Services($config))->kernel($this->log);
    }

    private function kernelWith(Controller $page): Kernel
    {
        return new Kernel(new Router([], $page), new JsonLogger($this->log, 'info'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function logLines(): array
    {
        rewind($this->log);
        $lines = [];
        foreach (explode("\n", trim((string) stream_get_contents($this->log))) as $raw) {
            $line = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($line);
            /** @var array<string, mixed> $line */
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(Response $response): array
    {
        $body = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        /** @var array<string, mixed> $body */
        return $body;
    }

    private static function errorCode(Response $response): mixed
    {
        $error = self::json($response)['error'];
        self::assertIsArray($error);

        return $error['code'];
    }

    private static function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    /**
     * @return resource
     */
    private static function memoryStream()
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }
}
