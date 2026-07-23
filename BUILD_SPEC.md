# IITH Transit — Build Specification (HTML + PHP + MySQL on Hostinger)

You are building a campus mobility app for IIT Hyderabad. A senior designer has already **locked the visual design and the home screen**. Your job is to turn it into a real, running product and build the backend + remaining features **on top of the existing design** — without changing the look and feel.

> **Design reference lives in `/design-reference/`:**
> - `home.html` — the finished, interactive home screen. It is a single self-contained HTML/CSS/JS file (a phone-frame demo wraps it — ignore the wrapper/intro; the real app is the phone-screen content). **This IS the frontend** — you extend it, you do not rebuild it.
> - `ticket-and-driver-scan.html` — the issued boarding-pass ticket (centered QR) and the driver scan/verify screen.
>
> Open these, study them, and match the design system exactly for every new screen.

---

## 0. THE ONE HARD RULE

**Do not redesign anything.** The Ivory & Oxblood visual language, the tear-off route cards, the boarding-pass ticket, the gold departure times, the radius system, and all spacing are final after many rounds of iteration. The HTML in `/design-reference/` is the source of truth. Every new screen (buy flow, ticket, driver scan, tickets history, cabs, complaints…) must be built **in this same language** — reuse the exact tokens and components. If your output doesn't look like `home.html`, it's wrong. Do not introduce new fonts, colors, gradients, shadows, or radii.

---

## 1. Tech stack (locked — this is shared hosting, keep it simple)

| Layer | Choice | Notes |
|---|---|---|
| Frontend | **Plain HTML + CSS + vanilla JS** | Extend `design-reference/home.html`. **No framework, no build step, no npm.** Static files. |
| Backend | **PHP 8** (no framework) | Plain PHP scripts under `/api`. Use **PDO** (prepared statements) for MySQL. |
| Database | **MySQL** (included with Hostinger) | Manage via phpMyAdmin. Schema in `/sql/schema.sql`. |
| Payments | **Razorpay** (Orders API + Checkout.js) | User already has a Razorpay account (used it on a WooCommerce store). Ticket issued from the **webhook**. |
| Hosting | **Hostinger shared hosting** | Deploy via **GitHub → Hostinger Git** (auto-deploy to `public_html`). Free SSL. |
| Auth | Phase 2: **Guest + email**; Phase 5+: **Google OAuth** (`@iith.ac.in`) | PHP sessions + a `users` table. |

**Constraints that matter:**
- No Node.js, no Next.js, no React, no bundler. Everything must run on PHP shared hosting as-is.
- **Prefer raw cURL calls to the Razorpay REST API** over the Composer SDK, so there's no `vendor/` dependency to install on shared hosting. (If Composer is available via SSH you may use `razorpay/razorpay`, but don't require it.)
- No paid third-party services. Everything lives on the one Hostinger account.
- Frontend and backend are **same-origin** (both on the Hostinger domain), so no CORS setup needed. Frontend talks to backend with `fetch('/api/xxx.php')`.

---

## 2. Design system (port exactly — same as the reference)

**Palette — "Ivory & Oxblood"**
```
--stage:  #e7e1db   /* app background */
--paper:  linear-gradient(180deg,#fffdf9,#f7f1e9)  /* card surface */
--paper2: #f3ece2   /* inset surfaces / toggle tracks */
--ink:    #2a1518   /* primary text */
--m2:     #9a857f   /* muted text / labels */
--ox:     #7a1f2b   /* oxblood — primary/brand */
--ox2:    #66131d   /* oxblood dark (gradients, pressed) */
--gold:   #c39a44   /* gold accent */
--goldt:  #a8791a   /* gold TEXT for departure times (legible on ivory) */
--pline:  rgba(150,110,100,.22)  /* hairlines/dividers */
--live:   #1a7a4e   /* green — free/live status only */
```
**Radius system (strict):** cards/tickets 18px · toggle & segment tracks 14px · buttons (Buy, chips) 12px · segments inside a padded track 10px · capsule (999px) ONLY for status badges (Free, Live).

**Typography:** system font stack is fine (as in the reference); keep sizes/weights/hierarchy identical. Departure times are the focal point: large, weight 900, color `--goldt`, `tabular-nums`. Use `tabular-nums` for all times/fares. Ensure pages are **UTF-8** so `→` and `₹` render.

**Signature components (already in `home.html`):** tear-off route card (route + `at <gold time> · ~<relative>` + `Schedule`/`● Live` buttons + fare stub); internal-shuttle card (Free capsule, two direction rows, "See full schedule" card-dropdown); one section-level **From IITH / To IITH** toggle driving all special buses; the **boarding-pass ticket** with a big centered QR; the dark **driver scan** screen. Reuse these as JS-rendered components.

---

## 3. Real data (seed into MySQL)

