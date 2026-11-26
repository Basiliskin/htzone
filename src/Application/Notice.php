<?php

declare(strict_types=1);

namespace Htzone\Application;

/**
 * A note shown above the results. The wording is the HTTP layer's job; this says only what happened.
 */
final readonly class Notice
{
    private function __construct(public NoticeType $type, public ?string $suggestion)
    {
    }

    public static function missingModel(): self
    {
        return new self(NoticeType::MissingModel, null);
    }

    public static function didYouMean(string $suggestion): self
    {
        return new self(NoticeType::DidYouMean, $suggestion);
    }
}
