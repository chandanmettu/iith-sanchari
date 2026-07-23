# Paste this to start the build agent

You are building **IITH Transit**, a campus mobility app for IIT Hyderabad. Everything you need is in this folder.

**Read these first, in order:**
1. `BUILD_SPEC.md` — the full spec: stack, design system, real data, DB schema, critical flows, roadmap, and deployment.
2. `design-reference/home.html` — the **finished, approved** home screen. Open it in a browser. This IS the frontend — you extend it, you do not rebuild it. (Ignore the outer phone-frame demo wrapper; the real app is the phone-screen content.)
3. `design-reference/ticket-and-driver-scan.html` — the issued boarding-pass ticket (centered QR) and the driver scan/verify screen.

**Non-negotiable rule:** the design is locked after many rounds of iteration. **Do not redesign anything.** Port the existing visual system exactly (colors, radii, components, spacing, gold departure times, tear-off cards, boarding-pass ticket) and build every new screen in that same language. If your output doesn't look like `home.html`, it's wrong.

**Stack (locked — this is Hostinger SHARED hosting, keep it simple):**
- Frontend: **plain HTML + CSS + vanilla JS** (no framework, no build step, no npm).
- Backend: **PHP 8** (no framework), **PDO** for MySQL.
- Database: **MySQL** (Hostinger, phpMyAdmin).
- Payments: **Razorpay** (Orders API via raw cURL — no Composer requirement; Checkout.js on the front; ticket issued from the **webhook**; QR is HMAC-signed and single-use).
- Deploy: **GitHub → Hostinger Git** auto-deploy to `public_html`. Same-origin, so no CORS.
- **No Node.js, no Next.js, no React, no bundler, no paid services.** It must run as-is on PHP shared hosting.

**Start with Phase 1 (Home):** turn `design-reference/home.html` into `index.html` + `/assets/`, and serve the real schedules, fares, and live-tracker links from `BUILD_SPEC.md` Section 3 (seed MySQL from `/sql/schema.sql`). Keep the live countdowns, expandable schedules/stops, and the From/To IITH toggle. **No payments in Phase 1.** Confirm the plan with me, then build Phase 1 end-to-end. After that we go phase by phase (Auth + Ticketing next).

Follow the exact project structure, DB schema, and endpoint contracts in `BUILD_SPEC.md` Sections 4–6. Keep all secrets in a git-ignored `api/config.php` (commit `api/config.sample.php` only). Ask me for the Razorpay keys and anything in "Open items" (Section 10) you need.
