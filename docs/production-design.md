# Production-grade design (beyond the brief)

*Moved from the README to keep it short (D47).*
What this would become for a real store (50k–1M products, real traffic) without the brief's limits (4 h, one free container, no API keys, no chat). This project is the first stage of the same design in miniature: enrichment as data, a pure tested ranker, a relevance test set. What would change in this codebase to get there, and in what order: [ADR 0004](adr/0004-migration-to-production-search.md).

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
