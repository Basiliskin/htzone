# QA report

Run 2026-11-26 on `main` @ `427073b`, local dev container and live https://htzone.onrender.com. Every result below comes from a real run in this session. The scripts are in the session scratchpad, so they're not committed.

## 1. Traceability

| Req | Covered by | Status |
|---|---|---|
| R1 search the catalog, fast | Relevance suite + p95 test; live timings §3 | ✅ |
| R2 name **and** need | Relevance rows 1–5, 11–14 | ✅ |
| R3 HE / EN / mixed | Rows 8–11; adversarial "אוזניות Bose" | ✅ |
| R4 meaning | Rows 4, 5, 14 (tags) | ✅ (tag-bound, documented) |
| R5 simple UI, RTL | `KernelTest` page tests; UX pass §5 | ✅ |
| R6 search field + results | E2E test 1 | ✅ |
| R7 image, name, price | E2E test 1; `render.test.js`; AC-2.1 | ✅ |
| R8 loading / empty / error | E2E tests 1–3; `state.test.js`; UX pass §5 | ✅ |
| R9 DB, persistent | `CatalogImporterTest`, `MigrationRunnerTest`, `RealCatalogImportTest` | ✅ |
| R10 deployed | Live E2E 3/3; `/health` live | ✅ |
| R11 GitHub + history | 14 commits, one per step; CI green on `427073b` | ✅ |
| R12 Claude use explained | README *Working with Claude*, SESSION-LOG | ✅ |
| R13 no secrets | grep of tree + history (§4) | ✅ |
| R14 image = direction only | design.md; manual | ✅ manual |
| R15 no signup/cart/… | manual | ✅ manual |
| AC-1.1–1.5 | Relevance rows 1, 6–10, 4–5 | ✅ |
| AC-1.6 p95 < 100 ms | `ExampleQueriesTest::p95` | ✅ |
| AC-2.1 `₪1,299` | `MoneyTest`, `render.test.js`, E2E 1 | ✅ |
| AC-2.2 no empty brand line | `render.test.js` | ✅ |
| AC-2.3 image fallback | UX pass (placehold.co blocked → `fallback.svg`, same size) | ✅ manual |
| AC-2.4 mixed-direction names | UX pass: computed `direction: rtl`; screenshot | ✅ manual |
| AC-2.5 ≤ 24, ranked | `RankerTest`, `SearchProductsTest` | ✅ |
| AC-2.6 stable prices | `PriceGeneratorTest` golden values | ✅ |
| AC-3.1 debounce, skeleton, no stale overwrite | `state.test.js` (race), E2E 1 | ✅ |
| AC-3.2 no results + chips | E2E 2 | ✅ |
| AC-3.3 error + Retry | E2E 3; UX pass (network abort) | ✅ |
| AC-3.4 `aria-live` | UX pass: "נמצאו 24 מוצרים" / "לא נמצאו מוצרים" | ✅ fixed F4: the notice is announced with the count |
| AC-3.5 empty page | UX pass `idle` | ✅ manual |
| AC-4.1–4.2 limits | `SearchQueryTest`, `state.test.js`, smoke §4 | ✅ 10k chars → JSON 414 `request_too_long` (F2 fixed) |
| AC-4.3 FTS/SQL chars | `SqliteProductRepositoryTest`, row 18, smoke §4 | ✅ local · live blocked by Render edge (documented) |
| AC-4.4 `<script>` | `KernelTest` (`<`), UX pass (no node injected, no dialog) | ✅ |
| AC-4.5 typo fallback | Rows 15–16 | ✅ |
| AC-4.6 missing model | Row 2, E2E 3 | ✅ |
| AC-4.7 Unicode | `QueryNormalizerTest`; smoke §4 (RLM, emoji, niqqud, NUL) | ✅ |
| AC-5.1 lang/dir, logical CSS | UX pass; design.md | ✅ |
| AC-5.2 1 / 4 columns, no h-scroll | UX pass: 360 → scrollWidth 360, list cards; 1280 → 4 × 280 px | ✅ manual |
| AC-5.3 `/`, `Esc`, focus visible | UX pass: `/` → `#q`; Esc → idle; every tab stop has a 3 px outline | ✅ manual |
| AC-5.4 AA contrast, reduced motion | `styles.css:206`; Lighthouse Accessibility **100** on the live URL (2026-11-26) | ✅ (F9 closed) |
| AC-5.5 `?q=` + history | `state.test.js`; UX pass URL | ✅ |
| AC-6.1–6.4 | Integration suite | ✅ |
| AC-7.1–7.3 | `KernelTest`; header dump §4 | ✅ HSTS added (F3), live after deploy |
| AC-7.4 log line | `KernelTest`, `JsonLoggerTest` | ✅ |
| AC-7.5–7.6 env, non-root, pinned | `.env.example`; clean-clone prod image `uid=33(www-data)`; `composer.lock`, `package-lock.json` | ✅ |
| AC-8.1 HTTPS + cold start in README | README line 5 | ✅ |
| AC-8.2 commits + CI green | `gh run list`: 3/3 jobs green on `427073b` | ✅ |
| AC-8.3 README sections | §8 | ✅ F8 fixed |
| AC-8.4 clean clone builds | §8 | ✅ |
| S1–S4 submission | §8 | ✅ |

