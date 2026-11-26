<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * A filter value can't be used (a price that isn't whole shekels, min above max, …). HTTP maps it to 400.
 */
final class InvalidFilter extends \InvalidArgumentException
{
}
