<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

/**
 * What an import did, for the bin/import summary.
 */
final readonly class ImportReport
{
    /**
     * @param list<string> $warnings non-fatal findings (missing enrichment, letters from other scripts …)
     */
    public function __construct(
        public int $products,
        public int $tagged,
        public array $warnings,
        public int $durationMs,
    ) {
    }
}