## 2. Gates (real output)

| Gate | Result | Time |
|---|---|---|
| `composer check`: CS Fixer | `Found 0 of 74 files that can be fixed` | |
| PHPStan max | `[OK] No errors` | |
| PHPUnit (Unit + Integration + Relevance) | `OK (172 tests, 10540 assertions)` | 0.91 s (3.2 s total) |
| Relevance suite alone | `OK (21 tests, 52 assertions)` | 0.26 s |
| `bin/check-enrichment` | `1000 products valid … data issues reported: 31` | 0.15 s |
| `npm test` (JS) | 15 pass, 0 fail | 0.15 s |
| E2E local (dev) | 3 passed | 2.6 s |
| E2E live | 3 passed | 3.9 s |
| CI on `427073b` | checks ✅ · frontend ✅ · image + smoke + E2E ✅ | 2 m 12 s |

## 3. Relevance (local server time / live round trip)

Local times are measured from the client to the dev container (warm). Live times include the network to Frankfurt.

| Query | Top 5 | Local / live | |
|---|---|---|---|
| שואב אבק | P0209 P0201 P0210 P0208 P0207 | 59* / 184 ms | ✅ all vacuums |
| iPhone 16 | P0413 P0407 P0401 P0461 P0473 + missing-model note | 4 / 91 | ✅ |
| אייפון | P0413 P0407 P0401 P0461 P0473 | 2 / 91 | ✅ |
| מתנה למישהו שאוהב לבשל | P0087 P0017 P0047 P0034 P0052 | 10 / 108 | ✅ all kitchen |
| משהו שיעזור לנקות שערות של כלב מהספה | P0950 P0203 P0207 P0218 P0941 | 4 / 117 | ✅ |
| שואבי אבק | P0212 P0209 P0210 P0208 P0214 | 4 / 92 | ✅ (P0212 car vacuum first, still a vacuum) |
| לכלב | P0936 P0941 P0938 P0939 P0937 | 2 / 86 | ✅ |
| Dyson / דייסון | P0201 P0701 P0707 | 2 / 92–113 | ✅ |
| שואב Dyson | P0201 first | 4 / 142 | ✅ |
| robot vacuum | P0213 P0204 P0205 P0203 P0202 | 2 / 94 | ✅ |
| מכונת קפה | P0104 P0103 P0111 P0110 P0102 | 4 / 146 | ✅ |
| Samsung | 5 × Samsung | 2 / 81 | ✅ |
| מתנה לחובב קפה | P0142 P0196 P0116 P0121 P0118 | 5 / 88 | ✅ |
| מקרוגל | P0279 P0285 … + "האם התכוונת: מיקרוגל?" | 9 / 104 | ✅ |
| macbok | P0305 P0306 … + "macbook?" | 4 / 98 | ✅ |
| קשדגכ / xyzzy | 0 → no-results | 2 / 82–122 | ✅ |
| **Adversarial** | | | |
| typo: שואב אבקק | vacuums P0214 P0208 P0209 P0207 P0212 | 4 / 101 | ✅ prefix match absorbs the extra letter |
| plural + prefixes: והמכונות קפה | P0103 P0111 P0104 P0101 P0110 | 6 / 99 | ✅ ו+ה stripped, plural → מכונת |
| English-only brand: Nespresso | P0135 P0102 P0131 P0137 P0143 | 2 / 86 | ✅ |
| mixed: אוזניות Bose | P0415 P0533 P0426 P0419 P0416 | 3 / 82 | ✅ |
| gibberish: asdfqwer | 0 | 4 / 89 | ✅ |
| extra: מתנות לכלבים | dog products | 7 / 98 | ✅ |
| extra: kettle | P0162 **P0802 spinning bike, P0805 kettlebell** P0009 P0952 | 2 / 87 | 🟡 → ✅ after F1 fix: P0162 P0009 P0952 P0802 P0805 |
| extra: Air Fryer | P0002 P0001 **P0305 MacBook Air**, air purifiers | 3 / 94 | ✅ only 2 fryers exist; the rest match "air" alone (D42) |

