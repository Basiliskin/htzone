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
```
The production image builds the same database during `docker build` and ships it read-only. All commands: [`docs/stack.md`](docs/stack.md).

## Key decisions
Full list with reasons: [`docs/decisions.md`](docs/decisions.md).
- No AI at runtime: Claude-written HE/EN tags are created once and committed; the live search is plain full-text (D2).
- Hebrew plurals/prefixes handled by query normalization + prefix matching (D3).
- Prices are **generated** (fixed seed, per-category ranges) because the CSV has none (D4).
- SQLite built into the image: one container, no DB password (D5).
- The catalog has no iPhone 16 — Apple phones are returned with a "no exact model" note (D14).
- Typo fallback only when nothing matches (D15).

## How it was tested
- **Unit (domain search logic):** a table-driven test of every normalizer rule (prefixes, plurals, final letters, aliases, niqqud, stop-words, qualifiers) and one hand-made test per ranking rule. Each ranking test was confirmed to **fail** when its rule is switched off (mutation check, SESSION-LOG S2).
- Relevance tests against the real catalog, from the example queries in [`docs/requirements.md` §4](docs/requirements.md), arrive in S3.

## Known limitations
- Prices are synthetic: generated per subcategory range from a hash of the id (`docs/data.md` §3), so within a subcategory they don't reflect the specific model.
- The source CSV has 31 data problems (typos, names that don't match the product); they are reported, not edited, and tags carry the correct spelling (`docs/data.md` §2).
- Images are text placeholders from the provided CSV.
- Meaning-based search depends on tag coverage; unusual phrasings not covered by tags can miss.

## With more time
- Filters/sorting and pagination.
- Embeddings (hybrid vector + keyword search) for phrasings the tags don't cover.
- Rate limiting with shared storage; search analytics and monitoring.
- Product detail page.
- Dark mode (the placeholder images are light; it needs real product photos first).

## Working with Claude
Process log: [`docs/ai-log/SESSION-LOG.md`](docs/ai-log/SESSION-LOG.md) (prompts auto-captured in `docs/ai-log/PROMPTS.md`).
- **Rejected:** Claude first proposed runtime embeddings via an external API + pgvector; replaced with offline enrichment so the deploy has no runtime dependency or secret.
- **Self-correction caught in prototyping:** Claude's own requirement said to use `dir="auto"` on product names; a screenshot of the prototype showed it flips names starting with English to left-to-right. The requirement and design were changed so names inherit RTL.
- **Tags written by 10 parallel Claude agents** from one committed guide (`data/ENRICHMENT-GUIDE.md`), verified by a check script, example-query coverage and a human spot-check (`docs/data.md` §5–6).
- **Rejected in review:** Claude-written tags that implied health claims the products don't make ("קורונה" on an oximeter, "אנטי אייג׳ינג" on an LED mask) and a tag that would make vacuum bags match "שואב אבק".
- **Self-correction caught by tests:** Claude's planned query cleanup deleted the geresh (דולצ׳ה → דולצה), but SQLite indexes the catalog by splitting at it, so the cleaned query could never match. Found by checking the index's own word list; the query now splits the same way.
- _More examples added as work progresses._
