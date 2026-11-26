<?php

declare(strict_types=1);

/*
 * Composition root (architecture §10): the only place that creates services.
 * S0 wires configuration only; S1–S4 add the repository, search use case, controllers and Kernel.
 */

use Htzone\Infrastructure\Config;

require __DIR__ . '/../vendor/autoload.php';

/** @var array<string, string> $env */
$env = getenv();

return Config::fromEnv($env);