\* first call after idle; repeats are 2–10 ms. Server-side times are in the log line and in the p95 test.

## 4. Security and robustness smoke (local = live unless noted)

| Case | Result | |
|---|---|---|
| empty / spaces / 1 char / zero-width only | 400 `query_too_short`, JSON | ✅ |
| no `q` / `q[]=x` | 400 `query_missing` | ✅ |
| 10,000 chars (Hebrew or Latin) | was Apache's HTML 414 → now `LimitRequestLine 2048` + JSON `{"error":{"code":"request_too_long"}}`, `no-store`, all security headers; 100 Hebrew chars still 200 | ✅ F2 fixed |
| `" * ( ) OR NEAR -- '`, `NEAR(a b) AND NOT ^x:y` | 200, searched as text | ✅ |
| `'; DROP TABLE …`, `" OR 1=1 --` | local 200 as text · **live 403 Render/Cloudflare page** | ✅ app / documented edge |
| `<script>alert(1)</script>`, `"><img onerror>` | API returns `<…`; browser: input shows text, 0 injected nodes, no dialog | ✅ |
| RLM/LRM/RLO marks, emoji, niqqud, NUL, invalid UTF-8 | 200; marks and niqqud stripped (same results as plain), NUL → space, bad bytes → `??` 0 results | ✅ |
| backend down (request aborted in the browser) | error panel + "נסו שוב" | ✅ |
| broken image (placehold.co blocked) | `fallback.svg` at the same size | ✅ |
| headers: page, API, JS, font, `/health`, 404 | one CSP (`script-src 'self'`, `frame-ancestors 'none'`), nosniff, referrer, permissions everywhere; cache: page/JS `no-cache`, API `max-age=60`, font 1 week, errors/health `no-store`; `Server: Apache` without version | ✅ · **no HSTS live** F3 |
| stack traces | none in any response; the 500 path is covered by `KernelTest` (thrown error + PHP warning → generic body, details only in the log) | ✅ |
| `/.env` | 403 | ✅ |
| `/var/catalog.sqlite`, `/../composer.json`, `/nope` | 200 page shell (not the file); soft 404 | 🟡 F5 |
| POST | 405 + `Allow: GET, HEAD` | ✅ |
| secrets | no keys or tokens in the tree, the history (`sk-`, `ghp_`, `AKIA`, private keys, `rnd_`) or the AI logs; `.env.example` only, no `.env` ever committed | ✅ |

## 5. UX pass (Playwright Chromium, live, 360×740 and 1280×900)

