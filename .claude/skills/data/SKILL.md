---
name: data
description: Data role for a home assignment — profiles the source data, fills gaps (e.g. generated values with a fixed seed), produces AI enrichment (tags, synonyms, translations) in validated batches, runs a check script and a human spot-check, and documents how Claude's output was verified. Use after /designer (or before /developer import steps), or whenever source data or enrichment must change.
---

# /data

Follow `.claude/skills/_shared/PROTOCOL.md` throughout.

## Goal
Committed, validated, reproducible data files that the import step loads, plus evidence of how they were checked.

## Steps
1. **Profile** the source data (look, don't ask): row count, columns, encoding/BOM, empty or malformed fields, duplicates, value distributions per category, broken image URLs, language mix. Write the findings at the top of `docs/data.md`.
2. **Gaps** (e.g. a missing price column): propose how to fill each one (protocol §2) unless settled in `docs/decisions.md`. Generated values must be **deterministic** (fixed seed, per-category ranges), stored in minor units, and disclosed in the README.
3. **Enrichment** (only if the settled search approach needs it). Agree the output schema first, e.g. one JSON line per product:
   `{"id":"P0001","tags_he":[…],"tags_en":[…]}`
   Tags should cover:
   - synonyms
   - spellings of foreign names in the other language (e.g. אייפון ↔ iPhone)
   - plural and prefixed forms of the main nouns
   - use-cases and needs ("gift for someone who loves cooking", "pet hair on the sofa")
   - who it suits
   Generate in **batches of ~50** rows, reading the real rows each time; append to the output file; never invent ids.
4. **Validate** with a committed check script (in the project's language, runnable via `docs/stack.md` commands) that fails on:
   - missing or extra ids, or duplicates
   - empty tag lists, too few or too many tags per product
   - wrong language in a field, or invalid JSON
   Run it and paste real output.
5. **Spot-check**: show the user ~20 random products with their tags in a compact table and ask them to flag bad ones. Fix flagged rows and anything similar to them. Record in SESSION-LOG what was wrong and what you changed: this is the "how did you verify Claude's output" evidence for the README.
6. Run the brief's example queries mentally against the tags. If an example has no plausible tag match, enrich the matching products and say so.
7. Finish `docs/data.md`: sources, schema, generation and enrichment method, validation rules, spot-check results, known weaknesses. Update the README *Working with Claude* and *Known limitations* sections. Log in SESSION-LOG.

## Hand off
➡️ Next step: `/developer`
Prompt to paste: `/developer S0 scaffold per docs/architecture.md and docs/stack.md`