**Internal shuttle** (Main Gate ⇄ Hostel Circle) — **free**. *Placeholder: 15-min interval*; real timings pending from the transport office — keep in the DB/config so it's swappable, don't hardcode in JS.

**Patancheru** — fare **₹30/seat**, runs **all days**. Live tracker: `https://tinyurl.com/4s6f94z8` (Patancheru→IITH, ~2 min delay).
- From IITH (IITH→PTC): `09:00 11:00 17:00 19:00 21:00 23:00`
- To IITH (PTC→IITH): `08:00 10:00 16:00 18:00 20:00 22:00`

**Miyapur** — fare **₹100/seat flat (any stop)**, **weekdays only (Mon–Fri, not on institute holidays)**, **one trip each way**. Live tracker: `https://app.fleetx.io/live/share/v2/eJwFwcERADAEBMCKzJwgKMfjlJHas6uBuC9S4TSVga341EgfK2HX%2BnCTjg%24drArI` (Miyapur→IITH).
- To IITH (onward) — 7:40 AM from Miyapur Metro, timed stops:
  `Miyapur Metro (Pillar 623) 7:40 · Madinaguda (Opp. Green Bawarchi Hotel) 7:54 · Chanda Nagar (Opp. Swagath Restaurant) 7:59 · BHEL Circle (BHEL Main Gate) 8:05 · Ashok Nagar (Near Indian Oil petrol bunk) 8:07 · Beeramguda (Opp. Vijetha Super Market) 8:10 · D Mart 8:11 · RC Puram (Near Ambedkar statue) 8:13 · Patancheru (Near bus stop) 8:24 · Muthangi 8:30 · Isnapur (X Road) 8:35`
- From IITH (return): departs **5:45 PM** from **C-Block back-side parking**, through all major institute stops, then to Miyapur.
- Terms: carry Institute ID (checked by driver/security); report 10 min before departure; non-refundable.

> The current manual process is "pay to the QR on the driver's register and write your UTR in the log." This app **replaces** that: rider pays in-app → gets a signed QR ticket → driver scans it.

---

## 4. Project structure

```
/  (repo root == Hostinger public_html)
  index.html                 # the app (extend design-reference/home.html)
  /assets/
    app.css                  # the design tokens + component styles
    app.js                   # frontend logic (schedules, countdowns, toggle, fetch calls)
    qrcode.min.js            # vendored client QR lib (no CDN)
  /api/
    config.php               # DB creds + Razorpay keys + secrets  (GIT-IGNORED)
    config.sample.php        # committed template with blank values
    db.php                   # PDO connection
    helpers.php              # json_out(), require_auth(), hmac_sign(), hmac_verify()
    create-order.php         # POST -> create Razorpay order (server-side amount)
    razorpay-webhook.php     # POST -> verify signature, mark paid, ISSUE TICKET
    my-tickets.php           # GET  -> current user's tickets
    verify-ticket.php        # POST -> driver scans QR: validate + mark used (single-use)
    /auth/  login.php, logout.php, me.php   # Phase 2: guest+email; later Google
    cabs.php complaints.php lost-found.php requests.php   # later phases
  /sql/
    schema.sql               # CREATE TABLE + seed data (Section 3)
  .gitignore                 # must include: /api/config.php
  README.md                  # setup + deploy steps
```

**Secrets:** never commit real keys. `api/config.php` is git-ignored and created on the server (via Hostinger File Manager) from `config.sample.php`. It holds: DB host/name/user/pass, `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`, and a `TICKET_HMAC_SECRET` (random 64-char string).

---

## 5. Database schema (MySQL)

Write `/sql/schema.sql`. Suggested tables (use `BIGINT AUTO_INCREMENT` PKs; unguessable random tokens where public):

```
users(id, email, name, role ENUM('student','driver','admin') DEFAULT 'student',
      tokens_balance INT DEFAULT 0, created_at)
routes(id, code, name, kind ENUM('shuttle','special'), is_paid TINYINT, live_url, notes)
schedules(id, route_id, direction ENUM('from_iith','to_iith'), depart_time TIME,
          days_mask TINYINT,          -- bitmask Mon..Sun; shuttle=interval flag in notes
          fare INT, active TINYINT DEFAULT 1)
stops(id, route_id, direction, name, landmark, seq INT, est_time TIME)
tickets(id, public_token CHAR(32) UNIQUE, user_id, route_id, direction,
        service_date DATE, fare INT,
        status ENUM('issued','used','expired','refunded') DEFAULT 'issued',
        qr_token VARCHAR(255),        -- signed token encoded in the QR
        issued_at, used_at NULL, used_by_driver NULL)
payments(id, ticket_id NULL, user_id, razorpay_order_id, razorpay_payment_id NULL,
         amount INT, status ENUM('created','paid','failed') DEFAULT 'created', created_at)
cab_requests(id, user_id, from_loc, to_loc, depart_window, seats, status, created_at)
cab_members(id, cab_request_id, user_id, joined_at)
complaints(id, user_id, category, body, status ENUM('open','in_progress','resolved'), created_at)
lost_found(id, user_id, type ENUM('lost','found'), title, description, route_id NULL, photo_url NULL, status, created_at)
requests(id, user_id, type ENUM('special_trip','additional_bus'), details, votes INT DEFAULT 0, status, created_at)
reward_events(id, user_id, ticket_id NULL, delta INT, reason, created_at)
```
Seed `routes`, `schedules`, and `stops` with Section 3 data.

