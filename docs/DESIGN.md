# IITH Sanchari — Design System

<img src="../assets/logo.svg" alt="IITH Sanchari logo" width="96">

**IITH Sanchari** — campus bus ticketing for the IIT Hyderabad community. The mark is a fan of stripes echoing the institute crest, reinterpreted as a road with a bus grounded on it; the sun above stands in for the crest's circle.

This is the single source of truth for the app's visual language. If you're building a new screen, match this exactly — don't invent new colors, fonts, or radii. If something here is wrong or the code has drifted from it, fix whichever one is stale and note it in `docs/PROGRESS.md`.

The live implementation of every token below is `assets/app.css`. This document explains and catalogs it; `app.css` is canonical if the two ever disagree.

---

## Palette — "Sunrise IITH"

Named for the gradient in the hero illustration (`assets/hero.png`): a vivid orange sun fading to gold. The whole app's color language is sampled from that image.

| Token | Hex | Swatch | Usage |
|---|---|---|---|
| `--stage` | `#fdf1e6` | <span style="display:inline-block;width:14px;height:14px;background:#fdf1e6;border:1px solid #ccc;vertical-align:middle"></span> | Page background |
| `--paper` | `#ffffff` | <span style="display:inline-block;width:14px;height:14px;background:#ffffff;border:1px solid #ccc;vertical-align:middle"></span> | Card / surface background |
| `--paper2` | `#fbe9db` | <span style="display:inline-block;width:14px;height:14px;background:#fbe9db;border:1px solid #ccc;vertical-align:middle"></span> | Inset surfaces — toggle tracks, icon badges, chips |
| `--ink` | `#241d1a` | <span style="display:inline-block;width:14px;height:14px;background:#241d1a;border:1px solid #ccc;vertical-align:middle"></span> | Primary text |
| `--m2` | `#8c8078` | <span style="display:inline-block;width:14px;height:14px;background:#8c8078;border:1px solid #ccc;vertical-align:middle"></span> | Muted text / labels |
| `--ox` | `#e8491f` | <span style="display:inline-block;width:14px;height:14px;background:#e8491f;border:1px solid #ccc;vertical-align:middle"></span> | Primary brand / accent (red-orange) |
| `--ox2` | `#c93c17` | <span style="display:inline-block;width:14px;height:14px;background:#c93c17;border:1px solid #ccc;vertical-align:middle"></span> | Oxblood-dark — gradient shade, pressed states |
| `--gold` | `#f2a71b` | <span style="display:inline-block;width:14px;height:14px;background:#f2a71b;border:1px solid #ccc;vertical-align:middle"></span> | Secondary accent (Miyapur route, gold gradient) |
| `--goldt` | `#d98f12` | <span style="display:inline-block;width:14px;height:14px;background:#d98f12;border:1px solid #ccc;vertical-align:middle"></span> | Gold text / dark gold shade |
| `--live` | `#1f8a4c` | <span style="display:inline-block;width:14px;height:14px;background:#1f8a4c;border:1px solid #ccc;vertical-align:middle"></span> | Live status, "Paid" badge, verified state — **semantic green, not a brand color** |
| `--pline` | `rgba(120,90,70,.14)` | | Hairlines / dividers |
| `--sh` | `rgba(180,90,40,.18)` | | Shadow tint (warm, not neutral gray) |

**Gradients** (both sampled from the hero sun — see `assets/app.css` for exact stops):
```css
--grad-ox:   linear-gradient(160deg,#ff8a3d 0%,#f0501c 42%,#c02a12 100%);
--grad-gold: linear-gradient(160deg,#ffd873 0%,#f2a71b 45%,#c67908 100%);
```
Used on: ticket cards (`--grad-ox` for Patancheru, `--grad-gold` for Miyapur), shuttle badges, active toggle/pill states, the ticket boarding-pass band.

**Rule:** every shadow, divider, and neutral in this system has a warm bias toward the orange accent — never pure gray (`--pline`, `--sh` are both `rgba` tints of brown/orange, not black). Don't introduce a cold gray; it'll read as unconsidered against everything else.

---

## Typography

```css
font-family: ui-rounded, "SF Pro Rounded", "Segoe UI Rounded",
             -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
             Helvetica, Arial, sans-serif;
```

One typeface, no serif, no second display face. **SF Rounded** was chosen deliberately (see `docs/PROGRESS.md` log) because its softened curves match the rounded-pill language used everywhere else — it's not a neutral default, it reinforces the shape system. Falls back cleanly to the platform system font on non-Apple devices.

