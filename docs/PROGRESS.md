# Sanchari — Progress Log

**Read this whole file before touching the code.** It's the compressed memory of every session that's worked on this project. Don't rely on chat history from a previous conversation being available to you — it won't be. This file is how continuity actually happens.

## Rule for every agent (Claude, Gemini, Codex, or human) touching this repo

**After you make changes, append a new dated entry to the top of the [Log](#log-newest-first) section below — newest first, never delete or rewrite past entries.** Use the template at the bottom of this file. Be specific: what changed, why, what's still broken. A vague entry is worse than no entry — the next agent needs to make real decisions from what you write.

If you're a human reading this and an agent just finished work without logging it, ask it to add an entry before you close the session.

> **This file is in the repository, so treat it as publishable.** Keep it to
> engineering: what changed, why, what's still broken. Account ownership, KYC,
> institutional status, business arrangements and step-by-step details of unfixed
> vulnerabilities do **not** belong here — they go in the private tracker. This
> rule exists because those things were in this file while the repo was public.

---

## Project at a glance

- **What:** ticket booking + live schedule app for campus shuttle and outstation buses (Patancheru, Miyapur) serving the IIT Hyderabad community. Developed in coordination with the IIT Hyderabad Transport Department.
- **Live at:** [transport.iith.online](https://transport.iith.online)
- **Repo:** [github.com/saichandanmettu/iith-transport](https://github.com/saichandanmettu/iith-transport) → Hostinger Git auto-deploy (push to `main` = live within seconds, but see the CDN caching gotcha below)
- **Local path:** `~/My Project Builds/iith-transport`
- **Design system:** [`docs/DESIGN.md`](./DESIGN.md) — read before touching any UI
- **Original full spec:** [`docs/BUILD_SPEC.md`](./BUILD_SPEC.md) — historical, partially superseded (palette changed entirely, nav/accounts scope changed — this log is more current than that doc for anything it disagrees with)

## Stack (locked, don't relitigate without strong reason)

- **Frontend:** static HTML/CSS/JS. No framework, no build step, no npm, no bundler.
- **Backend:** PHP 8, no framework, no Composer. Raw `cURL` to the payment gateway REST API — chosen specifically because Hostinger shared hosting doesn't guarantee Composer/SSH access.
- **Database:** MySQL — **not yet provisioned.** This is the single biggest open item; see below.
- **Hosting:** Hostinger shared hosting, deployed via GitHub → Hostinger Git integration.
- **Payments:** the gateway’s hosted checkout, orders created server-side (fare is never trusted from the client).
- **Font:** SF Rounded (`ui-rounded` stack) — see DESIGN.md.

## Folder structure

```
transport/
├── index.html, ticket.html, legal.html   ← served pages, stay at repo root
├── assets/                                ← served: app.css, app.js, logo.svg/png, hero.png, qrcode.js
├── api/                                   ← served PHP endpoints
│   └── config.php                         ← git-ignored, real secrets live here ONLY (see below)
├── docs/                                  ← this file, DESIGN.md, historical planning docs
└── inspirations/                          ← design-reference/ (locked mockups), raw-drops/ (git-ignored
                                              source images — duplicates of what's in assets/, kept
                                              locally for reference only, never committed)
```

**Do not move `index.html`/`ticket.html`/`legal.html`/`assets/`/`api/` out of repo root** — Hostinger's Git deploy serves the repo root as `public_html` directly. Moving them requires a manual hPanel deploy-path change first, or the live site goes down.

## Current state (as of 2026-07-24)

### Done
- Full home screen: internal shuttle schedule (live countdown), Patancheru/Miyapur route cards with real fare/schedule data, live-tracker links, expandable schedule panels
- Complete visual identity: "Sunrise IITH" palette, logo, SF Rounded typography — see DESIGN.md
- the gateway’s hosted checkout: order creation (server-side fare lookup, never client-trusted), signature verification, all live-tested against the real the payment gateway API
- Boarding-pass ticket page (`ticket.html`) — real scannable QR (vendored `qrcode.js`, no CDN), journey duration + estimated arrival (not a stale countdown), icon-labeled meta grid, "Verified" stamp
- `legal.html` — privacy/terms/payments page, linked from footer
- "Coming Next" preview grid (static, non-interactive) previewing roadmap features

### Not done / open items, roughly in priority order
1. **MySQL database not provisioned.** Blocks: persistent "My Tickets" (right now a ticket only exists at its one-time URL — close the tab and it's gone), driver check-in / single-use QR verification, ticket-by-email. This is the next big unlock — most other open items depend on it.
2. **Payment provider onboarding is the critical-path blocker.** Test credentials have been invalidated repeatedly for reasons outside our control — confirmed each time by querying the provider API directly, so not a caching or code issue. **The full paid flow has never been confirmed end-to-end with a real successful payment**: everything up to and including the success callback is built and tested, but the "payment succeeds → redirect fires" moment has not yet happened. Provider and account specifics are in the private tracker.
3. No accounts/login — guest checkout only, by design for now (do not add name/signup fields without being asked; this was a deliberate scope cut).
4. "Buy" → "Show Ticket" button state not implemented (needs either localStorage as a device-local stopgap, or the database for a real solution).
5. Ticket is not emailed. the payment gateway may send its own generic payment receipt if it collects an email, but that's not our ticket — building real delivery needs a transactional email service (Hostinger's PHP `mail()` is unreliable/spam-flagged), which needs its own API key/account decision.
6. Driver-scan / check-in screen not built (needs #1).

### Constraints and decisions — don't re-litigate these without a real reason
- No Composer/npm/build step, ever, on this hosting.
- The payments account, its ownership and the exact institutional relationship are recorded in the private project tracker, not here. `legal.html` states the relationship deliberately and precisely — **do not reword or strengthen that page's claims** without checking the tracker first.
- No name field collected at checkout (the payment gateway only collects phone by default) — ticket shows phone, not a passenger name.
- "Departs" on the ticket says "Scheduled," never "On time" — no live tracking feed exists to back up an on-time claim.
- Don't have an agent regenerate the logo/hero artwork — those are provided assets from Chandan, not agent-generated. If a change is needed, ask for a new file.
- Bento-grid layout was tried and explicitly reverted ("the previous model was better") — don't re-propose it.

---

## Log (newest first)

### 2026-08-19 — Security audit: forged tickets were rendering as genuine

**The bug.** `ticket.html` rendered a ticket's contents without verifying its signature, so the trust markers could appear on a ticket the server never issued. With no driver scanner yet, tickets are checked by eye, which made that a real fare-evasion route. Details of the method are in the private tracker, not here.

**The fix.** Added `api/verify-ticket.php` — recomputes `hash_hmac('sha256', payloadB64, TICKET_HMAC_SECRET)` and compares with `hash_equals`. `ticket.html` now calls it on load and the trust markers (stamp / Paid / Valid) are CSS-gated behind an `is-verified` class that only a 200 from that endpoint adds.
- Ticket *details* still render straight from the token, so the ticket still opens with no signal at the bus stop. Only the trust markers wait on the server.
- Three states: verified (green Valid), rejected (red "Not valid" + explanatory banner), network failure (amber "Unverified" — deliberately does not accuse a paying rider of forgery just because the stop has no signal).
- This endpoint is also the seam the future driver-scan screen verifies against.

**Also fixed in the same pass:**
- **Route name was client-controlled.** `route_display` and `journey_display` were sent up from the browser and printed on the ticket, so you could pay the ₹30 Patancheru fare and have the ticket print "IITH → Miyapur". Both are now derived server-side in `create-order.php` from the route id; `fares.php` gained `journey_mins` so the duration has a server-side source too. `app.js` no longer sends either field. Departure/arrival clock strings still come from the client (informational only) — moving them server-side means duplicating the timetable out of `app.js`, so that waits for the DB.
- **Error responses leaked internals.** `create-order.php` returned the raw gateway response body and a message naming `api/config.php`; `razorpay.php` told the browser which files to copy. All now log server-side via `error_log()` and return generic text. Status codes corrected too (gateway auth failure was returning 401 to the browser, now 503).

**Still open (both need the DB, unchanged by this pass):** tickets are not single-use and have no expiry window, so a *genuine* ticket is still replayable. `verify-ticket.php` documents both gaps inline. The ticket-page footnote was reworded from "cryptographically signed and genuine" (which was false for forged tokens) to state plainly that a genuine ticket can still be shown more than once.

**Performance.** `assets/logo.svg` was 4.9 MB — an SVG wrapper around two base64-embedded PNGs, one of them 7071×7071 — and it is the favicon on all three pages plus the 38×38 header mark. Re-embedded at 512px: **4.86 MB → 0.16 MB (-97%)**. `logo.png` (apple-touch-icon) 1024px/355 KB → 180px/23 KB. Artwork visually unchanged, verified in-browser. Cache-bust bumped (`app.css?v=9`, `app.js?v=6`) per the CDN gotcha below.

**Housekeeping.** Removed tracked-adjacent `.DS_Store` files; dropped dead code (`.tk-valid`/`.tk-star` CSS, unused `isMiya`, `fmtDuration`). Repo moved locally to `~/My Project Builds/iith-transport` and the git remote repointed to `saichandanmettu/iith-transport` after the GitHub username change.

**Note for whoever deploys this:** PHP is not installed on the dev Mac, so `api/verify-ticket.php` was validated against a Python mock replicating its exact logic (genuine token → 200 valid, forged → 400 bad_signature) and the client flow was tested end-to-end against that mock. **The PHP file itself has not executed on a real PHP runtime yet — smoke-test it on Hostinger before trusting it.** Quickest check: open a real ticket URL and confirm the stamp appears; then change one character of the signature in the URL and confirm it flips to "Not valid".

### 2026-07-27 — Schedule dropdown: added a real "recessed panel" token
- Follow-up to the fix below: mapping `.sched-panel` onto `var(--paper2)` solved the "stray hex" inconsistency but not the actual complaint — `--paper2` (`#fbe9db`) and `--stage` (`#fdf1e6`, page bg) are only ~2% apart in lightness, so the open panel was still visually indistinguishable from the page and the button above it. Chandan called this out directly.
- Added a new token `--paper3: #f5dcb8` — genuinely deeper/warmer, for recessed or expanded panels that need to visibly separate from the page (currently just the schedule dropdown). Documented in `docs/DESIGN.md`'s token table.
- `.sched-panel` now uses `var(--paper3)` plus a real inset shadow (`inset 0 3px 8px -4px rgba(80,40,10,.22)`) for a pressed-in look, not just a flat color swap. Bumped `assets/app.css?v=` 7 → 8.
- Lesson: matching an *existing* token isn't automatically correct if the token itself doesn't provide the contrast the UI actually needs — check the rendered result, not just "does this look like it reuses a variable."

### 2026-07-27 — Fixed mismatched schedule-dropdown background color
- `.sched-panel` had a stray hardcoded `#f3e6d5` background not present anywhere in the design token set — created a visible color seam against the page background when the "See full schedule" panel opened. Flagged by Chandan as looking like a design mistake.
- Fixed to `var(--paper2)`, the documented inset-surface token (same as the button above it and other inset UI). Bumped `assets/app.css?v=` 6 → 7.
- Lesson: any new hardcoded hex color outside `assets/app.css`'s `:root` token block is very likely a bug, not an intentional choice — grep for stray hex values if something looks visually "off."

### 2026-07-27 — Operator legal name added to footer (payment-gateway KYC requirement)
- Payment gateway KYC review flagged that the operating individual's legal name wasn't visible anywhere on the site.
- Added a small muted line to the homepage footer (`index.html`, `.footer-op` class, new CSS in `assets/app.css`) and to `legal.html`'s existing `.legal-foot` block: "Sanchari is operated by SAICHANDAN METTU, in coordination with the IIT Hyderabad Transport Department."
- Styled to match existing footer text exactly (small, muted, `var(--m2)`) — compliance addition, not a new visual section.
- Bumped `assets/app.css?v=` from 5 to 6 across `index.html`/`legal.html`/`ticket.html` since the stylesheet changed (Hostinger CDN caching gotcha, see earlier entries).

### 2026-07-27 — legal.html: genericized payment-gateway wording
- `legal.html` no longer names a specific payment gateway anywhere in its visible text (Privacy §02, Payments §03) — replaced with generic "payment gateway" / "the payment gateway" phrasing. No link to any gateway's external privacy policy either.
- Reason: business/vendor-facing, not to be re-litigated here — ask Chandan before naming a specific provider in any user-facing copy again.
- Nothing else on the page changed; contact email stays `office.transport@iith.ac.in`.
- Note: this is a **content-only** change. The actual payment integration code (`api/create-order.php`, `api/verify-payment.php`, `api/razorpay.php`, the `checkout.razorpay.com` script tag in `index.html`) is untouched and still the payment gateway-specific under the hood — don't assume the backend has changed just because the copy is now vendor-neutral.

### 2026-07-27 — "Coming Next" section temporarily hidden
- Wrapped the entire "Coming Next" 3×3 tile grid (`<div class="comingnext">...</div>`, `index.html` around line 111) in an HTML comment. Markup left completely intact inside the comment, nothing deleted.
- To restore: open `index.html`, find the block starting `<!-- COMING NEXT — temporarily hidden ...` and delete the opening comment-explanation lines and the trailing `END COMING NEXT -->` line, leaving the `<div class="comingnext">` markup live again.
- Special Buses section and everything else on the page untouched. `.comingnext` only ever set `margin-top:26px` in `assets/app.css`, so removing it from the render just lets the footer's own `margin-top:28px` apply directly — no layout gap, verified visually.
- Reason: Chandan wanted it hidden from the live homepage without losing the content, to bring back later with a single change.

### 2026-07-24 — Repo reorganization + persistent docs
- Moved `BUILD_SPEC.md`, `AGENT_PROMPT.md` → `docs/`; `design-reference/` → `inspirations/design-reference/`; raw source image drops → `inspirations/raw-drops/` (git-ignored, duplicates of what's already in `assets/`)
- Added `docs/DESIGN.md` (full design system reference) and this file
- Reason: session context had grown very large; this is the persistence mechanism so any future session/agent can pick up without replaying the whole conversation

### 2026-07-24 — Ticket page icon/badge polish
- Added icon badges + subtitles ("Approx.", "Scheduled", weekday) to the ticket meta grid, per a reference screenshot Chandan provided
- Green "Paid" pill badge instead of plain "· Paid" text
- Dropped the `+91` prefix from the displayed contact number (implied for India) and fixed it wrapping awkwardly onto two lines

### 2026-07-24 — Ticket: journey time instead of a frozen countdown
- The ticket previously baked in a live "~X min" countdown at purchase time, which goes stale immediately since the ticket page never re-renders after load. Replaced with journey duration (Patancheru ~1 hr, Miyapur ~1 hr 40 min, confirmed by Chandan) and a computed estimated-arrival time — both evergreen, correct no matter when the ticket is viewed later
- Added a rotated "VERIFIED" stamp seal next to the QR

### 2026-07-24 — Bento-grid redesign, then reverted
- Tried restructuring the home screen top section into a bento-style tile grid (shuttle rows as independent side-by-side cards). Chandan: "I don't like this... revert back." Reverted via `git revert`, confirmed clean.

### 2026-07-24 — Ticket screen + the payment gateway checkout wiring
- Built the full boarding-pass ticket page: real QR (vendored `qrcode-generator` by kazuhikoarase, MIT, no CDN), signed HMAC token (separate secret from the payment gateway secret), fetches phone number from the payment gateway's Payments API post-verification
- No database yet, so the ticket is **stateless by design** — everything needed to render it is encoded in the signed token itself, round-tripped through the payment gateway's own order `notes` field
- Wired the Buy button's success handler to redirect to `ticket.html?t=<token>` instead of showing an alert

### 2026-07-24 — Payment integration debugging
- Multiple rounds of test credentials failing (`expired`, then authentication errors), confirmed independent of our code, config or caching. Account-level cause; details in the private tracker.
- A test payment failed on a card-eligibility rule rather than anything in our code — the generic test card commonly used online was rejected by the account's own settings.
- **Lesson worth keeping:** don't reason backwards from the frontend symptom. Query the provider's API directly for the error it actually recorded. That turned several dead ends into one-line answers.

### 2026-07-23/24 — the gateway’s hosted checkout built
- `api/create-order.php`, `api/verify-payment.php`, `api/razorpay.php` (shared cURL/config helpers), `api/fares.php` (server-side fare table — frontend never dictates price)
- `api/config.php` (real secrets) is git-ignored; `api/config.sample.php` is the committed template
- **Manual step every time credentials change:** the server's `api/config.php` must be edited directly via Hostinger File Manager — it is NOT deployed by git push, by design (it's git-ignored). This has repeatedly been the actual cause of "it's still broken" when the code itself was already fixed.

### 2026-07-23/24 — Full visual retheme: "Sunrise IITH"
- Replaced the original Phase 1 "Ivory & Oxblood" palette entirely with a red-orange/gold palette sampled from the IITH crest and the hero illustration
- New logo (provided by Chandan, not agent-generated), new hero illustration, ticket cards redesigned as full-bleed gradient "tickets" with a corrected tear-off perforation (see DESIGN.md for the exact math — it was wrong on the first attempt, looked like plain circles instead of clean semicircles)
- Font switched to SF Rounded after a side-by-side comparison of 5 options
- Bottom nav removed (Cabs/Tickets/Me were unreachable placeholders); added a static "Coming Next" 3×3 grid instead, listing actual roadmap items (Cab Sharing, Lost & Found, Tourist Places, Railway Station, Airport, Complaints, Request a Trip, Rewards, Account Management)
- Added `legal.html` (privacy/terms/payments), linked from footer, contact `transport@iith.ac.in`
- Fixed a Hostinger CDN caching bug: static assets were cached 7 days and didn't bust on deploy, so pushed code silently wasn't reaching visitors. Fixed by adding `?v=N` cache-busting query params to `assets/app.css`/`assets/app.js` in `index.html`/`legal.html` — **bump the version number on every future CSS/JS change**, or the CDN will serve stale files again.

### 2026-07-23 — Phase 1: initial build
- Static HTML/CSS/JS home screen built from a locked design reference (`inspirations/design-reference/`): internal shuttle schedule, Patancheru/Miyapur route cards with real fare/schedule data (fares: ₹30 / ₹100), live-tracker links
- Original palette "Ivory & Oxblood" (since fully replaced, see above)
- Pushed to GitHub (`saichandanmettu/iith-transport`) via a repo-scoped SSH deploy key; connected to Hostinger Git auto-deploy

---

## Entry template — copy this for your new entry

```markdown
### YYYY-MM-DD — Short title
- What changed
- Why
- What's still open / broken / next, if relevant
```
