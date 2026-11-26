<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

/**
 * Reads data/enrichment.jsonl (one JSON object per line) into id → tags (Hebrew first, then English).
 * Only checks what the import needs; the full content rules live in bin/check-enrichment.
 */
final class EnrichmentReader
{
    /**
     * @return array<string, list<string>>
     *
     * @throws ImportFailed
     */
    public function read(string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new ImportFailed(["cannot read enrichment file {$path}"]);
        }

        $tags = [];
        $problems = [];
        foreach ($lines as $index => $line) {
            $where = 'enrichment line ' . ($index + 1);
            try {
                $item = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $problems[] = "{$where}: invalid JSON ({$e->getMessage()})";
                continue;
            }
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (!is_array($item) || !is_string($id)) {
                $problems[] = "{$where}: expected an object with a string id";
                continue;
            }
            $he = $item['tags_he'] ?? null;
            $en = $item['tags_en'] ?? null;
            if (!self::isStringList($he) || !self::isStringList($en)) {
                $problems[] = "{$where} ({$id}): tags_he and tags_en must be lists of strings";
                continue;
            }
            if (isset($tags[$id])) {
                $problems[] = "{$where} ({$id}): duplicate id";
                continue;
            }
            $tags[$id] = [...$he, ...$en];
        }

        if ($problems !== []) {
            throw new ImportFailed($problems);
        }

        return $tags;
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isStringList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, static fn (mixed $v): bool => !is_string($v)) === [];
    }
}
