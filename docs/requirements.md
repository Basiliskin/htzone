# Requirements — htzone smart catalog search

Source: `docs/assignment.md` (wins on conflict). Settled decisions: `docs/decisions.md`. Terms: `GLOSSARY.md`.

## 1. Requirements checklist (from the brief)

### Product
- [ ] R1 Small website where a Visitor searches the provided Catalog and gets relevant Results quickly.
- [ ] R2 Finds Products by exact name **and** by described need ("something that helps clean dog hair off the sofa").
- [ ] R3 Hebrew, English, and mixed Hebrew+English Queries.
- [ ] R4 Matches by the meaning of the request, not only shared words.
- [ ] R5 Simple, clear, comfortable web UI with correct Hebrew / RTL support.
- [ ] R6 Search field + display of relevant Products from the provided Catalog.
- [ ] R7 Each Result shows image, product name and price.
- [ ] R8 Loading, no-results and error states.
- [ ] R9 Connected to a DB; Catalog data stored persistently.
- [ ] R10 Deployed and publicly reachable; testable without running anything locally.
- [ ] R11 Code managed in a GitHub repo, with working history.
- [ ] R12 Claude used during development; we can explain how and how its output was verified.
- [ ] R13 No API keys or passwords in code or repo.
- [ ] R14 The reference image is direction only (no pixel copy).
- [ ] R15 No signup, cart, payment, admin or chat.

### Submission
- [ ] S1 Link to the live site.
- [ ] S2 Link to the GitHub repo with work history.
- [ ] S3 Short README with: run + catalog-load instructions · key decisions and reasons · how it was tested with example searches · known limitations and future improvements · how Claude was used, incl. a suggestion that was changed or rejected.
- [ ] S4 Open/unfinished parts listed in the submission.

### What they will check (quality bar)
- [ ] Q1 Result relevance · Q2 search speed · Q3 UX quality · Q4 work with data · Q5 working end-to-end product · Q6 focused and polished over many extras.
- [ ] Q7 Interview: they will use the live site, walk through the code, discuss decisions and may ask for a small change made with Claude, explained and verified.

## 2. Data facts (profiled from `assets/catalog_no_prices.csv`)
- 1000 Products, unique ids `P0001…P1000`, UTF-8 with BOM. Columns: `id, name, brand, category, subcategory, description, attributes, image_url`.
- **No price column** → prices are generated (D4).
- 52 Products have an empty `brand`. 13 categories (100 each for 9 of them, pets only 15), 80 subcategories, 411 brands.
- Names: 859 Hebrew-only, 137 mixed, 4 English-only. Brands are always Latin script, even when the name spells them in Hebrew (P0201 `דייסון` / `Dyson`).
- `attributes` is a ` | `-separated list of `key: value`.
- All `image_url` values are `placehold.co` text placeholders.
- **No Product mentions "iPhone"/"אייפון" or "16"**. Apple phones are generic: P0401, P0407, P0413 (brand Apple, subcategory סמארטפונים) → D14.

## 3. User stories and acceptance criteria

The actor is the **Visitor** (anonymous). AC ids are referenced by tests (`AC-x.y`).

### US1 — Search by name or need
*As a Visitor I want to type what I'm looking for, in Hebrew or English or both, so that I find the right Product whether I know its name or only my need.*
- **AC-1.1** Given the Catalog is loaded, when I search a Product-type word ("שואב אבק"), then the top 5 Results are all of that type.
- **AC-1.2** Given a brand in either script ("Dyson" / "דייסון"), when I search it, then that brand's Products rank first.
- **AC-1.3** Given a mixed Query ("שואב Dyson"), when I search, then the Product matching both words ranks first.
- **AC-1.4** Given a need described in a sentence (example-query table rows 4–5), when I search, then the top 5 include the expected Products.
- **AC-1.5** Given plural or prefixed Hebrew forms ("שואבי אבק", "לכלב", "מהספה"), when I search, then the Results match the base form.
- **AC-1.6** Given any example-query table row, the server answers in **< 100 ms p95** locally (excluding network) and logs the duration.

