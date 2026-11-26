<?php

declare(strict_types=1);

namespace Htzone\Tests\Unit\Infrastructure\Persistence;

use Htzone\Infrastructure\Persistence\LazyProductRepository;
use Htzone\Tests\Support\FakeProductRepository;
use PHPUnit\Framework\TestCase;

final class LazyProductRepositoryTest extends TestCase
{
    public function testBuildsTheRealRepositoryOnFirstUseOnly(): void
    {
        $built = 0;
        $lazy = new LazyProductRepository(static function () use (&$built): FakeProductRepository {
            ++$built;

            return new FakeProductRepository([], []);
        });
        self::assertSame(0, $built, 'constructing must not open the database');

        $lazy->count();
        $lazy->findCandidates([], 10);
        $lazy->findByTrigrams(['abc'], 10);

        self::assertSame(1, $built);
    }

    public function testAFailedOpenIsRetriedOnTheNextCall(): void
    {
        $attempts = 0;
        $lazy = new LazyProductRepository(static function () use (&$attempts): FakeProductRepository {
            if (++$attempts === 1) {
                throw new \PDOException('unable to open database file');
            }

            return new FakeProductRepository([], []);
        });

        try {
            $lazy->count();
            self::fail('expected the first open to fail');
        } catch (\PDOException) {
        }
        $lazy->count();

        self::assertSame(2, $attempts);
    }
}
