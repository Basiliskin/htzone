---
name: qa
description: QA, security and final-review role for a home assignment — maps every requirement to a test, runs all suites and a manual smoke run on local and live, checks relevance against the example queries, audits security, SOLID/KISS/DRY/light-DDD and the production checklist, and confirms the repo and README are ready to submit. Use after /devops, or any time as an independent review.
---

# /qa

Follow `.claude/skills/_shared/PROTOCOL.md` throughout. Review as if you didn't write the code: verify claims by running things, not by reading summaries.

## Steps
1. **Traceability**: in `docs/qa-report.md`, write a short table of each requirement in `docs/assignment.md` and `docs/requirements.md` → the test that covers it (or "manual") → status. Mark gaps. Include the submission items (live link, repo history, README sections, no secrets).
2. **Run everything** from `docs/stack.md`: format check, static analysis, unit, integration, relevance and E2E tests. Paste real results and timings.
3. **Relevance**:
   - run every example query, locally and live; record the top 5 and the latency
   - add 5 adversarial queries: typo, plural with a prefix, English-only brand, mixed language, gibberish
   - mark each ✅ / 🟡 / ❌ with a one-line reason
4. **Security and robustness smoke**:
   - empty query and a 10,000-character query
   - SQL and full-text-search special characters (`" * ( ) OR NEAR -- '`)
   - `<script>` in the query (is it reflected or escaped?)
   - Unicode edge cases (RTL marks, emoji, niqqud)
   - backend down → UI error state
   - broken image URL
   - response headers (CSP, nosniff, referrer, cache)
   - no stack traces in error responses
   - secrets: grep the repo and git history
5. **UX pass**:
   - 360px and desktop widths
   - RTL alignment and mixed-direction product names
   - keyboard-only use and focus visibility
   - screen-reader announcements (`aria-live`)
   - loading / no-results / error states all reachable
6. **Architecture audit**, citing `file:line`:
   - layer violations (domain importing DB or HTTP)
   - fat controller; duplicated logic or formatting (DRY)
   - interfaces with one implementation that are never mocked (YAGNI)
   - dead code, missing strict types
   - production-checklist items missing (protocol §4)
7. Propose fixes as options: *fix now* / *defer to README "With more time"* / *ignore*, each with a recommendation. **Wait**, then apply the chosen small fixes or hand larger ones back to `/developer`.
8. **Submission readiness**:
   - README has every required section and they're accurate: run & load catalog, key decisions and why, how it was tested with example queries, known limitations, with more time, and working with Claude including a changed or rejected suggestion
   - live link works
   - repo history shows small, meaningful commits
   - `.env.example` exists; a clean clone + documented commands works from scratch
   - `.claude/skills/interview/` is not tracked
9. Log in SESSION-LOG.

## Hand off
If gaps remain:
➡️ Next step: `/developer`
Prompt to paste: `/developer fix <gap> from docs/qa-report.md`

Otherwise:
✅ Ready to submit.
➡️ Next step: `/interview`
Prompt to paste: `/interview`
