# IITH Sanchari

Bus ticket booking and live schedules for the IIT Hyderabad community — the free campus shuttle plus the paid outstation routes to Patancheru and Miyapur. Pick a route, pay in-app, get a QR boarding pass, instead of paying the driver cash and writing your UTR in a paper register.

Built by a student, for the IIT Hyderabad community, in coordination with the IITH Transport Department. **Not** official institute infrastructure and not an institute-endorsed platform — see [`legal.html`](legal.html).

**Live:** [transport.iith.online](https://transport.iith.online)

## Start here

If you're an AI agent (Claude, Gemini, Codex, or otherwise) or a new contributor picking this up:

1. Read [`docs/PROGRESS.md`](docs/PROGRESS.md) first — project state, what's done, what's broken, and every decision with its reasoning. Don't assume you have context from a prior conversation; this file is the actual continuity mechanism.
2. Read [`docs/DESIGN.md`](docs/DESIGN.md) before touching anything visual — colours, type, radii and component patterns are locked and documented there.
3. Check [`KNOWN_ISSUES.md`](KNOWN_ISSUES.md) — what's already known to be
   broken or unfinished, and what's been fixed. Update it when either changes.
4. **After you make changes, append a dated entry to `docs/PROGRESS.md`.** Not optional — it's how the next person avoids re-discovering the same bugs.

## Stack

| Layer | Choice | Why it's locked |
|---|---|---|
| Frontend | Plain HTML + CSS + vanilla JS | No framework, no bundler, no npm, **no build step** — files are served exactly as committed |
| Backend | PHP 8, no framework, no Composer | Raw `cURL` to the payment gateway's REST API; Hostinger shared hosting doesn't guarantee Composer or SSH |
| Database | MySQL — **not yet provisioned** | Schema drafted in `docs/BUILD_SPEC.md`; the ticket flow currently works around its absence |
| Payments | Hosted gateway checkout, server-side fare authority, HMAC-signed tickets | The browser never dictates price, and never sees card/UPI details |
| QR | Vendored `qrcode-generator` (MIT), `assets/qrcode.js` | Local copy, no CDN dependency |
| Hosting | Hostinger shared hosting, GitHub → Hostinger Git auto-deploy | Push to `main` deploys live |

Two deployment gotchas that have each cost real debugging time:

- **`api/config.php` is never deployed by git.** It's git-ignored and must be created/edited directly on the server via Hostinger File Manager. This has repeatedly been the actual cause of "the code is fixed but it's still broken".
- **Hostinger's CDN caches static assets for 7 days.** Bump the `?v=N` query param on `assets/app.css` / `assets/app.js` in every page that references them whenever either changes, or visitors keep getting stale files.

## Folder structure

```
index.html, ticket.html, legal.html   ← served pages (repo root = public_html — don't move these)
assets/                                ← served CSS / JS / images
api/                                   ← served PHP endpoints (api/config.php git-ignored: real secrets)
docs/                                  ← PROGRESS.md, DESIGN.md, BUILD_SPEC.md, AGENT_PROMPT.md
inspirations/                          ← locked design references; raw-drops/ is git-ignored
```

### API endpoints

| Endpoint | Does |
|---|---|
| `api/create-order.php` | Looks up the fare **server-side** from `fares.php`, derives the route name and journey duration from the route id, creates a gateway order |
| `api/verify-payment.php` | Recomputes the payment signature to confirm the payment is genuine, then issues an HMAC-signed ticket token |
| `api/verify-ticket.php` | Verifies a ticket token's HMAC. `ticket.html` gates its Verified/Paid/Valid markers on this; the future driver-scan screen verifies here too |
| `api/fares.php` | Server-side route table — fare and journey duration per route. Single source of truth for pricing |
| `api/razorpay.php` | Shared helpers: config loading, raw cURL, JSON in/out |

## Local development

There's no build step — serve the folder statically and open it:

```bash
python3 -m http.server 8138
```

The homepage, schedules and ticket rendering all work this way. The `api/*.php` endpoints need a PHP runtime (they don't run under `http.server`), so payment and ticket verification have to be exercised on the server or a local PHP host.

## Setup

```bash
cp api/config.sample.php api/config.php
```

Then fill in the gateway key/secret and a `TICKET_HMAC_SECRET` (generate with `openssl rand -hex 32` — it signs ticket QR tokens and is unrelated to the gateway credentials). `api/config.php` is git-ignored and must never be committed.

## Known gaps

Tickets are **not single-use and have no expiry window** — a genuine ticket can be shown more than once. Closing that needs the database and the driver-scan screen; `api/verify-ticket.php` documents the gap inline. See `docs/PROGRESS.md` for the current punch-list.
