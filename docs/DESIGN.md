# Sanchari — Design System

<img src="../assets/logo.svg" alt="Sanchari logo" width="96">

**Sanchari** — campus bus ticketing for the IIT Hyderabad community. The mark is a fan of stripes echoing the institute crest, reinterpreted as a road with a bus grounded on it; the sun above stands in for the crest's circle.

This is the single source of truth for the app's visual language. If you're building a new screen, match this exactly — don't invent new colors, fonts, or radii. If something here is wrong or the code has drifted from it, fix whichever one is stale and note it in `docs/PROGRESS.md`.

The live implementation of every token below is `assets/app.css`. This document explains and catalogs it; `app.css` is canonical if the two ever disagree.

---

## September 25 visual refinement

The current home is campus-first: the working Main Gate–Hostel Circle
timetable with a compact date beside its heading, then direct-Buy Patancheru
and Miyapur cards. The decorative hero
was removed at the owner’s request; its artwork remains an unused source asset. The shuttle value follows scheduled 15-minute intervals in IST; it is
not a GPS prediction. The owner asked for a cleaner design without changing
features, so the layout stays a compact centered app rather than a dashboard.

`assets/app.css` is canonical. The current UI uses locally hosted Outfit under
its Open Font License, warm ivory `#faf5ee`, dark espresso `#30231e`, muted
brown `#66554b`, and stronger sunset orange and saffron card gradients. Cards
have 23px corners, careful white space and warm low-opacity borders/shadows.
The date is a readable `Fri, 25 Sep` chip beside the campus heading. The two
directions use opposite arrows
and distinct terracotta/amber accents. Each scheduled countdown displays
`mm:ss`, derived from the same IST 0/8-minute phases as the expanded timetable;
seconds are a clock display, not a location feed. The full-schedule control is a
high-contrast dark-orange bar. The primary route times, fares and shuttle
countdown are the most prominent numbers. Tomorrow's departures use `Tom` to
keep the time on one line; today’s time appears without
repeating the date. Buttons keep a visible focus ring and reduced-motion
behavior. The palette/type catalogue below records the earlier sunrise system;
use the CSS tokens for exact current values.

## Palette — "Sunrise IITH"

Named for the gradient in the hero illustration (`assets/hero.png`): a vivid orange sun fading to gold. The whole app's color language is sampled from that image.

| Token | Hex | Swatch | Usage |
|---|---|---|---|
| `--stage` | `#faf5ee` | <span style="display:inline-block;width:14px;height:14px;background:#faf5ee;border:1px solid #ccc;vertical-align:middle"></span> | Page background |
| `--paper` | `#fffefa` | <span style="display:inline-block;width:14px;height:14px;background:#fffefa;border:1px solid #ccc;vertical-align:middle"></span> | Card / surface background |
| `--paper2` | `#fff0e3` | <span style="display:inline-block;width:14px;height:14px;background:#fff0e3;border:1px solid #ccc;vertical-align:middle"></span> | Inset surfaces — toggle tracks, icon badges, chips |
| `--paper3` | `#fff5e9` | <span style="display:inline-block;width:14px;height:14px;background:#fff5e9;border:1px solid #ccc;vertical-align:middle"></span> | Recessed/expanded panels that need to visibly separate from the page — e.g. the open schedule dropdown. A lighter warm surface that separates the open schedule from the surrounding page with a subtle inset edge. |
| `--ink` | `#30231e` | <span style="display:inline-block;width:14px;height:14px;background:#30231e;border:1px solid #ccc;vertical-align:middle"></span> | Primary text |
| `--m2` | `#66554b` | <span style="display:inline-block;width:14px;height:14px;background:#66554b;border:1px solid #ccc;vertical-align:middle"></span> | Muted text / labels |
| `--ox` | `#bd3c1e` | <span style="display:inline-block;width:14px;height:14px;background:#bd3c1e;border:1px solid #ccc;vertical-align:middle"></span> | Primary brand / accent (red-orange) |
| `--ox2` | `#932a1b` | <span style="display:inline-block;width:14px;height:14px;background:#932a1b;border:1px solid #ccc;vertical-align:middle"></span> | Oxblood-dark — gradient shade, pressed states |
| `--gold` | `#e9a62f` | <span style="display:inline-block;width:14px;height:14px;background:#e9a62f;border:1px solid #ccc;vertical-align:middle"></span> | Secondary accent (Miyapur route, gold gradient) |
| `--goldt` | `#82500f` | <span style="display:inline-block;width:14px;height:14px;background:#82500f;border:1px solid #ccc;vertical-align:middle"></span> | Gold text / dark gold shade |
| `--live` | `#226443` | <span style="display:inline-block;width:14px;height:14px;background:#226443;border:1px solid #ccc;vertical-align:middle"></span> | Live status, "Paid" badge, verified state — **semantic green, not a brand color** |
| `--pline` | `rgba(120,90,70,.14)` | | Hairlines / dividers |
| `--sh` | `rgba(180,90,40,.18)` | | Shadow tint (warm, not neutral gray) |

**Gradients** (both sampled from the hero sun — see `assets/app.css` for exact stops):
```css
--grad-ox:   linear-gradient(135deg,#ed6937 0%,#d94724 48%,#a82b20 100%);
--grad-gold: linear-gradient(135deg,#ffe19a 0%,#f3b640 51%,#d5891b 100%);
```
Used on the Patancheru and Miyapur ticket cards. Shuttle badges and active direction controls use darker companion gradients for readable labels.

**Rule:** every shadow, divider, and neutral in this system has a warm bias toward the orange accent — never pure gray (`--pline`, `--sh` are both `rgba` tints of brown/orange, not black). Don't introduce a cold gray; it'll read as unconsidered against everything else.

---

## Typography

```css
@font-face { font-family: Outfit; src: url("fonts/outfit-variable.ttf") format("truetype"); font-weight: 100 900; font-display: swap; }
font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
```

One locally hosted variable typeface with platform fallbacks. Outfit keeps the
friendly rounded character while improving clarity and consistency across
phones and desktops. Its OFL license is in `assets/fonts/OFL-Outfit.txt`.

**Observed type scale** (no formal `--fs-*` tokens yet — these are the sizes actually in use):

| Role | Size | Weight | Notes |
|---|---|---|---|
| Hero headline | 30px | 660 | `letter-spacing:-.02em` |
| Ticket route / card title | 20px | 650 | |
| Body / route names | 13–15px | 500–630 | |
| Fare / time numerals | 19–28px | 700–720 | always `font-variant-numeric: tabular-nums` |
| Labels (uppercase eyebrows) | 10–12px | 700–750 | `letter-spacing: .1em` to `.14em`, uppercase |
| Buttons / pills | 12–13px | 600–700 | |
| Muted / meta text | 11–13px | 450–550 | color `var(--m2)` |

**Numerals always get `tabular-nums`** wherever digits align in a column or update dynamically (fares, times, countdowns) — non-negotiable, prevents layout jitter.

---

## Radius system

Deliberately collapsed to **two tokens** — see `docs/PROGRESS.md` 2026-07-24 entry for why (it used to be four, and buttons were inconsistent as a result):

```css
--r-card: 23px;   /* every card, ticket, modal-like surface */
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

**Buttons / pills** — always `--r-btn` (full pill). The selected direction uses a dark companion gradient; controls on ticket cards use white or light peach fills with dark labels.

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
