# ADR 0003 — Two-stage search: FTS5 candidates + pure-PHP Ranker

**Status:** accepted (2026-11-26) · **Decision ref:** D17

## Context
Queries range from a brand ("Dyson") to full sentences ("משהו שיעזור לנקות שערות של כלב מהספה"). Requiring every word to match returns nothing for sentences. BM25 alone can't express "brand first", "more query words matched wins" or "a number alone (16) must not pull in products".

## Decision
1. Normalize the query into terms with variants (stop-words, aliases, Hebrew prefixes/plurals).
2. FTS5 `MATCH` with terms joined by OR, ordered by weighted bm25 → top 100 candidates.
3. Domain `Ranker` re-scores the candidates: coverage, exact form, brand, name, qualifier rules, deterministic ties → top 24.

## Consequences
- All ranking knobs live in one class (`RankingWeights`), unit-tested with hand-made candidates and tuned against the relevance suite.
- The DB stays a candidate generator → swapping it (Postgres, vectors) keeps the ranking.
- Slight extra CPU in PHP (≤ 100 rows) — negligible at this size.
