<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * One query word and the forms it is searched in. A qualifier is a number, maybe with a unit (16, 128gb):
 * it only refines word matches and never pulls in products on its own (ADR 0003).
 */
final readonly class SearchTerm
{
    /**
     * @param non-empty-list<Variant> $variants the first one is the word as typed
     */
    public function __construct(public string $original, public array $variants, public bool $isQualifier)
    {
        if ($variants[0]->text !== $original) {
            throw new \InvalidArgumentException("The first variant of \"{$original}\" must be the word itself");
        }
    }

    /**
     * @return list<string>
     */
    public function texts(): array
    {
        return array_map(static fn (Variant $v): string => $v->text, $this->variants);
    }
}
