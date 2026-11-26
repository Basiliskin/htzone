# htzone — smart catalog search

Search a 1000-product catalog in Hebrew, English or both — by product name or by describing a need.

- **Live:** _TBD (Render)_ — free tier: the first load after a quiet period may take ~30–50 s.
- **Stack:** PHP 8.3 (no framework) · SQLite + FTS5 · vanilla JS/CSS (RTL) · Docker · GitHub Actions.

## Run & load catalog
Needs only Docker.
```bash
docker compose up -d --build                 # dev container (PHP 8.3 + Apache), http://localhost:8080
docker compose exec app composer install     # dependencies
docker compose exec app php bin/import       # migrations + 1000 products + tags + prices → var/catalog.sqlite
docker compose exec app composer check       # format check + PHPStan (max) + PHPUnit
npm test                                     # JS unit tests (built-in node --test, no install)
```
The production image builds the same database during `docker build` and ships it read-only. All commands: [`docs/stack.md`](docs/stack.md).

API (contract in [`docs/architecture.md` §8](docs/architecture.md)): `GET /api/search?q=…` → `{query,total,results[],notice}`, `GET /health` → `{"status":"ok","products":1000}`. Errors are always `{"error":{"code","message"}}` (400 `query_missing`/`query_too_short`, 404, 405, 500 `internal_error`, never a stack trace). Every request writes one JSON log line to stdout (`docker compose logs -f app`). Config comes from env only (`APP_ENV`, `DB_PATH`, `LOG_LEVEL`, see `.env.example`); there are no secrets.

## Key decisions
Full list with reasons: [`docs/decisions.md`](docs/decisions.md).
- No AI at runtime: Claude-written HE/EN tags are created once and committed; the live search is plain full-text (D2).
- Hebrew plurals/prefixes handled by query normalization + prefix matching (D3).
- Prices are **generated** (fixed seed, per-category ranges) because the CSV has none (D4).
- SQLite built into the image: one container, no DB password (D5).
- The catalog has no iPhone 16 — Apple phones are returned with a "no exact model" note (D14).
- Typo fallback only when nothing matches (D15).
- Hand-written HTTP layer (~250 lines: Request/Response/Router/Kernel), no framework, so every line can be explained (D19). The DB is opened lazily, so a missing DB gives `/health` 503 instead of a crash (D33). Each request logs one line carrying the search details (D34).
- UI: plain ES modules, no build step; page state lives on `body[data-state]` and CSS alone shows/hides sections. Without JS the form and suggestion links still work but results need JS (D35). JS/CSS are revalidated on every load (`no-cache` + ETag) because filenames aren't hashed; fonts/images are cached a week.
- Defaults: `q` missing or not text → 400; under 2 characters → 400; over 100 → cut. Search answers are cached for 60 s (catalog changes only on deploy); errors and `/health` are `no-store`. PHP warnings become a logged 500.

