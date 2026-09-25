# Ticketing activation and acceptance

Prepared locally on 25 September 2026. Booking stays off by default. A deployment
of the UI is separate from permission to accept payments.

## Server prerequisites

- PHP 8.1+ with cURL, PDO and PDO MySQL; HTTPS; MySQL 8 or MariaDB with InnoDB.
- Back up the existing server-only configuration and any existing database to
  private storage. Keep backups outside public_html. Test the restore process.
- Import `api/schema.sql` once into the private database. It creates bookings,
  short-lived rate limits and a staff operation audit log. Do not import a test
  fixture or point production at a test database.
- Merge the new fields from `api/config.sample.php` into the server-only config.
  Generate a long independent signing secret. Never publish config or secrets.
- Configure exact payment auth/base URLs, client credentials/version and allowed
  checkout redirect hosts for the approved account and environment. The adapter
  supports `oauth-checkout-v2`; other protocols require an adapter, not URL swaps.
- Set a canonical HTTPS PUBLIC_URL. Verify return fragments survive the provider
  redirect on Android, iPhone and desktop.
- Configure `/api/webhook.php` with the provider's SHA username/password mode,
  subscribe to order success/failure and refund-completed events, and verify
  signatures and actual event shapes in sandbox. Other webhook auth modes are
  not interchangeable.
- Give each driver a unique staff identifier and strong access key. Store only
  `password_hash(key, PASSWORD_DEFAULT)` in STAFF; never store a plain key in
  the repository or distribute one shared staff key. Office accounts need the
  `admin` role; drivers must retain the `driver` role.
- Configure hosting cron to run `php /absolute/path/api/reconcile.php` every
  minute. It retries unresolved payments for seven days in bounded batches,
  updates status and clears expired rate-limit buckets. Monitor nonzero exits,
  payment errors and webhook delivery failures with a named operator.
- Tune ORDER_RATE_PER_MIN for campus shared networks. Trust only verified proxy
  headers if configuring a proxy; the default uses the direct remote address.
- Check `.htaccess` behavior on the actual host: config/helper PHP, SQL, Markdown,
  backups and dotfiles must not be downloadable. Directory listing must be off.
  Local FrankenPHP does not execute Apache .htaccess directives.
- Update and approve `assets/routes.json`: fares, boarding points, holidays and
  timetable; set `confirmed` only after checking the operational data. Both the
  browser and PHP use this file. All journey times use Asia/Kolkata.
- Set approved BOARDING_BEFORE_MIN / BOARDING_AFTER_MIN, then
  OPERATIONS_CONFIRMED. Resolve capacity/admission, cancelled journeys, support,
  refund, data retention and merchant/contact policy requirements before this.
- Set BOOKING_ENABLED only after successful UAT and an authorised launch decision.
  `/api/status.php` exposes a boolean, never configuration values.

## Acceptance checklist

Use sandbox first. Local mock tests establish application behavior, not provider
acceptance, settlements, device compatibility or Hostinger database behavior.

- [ ] Schema works on the actual MySQL/MariaDB server; host backups and restore pass.
- [ ] Correct fare, route, direction, departure, holiday handling and boarding point.
- [ ] Wrong amount, route/time injection and invalid signatures are rejected.
- [ ] Successful mobile UPI and desktop checkout issue one recoverable ticket.
- [ ] Cancelled, failed, pending and delayed payments have accurate states.
- [ ] Close the tab after paying: webhook/cron issues the pass; saved recovery link
      and My tickets recover it without another charge.
- [ ] Retry the same create request: no second order is created. On an ambiguous
      create timeout the UI offers recovery, never silently makes another order.
- [ ] Duplicate/out-of-order webhooks cannot restore a used, revoked or refunded pass.
- [ ] Phone camera decodes the printed and on-screen QR on real driver devices.
- [ ] Staff authentication, role separation and wrong-journey rejection work.
- [ ] Before/after boarding window is rejected. Two simultaneous scans admit once.
- [ ] Office can recheck/revoke a pass; driver cannot perform admin actions.
- [ ] Refund is handled in the payment dashboard; pass revocation and notification
      are checked; refund/reconciliation is recorded by the office.
- [ ] Network/camera failure procedure is approved. No offline admission override
      is implemented; staff must not admit based solely on a screenshot.
- [ ] Error alerting is reaching the named operator. Private data stays out of logs.
- [ ] Approved supervised real payment → ticket → scan → duplicate rejection →
      refund → actual settlement/bank-credit verification.

## Operations and limitations

The rider remains a guest. My tickets holds at most 30 recovery links in this
browser. Those links can be copied to another device; losing both browser data
and the private recovery link requires support. No email/SMS delivery or
identity-based self-service recovery is implemented.

This is an unreserved, single-passenger, exact-departure travel pass, not seat
inventory. Do not enable payments if admission requires guaranteed capacity
until inventory enforcement is implemented and tested.

Office `/operations.html` lists the most recent 100 bookings, rechecks payment
status, and revokes passes. It is not an accounting ledger or settlement report.
Use the gateway's settlement exports and the office's financial process for
reconciliation. Revocation itself does not move money. Staff credentials are
kept in page memory and discarded on sign-out; revoke them server-side when
staff access ends.

Old stateless tickets are not accepted by the new issuance/check-in flow. Confirm
there are no outstanding genuine passes before switching; if there are, agree
and test a supervised migration/validation process first.

## Local validation

PHP parsed/executed on a temporary PHP 8.5.11 runtime. Integration tests used a
private SQLite fixture and a local HTTP provider simulator. MySQL schema and
actual provider sandbox/production are separate acceptance gates above.


Recorded checks: 13 PHP files parsed; all JavaScript parsed; 20 API integration
tests passed, including concurrent check-in, amount/order mismatches and recovery;
26 browser checks at 320–1440px; no page errors; no automated WCAG A/AA violations
on the ten pages, journey dialog and populated ticket. The complete browser flow
and QR decode/check-in/reuse rejection passed. Focus containment/return and local
links were checked. These results do not close the host/provider/device gates.