### US2 — See useful Results
*As a Visitor I want each Result to show an image, name and price so that I can compare at a glance.*
- **AC-2.1** Each Result card shows image, name and a price formatted as `₪1,299` (whole shekels, agorot hidden when zero).
- **AC-2.2** When a Product has no brand, the card shows no brand line (no empty label or "undefined").
- **AC-2.3** When an image fails to load, a local fallback image is shown and the layout does not shift.
- **AC-2.4** Mixed-direction names display in the correct order and are right-aligned, including names that *start* with English (`Air Fryer קומפקטי דיגיטלי`). Names inherit the page's `dir="rtl"`; they must **not** use `dir="auto"`, which would flip names starting with Latin letters to LTR. Brand (Latin) is isolated with `<bdi>`.
- **AC-2.5** At most 24 Results, ordered by relevance (exact brand/model > name > tags/category > description).
- **AC-2.6** Prices are stable: the same Product shows the same price after re-import and in every environment.

### US3 — Know what's happening
*As a Visitor I want clear feedback while searching so that I'm never confused.*
- **AC-3.1** Loading: after typing stops for 250 ms, skeleton cards show until the response arrives. A newer Query's response is never overwritten by an older one.
- **AC-3.2** No results: shows a friendly Hebrew message plus clickable suggested Queries.
- **AC-3.3** Error: when the API fails or the network is down, shows an error message with a **Retry** button. Retry re-runs the same Query.
- **AC-3.4** The result count / state change is announced to screen readers (`aria-live`).
- **AC-3.5** Empty page (no Query): shows the heading and suggested Queries, and no Results grid.

### US4 — Forgiving input
*As a Visitor I want the search to cope with messy input so that I still get results.*
- **AC-4.1** A Query that is empty or whitespace-only after trimming is not sent; the empty state stays.
- **AC-4.2** Shorter than 2 characters → not searched, with a hint "הקלידו לפחות 2 תווים" ("type at least 2 characters"). Longer than 100 → cut to 100 characters, never an error.
- **AC-4.3** Full-text special characters and SQL fragments (`" * ( ) : ^ - OR NEAR '; DROP`) never cause an error. They're treated as plain text.
- **AC-4.4** `<script>alert(1)</script>` as a Query is shown as text and never executed (not in the UI, not in the API response).
- **AC-4.5** **Typo fallback (D15):** when the main search returns 0 Results, a close-spelling (trigram) search runs. If it finds Results, they're shown under "האם התכוונת…" ("did you mean…"). Example: "מקרוגל" → מיקרוגל (P0279, P0285).
- **AC-4.6** **Missing model (D14):** when a Query contains a model number or version token (e.g. `16`) that no Result contains, but other words match, show a note "לא נמצא דגם מדויק, הנה המוצרים הקרובים" ("no exact model found, here are the closest products") above the Results.
- **AC-4.7** Unicode edge cases (niqqud, RTL marks, emoji) don't cause errors. Niqqud is ignored when matching.

### US5 — Comfortable on any device, in Hebrew
*As a Visitor I want the page to feel modern and work on my phone so that I can search anywhere.*
- **AC-5.1** `<html lang="he" dir="rtl">`. Layout uses logical CSS properties, and the search icon and clear (✕) button are on the correct sides.
- **AC-5.2** Columns adapt to the screen: 1 at 360 px, 2 on tablets, 4 on desktop. There's no horizontal scroll at 360 px.
- **AC-5.3** Keyboard: `/` focuses the search box, `Esc` clears it, Tab order is logical, and focus is always visible.
- **AC-5.4** Contrast meets AA. `prefers-reduced-motion` turns off the skeleton shimmer.
- **AC-5.5** The Query is kept in the URL (`?q=`). Opening that URL runs the search, and Back/Forward restore earlier Queries.

### US6 — Persistent, reproducible Catalog
*As the developer/reviewer I want the Catalog loaded into a DB by a repeatable command so that the data is persistent and anyone can rebuild it.*
- **AC-6.1** Migrations run in order and are tracked. Running them twice changes nothing.
- **AC-6.2** Import loads all 1000 CSV rows plus enrichment plus generated prices. It fails loudly with the row/id if a row is invalid. Re-running gives the same DB contents.
- **AC-6.3** The import rejects enrichment that references unknown ids or misses Catalog ids.
- **AC-6.4** At runtime the DB is opened read-only.

