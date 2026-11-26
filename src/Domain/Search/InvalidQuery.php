<?php

declare(strict_types=1);

namespace Htzone\Domain\Search;

/**
 * The raw query can't be searched (shorter than SearchQuery::MIN_LENGTH after cleaning). HTTP maps it to 400.
 */
final class InvalidQuery extends \InvalidArgumentException
{
}
