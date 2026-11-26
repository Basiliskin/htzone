<?php

declare(strict_types=1);

/*
 * The HTML shell (architecture §9). Every value is escaped; no inline script or style (CSP).
 * S4: minimal shell with a working no-JS form. S5 adds the full UI from docs/design.md.
 */

/** @var string $q the raw query from ?q= (set by PageController::render), may be anything */

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>htzone — חיפוש מוצרים</title>
</head>
<body>
    <main>
        <h1>מה תרצו למצוא?</h1>
        <form action="/" method="get" role="search">
            <label for="q">חיפוש מוצרים</label>
            <input id="q" name="q" type="search" maxlength="100" value="<?= $e($q) ?>">
            <button type="submit">חיפוש</button>
        </form>
    </main>
</body>
</html>