### US7 — Production-grade service
*As an operator I want the service to be safe and observable so that it can run unattended.*
- **AC-7.1** `GET /health` → 200 `{"status":"ok"}` once the DB is reachable, otherwise 503.
- **AC-7.2** `GET /api/search?q=` → 200 JSON `{query, total, results[{id,name,brand,price:{amount,currency,formatted},image}], notice?}`. Invalid input → 400 with the error shape `{error:{code,message}}`. Unexpected failure → 500 with the same shape and no stack trace.
- **AC-7.3** Headers on every response: CSP (`default-src 'self'; img-src 'self' https://placehold.co`; no inline scripts), `X-Content-Type-Options: nosniff`, `Referrer-Policy`. Search responses have short cache headers; static files have long ones.
- **AC-7.4** Each request logs one JSON line to stdout: route, Query (cut to 100 characters), result count, duration in ms, status.
- **AC-7.5** Config comes from env, and `.env.example` is committed. No secret appears in the repo or its history.
- **AC-7.6** The container runs as non-root. Dependency versions are pinned (`composer.lock`).

### US9 — Narrow the Results by category and price *(added after QA, D43–D46)*
*As a Visitor I want to narrow a broad search to one category and a budget so that I can compare only what fits.*
- **AC-9.1** Results that span 2+ categories show category chips "‹name› (N)" and "הכול"; choosing one shows exactly N products (up to 24), in the same order as before.
- **AC-9.2** Two price fields (whole shekels, inclusive) narrow the Results; their placeholders show the price range of the current matches.
- **AC-9.3** The filters are in the URL (`cat`, `min`, `max`); Back undoes the last filter change; a new query clears the category and keeps the price.
- **AC-9.4** A filter that leaves nothing shows "אין מוצרים בסינון הזה" with "נקו סינון", not the no-results panel.
- **AC-9.5** Chips and filtered lists use only strong matches (≥ 50 % of the best score); without a filter the Results are unchanged. Invalid values → 400 `invalid_filter`.

### US8 — Deliverables
*As a reviewer I want to open a link, read the repo and README, and understand the decisions.*
- **AC-8.1** The live URL works over HTTPS without local setup. The README states the cold-start delay.
- **AC-8.2** The GitHub repo has small, meaningful commits per build step, and CI is green on `main`.
- **AC-8.3** The README has every section listed in S3. The "Working with Claude" section cites concrete examples from `docs/ai-log/SESSION-LOG.md`.
- **AC-8.4** A clean clone plus the documented commands builds and runs locally.

## 4. Example-query table (relevance test set)

"Pass" is what the relevance test checks against the real imported Catalog. The 🅱 rows are the brief's examples.

