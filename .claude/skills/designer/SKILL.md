---
name: designer
description: UI/UX designer role for a home assignment — builds 2 clickable, self-contained HTML prototypes with mocked data covering every UI state, lets the user pick or mix, then writes docs/design.md (layout, tokens, components, states, accessibility, RTL). Use after /architect, or when the UI needs rethinking.
---

# /designer

Follow `.claude/skills/_shared/PROTOCOL.md` throughout.

## Goal
Let the user *feel* the UX before real code exists, then lock it into `docs/design.md`.

## Steps
1. Ground yourself (protocol §1). Study any reference image in `assets/` or `docs/` — it is **direction only**, not something to copy pixel by pixel. Note the frontend tooling settled in `docs/decisions.md` and respect it in the prototypes.
2. Build **2 throwaway prototypes**: `prototypes/variant-a.html` and `prototypes/variant-b.html`. Each is one self-contained file (inline CSS/JS, no build, no external scripts), driven by mocked results that look like the real data (use real rows from the data files). Each must be clickable through **every state**:
   - empty/landing (with suggested queries)
   - typing → debounce → loading skeletons
   - results grid (image, name, price)
   - no results (with suggestions)
   - error (with retry)
   - broken image fallback
   - very long product names, mixed Hebrew/English text
   Make the variants genuinely different (e.g. A — centred hero search like the reference; B — sticky top search bar with instant results and compact cards).
3. Every variant meets the **UX checklist**:
   - correct `dir="rtl"`, using logical CSS properties (`margin-inline-start` etc.)
   - mixed-direction text displays correctly (`dir="auto"` / `<bdi>` on product names)
   - mobile-first: works at 360px; 1 / 2 / 4 columns
   - keyboard: `/` focuses search, `Esc` clears, visible focus states
   - `aria-live` announces result count / errors
   - AA contrast; `prefers-reduced-motion` respected
   - price formatted for the locale (e.g. `₪1,299`)
4. Tell the user how to open them (`open prototypes/variant-a.html`) and propose the choice (protocol §2). The user may pick, mix ("A layout with B cards") or describe their own — iterate until they confirm.
5. Write `docs/design.md`:
   - chosen layout (ASCII sketch)
   - design tokens (colours, spacing, radius, font stack)
   - component list (search box, result card, skeleton, empty/error panels) with their states
   - interaction rules (debounce ms, URL sync, keyboard)
   - accessibility and RTL notes
   Log in SESSION-LOG.

Prototypes hold **mock logic only** and are never imported by the real app.

## Hand off
➡️ Next step: `/data`
Prompt to paste: `/data enrich the catalog and generate derived data per docs/decisions.md and docs/architecture.md`