- 360 px: scrollWidth 360 in every state; list cards; the name `Air Fryer קומפקטי דיגיטלי` computes `direction: rtl`, with English first on the right. 1280 px: 4 columns of 280 px, centered card text.
- Keyboard: `/` focuses `#q`, `Esc` clears and returns to idle. Tab order: search button → chips. Every stop shows a 3 px solid outline.
- `aria-live` (filled 50 ms after the change, `app.js:40`): "נמצאו 24 מוצרים", "לא נמצאו מוצרים". With a notice (iPhone 16, מקרוגל) it only says the count (F4).
- States reached: idle, loading (E2E), results, empty, error, short (unit). No console errors and no CSP violations.

## 6. Architecture audit

- **Layers:** clean. `src/Domain` and `src/Application` import nothing from Http/Infrastructure and use no PDO, superglobals or output (grep). The only port is `ProductRepository` (`src/Domain/Catalog/ProductRepository.php:13`), with 2 real implementations and a fake, so it earns its place. The `Controller` interface (`src/Http/Controller.php:10`) has 3 implementations.
- **Thin controller:** `SearchController::handle` (`src/Http/Controller/SearchController.php:28-49`) is 20 lines: validate → use case → JSON.
- **Strictness:** `declare(strict_types=1)` in every PHP file under `src`, `tests`, `bin`, `public`, `templates`; PHPStan at max with no suppressions.
- **DRY:** the security headers are written twice, in `src/Http/Kernel.php:16-21` and `docker/apache.conf:20-24`. This is on purpose (Apache covers static files, PHP covers its own responses); a comment explains it and CI asserts exactly one CSP. Acceptable (F6).
- **Dependency direction:** `Kernel` uses the concrete `Infrastructure\Logging\JsonLogger` (`src/Http/Kernel.php:7`). Http is the outer layer, and adding an interface would be YAGNI. Acceptable.
- **Dead code / leftovers:** no TODO, `var_dump` or `console.log` in `src`, `public/assets`, `templates` or `bin`.
- **Production checklist (protocol §4):** all present. Missing: HSTS (F3) and a JSON body for Apache's 414 (F2). Rate limiting is documented as Won't.
- **CI:** Actions are pinned to `checkout@v4` / `setup-node@v4` / `upload-artifact@v4` / `setup-buildx@v3` / `build-push@v6`. The current releases are v7 / v7 / v7 / v4 / v7, and GitHub warns that Node 20 is deprecated (F7).

## 7. Findings and options

| # | Finding | Options | Recommendation |
|---|---|---|---|
| F1 | English prefix over-match: `kettle` → kettlebell, spinning bike; `Air Fryer` → MacBook Air at #3 | fix now (/developer: Latin whole-word bonus in `Ranker` + 2 relevance rows) / defer to README Known limitations / ignore | **defer + document**: off the brief's queries, and a ranking change risks the 21 green rows this close to submission |
| F2 | A 10k-character query gets Apache's HTML 414, not the JSON error shape | fix now (`ErrorDocument 414` JSON in `apache.conf`) / defer / ignore | **ignore + one README line**: the UI can't produce it |
| F3 | No `Strict-Transport-Security` live | fix now (one header in Kernel + apache.conf + tests) / defer / ignore | **fix now**: small, and visible in an interview header check |
| F4 | `aria-live` doesn't announce the missing-model or did-you-mean notice | fix now (`render.js` `announcement` appends `notice.message` + 1 JS test) / defer / ignore | **fix now**: ~5 lines, and AC-3.4 asks for it |
| F5 | Unknown paths (`/nope`, `/var/catalog.sqlite`) return the 200 page shell | fix now (Router → 404 for non-`/`) / defer / ignore | **ignore**: deliberate (`src/Http/Router.php:8-9`), no file is served |
| F6 | Security headers defined twice (Kernel + Apache) | / / ignore | **ignore**: documented, guarded by CI |
| F7 | CI Actions are on deprecated Node 20 majors | fix now (bump the 5 actions, verify CI green) / defer / ignore | **fix now**: one commit, and it removes a visible CI warning |
| F8 | README: leftover "_More examples added as work progresses._", no time-budget note (D12 asks to state the overrun), no link to this report | fix now | **fix now** |
| F9 | Lighthouse accessibility score never run (S5 open item) | user runs DevTools → Lighthouse on the live URL and adds the score / drop the claim from design.md | **user runs it**: 1 minute, can't be done headlessly here without downloading Lighthouse |

