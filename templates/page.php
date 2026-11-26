<?php

declare(strict_types=1);

/*
 * The HTML shell (architecture §9, design §1–§3). Every value is escaped; no inline script or style
 * (CSP). Without JS the form still submits GET /?q= and keeps the query (D35); with JS, app.js
 * searches in place. Which section shows is decided by CSS from body[data-state].
 */

/** @var string $q the raw query from ?q= (set by PageController::render), may be anything */

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

/** The brief's example queries. Real links, so they work without JS too. */
$examples = ['שואב אבק', 'iPhone 16', 'אייפון', 'מתנה למישהו שאוהב לבשל', 'משהו שיעזור לנקות שערות של כלב מהספה'];
$chips = static function (string $label) use ($examples, $e): string {
    $items = '';
    foreach ($examples as $example) {
        $items .= '<li><a class="chip" href="/?' . $e(http_build_query(['q' => $example])) . '" data-query="'
            . $e($example) . '"><bdi>' . $e($example) . '</bdi></a></li>';
    }

    return '<ul class="chips" aria-label="' . $e($label) . '">' . $items . '</ul>';
};
?>
<!doctype html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>htzone — חיפוש מוצרים</title>
    <meta name="description" content="חיפוש מוצרים בעברית ובאנגלית בקטלוג htzone">
    <meta name="theme-color" content="#f5f6f8">
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="/assets/fonts/heebo-hebrew.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/styles.css">
    <script type="module" src="/assets/app.js"></script>
</head>
<body data-state="idle">
    <a class="skip" href="#q">דלגו לחיפוש</a>

    <header class="top">
        <div class="top-inner">
            <span class="logo" aria-label="htzone" dir="ltr">htzone</span>
            <p class="kbd-hint" aria-hidden="true">לחצו <kbd>/</kbd> לחיפוש · <kbd>Esc</kbd> לניקוי</p>
        </div>
    </header>

    <section class="hero" aria-labelledby="idle-title">
        <h1 id="idle-title">מה בא לכם למצוא?</h1>
        <p class="subtitle">תארו מה אתם מחפשים, אנחנו נמצא מוצרים מתאימים</p>
        <form id="search-form" class="search" role="search" action="/" method="get">
            <label for="q" class="visually-hidden">חיפוש מוצרים</label>
            <input id="q" name="q" type="search" dir="auto" maxlength="100" autocomplete="off"
                   enterkeyhint="search" placeholder="למשל: מתנה למישהו שאוהב קפה" value="<?= $e($q) ?>">
            <button type="button" id="clear" class="clear" aria-label="ניקוי החיפוש" hidden>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
            <button type="submit" class="go">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <span class="go-text">חיפוש</span>
            </button>
        </form>
        <noscript><p class="noscript">כדי לראות תוצאות חיפוש צריך להפעיל JavaScript בדפדפן.</p></noscript>
        <?= $chips('חיפושים לדוגמה') ?>
    </section>

    <main>
        <div id="live" class="visually-hidden" role="status" aria-live="polite"></div>

        <section id="results" aria-labelledby="results-title">
            <div class="results-head">
                <h2 id="results-title" tabindex="-1">מצאנו מוצרים בשבילכם</h2>
                <span id="count" class="count"></span>
            </div>
            <div id="notice" class="notice" hidden></div>
            <ul id="grid" class="grid"></ul>
        </section>

        <section id="empty" class="panel" aria-labelledby="empty-title">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8 11h6"/></svg>
            <h2 id="empty-title" tabindex="-1">לא מצאנו מוצרים עבור „<bdi id="empty-q"></bdi>”</h2>
            <p>נסו מילים אחרות, או אחד מהחיפושים האלה:</p>
            <?= $chips('חיפושים מוצעים') ?>
        </section>

        <section id="error" class="panel error" role="alert" aria-labelledby="error-title">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 2 21h20L12 3Zm0 7v5m0 3v.01"/></svg>
            <h2 id="error-title" tabindex="-1">משהו השתבש בחיפוש</h2>
            <p>לא הצלחנו לקבל תוצאות כרגע. נסו שוב בעוד רגע.</p>
            <button type="button" id="retry" class="btn">נסו שוב</button>
        </section>

        <section id="short" class="panel" aria-labelledby="short-title">
            <p id="short-title" tabindex="-1">הקלידו לפחות 2 תווים כדי לחפש</p>
        </section>
    </main>
</body>
</html>
