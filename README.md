# htzone — smart catalog search

Search a 1000-product catalog in Hebrew, English or both — by product name or by describing a need.

- **Live:** https://htzone.onrender.com (Render free tier; try one of the example chips). After 15 min idle the first request takes about 12 s to wake the service; a keep-awake ping is on during the review.
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
- The production image builds the same database during `docker build` and ships it read-only. All commands: [`docs/stack.md`](docs/stack.md).
- **Deploy:** `render.yaml` (Render Blueprint, the Dockerfile's `prod` stage). A push to `main` deploys only after CI is green, so a failing test never reaches the live site.
- **API** ([contract](docs/architecture.md)): `GET /api/search?q=…[&cat=&min=&max=]` → `{query,total,results[],notice,facets}`; `GET /health`. Errors are always `{"error":{"code","message"}}`, never a stack trace. Config from env only (`.env.example`); no secrets.

## Key decisions
Full list with reasons: [`docs/decisions.md`](docs/decisions.md).
- **No AI at runtime** (D2): Claude wrote Hebrew/English tags once (synonyms, spellings, needs such as "מתנה למי שאוהב לבשל"); they are committed and validated. The live search is plain full-text, so it has no API key, no extra latency and no cost per query.
- **Hebrew handled in the query** (D3): up to two prefix letters (ו/ה/ב/ל/מ/ש/כ), plurals and final letters become variants, matched as word prefixes. It needs no Hebrew stemmer and covers the brief's examples.
- **SQLite FTS5 built into the image** (D5): one container, no DB password. A pure PHP ranker on top of it (share of query words found first, then brand and name matches; description counts least), with every weight in one class (`RankingWeights`), tuned against the relevance tests.
- **Prices are generated** (D4): the CSV has none, so each price is derived from a hash of the id within a per-subcategory range. The same product always gets the same price.
- **Honest answers to the brief's hard cases:** the catalog has no iPhone 16, so the Apple phones come back with a "no exact model" note (D14). A typo search runs only when nothing matches (D15).
- **No framework** (D19): a hand-written HTTP layer of about 250 lines, so every line can be explained. Light DDD (D7): Domain / Application / Infrastructure / Http.
- **Plain ES modules, no build step** (D35): page state is `body[data-state]`, and CSS alone shows the right section.
- **Category + price filter** (D43–D46, added after QA): filtered on the server after ranking, so the order never changes. Chips count only strong matches (≥ 50 % of the best score); without a filter the results are exactly as before.
- **Time:** planned at 5.5–6 h instead of ~4 (D12), spent on tests, relevance and hardening.

## How it was tested
Example searches on the live site (the first five are the brief's):

| Search | What comes back |
|---|---|
| `שואב אבק` | 24 vacuums: Samsung, Dyson, LG stick vacuums first; vacuum *bags* kept out of the top 5 |
| `iPhone 16` | the 3 Apple phones + "לא נמצא דגם מדויק, הנה המוצרים הקרובים" (the catalog has no iPhone 16) |
| `אייפון` | the same 3 Apple phones, no notice (Hebrew spelling → `iphone` alias) |
| `מתנה למישהו שאוהב לבשל` | kitchen gifts (microplane grater, waffle maker, cupcake mould …), ≥ 4 of the top 5 from `מטבח ובישול` |
| `משהו שיעזור לנקות שערות של כלב מהספה` | pet-hair brush, robot vacuum, handheld vacuum |
| `מקרוגל` (typo) | microwaves + "האם התכוונת: מיקרוגל?" |
| `להתאמן בבית` · `gift for a runner` | home-fitness gear · running bottle bag, sport bottle, running watch |
| `kettle` | the two electric kettles first, not "Kettler" or a kettlebell (D41) |
| `מתנה` + chip `מטבח ובישול` + `עד ₪400` | 4 kitchen gifts, ₪199–₪379 |

- **Relevance:** the searches above and 16 more ([`docs/requirements.md` §4](docs/requirements.md)) run against the real catalog in every CI run. When a row failed, ranking or data was fixed; the test was never relaxed.
- **Unit, integration, HTTP:**
  - every normalizer and ranking rule;
  - the repository against a real DB, with hostile input (`" OR 1=1 --`, `NEAR(`);
  - every API error, header and cache rule.
  Tests were mutation-checked: each rule was switched off to confirm a test fails.
- **Frontend and E2E:**
  - JS unit tests for state, races and URL/history;
  - 5 Playwright flows in a real browser against the real server, run in CI against the production image and once against the live site.
- **QA and accessibility:**
  - a full QA pass, with a security smoke and a browser pass at 360 px and desktop ([`docs/qa-report.md`](docs/qa-report.md));
  - Lighthouse Accessibility **100** on the live site.

Details: [`docs/testing.md`](docs/testing.md).

## Known limitations
- Prices are synthetic (per subcategory range), and images are text placeholders from the CSV. The CSV's 31 data problems are reported, not edited (`docs/data.md` §2).
- Meaning-based search depends on tag coverage. A pass with 20 new need-queries found verbs ("להתאמן בבית") missing from noun tags; 308 tags fixed 6 misses (19/20 good now), but other phrasings can still miss.
- `Air Fryer` shows the only two air fryers first, then products matching "air" alone (MacBook Air), because sentence queries need partial matches (D42).
- Filters are single-category with no sorting; the unfiltered list can still end with weak matches (D46).
- No pagination (24 results max) and no rate limiting. Without JavaScript the page is a working form but shows no results (D35).
- Free hosting: cold start after 15 min idle. Render's edge blocks SQL-looking queries (`" OR 1=1 --`) with its own 403 before they reach the app, which itself treats them as plain text.

## With more time
- Embeddings (hybrid vector + keyword search) for phrasings the tags don't cover.
- Sorting, pagination, multi-select filters; the strong-match cut for unfiltered lists, judged as its own relevance change.
- Search analytics (zero-result and no-click queries), monitoring, rate limiting with shared storage.
- Server-rendered results for no-JS visitors; a product page; dark mode once there are real photos.
- For a real store (50k–1M products): [`docs/production-design.md`](docs/production-design.md), and the migration plan in [ADR 0004](docs/adr/0004-migration-to-production-search.md).

## Working with Claude
Built with Claude Code using one skill per role (product, architect, designer, data, developer, devops, QA). Each skill proposes 2–3 options and waits for my decision, and every answer is logged in [`docs/ai-log/SESSION-LOG.md`](docs/ai-log/SESSION-LOG.md). The product tags were written by 10 parallel Claude agents from one guide and verified by a script, the relevance tests and a human spot-check.
- **Rejected:** Claude first proposed runtime embeddings through an external API + pgvector. I replaced them with offline tags, so the deploy has no runtime dependency or secret.
- **Changed after a prototype screenshot:** Claude's own spec put `dir="auto"` on product names. The screenshot showed it flipping names that start with English to left-to-right, so names now inherit RTL.
- **Fixed in the data, not the code:** for the brief's "מתנה למישהו שאוהב לבשל", Claude's tags said "מתנה לחובבי בישול", which never matches the verb "לבשל", so coffee sets ranked first. The relevance test caught it, and the fix was a need tag on 72 cooking products, not a special case in code.
- **Green tests, wrong server:** the Kernel tests passed, but `curl` showed every response carried each security header **twice** (Apache added copies next to PHP's). Apache now skips PHP responses, and CI checks for exactly one CSP header.
- **A test changed an approved design:** the filter design as approved counted chips over all candidates. A new test showed "טלוויזיה, גיימינג ובידור (25)" as the top chip for `שואב אבק`. Claude measured the scores and asked again instead of silently changing the ranking (D46).

All 15 examples: [`docs/ai-log/claude-examples.md`](docs/ai-log/claude-examples.md).
