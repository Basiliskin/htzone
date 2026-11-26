---
name: developer
description: Developer role for a home assignment — implements exactly one build step from docs/architecture.md (S0…Sn), proposing implementation options first, then writing code test-first, running all gates with real output, tuning search relevance against the example-query table when relevant, and proposing a commit message. Use with a step id, e.g. "/developer S2".
---

# /developer <step>

Follow `.claude/skills/_shared/PROTOCOL.md` throughout.

## Goal
Deliver one build step: green, reviewed and ready to commit, without scope creep.

## Steps
1. Read the step in `docs/architecture.md` (goal, files, tests, done-when), the matching acceptance criteria in `docs/requirements.md`, `docs/design.md` for UI steps and `docs/data.md` for import steps. If no step is given, pick the next unfinished one from SESSION-LOG and say so.
2. If the language or framework is new to the user (see `docs/decisions.md`), add a short **"how this works"** note for each new idea you introduce (2–3 lines, e.g. what PSR-4 autoloading or a PDO prepared statement is). The user must be able to explain every line in the interview.
3. Propose **2–3 options** for the step's real choices only (e.g. how the normalizer generates variants; how FTS5 queries are escaped; JS state handling). Recommend one. **Wait.**
4. Implement **test-first** where there is logic: failing test → code → green. By step type:
   - domain/pure logic → unit tests
   - repository/search → integration tests against a real temporary DB
   - search ranking → **relevance tests** from the example-query table (`expected product in top N`). If one fails, tune weights, aliases or enrichment and report what changed and why.
   - API → status codes, error shape, headers (CSP, cache), input limits
   - UI → states render correctly; RTL; keyboard
5. Hold the bar (protocol §4):
   - domain free of DB/HTTP imports; thin controller
   - one composition root; no duplicated mapping or formatting (DRY)
   - production-checklist items that belong to this step
6. Run the gates from `docs/stack.md` (format, static analysis, tests). Paste **real** output. Fix failures before handing off. Never weaken a test or a static-analysis level to get green — if you think it's wrong, propose that as a decision.
7. Self-review the diff: dead code, leftover debug output, secrets, TODOs without a README entry.
8. List the files changed, update the README sections this step touches, log in SESSION-LOG, and **propose a commit message** (one step = one commit; commit only if the user asks).

## Hand off
If steps remain:
➡️ Next step: `/developer`
Prompt to paste: `/developer S<next> <step title from docs/architecture.md>`

When all build steps are done:
➡️ Next step: `/devops`
Prompt to paste: `/devops deploy per docs/stack.md and verify the live site`
