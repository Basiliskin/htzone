<?php

declare(strict_types=1);

/*
 * Entry for every process (web and CLI): load classes, read config from env, return the composition root.
 */

use Htzone\Infrastructure\Config;
use Htzone\Services;

require __DIR__ . '/../vendor/autoload.php';

/** @var array<string, string> $env */
$env = getenv();

return new Services(Config::fromEnv($env));