| # | Query | Expected | Why |
|---|---|---|---|
| 1 🅱 | שואב אבק | top 5 ⊂ subcategory `שואבי אבק ורובוטים` (P0201–P0215); vacuum *bags* (P0077, P0678) not in the top 5 | Product-type phrase. Name match beats description. |
| 2 🅱 | iPhone 16 | top 3 = {P0401, P0413, P0407}; `notice` = missing model | No iPhone in the Catalog (D14). Tags plus a model note. |
| 3 🅱 | אייפון | top 3 = {P0401, P0413, P0407}; no notice | Hebrew spelling of an English name (alias + tags). |
| 4 🅱 | מתנה למישהו שאוהב לבשל | ≥ 4 of the top 5 in category `מטבח ובישול` | Need/meaning (gift tags). Stop-words such as "למישהו" and "ש" don't hurt. |
| 5 🅱 | משהו שיעזור לנקות שערות של כלב מהספה | top 5 includes P0950 and at least one of {P0207, P0225} | Need with prefixes and plurals (מהספה, שערות). |
| 6 | שואבי אבק | same set as row 1 | Plural / construct form. |
| 7 | לכלב | top 5 ⊂ P0936–P0941 | Attached prefix ל. |
| 8 | Dyson | top 3 = {P0201, P0701, P0707} | Brand, Latin script. |
| 9 | דייסון | P0201 first; P0701 and P0707 in the top 5 | Brand, Hebrew script (name plus alias). |
| 10 | שואב Dyson | P0201 first | Mixed Hebrew and English. |
| 11 | robot vacuum | top 5 ⊂ {P0202, P0203, P0204, P0205, P0213} | English only, against a Hebrew Catalog (EN tags). |
| 12 | מכונת קפה | top 3 ⊂ {P0101, P0102, P0103, P0104, P0108, P0109, P0111, P0115}; P0129 (cleaning brush) not first | Common two-word product type. |
| 13 | Samsung | all top 5 have brand Samsung | Brand field match. |
| 14 | מתנה לחובב קפה | ≥ 4 of the top 5 in category `קפה, משקאות וגורמה` | The reference image's scenario. |
| 15 | מקרוגל | 0 main results → typo fallback; top 2 = {P0279, P0285}; `notice` = did_you_mean "מיקרוגל" | Missing-letter Hebrew spelling (D15). Replaced "שואב אבכ": שואב already matches, and trigrams can't fix the 3-letter word אבכ. |
| 16 | macbok | P0305 and P0306 in the top 3 via typo fallback | English typo (D15). |
| 17 | קשדגכ / xyzzy | 0 Results → no-results state with suggestions | Nonsense. |
| 18 | `" OR 1=1 --` | 200, no error; treated as text | Robustness (AC-4.3). |
| 19 | iPhone 16 (qualifier check) | no product in the top 5 matched *only* on "16" (e.g. P0027, P0301, P0306) | Numbers alone must not pull in products (ADR 0003). |
| 20 | kettle | top 2 = {P0162, P0009}; P0802 (brand Kettler) and P0805 (kettlebell) not in the top 3 | English whole word beats a word it only starts (QA F1, D41). |
| 21 | Air Fryer | top 2 = {P0001, P0002} | Both words beat one: the catalog has only two air fryers, so later results matching "air" alone are expected (D42). |
| 22 | להתאמן בבית | top 5 ⊂ subcategory `כושר ביתי` | Need as a verb; tags were nouns ("כושר ביתי"). Meaning pass, `docs/data.md` §6. |
| 23 | לקרר את הבית בקיץ | top 5 ⊂ `אקלים ומיזוג`, no heaters (P0233, P0234, P0237, P0238, P0241) | Verb need; nothing said "לקרר" before. |
| 24 | לשמור על אוכל טרי | top 5 ⊂ `אחסון ושימור מזון` | Was matching dining furniture through "פינת אוכל". |
| 25 | ציוד לטיול | ≥ 4 of the top 5 in `קמפינג וטיולים` / `בקבוקים ותרמוסים`; no stroller P0901 | "טיול" also starts "טיולון" (stroller). |
| 26 | gift for a runner | ≥ 4 of the top 5 running-related (bottles, watches, earbuds, home fitness); no runner rug P0624 | English need; "runner" is also a rug. |

Exact product ids in rows 12 and 14 are pinned by `/data` after enrichment. If a row can't pass, it's changed only by an explicit decision, never quietly.

## 5. Scope (MoSCoW, D16)
- **Must:** R1–R15, S1–S4. Specifically:
  - search, plus results with image, name and price
  - loading, no-results and error states
  - Hebrew, English and mixed queries; meaning via tags; plurals and prefixes
  - right-to-left, mobile-first layout
  - SQLite with migrations
  - live site and repo
  - README with every section
  - production checklist (US7)
  - unit, integration and relevance tests, plus CI
- **Should:**
  - missing-model note (AC-4.6), typo fallback (AC-4.5)
  - URL sync and keyboard shortcuts (AC-5.3, AC-5.5)
  - suggested Queries
  - one Playwright E2E test
- **Could:** brand/category on cards · highlight matched words · dark mode. *Cut first if short on time, then Playwright.*
- **Won't** (each goes to README *With more time*):
  - ~~filters~~ *built after QA as US9 (D43–D46)*; sorting and pagination — not needed to judge relevance; 24 results is enough for the brief's Queries
  - embeddings / semantic vectors — runtime dependency; tags cover the brief (D2)
  - rate limiting — needs shared state; free host restarts reset it
  - search analytics / monitoring — no backend to send it to in scope
  - product detail page — the brief focuses on the search experience
  - signup, cart, payment, admin, chat — excluded by the brief (R15)

## 6. Defaults (stated, not asked)
- Query: trimmed, at least 2 and at most 100 characters (longer is cut, not rejected), niqqud removed.
- Products with no brand show no brand line.
- Images stay as `placehold.co` URLs with a local fallback. The CSP allows only that host.
- Top 24 Results, no pagination.
