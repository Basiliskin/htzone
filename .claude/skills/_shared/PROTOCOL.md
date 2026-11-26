# Skill Protocol (shared by product, architect, designer, data, developer, devops, qa)

Every role skill follows these rules. Read this file before acting. `/interview` is the only exception (see its own SKILL.md).

## 1. Ground yourself first
Read, in this order, and treat them as the source of truth:
1. `docs/assignment.md` — the original brief. It wins on any conflict.
2. `docs/stack.md` — the chosen stack and the exact commands (build, test, lint, run, deploy).
3. `docs/decisions.md` — settled decisions. **Never re-ask a settled decision.** If new evidence contradicts one, say so and propose reopening it as a decision.
4. `docs/ai-log/SESSION-LOG.md` — what happened so far and the last suggested next step.
5. Outputs of earlier roles that exist: `docs/requirements.md`, `docs/architecture.md`, `docs/design.md`, `docs/qa-report.md`, `docs/adr/`, `GLOSSARY.md`.

Facts are your job: look them up in the repo or environment, never ask the user for something you can check. Decisions are the user's.

## 2. Propose, never decide
- For every non-trivial decision present **2–3 options** with trade-offs (KISS / SOLID / DRY / DDD / production risk / time cost), and mark one `(Recommended)`.
- Format:
  ```
  ❓ D<n> — <decision title>
  (a) … (Recommended)
  (b) …
  (c) …
  ➡️ why the recommendation, in one or two sentences
  ```
- Max **3 decisions per round**. Anything smaller becomes a stated default, recorded in the README.
- Plain language, one concrete example per decision. **Stop and wait.** The user picks a letter, mixes options or writes their own. Never proceed on an unanswered decision.
- Once answered, add the decision to `docs/decisions.md` (one line: decision → reason).

## 3. Record everything
After every user answer, append to `docs/ai-log/SESSION-LOG.md` (prompts are also captured automatically in `docs/ai-log/PROMPTS.md` by the hook):

```
## <YYYY-MM-DD HH:MM> : <skill> — <step>

**User input:**
> <verbatim>

**Options offered:** D1 (a/b/c) …
**Decision:** …
**Claude suggestion changed/rejected:** <if any — what was proposed, what the user chose instead, why>
**Artifacts:** files created/changed
```

The "changed/rejected" line feeds the graded README section on working with Claude — fill it every time it applies.

**README** (`README.md`): keep these sections current as you go, one line per item: *Run & load catalog*, *Key decisions*, *How it was tested* (with example queries), *Known limitations*, *With more time*, *Working with Claude*. Short beats complete.

Hard-to-reverse or surprising decisions with a real trade-off → offer an ADR in `docs/adr/NNNN-title.md`.

## 4. Engineering bar
- SOLID, KISS, DRY, **light DDD**: Domain / Application / Infrastructure / Http layers. Interfaces only where something is really swapped or mocked. Constructor injection wired in one place. YAGNI beats cleverness.
- Domain code has no framework, DB or HTTP imports.
- `declare(strict_types=1)` (or the stack's equivalent strictness) everywhere; static analysis at the strictest level the stack supports.
- **Production checklist** (every role checks what applies to its work):
  - input validated and length-limited; parameterized queries only; special characters escaped for the search engine
  - all output escaped; security headers incl. CSP; no stack traces to clients; one error shape
  - structured logs to stdout (what, how long, how many results)
  - `/health` endpoint; cache headers; config from env; no secrets in repo (`.env.example` only)
  - non-root container; pinned dependency versions
  - accessibility: labels, focus states, `aria-live`, AA contrast; RTL correctness for Hebrew
- Tests: unit for pure logic, integration against a real temporary DB, relevance tests for the brief's example queries, one browser E2E. Report **real** command output — never claim green without running.

## 5. Hand off
End every run with:

```
✅ Done: <what was produced>
⚠️ Open: <anything skipped or uncertain, or "none">

➡️ Next step: /<next-skill>
Prompt to paste:
/<next-skill> <ready-to-run prompt with concrete context>
```

Chain: `/product` → `/architect` → `/designer` → `/data` → `/developer <step>` (repeat) → `/devops` → `/qa` → `/interview` (local only).
Roles may be re-entered at any time when something needs revisiting. Log the suggested next step in SESSION-LOG too.

Never commit or push unless the user asks; propose a commit message instead.
