# ADR 0002 — SQLite + FTS5, built into the Docker image

**Status:** accepted (2026-11-26) · **Decision ref:** D5, D18

## Context
The brief requires a DB connection and persistent storage of the catalog. The catalog is 1000 rows and read-only at runtime. The free host should run a single service.

## Options
1. Postgres (local Docker + Neon in prod): "serious", but two services, a password to manage, and remote migrations.
2. **SQLite file built by `bin/import` during the image build, opened read-only at runtime.**

## Decision
Option 2. FTS5 gives weighted BM25 ranking, prefix matching and a trigram tokenizer (verified in `php:8.3-apache`, SQLite 3.46.1).

## Consequences
- Deploy = one image; data is versioned with the code; every deploy rebuilds the catalog from source (reproducible).
- No runtime writes: there's no need for a persistent disk on the host.
- If the catalog becomes editable or multi-instance writes are needed → implement `ProductRepository` for Postgres (tsvector + pg_trgm); the Domain and Application layers don't change.
