# Design System — Pathary

> Source of truth for Pathary's visual language. Read this before any UI change.
> Approved 2026-07-05 via /design-consultation, grounded in the live app (pathary.tv)
> and `public/assets/css/theme.css`.

## Product Context
- **What this is:** Self-hosted group movie-tracking web app with a 1–7 popcorn rating. Fork of Movary.
- **Who it's for:** Benjamin + a small private circle of friends tracking and rating watched films together.
- **Space:** Self-hosted media apps (peers: Jellyfin, Plex, Letterboxd, Trakt, Movary).
- **Project type:** Web app — poster-wall library, movie detail, stats, admin. PHP 8.4 + Twig + Bootstrap 5.

## North Star
A **modern, neutral, poster-forward product**. The real TMDB poster art carries the colour and life;
the UI is a quiet, cool-neutral frame around it, with **gold as the single accent**. Clean and
functional first — a movie library, not an editorial art piece.

**Hard nos (learned during design):**
- No warm / brown-tinted neutrals — the canvas is **cool neutral grey**, never beige/cream/brown.
- No serif display face — typography is a **clean modern sans**, not a literary/film-journal serif.
- No decorative gradients as accent (no purple→gold washes, no gradient hero backdrops).
- Posters are never faked with gradient placeholders — real poster art is the colour.

## Aesthetic Direction
- **Direction:** Modern product / functional dashboard, poster-forward.
- **Decoration level:** minimal — flat surfaces, one gold accent, real poster art does the work.
- **Mood:** Calm, neutral, confident. The films are loud; the chrome is quiet.
- **Reference:** the live pathary.tv, elevated (cleaner type, tighter cards, consistent gold).

## Typography
Clean modern sans throughout — no serif.
- **Display / headings:** **Geist** 600, tight tracking (`-0.03em` on large sizes).
- **Body / UI:** **Geist** 400/500.
- **Data / numbers / metadata:** **Geist Mono** (tabular by nature) — year, runtime, ratings, counts.
- **Code / admin:** **Geist Mono**.
- **Loading:** self-hosted variable fonts (`public/fonts/Geist-Variable.woff2`, `GeistMono-Variable.woff2`), declared in `theme.css` and preloaded by `templates/partials/head_assets.twig`. Icons: Bootstrap Icons 1.11.3, self-hosted. No CDNs.
- **Scale (rem):** h1 1.9 · h2 1.45 · h3 0.9 (uppercase, tracked) · body 0.95 · small 0.8 · micro 0.72.
- Base letter-spacing `-0.006em`; headings tighter.

## Color
Dark-first. Neutral **cool** greys (slight blue undertone) — never warm.

All tokens live in one layer: `public/assets/css/theme.css` (`:root`, `.pds-root`) plus the showcase
tokens in `public/css/global.css`. `tests/unit/Frontend/DesignTokenContrastTest.php` reads them and fails
the build when a text token drops below 4.5:1 on its surfaces (WCAG 2.2 AA).

**Brand tokens:**
- `--gold` **#FBBC09**: the single accent for fills, borders, icons, CTAs, active states, section rule.
- `--gold-ink`: gold used as **text**, #FBBC09 on dark and **#8F6408** on light (5.3:1 on white; #FBBC09 on
  white is only 1.7:1). Page tokens expose it as `--pds-gold-text`, the showcase as `--sc-gold-text`.
- **Focus:** one global `:focus-visible` ring, `2px solid var(--focus-ring)` with 2px offset. The ring is
  `--gold-ink`, so gold on dark and dark gold on light. The old purple ring (#6F2DBD) is retired.

**Dark (default) — cool neutral ramp:**
| Role | Hex |
|---|---|
| nav / footer | `#0F1114` |
| bg | `#15171B` |
| surface | `#1C1F24` |
| surface-2 | `#23272D` |
| border | `#30353D` |
| hairline | `#262B31` |
| text | `#E7E9EC` |
| muted | `#9CA3AC` |
| dim | `#8A919B` (4.7:1 on surface-2) |

**Light — cool neutral:**
| Role | Hex |
|---|---|
| bg | `#FBFBFC` |
| surface | `#FFFFFF` |
| surface-2 | `#F3F4F6` |
| border | `#E4E7EB` |
| text | `#191C20` |
| muted | `#5D646D` |
| dim | `#676E78` (4.7:1 on surface-2) |

**Semantic:** success `#5AA57A` · warning `#C98A34` (held apart from brand gold) · error `#C7443F` · info `#5A97A3`.
Nav bar, footer, the home CTA band and the movie detail hero stay dark in **both** themes; inside them
use the dark-theme text and gold values.

**Dark mode strategy:** redesign surfaces (don't just invert). Keep neutrals cool; gold stays #FBBC09.

## Spacing
- **Base unit:** 8px (4px half-step allowed).
- **Density:** comfortable. Poster-wall gap 20px; card padding 18–22px.
- **Scale:** 4 · 8 · 16 · 20 · 24 · 32 · 46 · 56.

## Layout
- **Approach:** grid-disciplined. Centered section headings (`text-align:center`) with muted subtitle — matches the app.
- **Content width:** max **1180px**, 26px gutter.
- **Poster wall:** 6 columns desktop → 3 columns ≤860px. Poster aspect **2:3**, `object-fit:cover`.
- **Border radius:** cards/posters 10px · thumbs/inputs 5–6px · pills 9999px.
- **Cards:** 1px `--border`, flat surface, subtle shadow on dark only; hover lifts poster 4px + gold border.
- **Section rule:** 1px border with an 80×3px gold tab centered on it (the divider motif).

## Motion
- **Approach:** minimal-functional.
- **Easing:** ease-out for enter/hover.
- **Duration:** hover/micro 160ms · theme switch 200ms.
- Poster hover: `translateY(-4px)` + gold border + shadow. Popcorn rating (existing) scales on hover.

## Signature
- **Popcorn rating 🍿 (1–7):** the brand primitive. Filled = full-colour emoji, empty = grayscale+dimmed.
  Ratings render in gold, mono figures. Consider a custom SVG popcorn later for cross-OS consistency.

## Decisions Log
| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-07-05 | Neutral **cool** grey canvas, not warm/`#231F20` | Warm neutrals read brown/muddy; the live app is neutral. Cool greys match reality. |
| 2026-07-05 | **Geist** sans, no serif display | Fraunces serif diverged from the app and read as "AI editorial". Clean modern sans fits the product. |
| 2026-07-05 | **Gold #FBBC09 as sole accent**, purple → focus-ring only | Committing to one accent; posters carry all other colour. |
| 2026-07-05 | Real poster art is the colour; never fake posters | Live app's strength is the wall of real TMDB art. |
| 2026-07-05 | Dark-first, nav/footer dark in both themes | Matches live app; media browsing expects dark. |
| 2026-11-08 | Light-mode text gold #8F6408, dim greys raised, gold focus ring | WCAG 2.2 AA: gold text on white was 1.7:1, dim text 3.0-3.7:1. Purple focus ring dropped for one gold ring. |
| 2026-11-08 | No blur, glow or gradient decoration | Falling blurred popcorn, blurred poster backdrop and gradient placeholders replaced by flat surfaces. |
