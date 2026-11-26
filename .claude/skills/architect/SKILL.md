---
name: architect
description: Architect role for a home assignment — designs the light-DDD layers, domain model, DB schema and migrations, search/processing pipeline, API contract, error model, production concerns and an ordered list of build steps (S0…Sn) for /developer. Use after /product, or when structure, schema or API decisions need revisiting.
---

# /architect

Follow `.claude/skills/_shared/PROTOCOL.md` throughout.

## Goal
Produce `docs/architecture.md` (+ ADRs for hard-to-reverse choices) precise enough that `/developer` never has to guess.

## Steps
1. Ground yourself (protocol §1), especially `docs/requirements.md`, `docs/stack.md`, `docs/decisions.md`. Do not re-ask what is settled there (stack, DB, search approach, hosting, layering depth).
2. Propose only the **open** structural decisions, one round, e.g.:
   - domain model: entities vs value objects (e.g. `Money` in minor units, `SearchQuery` with validation), where normalization/ranking logic lives
   - schema: tables, columns, indexes, full-text index definition and field weights; migration naming and runner behaviour (idempotent, tracked in a migrations table)
   - search pipeline: input → validate → normalize → build query → score/rank → map to DTO; where each step lives and how it is unit-tested
   - API contract: endpoints (e.g. `GET /api/search?q=`, `GET /health`), response shape, error shape, status codes, cache headers
   - import pipeline: source file(s) → validation → derived data → DB; how it runs locally, in tests and in the image build
   - composition root: where dependencies are wired; config from env
3. Check the design against the production checklist (protocol §4) and say where each item is handled.
4. Write `docs/architecture.md`:
   - context diagram (mermaid) and folder tree
   - layer rules (what may import what)
   - domain model + invariants
   - DB schema + migrations list
   - search pipeline with ranking rules
   - endpoint table, error table, log fields
   - production-checklist → where it is handled
   - **ordered build steps** `S0…Sn` for `/developer`, each with: goal, files, tests that prove it, done-when. Typical order: S0 scaffold + Docker + CI → S1 migrations + import → S2 domain + normalizer (unit) → S3 repository + search (integration + relevance) → S4 API + production concerns → S5 UI → S6 E2E. Deploy is `/devops`.
5. Offer ADRs for real trade-offs (e.g. DB choice, no runtime AI). Log in SESSION-LOG.

## Hand off
➡️ Next step: `/designer`
Prompt to paste: `/designer build 2 clickable HTML prototypes of the search page per docs/requirements.md, docs/architecture.md and the reference image in assets/`
