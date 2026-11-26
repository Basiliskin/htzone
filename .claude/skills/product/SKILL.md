---
name: product
description: Product-owner role for a home assignment — turns docs/assignment.md and docs/decisions.md into user stories with testable Given/When/Then acceptance criteria, an example-query test set and a MoSCoW scope cut sized to the time budget. Use first in the chain, or when requirements or scope need revisiting.
---

# /product

Follow `.claude/skills/_shared/PROTOCOL.md` throughout.

## Goal
Produce `docs/requirements.md`: the single source of truth for *what* gets built.

## Steps
1. Ground yourself (protocol §1). List every explicit requirement in the brief as a checklist — including the "what to submit" and "what we'll check" sections, not only features.
2. Write user stories (`As a <visitor> I want … so that …`) covering every requirement. Typical areas: search input, results (image, name, price), loading / no-results / error states, language support (and mixed languages), meaning-based matching, responsiveness, RTL, persistence, deploy, repo, README.
3. For each story write **Given/When/Then acceptance criteria** that a test can check. Include negative and edge cases: empty query, very long query, special characters, unknown words, backend down, missing/broken image, missing data in the source file.
4. Build an **example-query table**: every example from the brief plus 5–10 of your own (plurals, attached prefixes, typos, brand only, mixed language, nonsense). Columns: query · expected top result(s) · why. This becomes the relevance test set for `/developer` and `/qa`.
5. Find gaps between the brief and the data (look at the actual data files). Propose each as a decision (protocol §2) unless already in `docs/decisions.md`.
6. Propose a **MoSCoW scope cut** sized to the time budget in `docs/decisions.md`; the user confirms. Everything in *Won't* goes to README *With more time* with a one-line reason.
7. Write `docs/requirements.md` (requirements checklist, stories + AC, example-query table, scope, out-of-scope). Create/update `GLOSSARY.md` with domain terms (e.g. Product, Catalog, Query, Tag, Result). Log in SESSION-LOG.

## Hand off
➡️ Next step: `/architect`
Prompt to paste: `/architect design layers, domain model, DB schema, search pipeline, API contract and ordered build steps for docs/requirements.md`