**Observed type scale** (no formal `--fs-*` tokens yet — these are the sizes actually in use):

| Role | Size | Weight | Notes |
|---|---|---|---|
| Hero headline | 22px | 800 | `letter-spacing:-.02em` |
| Ticket route / card title | 18–19px | 800 | |
| Body / route names | 13.5–14px | 700 | |
| Fare / time numerals | 19–23px | 900 | always `font-variant-numeric: tabular-nums` |
| Labels (uppercase eyebrows) | 9–11px | 800 | `letter-spacing: .1em` to `.14em`, uppercase |
| Buttons / pills | 11.5–13px | 800 | |
| Muted / meta text | 10.5–13px | 600–700 | color `var(--m2)` |

**Numerals always get `tabular-nums`** wherever digits align in a column or update dynamically (fares, times, countdowns) — non-negotiable, prevents layout jitter.

---

## Radius system

Deliberately collapsed to **two tokens** — see `docs/PROGRESS.md` 2026-07-24 entry for why (it used to be four, and buttons were inconsistent as a result):

```css
--r-card: 20px;   /* every card, ticket, hero, modal-like surface */
--r-btn:  999px;  /* every button, pill, chip, toggle segment — always a full stadium/pill */
```

There is no "medium" radius. If something looks like a container → `--r-card`. If it looks like a button/tag/chip → `--r-btn`. Small decorative elements (icon badges in the ticket meta grid, `Coming Next` tiles) use a one-off ~13–16px — close enough to `--r-card`'s family that it doesn't read as a third system.

---

## Shadows & elevation

All shadows use `--sh` (warm-tinted, never pure black) at varying blur/spread for a soft, low-contrast lift — nothing hard-edged.

```css
/* card at rest */
box-shadow: 0 14px 30px -20px var(--sh);
/* ticket cards — more pronounced */
box-shadow: 0 16px 32px -18px var(--sh);
/* small floating elements (bell, badges) */
box-shadow: 0 6px 14px -8px var(--sh);
```

---

## Components

**Cards** — `var(--paper)` background, `1px solid var(--pline)` border, `--r-card` radius, `--sh`-tinted shadow. Used for: the shuttle schedule card, ticket detail expansions, the ticket boarding pass itself.

**Buttons / pills** — always `--r-btn` (full pill), always a gradient fill (`--grad-ox` or `--grad-gold`) when "primary," white/`--paper` fill with colored text when "on a colored surface" (e.g. Buy/Schedule/Live pills sitting on a gradient ticket card).

**Tear-off ticket cards** (Patancheru/Miyapur route cards, and the boarding pass) — the signature motif: a dashed vertical perforation with circular punch-hole notches. The notch math matters — get it wrong and it looks like plain circles instead of clean bitten semicircles:

```css
/* circle center must sit exactly on the card edge for a clean 50/50 semicircle */
/* offset = -(margin + radius) */
.rc-vp { margin: 16px 0; }
.rc-vp::before, .rc-vp::after {
  width: 16px; height: 16px; border-radius: 50%;
  background: var(--stage); /* punches through to the page color, not white */
}
.rc-vp::before { top: -24px; }   /* -(16 margin + 8 radius) */
.rc-vp::after  { bottom: -24px; }
```

**Chips / badges** — `--r-btn` radius, small (9–10.5px) bold uppercase text, tinted background at low opacity of the relevant color (e.g. `rgba(31,138,76,.12)` for the green "Paid"/"Live" chips).

**Icon badges** (ticket meta grid, Coming Next tiles) — rounded square or circle, `var(--paper2)` background, `var(--ox)` icon color, icons are inline SVG (stroke-based, `currentColor`, `stroke-width:2`, 24×24 viewBox) — never emoji, never a raster icon set.

---

## Logo & app icon

Source files: `assets/logo.svg` (favicon, in-app), `assets/logo.png` (Apple touch icon — SVG isn't supported there). Both are **provided assets, not AI-generated by an agent** — if a logo update is ever needed, get a new file from Chandan; don't have an agent redraw it (this was an explicit instruction from an earlier session — see `docs/PROGRESS.md`).

---

## Explicitly rejected directions

Worth recording so nobody re-proposes these:
- **Bento-grid layout** for the home screen (tried 2026-07-24, reverted same day — "the previous model was better")
- **Ivory & Oxblood palette** (the original Phase 1 palette) — fully replaced by Sunrise IITH; don't resurrect
- **"On time" status claims** on the ticket — we have no live tracking feed to back that up; use "Scheduled" instead
