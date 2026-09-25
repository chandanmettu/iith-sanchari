# Sanchari

Transport companion for the IIT Hyderabad community: the free campus shuttle
schedule plus paid outstation routes (Patancheru, Miyapur) with in-app checkout
and a signed QR boarding pass. Student-built in coordination with the IITH
Transport Department. It is **not** official institute infrastructure; see
[`legal.html`](legal.html).

| | |
|---|---|
| **Live** | [sanchari.iith.online](https://sanchari.iith.online) |
| **Repository** | `github.com/chandanmettu/iith-sanchari` (public). |
| **Push via** | SSH host alias `github-iith-transport` (remote `git@github-iith-transport:chandanmettu/iith-sanchari.git`). The alias name predates the rename, so leave it. |
| **Deploy** | Hostinger Git auto-deploy from `main`. **A push is a production release.** |
| **Status** | Live schedule site; a redesigned UI and persistent ticketing flow are prepared locally. Booking remains disabled until activation and acceptance tests. |

## Start here

1. [`docs/PROGRESS.md`](docs/PROGRESS.md) holds the state, the decisions and a dated log. It is how context carries between agents. **Append a dated entry after every change.**
2. [`KNOWN_ISSUES.md`](KNOWN_ISSUES.md) lists what is knowingly broken or unfinished (`SAN-` numbers). Read it before you assume a bug is new.
3. [`docs/DESIGN.md`](docs/DESIGN.md) documents the "Sunrise IITH" palette, type and components. They are locked, so read it before any visual change.
4. `docs/BUILD_SPEC.md` has the original spec and the drafted DB schema. `docs/AGENT_PROMPT.md` is the original agent brief.

## Stack

| Layer | Choice |
|---|---|
| Frontend | Plain HTML, CSS and vanilla JS. No framework, no npm, **no build step**. |
| Backend | PHP 8 with no framework or Composer. It talks to the payment provider's REST API through raw cURL. |
| Database | MySQL schema prepared in `api/schema.sql`; production database setup is pending. |
| Payments | Hosted redirect checkout adapter; server-authoritative fares, persistent orders and signed ticket links. |
| QR | Vendored `qrcode-generator` (MIT) for tickets and `jsQR` 1.4.0 (Apache-2.0) for camera decoding; licence retained in assets. |

```text
index.html               route schedules and journey review
payment.html             payment recovery after redirect or interruption
tickets.html             this browser's saved bookings
ticket.html              server-verified QR travel pass
scanner.html             authenticated driver check-in
operations.html          administrator recheck/revocation
assets/routes.json       shared fares, timetable and boarding data
api/common.php           database, ticket signing and access helpers
api/hosted.php           hosted-checkout protocol adapter
api/create-order.php     validate journey, persist order, create checkout
api/verify-payment.php   recover booking and check provider status
api/verify-ticket.php    read-only ticket state
api/check-in.php         atomic authenticated single-use boarding
api/webhook.php          authenticated payment/refund notifications
api/reconcile.php        CLI-only scheduled recovery
api/schema.sql           production MySQL/MariaDB tables
api/config.sample.php    safe template; real config is server-only
```

## Run locally

Serve the directory with PHP 8.1+ and PDO/cURL to exercise API paths, or use
`python3 -m http.server 8138` for the disabled-booking visual preview. The current
local preview uses a temporary PHP runtime at `http://127.0.0.1:8138`.

Booking is deliberately disabled with the existing configuration. Do not copy
production secrets into local test fixtures. See [activation and acceptance](docs/LAUNCH.md).

## Release

1. Bump `?v=N` on `assets/app.css` / `assets/app.js` in **every** page that loads them, because Hostinger's CDN caches assets for 7 days.
2. Commit and push to `main`. Hostinger pulls within a minute or two.
3. Check the live site with a cache-busted request, for example `curl -s "https://sanchari.iith.online/assets/app.js?cb=$RANDOM" | grep <new-symbol>`.

**Server-only secrets:** `api/config.php` is git-ignored and is never deployed
by git. It holds the provider key and secret plus `TICKET_HMAC_SECRET`
(generate one with `openssl rand -hex 32`). Edit it in the Hostinger File
Manager. "The code is fixed but it's still broken" has more than once been a
missing or stale `config.php`.

## Rules

- **Never name the payment provider** in user-facing copy or new code and docs. Use generic wording. The old gateway's name still appears in this public repo (`api/razorpay.php`, the checkout script tag in `index.html`, some code comments). Clean it up as part of the gateway swap (SAN-004), not piecemeal.
- The repo root is `public_html`, so every committed file is publicly downloadable. Keep secrets and internal notes out of it. Account, KYC and institutional detail belong in the private Notion tracker.
- Don't reword `legal.html`'s claims about the institute relationship without checking the tracker.
- Logo and hero artwork are provided assets. Don't regenerate them.

## Next

Complete [activation and acceptance](docs/LAUNCH.md), including the production
database, approved payment account, confirmed operational data, staff access,
webhooks/cron/alerting and supervised payment-to-boarding/refund/settlement tests.
Local tests do not establish production readiness. No deployment has been made
as part of the September 25 preparation.
