<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Search;

use Htzone\Domain\Search\InvalidQuery;
use Htzone\Domain\Search\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cleaned(): iterable
    {
        yield 'trims and collapses whitespace' => ["  שואב \t\n  אבק  ", 'שואב אבק'];
        yield 'removes bidi marks and zero-width characters (AC-4.7)' => ["\u{200F}שואב\u{200E} \u{202B}Dyson\u{202C}\u{200D}", 'שואב Dyson'];
        yield 'control characters become spaces' => ["iphone\x0016\x07", 'iphone 16'];
        yield 'keeps emoji and markup as plain text (AC-4.4)' => ['<script>alert(1)</script> 🐶', '<script>alert(1)</script> 🐶'];
        yield 'invalid UTF-8 never breaks the pipeline' => ["ספה\xFF", 'ספה?'];
    }

    #[DataProvider('cleaned')]
    public function testCleansRawInput(string $raw, string $expected): void
    {
        self::assertSame($expected, SearchQuery::fromRaw($raw)->text);
    }

    public function testCutsLongInputTo100CharactersInsteadOfFailing(): void
    {
        $query = SearchQuery::fromRaw(str_repeat('א', 99) . ' ' . str_repeat('ב', 50));

        self::assertSame(str_repeat('א', 99), $query->text, 'cut to 100, then the trailing space trimmed');
        self::assertSame(100, mb_strlen(SearchQuery::fromRaw(str_repeat('ש', 500))->text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tooShort(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ["  \t "];
        yield 'one letter' => ['א'];
        yield 'one letter wrapped in invisible marks' => ["\u{200F}a\u{200E}"];
    }

    #[DataProvider('tooShort')]
    public function testRejectsQueriesShorterThan2Characters(string $raw): void
    {
        $this->expectException(InvalidQuery::class);
        SearchQuery::fromRaw($raw);
    }
}
