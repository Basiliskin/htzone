<?php

declare(strict_types=1);

/*
 * Front controller: Apache sends every request that isn't a real file here.
 * S0 placeholder page; S4 replaces the body with: (require bootstrap)->handle(Request::fromGlobals()).
 */

use Htzone\Infrastructure\Config;

/** @var Config $config */
$config = require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

$env = htmlspecialchars($config->appEnv, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo <<<HTML
    <!doctype html>
    <html lang="he" dir="rtl">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>htzone — חיפוש</title></head>
    <body><h1>htzone</h1><p>השלד עובד (סביבה: {$env}). החיפוש יגיע בשלבים הבאים.</p></body>
    </html>
    HTML;
