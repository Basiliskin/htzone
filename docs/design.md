# Design — htzone search page

The chosen design is **D20 (c)**: Variant A's centred hero, matching the reference image, combined with three ideas from Variant B. Prototype: `prototypes/variant-c.html`; A and B are kept for reference. Prototypes contain mock logic only and are never imported by the app (`architecture.md` §9 is the real implementation plan).

## 1. Layout

**Landing (idle)**
```
┌──────────────────────────────────────────────────────────────┐
│ "/" לחיפוש · Esc לניקוי                              htzone● │  header (white, bottom border)
├──────────────────────────────────────────────────────────────┤
│                     מה בא לכם למצוא?                         │  h1, Heebo 800
│        תארו מה אתם מחפשים, אנחנו נמצא מוצרים מתאימים          │  muted subtitle
│   ┌────────────────────────────────────────────────────────┐ │
│   │ [🔍 חיפוש]  ✕                  למשל: מתנה למישהו שאוהב קפה│ │  pill search, black button at inline-end
│   └────────────────────────────────────────────────────────┘ │
│     (שואב אבק) (iPhone 16) (אייפון) (מתנה למישהו שאוהב לבשל) …  │  suggestion chips
└──────────────────────────────────────────────────────────────┘
```

**After a search**: the hero collapses into a **sticky bar** that holds only the search form; h1 and subtitle stay in the DOM but are visually hidden.
```
┌──────────────────────────────────────────────────────────────┐
│ "/" … Esc …                                          htzone● │
├══════════════════════════════════════════════════════════════┤ ← sticky, blurred background
│   [🔍 חיפוש] ✕                                    שואב Dyson │
├══════════════════════════════════════════════════════════════┤
│ 8 מוצרים                                 מצאנו מוצרים בשבילכם │
│ ┌ notice (missing model / did-you-mean), when present ───────┐│
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐                           │  desktop ≥ 960px: 4 columns
│ │ img  │ │ img  │ │ img  │ │ img  │                           │  tablet 560–959px: 2 columns
│ │Brand │ │Brand │ │      │ │Brand │  ← brand line (hidden if none)
│ │ name │ │ name │ │ name │ │ name │  ← 2 lines max, ellipsis
│ │ ₪909 │ │₪1,909│ │ ₪369 │ │ ₪649 │                           │
│ └──────┘ └──────┘ └──────┘ └──────┘                           │
└──────────────────────────────────────────────────────────────┘
```

**Mobile < 560px**: list cards, a 96px image at inline-start, then brand / name / price.
```
┌───────────────────────────────┐
│ Philips                ┌────┐ │
│ מטהג אוויר חם Air Fryer XL │img │ │
│ ₪779                   └────┘ │
└───────────────────────────────┘
```

## 2. Design tokens (CSS custom properties in `styles.css`)

| Token | Value | Use |
|---|---|---|
| `--bg` | `#f5f6f8` | page background |
| `--surface` | `#ffffff` | header, cards, panels, search |
| `--text` | `#111214` | body text (contrast 17:1 on `--surface`) |
| `--muted` | `#5a5f68` | subtitle, count, brand (6.4:1 on `--surface`, 5.9:1 on `--bg`, AA) |
| `--line` | `#e3e5e9` | borders |
| `--ink` | `#111214` | primary button background (white text 17:1) |
| `--accent` | `#b8f13d` | logo dot **only** (decorative, never text) |
| `--focus` | `#2563eb` | focus ring 3px, offset 2px; links |
| `--danger` | `#b42318` | error heading |
| `--notice-bg` / `--notice-text` | `#fff8e1` / `#6b4e00` | notice banner (7:1) |
| `--placeholder` | `#eceef1` | image box, skeleton |
| radius | `--radius-lg 20px` (cards, panels), `--radius 14px`, `--radius-pill 999px` (search, chips, buttons) | |
| spacing | `--s1…--s8` = 4, 8, 12, 16, 24, 32, 48, 72px | |
| font | `"Heebo", system-ui, -apple-system, "Segoe UI", Arial, sans-serif` | **Heebo is self-hosted** (D21): `public/assets/fonts/heebo-var.woff2`, a variable font covering Hebrew + Latin, weights 400–800, `font-display: swap`, preloaded. Licence: SIL OFL, with the licence file next to it. |
| type scale | h1 `clamp(2rem, 6vw, 3.4rem)` 800 · results h2 `clamp(1.25rem, 3vw, 1.75rem)` 800 · price 1.6rem 800 (mobile 1.2rem) · name 1.05rem 500 · body 1rem | |
| motion | 150–250ms ease; skeleton shimmer 1.2s; **all off** under `prefers-reduced-motion` | |

Dark mode: **not built** (D22). It's listed in the README *With more time* (the light placeholder images would look like bright tiles).

## 3. Components and states

