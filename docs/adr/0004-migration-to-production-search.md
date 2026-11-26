# ADR 0004 — Migrating to a production search stack

**Status:** proposed (2026-11-27), not implemented: the path beyond the brief · **Relates to:** ADR 0001, 0002, 0003 · D2, D3, D5, D15, D17, D41 · README *Production-grade design*

## Context
The current design is sized for the brief: 1,000 read-only products, one free container, no API keys, ~4 h of work. Search is SQLite FTS5 plus our own Hebrew word handling (`QueryNormalizer`, D3/D31/D32), a PHP `Ranker` with hand-tuned `RankingWeights` (ADR 0003, D41) and offline Claude tags (ADR 0001).

At store scale (50k–1M products, live prices and stock, real traffic), this runs out of room:
- **Word handling is hand-written.** Prefix matching stands in for a stemmer, which caused QA F1 (`kettle` → "kettlebell", patched with `latinPrefix`, D41).
- **Ranking is hand-tuned** against 23 example queries; there are no click data or judged query sets.
- **The catalog is baked into the image.** A price change needs a deploy (ADR 0002).
- **There is no analytics, cache, rate limiting or meaning-based (vector) retrieval.**

The target architecture is described in the README section *Production-grade design (beyond the brief)*. This ADR records **what has to change in this codebase to get there, and in what order**.

## Options
1. **Keep the design and scale it in place:** Postgres full-text search (tsvector + pg_trgm) behind the current `ProductRepository`, keeping `QueryNormalizer` and `Ranker`. Cheapest, but it keeps the hand-written word handling and hand-tuned ranking, which are the real limits.
2. **Rewrite the search service from scratch on a new stack.** It's clean, but nothing works until all of it works. It throws away a tested HTTP layer, frontend and relevance suite, and there's no way to compare the old and new systems on the same queries.
3. **Gradual migration (strangler fig):** build the new search next to the old one behind a redesigned interface, gate it on the existing relevance suite, run it in shadow mode, then shift traffic with a flag.

## Decision
**Option 3**, if the project goes beyond the brief.

### What stays
| Part | Why |
|---|---|
| `src/Http/*`: API contract, one error shape, security headers, request log, `/health` | Clients don't depend on what is behind `/api/search` |
| `public/assets/*`: UI states, URL sync, race protection, RTL cards | Grows (filters, facets, "load more"); isn't replaced |
| `Product`, `Money`, `SearchQuery` | Pure domain values with no storage assumptions |
| `tests/Relevance/ExampleQueriesTest.php` | Becomes the **gate**: the new engine must pass every row before it takes traffic |
| `data/ENRICHMENT-GUIDE.md`, `bin/check-enrichment` | Become the spec and validator of the enrichment worker |
| `src/Services.php` (one composition root), Docker, CI | New classes are wired in at the same place |

### What changes
**1. The repository interface, the real redesign.** `ProductRepository::findCandidates(list<SearchTerm>)` receives *our* normalized Hebrew variants and leaves ranking to the PHP `Ranker`. It is shaped around FTS5. ADR 0002's note that "the Domain and Application layers don't change" holds only for option 1. With a real engine, word handling and ranking happen inside the engine, so the interface becomes something like:
```php
interface ProductSearch
{
    public function search(SearchRequest $request): SearchHits; // query, filters, page, sort
}
```
- `SearchProducts` shrinks to orchestration: understand the query → search → business rules → notice.
- `OpenSearchProductSearch` is added in Infrastructure.
- The SQLite implementation stays for tests, local development and as a fallback.

**2. Domain search logic moves into engine configuration.**
| Now | Becomes |
|---|---|
| `QueryNormalizer` (prefixes, plurals, final letters) | Hebrew morphological analyzer (HebMorph) + English stemmer in the index mapping |
| `Aliases`, `StopWords` | A synonyms file and stop-word list in the engine, edited without a deploy |
| `Ranker` + `RankingWeights` | Field weights in the engine query, then learning to rank; today's weights become model features |
| `Trigrams` typo fallback (D15) | The engine's fuzzy matching / spelling suggestions |
| `latinPrefix` (D41) | A separate low-weight edge-n-gram autocomplete field |

These classes stay in place, as the fallback implementation, until the new engine has passed the gate and taken full traffic.

**3. Data: from an image file to a database with an index pipeline** (supersedes ADR 0002 at that point).
- Postgres is the source of truth. The migrations in `migrations/` move to a standard tool (Doctrine Migrations / Phinx).
- `CatalogImporter` splits into a loader into Postgres and an **indexer** that reads changes (outbox table or CDC) and writes OpenSearch documents.
- `PriceGenerator` is removed: real prices and stock come from the store (D4 no longer applies).
- The DB is no longer built during `docker build`; credentials come from a secret manager.

**4. New components.**
- **Enrichment worker:** a queue of changed products → LLM tags (validated with the `check-enrichment` rules) + multilingual embeddings, with the model version stored.
- **Query understanding:** brand and category detection, filter extraction ("up to ₪500"), an LLM rewrite of long need-sentences, cached and with a timeout.
- **Redis:** cache for popular queries, and shared state for rate limiting.
- **Analytics events** from the UI (`search`, `click`, `add_to_cart`, with query and position) → a warehouse. They supply training data for learning to rank and dashboards of zero-result and no-click queries.
- **Per-stage monitoring:** latency, cache hit rate, enrichment failures.

**5. Hosting.** One free Render container becomes:
- the app (several instances behind a load balancer);
- managed Postgres, OpenSearch and Redis;
- a queue and a worker;
- infrastructure as code (Terraform), with separate staging and production.

### Order of work
| Phase | Work | Done when | Rollback | Rough effort |
|---|---|---|---|---|
| 1 | Postgres + indexer + OpenSearch with analyzers; `ProductSearch` interface with both implementations | All relevance rows pass on OpenSearch; in **shadow mode** (real queries, results logged and compared, never shown) the top-5 overlap is reviewed | Nothing is user-facing yet | 2–3 weeks |
| 2 | Shift traffic with a flag: 5 % → 50 % → 100 % | Error rate and p95 latency within targets at each step | Flip the flag back to SQLite | ~1 week |
| 3 | Analytics events + a judged query set (several hundred queries, HE/EN/mixed) + NDCG@10 | A baseline NDCG for the current ranking | No ranking change ships before this exists | ~2 weeks |
| 4 | Hybrid vectors (BM25 + kNN, Reciprocal Rank Fusion) + enrichment worker | NDCG improves on the judged set, and an A/B test wins on add-to-cart | Fall back to keyword-only | 2–3 weeks |
| 5 | Learning to rank, query understanding, optional "help me choose" panel (RAG over the shown products only, prices and stock from the DB) | Each one gated by NDCG and an A/B test | Feature flag per component | Ongoing |

## Consequences
- **Good:**
  - Users always have a working search, and every phase can be undone with a flag.
  - The relevance suite written for the brief becomes the acceptance test for the new engine.
  - Ranking moves from hand-tuning to measurement.
- **Cost:** more moving parts (DB, search cluster, cache, queue, worker) to run and pay for, which makes on-call and monitoring necessary. LLM enrichment and embeddings add model cost and need versioning.
- **During migration:** two search implementations are maintained at once, and must be compared on the same queries until the old one is removed.
- **Note on earlier ADRs:**
  - ADR 0002's "only the repository changes" is true for option 1, not for this path. Its decision stays right for the brief.
  - ADR 0001 (no AI at runtime) still holds for the critical path. LLMs run offline or asynchronously, behind a cache and a timeout.
