# Session log

How the work was done with Claude, step by step. Prompts are captured verbatim in `PROMPTS.md` by a hook; this file records options, decisions and where Claude's suggestions were changed or rejected.

## 2026-11-26 : planning (grilling-simple) — scope, stack and approach

**User input:**
> This is a home assignment for 4 hours … will use PHP — never worked with it. What time will it take to set up a secure local env and later publish, and where can I publish for free? … not only the right solution, secure and reliable, good UX for Hebrew/RTL, but also local env setup to run/debug locally — unit/e2e/integration tests, migrations — and finally deploying it.

**Findings Claude looked up (not asked):** CSV has 1000 products and **no price column**; no PHP/Composer on the Mac, Docker present; reference image = centred hero search + 4-column cards.

**Options offered and decisions:** see `docs/decisions.md` D1–D12 (PHP flavour, search approach, prices, DB, frontend, architecture depth, production concerns, tests, hosting, enrichment, ranking, process).

**Claude suggestion changed/rejected:**
1. **Runtime embeddings rejected.** Claude first recommended hybrid search with an embedding API (Voyage) + Postgres/pgvector. The user pushed back: an external API complicates deploy and they wanted a self-contained deployment, asking about local models. Claude explained a local model must run at query time (heavy on 512 MB free hosts) and proposed offline enrichment with Claude-written tags + plain full-text search instead — adopted (D2).
2. **Plurals.** The user asked whether word search supports plurals; Claude admitted it doesn't by default and added query normalization for Hebrew prefixes/plural suffixes + prefix matching + plural forms in tags (D3).
3. **Architecture pushed to production-grade.** Claude proposed a minimal light-DDD layout; the user required "close to production grade". Claude kept the light layering (arguing heavier DDD would be over-engineering for a read-only catalog) but added a concrete production checklist (D7, D8) and an honest ~5.5–6 h estimate.
4. **Requirement re-read.** The user re-emphasised the brief's Hebrew + English + mixed + meaning-based requirement, which ruled out the keyword-only option.

**Artifacts:** `docs/decisions.md`.

## 2026-11-26 : planning — role skills and hooks

**User input:**
> yes — but for now I want to build skills for each role which will help me with this assignment; roeto-assignment already has skills/hooks which I used to generate the solution for an assignment.

**Options offered:** Q13 htzone-specific vs generic + project file · Q14 roles (6 original + `/data` + `/devops`) · Q15 committed in repo vs personal, which hooks.
**Decision:** generic skills + `docs/stack.md` / `docs/decisions.md`; 8 roles; committed in repo with `/interview` git-ignored; prompt logger switched on (it was only in a `.bak` settings file in the previous project) + PHP check hook; personal `ha-*` copies in `~/.claude/skills`.
**Claude suggestion changed/rejected:** user added that `/interview` must be git-ignored (Claude had only proposed skipping its prompt logging).
**Artifacts:** `.claude/skills/{_shared,product,architect,designer,data,developer,devops,qa,interview}`, `.claude/hooks/{log-prompt,php-check}.mjs`, `.claude/settings.json`, `docs/stack.md`, `docs/decisions.md`, `.gitignore`.

**Next step suggested:** `/product turn docs/assignment.md into requirements, acceptance criteria and the example-query table`

## 2026-11-26 12:20 : product — requirements, acceptance criteria, example queries

**User input:**
> /product turn docs/assignment.md into requirements, acceptance criteria and the example-query table
> D14 a, D15 b, D16 agree

**Findings (profiled, not asked):** 1000 rows, unique ids, 52 empty brands, brands always Latin even when the name spells them in Hebrew, all images are placehold.co text placeholders, **no product mentions iPhone/אייפון/16** (Apple phones P0401/P0407/P0413 are generic "סמארטפון").
**Options offered:** D14 iPhone gap (a tags + "no exact model" note / b tags only / c no results) · D15 typos (a skip / b trigram fallback on zero results / c always fuzzy) · D16 MoSCoW cut.
**Decision:** D14 a · D15 b · D16 as proposed. Defaults stated: query 2–100 chars (truncate, never reject), hide empty brand, keep placeholder images with local fallback, top 24.
**Claude suggestion changed/rejected:** none this step (all recommendations accepted).
**Artifacts:** `docs/requirements.md`, `GLOSSARY.md`, `README.md` (skeleton), `docs/decisions.md` (D14–D16).

**Next step suggested:** `/architect design layers, domain model, DB schema, search pipeline, API contract and ordered build steps for docs/requirements.md`
