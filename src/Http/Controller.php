<?php

declare(strict_types=1);

namespace Htzone\Http;

/**
 * One route's handler: Request in, Response out. Thrown exceptions are the Kernel's job.
 */
interface Controller
{
    public function handle(Request $request): Response;
}
