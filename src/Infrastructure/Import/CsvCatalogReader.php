<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

/**
 * Reads assets/catalog_no_prices.csv: strips the BOM, checks the header exactly and validates every row.
 * All problems are collected and thrown together (D29), so one run shows everything to fix.
 */
final class CsvCatalogReader
{
    public const HEADER = ['id', 'name', 'brand', 'category', 'subcategory', 'description', 'attributes', 'image_url'];
    private const REQUIRED = ['name', 'category', 'subcategory', 'description', 'image_url'];

    /**
     * @return list<CatalogRow> in file order
     *
     * @throws ImportFailed
     */
    public function read(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new ImportFailed(["cannot open catalog file {$path}"]);
        }

        try {
            $header = fgetcsv($handle, escape: '');
            $header = is_array($header) ? array_map(static fn (?string $v): string => trim((string) $v), $header) : [];
            if ($header !== []) {
                $header[0] = (string) preg_replace('/^\x{FEFF}/u', '', $header[0]);
            }
            if ($header !== self::HEADER) {
                throw new ImportFailed([sprintf('header must be %s; got %s', implode(',', self::HEADER), implode(',', $header))]);
            }

            $rows = [];
            $problems = [];
            $seen = [];
            $number = 0;
            while (($fields = fgetcsv($handle, escape: '')) !== false) {
                if ($fields === [null]) {
                    continue; // blank line
                }
                $number++;
                $rowProblems = $this->validate($fields, $seen);
                $id = trim($fields[0] ?? '');
                $id = $id === '' ? '?' : $id;
                foreach ($rowProblems as $problem) {
                    $problems[] = "row {$number} ({$id}): {$problem}";
                }
                if ($rowProblems === []) {
                    $rows[] = $this->toRow($fields);
                }
                $seen[$id] = true;
            }
        } finally {
            fclose($handle);
        }

        if ($problems !== []) {
            throw new ImportFailed($problems);
        }
        if ($rows === []) {
            throw new ImportFailed(['catalog has no rows']);
        }

        return $rows;
    }

    /**
     * @param array<int, string|null> $fields
     * @param array<string, true>     $seen   ids of earlier rows
     * @return list<string>
     */
    private function validate(array $fields, array $seen): array
    {
        if (count($fields) !== count(self::HEADER)) {
            return [sprintf('expected %d columns, got %d', count(self::HEADER), count($fields))];
        }
        $values = array_combine(self::HEADER, array_map(static fn (?string $v): string => trim((string) $v), $fields));

        $problems = [];
        if (preg_match('/^P\d{4}$/', $values['id']) !== 1) {
            $problems[] = 'id must look like P0001';
        } elseif (isset($seen[$values['id']])) {
            $problems[] = 'duplicate id';
        }
        foreach (self::REQUIRED as $column) {
            if ($values[$column] === '') {
                $problems[] = "{$column} is empty";
            }
        }
        if ($values['image_url'] !== '' && (!str_starts_with($values['image_url'], 'https://') || filter_var($values['image_url'], FILTER_VALIDATE_URL) === false)) {
            $problems[] = 'image_url must be an https URL';
        }

        return $problems;
    }

    /**
     * @param array<int, string|null> $fields already validated
     */
    private function toRow(array $fields): CatalogRow
    {
        $v = array_map(static fn (?string $value): string => trim((string) $value), array_values($fields));

        return new CatalogRow($v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7]);
    }
}
