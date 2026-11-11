# PR #6 — Persistent rate limiting for self-service orders

This branch stacks on PR #5 (`feature/requested-status-guest-join-member-progress`). **PR #5 must be merged first; PR #6 stays Draft until its own Angsila tests pass.**

## Why

PR #5 has a 60-second per-session guest cooldown and honeypot. A person opening another browser/incognito session can bypass a session-only limit. This PR adds database counters keyed by a pseudonymous, HMAC-SHA256 identity.

- Guest: use the server-supplied `REMOTE_ADDR` (not user-controlled `X-Forwarded-For`) and apply **8 submissions/15 minutes** and **30 submissions/24 hours**.
- Signed-in customer: use their account ID and apply **10 submissions/15 minutes** and **50 submissions/24 hours**.
- Count submissions reaching the validated-CSRF/no-honeypot self-order endpoint, including those with other invalid form fields. The old guest session cooldown is retained. This is not a general website request or DDoS limiter.
- Locks InnoDB rate-limit rows inside a transaction and increments two counters atomically. Failed order creation transactions do not erase previously recorded submission attempts.
- When the HMAC secret, client IP or database table is unavailable, self-order submission **fails closed** with a user-facing message. Admin manual orders, existing status views, QR tracking and member login remain unaffected.
- Scope, salted identity digest, window timestamp and count are stored. The full IP is not stored in the counter table.
- Shared public IPs (e.g. a university/dorm NAT or shared reverse proxy) **share** the guest limit; monitor false positives and tune the limits before wide rollout.

## CRITICAL installation order

1. Verify PR #5 remains working and backup both the site's PHP files and relevant MySQL tables. Have an actual .sql export (a .tar.gz of the PHP files is **not** a DB backup).
2. Apply `laundrytrack_private/migrations/2026-11-11_order_rate_limiting.sql` **once** to the live LaundryTrack database, confirmed as `s68160395` during previous tests. This only creates an empty counter table; it does not change orders/users.
3. Add a long random key to the PRIVATE `~/laundrytrack_private/.env`, never to the web root and never to GitHub. Example to generate the value on Angsila:

   ```bash
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

   Paste the resulting 64-hex-character value into a new line of the private file:

   ```dotenv
   RATE_LIMIT_SECRET=<paste-the-generated-random-value-here>
   ```

   Do **not** share this value in screenshots/chats. If `RATE_LIMIT_SECRET` already exists, **keep** it instead of creating a duplicate. Ensure the PHP server account can still read the private file; do not loosen .env permissions indiscriminately.
4. Now pull PR #6 code and deploy **only**:
   - `laundrytrack_private/app/bootstrap.php`
   - `laundrytrack_private/app/actions.php`

   Use existing working permission settings. No changes to JS/CSS/SQL order data are needed.
5. Lint `php -l` for both files and hard-refresh.
6. Confirm private `RATE_LIMIT_SECRET` exists by looking for its **key name**, not displaying its value. Example:
   ```bash
   grep -q '^RATE_LIMIT_SECRET=' ~/laundrytrack_private/.env && echo 'RATE LIMIT SECRET CONFIGURED'
   ```

## Tests (use isolated test orders and avoid flooding production)

1. In guest/incognito, submit **one** test self-service order. It should get `requested` as before. Existing QR, new-member conversion and Admin staff handoff still work.
2. In member mode, submit one test self-service order; it should also work and appear in member progress.
3. Using phpMyAdmin (without sharing digests) inspect only aggregate counters:

   ```sql
   SELECT scope, COUNT(*) AS identities, SUM(attempt_count) AS counted_attempts
   FROM laundry__order_rate_limits_tbl
   GROUP BY scope ORDER BY scope;
   ```

   After one guest and one member test, expect at least four scope rows (`guest-15m`, `guest-24h`, `member-15m`, `member-24h`). Distinct members/IPs create more.
4. On an isolated staging/test copy, verify the same IP across two fresh browser sessions shares its guest quota; at the limit the order does not create any `laundry__orders_tbl` or `laundry__users_tbl` row. Do **not** generate dozens of fake orders on production.
5. Temporarily remove the secret **only on staging** and verify that order creation fails closed without displaying secrets/stack traces. Test rollback/restoration.
6. The Admin manual create flow, guest QR tracking, post-pickup join, notification feed and guest logout revocation must behave exactly as PR #5.

## Limits and next hardening

- Rate limits reduce spam order creation but cannot prevent distributed multi-IP abuse, broad denial-of-service or all account creation abuse. A CAPTCHA/Turnstile, signup email verification and application-level WAF remain possible next layers.
- A tracking QR is still a bearer credential for *pickup confirmation*, which should ultimately be verified against the actual customer at the shop.
- Guest limits are shared behind NAT/proxies; check the actual `REMOTE_ADDR` delivered to PHP from the hosting provider, and prefer a trusted reverse-proxy configuration rather than accepting arbitrary forwarded headers.
- Keep `RATE_LIMIT_SECRET` stable. Rotating it intentionally resets identity counters.
- Optional after >30 days: archive/backup then prune counters older than 30 days by running the documented `DELETE` maintenance statement. This is not required to deploy.
- This PR does not automatically change the PR #5 draft/merge state.
