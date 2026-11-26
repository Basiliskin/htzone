<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Domain\Search;

use Htzone\Domain\Search\Trigrams;
use PHPUnit\Framework\TestCase;

final class TrigramsTest extends TestCase
{
    public function testSplitsAWordIntoOverlapping3LetterChunks(): void
    {
        self::assertSame(['מקר', 'קרו', 'רוג', 'וגל'], Trigrams::split('מקרוגל'));
        self::assertSame(['mac', 'acb', 'cbo', 'bok'], Trigrams::split('MacBok'));
        self::assertSame(['ab'], Trigrams::split('ab'), 'a word shorter than 3 is its own chunk');
        self::assertSame(['aaa'], Trigrams::split('aaaa'), 'repeated chunks count once');
    }

    public function testSimilarityIsSharedChunksOverAllChunks(): void
    {
        self::assertSame(0.5, Trigrams::similarity('מקרוגל', 'מיקרוגל'), '3 shared of 6 distinct (AC-4.5)');
        self::assertSame(0.5, Trigrams::similarity('macbok', 'macbook'));
        self::assertSame(1.0, Trigrams::similarity('dyson', 'DYSON'));
        self::assertSame(0.0, Trigrams::similarity('dyson', 'ספה'));
        self::assertSame(0.2, Trigrams::similarity('מקרוגל', 'מקרר'));
    }

    public function testClosestPicksTheMostSimilarWord(): void
    {
        self::assertSame(['מיקרוגל', 0.5], Trigrams::closest('מקרוגל', ['תנור', 'מקרר', 'מיקרוגל']));
        self::assertNull(Trigrams::closest('מקרוגל', []));
    }
}
