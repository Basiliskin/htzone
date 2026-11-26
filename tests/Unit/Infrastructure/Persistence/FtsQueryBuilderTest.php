<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Infrastructure\Persistence;

use Htzone\Domain\Search\QueryNormalizer;
use Htzone\Domain\Search\SearchQuery;
use Htzone\Domain\Search\SearchTerm;
use Htzone\Domain\Search\Variant;
use Htzone\Infrastructure\Persistence\FtsQueryBuilder;
use PHPUnit\Framework\TestCase;

final class FtsQueryBuilderTest extends TestCase
{
    private static function match(string $query): string
    {
        return (new FtsQueryBuilder())->forTerms((new QueryNormalizer())->normalize(SearchQuery::fromRaw($query)));
    }

    public function testEachWordIsAnOrGroupOfQuotedPrefixVariants(): void
    {
        self::assertSame('("לכלב"* OR "כלב"*) OR ("dyson"* OR "דייסון"*)', self::match('לכלב Dyson'));
    }

    public function testNumbersMustMatchAWholeWord(): void
    {
        self::assertSame('("iphone"* OR "אייפון"*) OR ("16")', self::match('iPhone 16'));
    }

    public function testFtsOperatorsAndQuotesStayPlainText(): void
    {
        // AC-4.3: whatever survives tokenizing is inside quotes, so NEAR, NOT, ^, :, *, ( ) can't act as operators.
        self::assertSame('("near"*) OR ("not"*) OR ("title"*) OR ("drop"*) OR ("table"*)', self::match('NEAR( NOT ^title:* "; DROP TABLE'));
        self::assertSame('("a""b"*)', (new FtsQueryBuilder())->forTerms([new SearchTerm('a"b', [new Variant('a"b', 1.0)], false)]), 'a quote inside a word is doubled');
    }

    public function testTrigramQueryQuotesEach3LetterChunk(): void
    {
        self::assertSame('"מקר" OR "קרו" OR "רוג" OR "וגל" OR "mac" OR "acb" OR "cbo" OR "bok"', (new FtsQueryBuilder())->forTrigrams(['מקרוגל', 'MacBok']));
        self::assertSame('', (new FtsQueryBuilder())->forTrigrams(['ab']), 'the trigram index can\'t match words shorter than 3');
    }
}
