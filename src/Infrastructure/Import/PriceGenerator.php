<?php

declare(strict_types=1);

namespace Htzone\Infrastructure\Import;

use Htzone\Domain\Catalog\Money;

/**
 * Deterministic synthetic prices (D4, D24, docs/data.md §3).
 * price = min + crc32("htzone-v1:" . id) mod (max − min + 1), rounded up to …9 (< ₪1,000) or …99,
 * capped at max. No random generator, so every machine and every build gets the same prices.
 */
final readonly class PriceGenerator
{
    private const SEED = 'htzone-v1:';

    /** @var array<string, array{int, int}> */
    private array $ranges;

    /**
     * @param array<array-key, mixed> $ranges "category > subcategory" → [min, max] in whole shekels (validated here)
     */
    public function __construct(array $ranges)
    {
        if ($ranges === []) {
            throw new \InvalidArgumentException('Price ranges are empty');
        }
        $valid = [];
        foreach ($ranges as $key => $range) {
            if (!is_array($range) || count($range) !== 2 || !is_int($range[0] ?? null) || !is_int($range[1] ?? null)) {
                throw new \InvalidArgumentException("Price range for \"{$key}\" must be [min, max] integers");
            }
            [$min, $max] = [$range[0], $range[1]];
            if ($min < 1 || $min >= $max) {
                throw new \InvalidArgumentException("Price range for \"{$key}\" must satisfy 1 <= min < max");
            }
            $valid[(string) $key] = [$min, $max];
        }
        $this->ranges = $valid;
    }

    public static function fromFile(string $path): self
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Cannot read price ranges file {$path}");
        }
        $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['ranges'] ?? null)) {
            throw new \InvalidArgumentException("{$path}: expected an object with a \"ranges\" map");
        }

        return new self($data['ranges']);
    }

    public function priceFor(string $productId, string $categoryKey): Money
    {
        $range = $this->ranges[$categoryKey] ?? null;
        if ($range === null) {
            throw new \InvalidArgumentException("{$productId}: no price range for \"{$categoryKey}\"");
        }
        [$min, $max] = $range;

        $shekels = $min + crc32(self::SEED . $productId) % ($max - $min + 1);

        return Money::fromShekels(min(self::storeEnding($shekels), $max));
    }

    /** 437 → 439, 1450 → 1499: prices that look like a real shop's. */
    private static function storeEnding(int $shekels): int
    {
        $step = $shekels < 1000 ? 10 : 100;

        return intdiv($shekels, $step) * $step + $step - 1;
    }
}
