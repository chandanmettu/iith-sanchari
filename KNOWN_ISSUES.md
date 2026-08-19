# Known issues — Sanchari

Things we know are wrong or unfinished. Listed here deliberately: an issue
recorded and accepted is a decision, an issue nobody wrote down is a surprise
waiting to happen.

**When you fix one:** move it to Fixed at the bottom, add the date and commit,
and don't delete it. The history of what was wrong is worth keeping.

**When you find one:** add it to Open with the next `SAN-` number. Say what
breaks and who it affects, not just what the code does.

---

## Open

### SAN-001 — Ticket lifecycle is incomplete
**Severity:** High · **Affects:** fare revenue · **Since:** ticketing was built

A ticket has no use-tracking and no validity window. Forged tickets are rejected
(SAN-100); what remains is the lifecycle gap. Specifics are in the private
tracker rather than here, since this repository may be public.

Blocked on the database and the driver-scan screen — a ticket can't be marked
used until there's somewhere to record that it was. `api/verify-ticket.php` is
already the endpoint the scanner will call.

### SAN-002 — Shuttle timings shown to riders are placeholder data
**Severity:** High · **Affects:** every visitor · **Since:** Phase 1

The Main Gate ⇄ Hostel Circle countdown runs on a hardcoded 15-minute cadence
that was always a stand-in awaiting real timings from the Transport Department.
The homepage presents it exactly like the real Patancheru and Miyapur schedules:
a confident "6 min away" that is not based on anything.

This is a trust problem more than a technical one — someone can miss a bus
because of it. Either get the real timings, or label the section as indicative
until they arrive. The code was written so the schedule is swappable without a
rewrite.

### SAN-003 — No real payment has ever completed end to end
**Severity:** High · **Affects:** the whole product · **Since:** always

Every piece of the payment path is built and has been tested against the
gateway's API directly, but "rider pays → gateway confirms → ticket renders" has
never once been observed with a genuine payment. The blocker has been the
payment account's review status, not the code. Until it happens once, the flow
is unproven no matter how correct it looks.

### SAN-004 — Payment gateway still needs swapping
**Severity:** High · **Affects:** launch · **Since:** the pivot

The live code path is still the old gateway. The seams are in the right place —
fares are server-side in `api/fares.php` and signature verification is isolated
in `api/verify-payment.php` — so this is rewriting those files and the checkout
script tag, not restructuring the app. Keep `legal.html` gateway-agnostic; it
deliberately never names a provider.

### SAN-005 — Mobile-only layout
**Severity:** Medium · **Affects:** anyone on a laptop or tablet · **Since:** Phase 1

`assets/app.css` caps the shell at `max-width:480px` and the stylesheet has one
`@media` rule, for reduced motion. There are no breakpoints. On a desktop the
app renders as a narrow phone-shaped column in the middle of empty space.

### SAN-006 — Operator name is on the public site
**Severity:** Medium · **Affects:** privacy · **Since:** Phase 1

The footer on `index.html` and `legal.html` both name the operator personally.

⚠️ **Check before removing.** Payment aggregators generally require the merchant
running a paid service to be identifiable on the site as part of onboarding, and
onboarding is already the critical-path blocker. Safer paths: a neutral operator
line on the homepage with full disclosure kept in `legal.html`, or confirm what
the new gateway actually requires first.

### SAN-007 — No rate limiting on order creation
**Severity:** Medium · **Affects:** us · **Since:** payments were wired

Nothing throttles the create-order endpoint, so it can be driven repeatedly to
generate junk orders against the payment account. Easier to add alongside the
gateway swap than after it.

### SAN-008 — Nothing reports failures in production
**Severity:** Medium · **Affects:** us · **Since:** always

Failures write to `error_log()`, which means a server log nobody reads. If
payments or ticket verification start failing for real riders, we find out when
someone tells us.

Note this is *error* monitoring only. `legal.html` promises "no cookies,
analytics, or trackers" and that promise should hold — this is not a licence to
add product analytics.

### SAN-009 — A ticket exists only at its URL
**Severity:** Medium · **Affects:** riders · **Since:** ticketing was built

There is no database, so a ticket lives entirely in the link. Close the tab and
it is gone: no "My Tickets", no re-issue, no emailed copy. Issuance is also
driven by the browser's success callback rather than a gateway webhook, so a
rider whose tab dies mid-redirect can pay and receive nothing.

Emailing tickets needs a transactional email service — shared-host `mail()` is
unreliable and spam-flagged — and no vendor has been chosen.

### SAN-010 — Departure and arrival strings on a ticket come from the browser
**Severity:** Low · **Affects:** ticket accuracy · **Since:** ticketing was built

Route name, fare and journey duration are all derived server-side and cannot be
tampered with. The departure and arrival clock strings are still passed up from
the client and only length-capped, because deriving them server-side means
duplicating the whole timetable out of `assets/app.js`. They are informational
and don't affect what was actually bought. Worth closing once schedules live in
a database table instead of a JS constant.

### SAN-011 — Accessibility never audited
**Severity:** Low · **Affects:** unknown · **Since:** always

Expandable cards are keyboard-operable, which is a good sign, but contrast
ratios, focus visibility and screen-reader labelling have never been checked.

---

## Fixed

### SAN-100 — Forged tickets displayed as genuine
**Fixed:** 2026-08-19 · `805b01f`

The ticket page decoded the token's payload and threw the signature away without
ever checking it. Any hand-written payload with an arbitrary suffix rendered a
complete boarding pass showing the Verified stamp, the Paid badge and a green
Valid status. With no driver scanner in place, tickets are checked by eye — so
this was free travel for anyone able to open developer tools. Confirmed by
actually forging one, not by reading the code.

`api/verify-ticket.php` now recomputes the HMAC with a constant-time comparison,
and the trust markers only appear once it responds. Ticket details still render
straight from the token so the pass opens without signal; only the markers wait
on the server, and a network failure shows "Unverified" rather than accusing a
paying rider of forgery. Verified closed on the live site.

### SAN-101 — The route on a ticket was chosen by the browser
**Fixed:** 2026-08-19 · `805b01f`

Route name and journey duration were sent up from the client and printed on the
ticket, so paying the ₹30 Patancheru fare could produce a ticket that read
"IITH → Miyapur". Both are now derived server-side from the route id.

### SAN-102 — Error responses leaked internals
**Fixed:** 2026-08-19 · `805b01f`

The order endpoint returned the gateway's raw response body to the browser and
named `api/config.php` in an error string. Both now log server-side and return
generic text, with corrected status codes.

### SAN-103 — The logo was 4.9 MB
**Fixed:** 2026-08-19 · `805b01f`

`assets/logo.svg` was an SVG wrapper around two embedded PNGs, one of them
7071×7071, and it is the favicon on every page plus a 38×38 header mark.
Re-embedded at 512px: 4.86 MB → 0.16 MB. `logo.png` went 355 KB → 23 KB. The
artwork is unchanged.

### SAN-104 — Ticket payload could fail to decode
**Fixed:** 2026-08-19 · `e032be4`

The issuing endpoint strips base64 padding, and strict decoding is not
guaranteed to tolerate its absence — a genuine ticket whose signature had just
passed could have been rejected as undecodable. Padding is now restored before
decoding.