---

## 6. Critical flows (get these exactly right)

**A. Buy → Razorpay → QR ticket**
1. Frontend `Buy` → choose trip/date (default next departure) → confirm.
2. `POST /api/create-order.php` → server looks up the fare **from the DB** (never trust a client amount), calls Razorpay Orders API (raw cURL, HTTP Basic auth `key_id:key_secret`), inserts a `payments` row `status=created`, returns `{order_id, key_id, amount}`.
3. Frontend opens **Razorpay Checkout.js** with that order_id + public key_id → user pays via UPI.
4. **`POST /api/razorpay-webhook.php`** (set this URL in the Razorpay dashboard): read the **raw** body, verify `X-Razorpay-Signature` = `hash_hmac('sha256', rawBody, RAZORPAY_WEBHOOK_SECRET)`. On `payment.captured`: mark payment `paid`, **create the ticket** (generate `public_token` + signed `qr_token`), add reward tokens.
5. Frontend polls `GET /api/my-tickets.php` (or a status endpoint) → shows the boarding-pass ticket with the QR.
> The webhook is the source of truth. Do not issue the ticket from the browser success callback alone.

**B. Signed, single-use QR**
- `qr_token = base64url(payload) . "." . hash_hmac('sha256', payload, TICKET_HMAC_SECRET)`, where `payload` encodes `{ticket_id, route_id, service_date, issued_at}`. Screenshots/forgeries fail HMAC verification.
- The QR image is rendered on the client from `qr_token` using the vendored `qrcode.min.js` (no CDN).

**C. Driver verify** — `POST /api/verify-ticket.php` with the scanned `qr_token`:
1. Recompute + check the HMAC. 2. Load the ticket; check paid + correct route/date + `status='issued'`. 3. Atomically set `status='used', used_at=NOW(), used_by_driver=?`. 4. Return `valid | invalid | already_used` with route/time/passenger. Driver UI matches `ticket-and-driver-scan.html`. (Offline scanning can come later; v1 = online verify.)

---

## 7. Roadmap (build in order)
1. **Phase 1 — Home**: extend `home.html` into `index.html` + `assets/`, serve schedules/fares/live links from Section 3 (seed DB, load via `GET /api/schedules.php` or embed for v1). Live countdowns, expandable schedules/stops, From/To toggle, `● Live` links. **No payments yet.**
2. **Phase 2 — Auth + Ticketing**: guest + email login; Buy → Razorpay → webhook → signed QR ticket; **My Tickets** history.
3. **Phase 3 — Driver + Admin**: driver scan/verify screen; simple admin (add/edit schedules, view bookings).
4. **Phase 4 — Community**: complaints; lost & found; request special trip; request additional bus (upvote/demand).
5. **Phase 5 — Network**: cab sharing (post/join); rewards tokens (earn per booking, redeem); Google `@iith.ac.in` login.
6. **Phase 6 — Partnerships**: third-party tours & travels.

Bottom nav (max 5): **Home · Cabs · Tickets · Me** (+ overflow). Already in the design.

---

## 8. Deployment (GitHub → Hostinger)
1. Push repo to GitHub.
2. In Hostinger hPanel → **Git**: connect the repo, set deploy path to `public_html`, enable auto-deployment (webhook) so pushes go live.
3. Create the MySQL database in hPanel; import `/sql/schema.sql` via phpMyAdmin.
4. Create `api/config.php` on the server (File Manager) from `config.sample.php` with real DB + Razorpay credentials.
5. In the Razorpay dashboard, add the webhook URL `https://<domain>/api/razorpay-webhook.php` and set the webhook secret to match config.
6. Verify: home loads, a test payment issues a ticket, driver verify flips it to "used".

## 9. Definition of done (per phase)
- Matches the design reference (visual diff at 375px against `home.html`).
- Mobile-first, no horizontal scroll, 44px+ touch targets, UTF-8 (→ and ₹ render).
- Real Section 3 data; times/fares correct; live links open the exact URLs.
- Payments: amount computed server-side, ticket issued from webhook, QR signed + single-use.
- Secrets only in git-ignored `api/config.php`; all SQL via PDO prepared statements.

## 10. Open items (flag, don't guess)
- Internal-shuttle real timings (awaiting transport office) — keep swappable.
- Whether the Patancheru live link covers both directions or only Patancheru→IITH.
- Transport-office sign-off to honor in-app QR tickets on the bus.
