<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Http;

use Htzone\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testReadsMethodPathAndQueryFromServerArrays(): void
    {
        $request = Request::fromArrays(['REQUEST_METHOD' => 'get', 'REQUEST_URI' => '/api/search?q=%D7%A9'], ['q' => 'ש']);

        self::assertSame('GET', $request->method);
        self::assertSame('/api/search', $request->path);
        self::assertSame('ש', $request->queryString('q'));
    }

    public function testDefaultsWhenServerValuesAreMissingOrBroken(): void
    {
        $request = Request::fromArrays([], []);
        self::assertSame('GET', $request->method);
        self::assertSame('/', $request->path);

        self::assertSame('/', Request::fromArrays(['REQUEST_URI' => '//'], [])->path);
        self::assertSame('/', Request::fromArrays(['REQUEST_URI' => ['x']], [])->path);
    }

    public function testNonStringParameterCountsAsMissing(): void
    {
        $request = new Request('GET', '/', ['q' => ['a', 'b'], 'n' => 5]);

        self::assertNull($request->queryString('q'));
        self::assertNull($request->queryString('n'));
        self::assertNull($request->queryString('absent'));
    }
}
