# htzone — smart catalog search

Search a 1000-product catalog in Hebrew, English or both — by product name or by describing a need.

- **Live:** _TBD (Render)_ — free tier: the first load after a quiet period may take ~30–50 s.
- **Stack:** PHP 8.3 (no framework) · SQLite + FTS5 · vanilla JS/CSS (RTL) · Docker · GitHub Actions.

## Run & load catalog
_TBD in S0/S1 — see `docs/stack.md`._

## Key decisions
Full list with reasons: [`docs/decisions.md`](docs/decisions.md).
- No AI at runtime: Claude-written HE/EN tags are created once and committed; the live search is plain full-text (D2).
- Hebrew plurals/prefixes handled by query normalization + prefix matching (D3).
- Prices are **generated** (fixed seed, per-category ranges) because the CSV has none (D4).
- SQLite built into the image: one container, no DB password (D5).
- The catalog has no iPhone 16 — Apple phones are returned with a "no exact model" note (D14).
- Typo fallback only when nothing matches (D15).

## How it was tested
_TBD — example queries: [`docs/requirements.md` §4](docs/requirements.md)._

## Known limitations
- Prices are synthetic.
- Images are text placeholders from the provided CSV.
- Meaning-based search depends on tag coverage; unusual phrasings not covered by tags can miss.

## With more time
- Filters/sorting and pagination.
- Embeddings (hybrid vector + keyword search) for phrasings the tags don't cover.
- Rate limiting with shared storage; search analytics and monitoring.
- Product detail page.

## Working with Claude
Process log: [`docs/ai-log/SESSION-LOG.md`](docs/ai-log/SESSION-LOG.md) (prompts auto-captured in `docs/ai-log/PROMPTS.md`).
- **Rejected:** Claude first proposed runtime embeddings via an external API + pgvector; replaced with offline enrichment so the deploy has no runtime dependency or secret.
- _More examples added as work progresses._
