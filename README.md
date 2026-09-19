# Sanchari

Transport companion for the IIT Hyderabad community: the free campus shuttle
schedule plus paid outstation routes (Patancheru, Miyapur) with in-app checkout
and a signed QR boarding pass. Student-built in coordination with the IITH
Transport Department. It is **not** official institute infrastructure; see
[`legal.html`](legal.html).

| | |
|---|---|
| **Live** | [sanchari.iith.online](https://sanchari.iith.online) |
| **Repository** | `github.com/chandanmettu/iith-sanchari` (public). The local folder is still called `iith-transport`. |
| **Push via** | SSH host alias `github-iith-transport` (remote `git@github-iith-transport:chandanmettu/iith-sanchari.git`) |
| **Deploy** | Hostinger Git auto-deploy from `main`. **A push is a production release.** |
| **Status** | Live. The payment path is built but has never completed one real payment, so ticketing is unproven. |

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
| Database | MySQL. **Not provisioned yet.** A ticket lives only in its URL. |
| Payments | Hosted checkout. Fares are server-authoritative and tickets are HMAC-signed. |
| QR | Vendored `qrcode-generator` (MIT) in `assets/qrcode.js`, so no CDN is needed. |

```text
index.html            home: shuttle countdown, route cards, schedules, checkout
ticket.html           boarding pass (verifies its token against the server)
legal.html privacy.html terms.html refund.html   policy pages
assets/               app.css, app.js (timetables live here), logo, hero, qrcode.js
api/fares.php         route table, the single source of truth for fare and duration
api/create-order.php  server-side fare lookup, then creates the provider order
api/verify-payment.php  checks the payment signature, then issues the signed ticket token
api/verify-ticket.php   HMAC check used by ticket.html (and later the driver scanner)
api/razorpay.php      shared helpers: config, cURL, JSON (filename predates the policy, see below)
api/config.sample.php template for the server-only api/config.php
docs/                 PROGRESS, DESIGN, BUILD_SPEC, AGENT_PROMPT
inspirations/         design references (raw-drops/ is git-ignored)
```

## Run locally

```bash
python3 -m http.server 8138
```

The pages, schedules and ticket rendering all work statically. PHP is **not**
installed on the dev Mac, so `api/*.php` can only be exercised on Hostinger.

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

1. SAN-004: swap the payment gateway, then run one real checkout-to-ticket test (SAN-003).
2. SAN-002: replace the placeholder campus-shuttle cadence with real timings, or label it as indicative.
3. Provision MySQL to unlock single-use tickets, "My Tickets", the driver scanner and email delivery (SAN-001, SAN-009).
4. Rate-limit order creation (SAN-007) and add error alerting (SAN-008).
