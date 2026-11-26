# ADR 0001 — Offline AI enrichment, no AI at runtime

**Status:** accepted (2026-11-26) · **Decision ref:** D2

## Context
The brief requires matching by meaning ("a gift for someone who loves cooking"), in Hebrew, English and mixed. The usual solution is embeddings (vectors) at query time. The site must be deployed for free and be easy to run.

## Options
1. Embedding API at query time (Voyage/Gemini) + pgvector: best recall, but an external dependency, an API key in the host, and latency/cost per query.
2. Local embedding model on the server: self-contained, but PHP can't run it natively (it would need a Python sidecar or an experimental FFI library), and ~0.5 GB of RAM doesn't fit free hosts.
3. **Claude writes HE+EN tags per product once (synonyms, cross-language spellings, plurals, use-cases, gift intents), committed as data; runtime is plain FTS5 search.**

## Decision
Option 3.

## Consequences
- One container, no secrets, < 50 ms search, deterministic results that tests can check.
- Recall depends on tag coverage; phrasings the tags don't anticipate can miss → mitigated by query normalization (D3) and the typo fallback (D15), documented as a known limitation.
- Upgrade path: add embeddings as a second candidate source feeding the same `Ranker` (hybrid), without changing the API.
