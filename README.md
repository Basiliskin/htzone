# htzone — smart catalog search

Search a 1000-product catalog in Hebrew, English or both — by product name or by describing a need.

- **Live:** https://htzone.onrender.com (Render free tier; try one of the example chips). After 15 min without traffic the service sleeps, and the first request takes about 12 s to wake it (measured: 12.4 s cold, 0.11 s warm). A keep-awake ping is on during the review.
- **Stack:** PHP 8.3 (no framework) · SQLite + FTS5 · vanilla JS/CSS (RTL) · Docker · GitHub Actions.

## Run & load catalog
Needs only Docker.
```bash
docker compose up -d --build                 # dev container (PHP 8.3 + Apache), http://localhost:8080
docker compose exec app composer install     # dependencies
docker compose exec app php bin/import       # migrations + 1000 products + tags + prices → var/catalog.sqlite
docker compose exec app composer check       # format check + PHPStan (max) + PHPUnit
npm test                                     # JS unit tests (built-in node --test, no install)
npm ci && npx playwright install chromium    # once, for the E2E
npm run e2e                                  # Playwright E2E against http://localhost:8080 (E2E_BASE_URL to change)
```
The production image builds the same database during `docker build` and ships it read-only. All commands: [`docs/stack.md`](docs/stack.md).

**Deploy (live):** `render.yaml` is a Render Blueprint: one free Docker web service built from the Dockerfile's `prod` stage, health check `/health`. A push to `main` deploys automatically **only after GitHub Actions CI is green** (`autoDeployTrigger: checksPass`), so a failing test or E2E never reaches the live site. Keep-awake: a free [cron-job.org](https://cron-job.org) job calls `GET https://htzone.onrender.com/health` every 10 minutes during the review period.

API (contract in [`docs/architecture.md` §8](docs/architecture.md)): `GET /api/search?q=…` → `{query,total,results[],notice}`, `GET /health` → `{"status":"ok","products":1000}`. Errors are always `{"error":{"code","message"}}` (400 `query_missing`/`query_too_short`, 404, 405, 414 `request_too_long` from Apache for a request line over 2 KB, 500 `internal_error`, never a stack trace). Every request writes one JSON log line to stdout (`docker compose logs -f app`). Config comes from env only (`APP_ENV`, `DB_PATH`, `LOG_LEVEL`, see `.env.example`); there are no secrets.

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
- English words that only start a product word count half (D41): `kettle` ranks the kettles above the brand "Kettler" and a kettlebell, while a half-typed `macb` still finds the MacBook. Hebrew keeps full prefix matching for its plurals and prefixes.
- Defaults: `q` missing or not text → 400; under 2 characters → 400; over 100 → cut. Search answers are cached for 60 s (catalog changes only on deploy); errors and `/health` are `no-store`. PHP warnings become a logged 500. Security headers on every response: CSP, nosniff, Referrer-Policy, Permissions-Policy and HSTS.
- Time: the brief plans ~4 h; this took longer on purpose (planned 5.5–6 h, D12), spent on tests, relevance checks and production hardening rather than extra features.