| Component | Element / class | States and rules |
|---|---|---|
| Header | `header.top` | logo `htzone` + lime dot (LTR text, `aria-label`); keyboard hint is `aria-hidden` and hidden < 560px |
| Search form | `form.search[role=search]` (`GET /?q=` works without JS) | idle / focused (border becomes `--ink`) / has text (✕ visible). Input: `type=search`, `dir="auto"` (input only), `maxlength=100`, `autocomplete=off`, visually hidden `<label>`. Button: icon + "חיפוש"; text hidden < 480px. ✕ has `aria-label="ניקוי החיפוש"` |
| Suggestion chips | `ul.chips > li > button.chip` | shown on landing and in the no-results panel; click → fills the input and searches. The list is the brief's 5 example queries |
| Results header | `.results-head` | "מצאנו מוצרים בשבילכם" + count "N מוצרים"; hidden (not removed) while loading so the layout doesn't jump |
| Notice | `#notice.notice` | hidden when `notice=null`; `missing_model` → message only; `did_you_mean` → message + link button "חפשו ‹suggestion›" |
| Result card | `li.card` | image (`loading=lazy`, `decoding=async`, width/height set → no layout shift; `alt=""` because the name is right next to it) · brand line (`<bdi>`, omitted when null) · name `h3` (2-line clamp, `title` = full name, **inherits RTL, never `dir="auto"`**) · price `<bdi dir="ltr">₪1,299</bdi>`. Hover lift 2px (desktop only) |
| Image fallback | JS `error` listener, once | swaps to `/assets/img/fallback.svg` and adds `.no-img`; same box size |
| Skeleton | 8 × `li.card.skeleton[aria-hidden]` | shown in `loading`; same box sizes as real cards; shimmer off under reduced motion |
| Empty panel | `#empty.panel` | "לא מצאנו מוצרים עבור „‹q›”" (`<bdi>`) + "נסו מילים אחרות…" + chips |
| Error panel | `#error.panel[role=alert]` | "משהו השתבש בחיפוש" + "נסו שוב" → re-runs the last query |
| Short-query hint | `#short.panel` | "הקלידו לפחות 2 תווים כדי לחפש" |
| Live region | `#live[aria-live=polite][role=status]` | announces "נמצאו N מוצרים" / "לא נמצאו מוצרים" / "אירעה שגיאה בחיפוש" / the short-query hint |

The page state lives on `body[data-state]`, which is one of `idle | short | loading | results | empty | error`. CSS shows and hides sections from this attribute only, and JS never toggles `display` itself.

## 4. Interaction rules

- **Debounce** 250ms after typing. Enter or the button searches immediately and cancels the debounce.
- **Race safety:** every search aborts the previous request (`AbortController`). Aborted responses are ignored, so an old response can never replace a newer one.
- **URL sync:** each search writes `?q=` with `history.replaceState`. Loading the page with `?q=` runs the search. *Back/Forward*: `pushState` on submitted searches (Enter, button or chip), `replaceState` while typing; `popstate` restores the query.
- **Limits** (mirror of the server rules): trimmed; empty → idle; 1 character → short hint, no request; more than 100 characters → cut.
- **Keyboard:**
  - `/` focuses the search box from anywhere, unless you're already typing in a field;
  - `Esc` in the input clears it and returns to idle;
  - Tab order: skip link → search → ✕ → button → chips/cards;
  - the focus ring is always visible (`:focus-visible`).
- **Retry** re-runs `lastQuery`. Focus stays on the button until the results render.
- **Scrolling:** after a search the sticky bar stays on top. Results don't scroll back to the top while you're typing.

## 5. Accessibility and RTL

- `<html lang="he" dir="rtl">`. Only logical CSS properties are used (`margin-inline-*`, `inset-inline-*`, `padding-block-*`, `text-align: start`).
- **Mixed-direction text** (found while prototyping): `dir="auto"` on product names makes names that start with English ("Air Fryer קומפקטי דיגיטלי") LTR and left-aligned. Names therefore inherit RTL, and the Unicode bidi algorithm orders the embedded English correctly. Latin-only fragments that stand alone (brand, price) are isolated with `<bdi>`. The search input keeps `dir="auto"` so typing English feels natural.
- A skip link to the search goes first in the DOM. Every icon-only button has an `aria-label`. Decorative icons and emoji are `aria-hidden`.
- Contrast is AA or better for all text (values in §2). Interactive targets are at least 40×40px.
- `prefers-reduced-motion` disables the shimmer, hover lift and hero transition.
- Target is Lighthouse accessibility ≥ 95, checked manually in S5 and noted in the README.

## 6. Notes for implementation (S5)

- No inline `<script>` or `<style>`, so the CSP holds (`script-src 'self'`, `style-src 'self'`). The prototype's inline code is split into `styles.css` and `app.js`, `api.js`, `state.js`, `render.js`, `dom.js`.
- DOM is built with `createElement` and `textContent` only. Never use `innerHTML` with data.
- The price comes pre-formatted from the API (`price.formatted`), so the browser doesn't format it again (DRY).
- The prototype's dev panel and mock engine are **not** carried over.
