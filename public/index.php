<?php

declare(strict_types=1);

/*
 * Front controller: Apache sends every request that isn't a real file here (docker/apache.conf).
 */

use Htzone\Http\Request;
use Htzone\Services;

/** @var Services $services */
$services = require __DIR__ . '/../src/bootstrap.php';

$services->kernel()->handle(Request::fromGlobals())->send();