## How it was tested
- **Unit (domain search logic):** a table-driven test of every normalizer rule (prefixes, plurals, final letters, aliases, niqqud, stop-words, qualifiers) and one hand-made test per ranking rule. Each ranking test was confirmed to **fail** when its rule is switched off (mutation check, SESSION-LOG S2).
- **Integration:** the SQLite repository against a real temporary DB opened read-only, including hostile input (`" OR 1=1 --`, `'; DROP TABLE`, `NEAR(` …) searched as plain text.
- **HTTP (integration, Kernel called directly, no server):** response shape, every error code, 404/405/HEAD, all four security headers on every response kind, cache headers, `/health` 200 and 503 (missing, corrupt and empty DB), a thrown error and a PHP warning both give a generic 500 with details only in the log, `<script>` in `q` comes back as `\u003C…`, and the fields of the log line. Mutation-checked (remove `JSON_HEX_TAG`, a header, HEAD body stripping, logging, or the empty-catalog rule → a test fails). Then `curl` against the dev container and the prod image; CI repeats the smoke (one CSP header, non-root, `/health`, a search, a 400).
- **Frontend (S5):** `node --test` unit tests for query limits, state transitions, an old response never replacing a newer one, Back/Forward rules and API error mapping (D36), each mutation-checked. PHP tests assert the page has no inline script/style (CSP), works as a form without JS, and has every element the script needs. Manual pass in Chrome: all six states, retry focus, `/` and `Esc`, Back/Forward, a broken image → fallback, `<script>` and `" OR 1=1 --` shown as text, no CSP violations in the console; 360 px checked with real device emulation (no horizontal scroll, list cards).
- **E2E (S6, Playwright, Chromium):** one spec, three flows in a real browser against the real server: type "שואב אבק" → 8 skeleton cards → real cards with a decoded image, a name and a `₪1,234` price; a nonsense query (`zzqxv`) → the no-results panel with the query and suggestion chips; a faked 500 → error panel → Retry sends the same query to the real API, shows the missing-model notice for "iPhone 16", and focuses the results heading. To see the skeleton, the test holds the request with `page.route` and then lets it reach the real server (D37). In CI it runs against the prod container from the smoke test (D38). Stable over 15 repeated runs; mutation-checked (no skeletons, Retry running nothing, raw agorot instead of ₪, no focus after Retry → each one fails a test).
- **Final QA ([`docs/qa-report.md`](docs/qa-report.md)):** every requirement mapped to its test; all suites re-run; the example queries plus adversarial ones (typo, prefixed plural, English-only brand, mixed, gibberish) run locally and live with top 5 and latency; a security smoke (empty, 10k-char, FTS/SQL characters, `<script>`, RTL marks, emoji, niqqud, backend down, broken images, headers, secrets in history); a browser pass at 360 px and desktop (keyboard, focus, `aria-live`, every state).
- **Relevance:** every row of the example-query table ([`docs/requirements.md` §4](docs/requirements.md), the brief's 5 examples + 16 of ours) runs against the real imported catalog, plus a p95 < 100 ms check (real searches take 0.3–5 ms). Two rows failed at first and were fixed in ranking and data, never by relaxing the test (SESSION-LOG S3).

## Known limitations
- Prices are synthetic: generated per subcategory range from a hash of the id (`docs/data.md` §3), so within a subcategory they don't reflect the specific model.
- The source CSV has 31 data problems (typos, names that don't match the product); they are reported, not edited, and tags carry the correct spelling (`docs/data.md` §2).
- Images are text placeholders from the provided CSV.
- Meaning-based search depends on tag coverage; unusual phrasings not covered by tags can miss.
- Without JavaScript the page is a working form that keeps the query, but shows no results (D35).
- Free hosting: after 15 min idle the first request waits for a cold start (measured above) unless the keep-awake ping is running.
- On the live site, Render's Cloudflare edge blocks queries that look like SQL injection (`" OR 1=1 --`, `'; DROP TABLE`) with its own 403 HTML page before they reach the app, so the UI shows the error panel. The app itself treats them as plain text (integration and relevance tests, and locally); `<script>` and other input pass through normally.
- Only two air fryers exist in the catalog, so `Air Fryer` shows them first and then products matching "air" alone (MacBook Air, air purifiers). Partial matches are kept on purpose because sentence queries depend on them (D42).
- No rate limiting or pagination (24 results max); query-string garbage such as `<script>alert(1)</script>` still returns loose prefix matches rather than nothing.

## With more time
- Filters/sorting and pagination.
- Embeddings (hybrid vector + keyword search) for phrasings the tags don't cover.
- Rate limiting with shared storage; search analytics and monitoring.
- Product detail page.
- Server-rendered results for no-JS visitors and faster first paint on shared `?q=` links (one shared card template).
- Dark mode (the placeholder images are light; it needs real product photos first).

## Production-grade design (beyond the brief)
What this would become for a real store (50k–1M products, real traffic) without the brief's limits (4 h, one free container, no API keys, no chat). This project is the first stage of the same design in miniature: enrichment as data, a pure tested ranker, a relevance test set. What would change in this codebase to get there, and in what order: [ADR 0004](docs/adr/0004-migration-to-production-search.md).

```
              ┌─────────── offline / async ───────────┐
Catalog DB ──► Indexing pipeline ──► Search engine (keyword + vector)
(Postgres)     • clean, normalize        ▲
   │ CDC       • LLM enrichment          │
   ▼           • embeddings              │
 events ───────┘                         │
Browser ─► API ─► Query understanding ─► Retrieve (hybrid) ─► Rerank ─► Business rules ─► results
        (cache)                          (BM25 + kNN, ~200)   (LTR, top 50) (stock, promos)
                                                                 └─► optional "help me choose" (RAG), async
       clicks / carts / purchases ─► analytics ─► training data · dashboards · offline evaluation
```

- **Data:** Postgres is the source of truth; price and stock changes reach the index within seconds through change events (CDC). An LLM enriches products in batches (tags, synonyms, translations, attributes) with schema validation and sampled human review, as D2 does here once. Each product also gets a multilingual embedding.
- **Search engine:** OpenSearch/Elasticsearch, or Vespa for advanced ranking. Each field gets a real language analyzer instead of our prefix rules (D3): a Hebrew morphological analyzer (HebMorph) and an English stemmer. Search-as-you-type uses a separate low-weight edge-n-gram field; this is what our `latinPrefix` approximates (D41). Brand and category are exact keywords and facets. Synonyms are a managed list the business edits without a deploy.
- **Query time (p95 ≈ 100 ms):**
  1. Cache popular queries.
  2. Query understanding: spelling, brand/category detection, filters such as "up to ₪500". Long need-sentences get an LLM rewrite, cached and with a timeout; if it's slow, the search goes on without it.
  3. Hybrid retrieval: BM25 + vector search, merged with Reciprocal Rank Fusion.
  4. Reranking of the top 50 with learning to rank trained on clicks and purchases, where today's `RankingWeights` become model features.
  5. Business rules last: stock, promotions, diversity.
  6. Facets and pagination.
- **LLMs / RAG:** good uses are offline enrichment and cached query rewriting. A "help me choose" answer (RAG) is optional: it loads after the results, uses only the products shown, cites their ids, and takes prices and stock from the DB, never from generated text. An LLM never sits on the critical path of the search box, because of latency, cost and hallucinated product facts.
- **Measuring quality:** a judged set of several hundred real queries (Hebrew, English, mixed) scored with NDCG@10 / MRR on every change, as our relevance table does at small scale. Then A/B tests on click-through, add-to-cart and conversion. Dashboards track zero-result and no-click queries, which become the to-do list for synonyms and enrichment.
- **Operations:**
  - service targets (e.g. p95 < 150 ms, 99.9 % availability);
  - fall back to keyword-only results when vectors or the LLM are slow;
  - blue/green index rebuilds behind an alias, for instant rollback;
  - per-stage latency monitoring;
  - rate limiting;
  - secrets in a secret manager.

| Catalog / traffic | Sensible stack |
|---|---|
| ~1k products, demo (this project) | SQLite FTS5 + hand-tuned ranker + offline tags |
| 10k–100k, a real store | Managed Typesense/Meilisearch or OpenSearch with analyzers, synonyms, hybrid vectors, basic analytics |
| 100k+, search drives revenue | All of the above + learning to rank + judged query set + A/B tests |

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
- **Claude's own E2E assumption was wrong (S6):** the first Retry test expected an "iPhone 16" card, but the catalog has none, which is the brief's own missing-model case (D14). The test now asserts the missing-model notice, which also covers that notice in a real browser.
- **QA caught Claude's own check (final QA):** a new CI smoke line `grep -q … && test …` could never fail under `set -e` (bash ignores failures on the left of `&&`). Found by running it against the old server, which still returned the HTML 414; it was split into two lines and now fails as it should. In the same pass, an "empty `aria-live`" finding turned out to be a measurement taken before the 50 ms announce delay. It was re-checked rather than "fixed", and the real gap it hid (the notice wasn't announced) was fixed with a test.
