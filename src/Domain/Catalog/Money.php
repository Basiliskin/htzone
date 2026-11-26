<?php

declare(strict_types=1);

namespace Htzone\Domain\Catalog;

/**
 * An amount of money in minor units (agorot). Never a float, so there's no rounding drift.
 */
final readonly class Money
{
    private const SYMBOLS = ['ILS' => '₪'];

    private function __construct(
        public int $minor,
        public string $currency,
    ) {
        if ($minor < 0) {
            throw new \InvalidArgumentException("Money can't be negative, got {$minor}");
        }
    }

    public static function ils(int $agorot): self
    {
        return new self($agorot, 'ILS');
    }

    public static function fromShekels(int $shekels): self
    {
        return self::ils($shekels * 100);
    }

    /** "₪1,299", or "₪1,299.90" when there are agorot. */
    public function format(): string
    {
        $symbol = self::SYMBOLS[$this->currency] ?? $this->currency . ' ';
        $decimals = $this->minor % 100 === 0 ? 0 : 2;

        return $symbol . number_format($this->minor / 100, $decimals, '.', ',');
    }
}
