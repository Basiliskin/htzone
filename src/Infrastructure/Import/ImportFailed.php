<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

/**
 * The import found problems in its input files. Carries every problem, not just the first (D29).
 */
final class ImportFailed extends \RuntimeException
{
    private const SHOWN = 50;

    /**
     * @param list<string> $problems e.g. "row 12 (P0011): name is empty"
     */
    public function __construct(public readonly array $problems)
    {
        $shown = array_slice($problems, 0, self::SHOWN);
        $more = count($problems) > self::SHOWN ? sprintf("\n  … and %d more", count($problems) - self::SHOWN) : '';

        parent::__construct(sprintf("%d problem(s) in the import input:\n  - %s%s", count($problems), implode("\n  - ", $shown), $more));
    }
}
