<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * One searchable form of a query word, with how sure we are it means the same thing (D31):
 * 1.0 for the word as typed or an alias, less for each guess (stripped prefix or plural).
 */
final readonly class Variant
{
    public function __construct(public string $text, public float $strength)
    {
        if ($text === '' || $strength <= 0.0 || $strength > 1.0) {
            throw new \InvalidArgumentException("Invalid variant \"{$text}\" ({$strength})");
        }
    }
}