## 8. Submission readiness

- Live link: ✅ (`/health` 200, E2E 3/3 live).
- Repo: public, 14 commits, one per build step. Two messages start with a stray space (`025ca43`, `fb5035d`); cosmetic, history is pushed, ignore.
- README sections: Run & load ✅ · Key decisions ✅ · How tested + example queries ✅ · Known limitations ✅ · With more time ✅ · Working with Claude, with changed/rejected suggestions ✅ — F8 polish pending.
- `.env.example` ✅. Clean clone (`git clone` → `docker build --target prod` → run): `/health` 200, 1000 products, `אייפון` → P0413, runs as `www-data`. The dev path (`compose` + `composer install`) is exercised from a fresh checkout by CI on every push.
- `.claude/skills/interview/` is git-ignored and not tracked ✅.
- Uncommitted: `docs/ai-log/PROMPTS.md` (hook output) + this report.

## 9. Decisions and fixes applied (2026-11-26)

User: F2 should be fixed too, limited on the server. D1 (a) and D2 (a) were taken as accepted ("also"). D3 (Lighthouse) is still open.

| # | Outcome | Evidence |
|---|---|---|
| F1 | **fixed** after QA by /developer: D41 (English prefix-only match ×0.5), D42 (Air Fryer row asserts top 2) | relevance rows 20–21 + 3 Ranker tests; row 20 and its unit test red → green; mutations (no penalty / all scripts / whole words only) each fail one test; 24 of 25 English/mixed queries keep their top 5, only `kettle` changes |
| F2 | **fixed**: `docker/apache.conf` `LimitRequestLine 2048` + `ErrorDocument 414 /errors/414.json` (`public/errors/414.json`), JSON charset, `no-store` (D39) | prod and dev image: 3000-char query → `414 {"error":{"code":"request_too_long",…}}`; 100 Hebrew chars → 200. New CI smoke lines fail against the old HTML 414 (checked) |
| F3 | **fixed**: `Strict-Transport-Security: max-age=31536000` in `src/Http/Kernel.php` + Apache (D40) | `KernelTest` red (5 failures) → green; header on API and static files of the rebuilt prod image; CI smoke asserts it |
| F4 | **fixed**: `render.js` `announcement` appends the notice message | new `render.test.js` case red → green; browser: "נמצאו 6 מוצרים. לא נמצא דגם מדויק, הנה המוצרים הקרובים", "נמצאו 5 מוצרים. האם התכוונת: מיקרוגל?" |
| F5, F6 | ignored (deliberate, documented) | |
| F7 | **fixed**: checkout/setup-node/upload-artifact v7, setup-buildx v4, build-push v7 (none of the removed inputs are used) | CI run 45819604339 on `6bd7aba`: all 3 jobs green, no Node 20 warning |
| F8 | **fixed**: placeholder removed, time note, QA line in *How it was tested*, a QA example in *Working with Claude*, 414 + HSTS in the API/defaults lines | README |
| F9 | **closed**: the user ran Lighthouse Accessibility on https://htzone.onrender.com (Incognito): **100**. Recorded in the README *How it was tested* | 2026-11-26 |

Re-run after the fixes: see SESSION-LOG (QA entry).

**Live after deploy (`6bd7aba`):** HSTS on the page, API and static files; a 3000-char query → `HTTP/2 414`, `application/json; charset=utf-8`, `no-store`, `request_too_long`; Render deployed within ~15 s of CI finishing; live E2E 3 passed (4.0 s); live `aria-live` reads the missing-model and did-you-mean notices.