## How it was tested
- **Unit (domain search logic):** a table-driven test of every normalizer rule (prefixes, plurals, final letters, aliases, niqqud, stop-words, qualifiers) and one hand-made test per ranking rule. Each ranking test was confirmed to **fail** when its rule is switched off (mutation check, SESSION-LOG S2).
- **Integration:** the SQLite repository against a real temporary DB opened read-only, including hostile input (`" OR 1=1 --`, `'; DROP TABLE`, `NEAR(` …) searched as plain text.
- **HTTP (integration, Kernel called directly, no server):** response shape, every error code, 404/405/HEAD, all four security headers on every response kind, cache headers, `/health` 200 and 503 (missing, corrupt and empty DB), a thrown error and a PHP warning both give a generic 500 with details only in the log, `<script>` in `q` comes back as `\u003C…`, and the fields of the log line. Mutation-checked (remove `JSON_HEX_TAG`, a header, HEAD body stripping, logging, or the empty-catalog rule → a test fails). Then `curl` against the dev container and the prod image; CI repeats the smoke (one CSP header, non-root, `/health`, a search, a 400).
- **Frontend (S5):** `node --test` unit tests for query limits, state transitions, an old response never replacing a newer one, Back/Forward rules and API error mapping (D36), each mutation-checked. PHP tests assert the page has no inline script/style (CSP), works as a form without JS, and has every element the script needs. Manual pass in Chrome: all six states, retry focus, `/` and `Esc`, Back/Forward, a broken image → fallback, `<script>` and `" OR 1=1 --` shown as text, no CSP violations in the console; 360 px checked with real device emulation (no horizontal scroll, list cards).
- **Relevance:** every row of the example-query table ([`docs/requirements.md` §4](docs/requirements.md), the brief's 5 examples + 14 of ours) runs against the real imported catalog, plus a p95 < 100 ms check (real searches take 0.3–5 ms). Two rows failed at first and were fixed in ranking and data, never by relaxing the test (SESSION-LOG S3).

## Known limitations
- Prices are synthetic: generated per subcategory range from a hash of the id (`docs/data.md` §3), so within a subcategory they don't reflect the specific model.
- The source CSV has 31 data problems (typos, names that don't match the product); they are reported, not edited, and tags carry the correct spelling (`docs/data.md` §2).
- Images are text placeholders from the provided CSV.
- Meaning-based search depends on tag coverage; unusual phrasings not covered by tags can miss.
- Without JavaScript the page is a working form that keeps the query, but shows no results (D35).
- No rate limiting or pagination (24 results max); query-string garbage such as `<script>alert(1)</script>` still returns loose prefix matches rather than nothing.

## With more time
- Filters/sorting and pagination.
- Embeddings (hybrid vector + keyword search) for phrasings the tags don't cover.
- Rate limiting with shared storage; search analytics and monitoring.
- Product detail page.
- Server-rendered results for no-JS visitors and faster first paint on shared `?q=` links (one shared card template).
- Dark mode (the placeholder images are light; it needs real product photos first).

## Working with Claude
Process log: [`docs/ai-log/SESSION-LOG.md`](docs/ai-log/SESSION-LOG.md) (prompts auto-captured in `docs/ai-log/PROMPTS.md`).
- **Rejected:** Claude first proposed runtime embeddings via an external API + pgvector; replaced with offline enrichment so the deploy has no runtime dependency or secret.
- **Self-correction caught in prototyping:** Claude's own requirement said to use `dir="auto"` on product names; a screenshot of the prototype showed it flips names starting with English to left-to-right. The requirement and design were changed so names inherit RTL.
- **Tags written by 10 parallel Claude agents** from one committed guide (`data/ENRICHMENT-GUIDE.md`), verified by a check script, example-query coverage and a human spot-check (`docs/data.md` §5–6).
- **Rejected in review:** Claude-written tags that implied health claims the products don't make ("קורונה" on an oximeter, "אנטי אייג׳ינג" on an LED mask) and a tag that would make vacuum bags match "שואב אבק".
- **Self-correction caught by tests:** Claude's planned query cleanup deleted the geresh (דולצ׳ה → דולצה), but SQLite indexes the catalog by splitting at it, so the cleaned query could never match. Found by checking the index's own word list; the query now splits the same way.
- **Caught by the relevance tests:** for the brief's "מתנה למישהו שאוהב לבשל", Claude's tags said "מתנה לחובבי בישול", which never matches the verb "לבשל", so coffee gift sets ranked first. The fix went into the data (a need tag on 72 cooking products), not into a special case in the code.
- **Caught by running the real server (S4):** the Kernel tests passed, but `curl` showed every PHP response carried each security header **twice**: Apache's `Header always set` adds a second copy next to PHP's. Apache now skips `index.php` responses, and CI checks for exactly one CSP header. The same `curl` pass also showed a missing `/assets/*.css` coming back as a 200 HTML page, so assets are no longer rewritten to PHP.
- **Claude's own test was wrong (S4):** the first warning→500 test failed. PHPUnit narrows `error_reporting` while it runs, and Claude's `@`-detection (`error_reporting() & $severity`) mistook every warning for a silenced one. It was replaced with the PHP 8 rule PHPUnit itself uses (only fatal levels left = `@`), which is correct in production as well.
- **Mutation check caught a missing test (S5):** with the `historyMethod` "stay committed" rule removed, all JS tests still passed. A case for typing that doesn't change the URL was added and now fails on that mutation. The first mutation script had also silently not applied one change; it was re-run until the diff was verified.
- **A misleading tool, not a bug (S5):** headless Chrome screenshots at `--window-size=360` looked cut off, but Chrome enforces a minimum window width and crops. The real check used DevTools device emulation (360 px viewport): no overflow.
- _More examples added as work progresses._
