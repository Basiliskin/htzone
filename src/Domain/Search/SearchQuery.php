<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * What the visitor typed, cleaned: valid UTF-8, no control or invisible bidi characters,
 * single spaces, at most 100 characters (architecture §4, AC-4.2, AC-4.7).
 */
final readonly class SearchQuery
{
    public const MIN_LENGTH = 2;
    public const MAX_LENGTH = 100;

    private function __construct(public string $text)
    {
    }

    /**
     * @throws InvalidQuery when fewer than 2 characters remain
     */
    public static function fromRaw(string $raw): self
    {
        $text = mb_scrub($raw, 'UTF-8');                               // invalid bytes → "?"
        $text = preg_replace(['/\p{Cc}+/u', '/\p{Cf}+/u'], [' ', ''], $text) ?? '';  // Cc = control, Cf = RLM/LRM/ZWJ/BOM …
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $text = rtrim(mb_substr($text, 0, self::MAX_LENGTH));          // too long is cut, never an error

        if (mb_strlen($text) < self::MIN_LENGTH) {
            throw new InvalidQuery('Query must be at least ' . self::MIN_LENGTH . ' characters');
        }

        return new self($text);
    }
}
