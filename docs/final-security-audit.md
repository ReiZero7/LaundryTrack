# Final Security Audit — LaundryTrack (PR #12)

Scope: repository commit on main as of 2026-11-11, focused on PHP authentication, authorization, CSRF, session lifetime, QR confidentiality, account isolation, PII handling, deployment. This is a **source-code review plus isolated CI**, NOT a penetration test of Angsila production.

## Verified pre-existing defenses

- POST actions run `require_csrf()`; login regenerates PHP session IDs; cookies set `HttpOnly`, conditional `Secure` over HTTPS and `SameSite=Lax`.
- PDO has emulated prepares disabled; user-supplied values reach DB via prepared statements in inspected workflows.
- Most user-controlled text output is encoded with `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE)`.
- `require_admin()` is called for staff-only actions, and nonstaff order detail queries are scoped to their own `customer_id`; QR tracking uses a 32-byte random token whose SHA-256 hash is stored in MySQL.
- Private `.env`, application code, and SQL backups are outside the public `public_html` deployment directory. Repository `.gitignore` excludes `.env` and SQL dumps.
- Tracking endpoints set `Cache-Control: private, no-store` and `Referrer-Policy: no-referrer`; application sends CSP, X-Content-Type-Options, and frame protection.
- Pickup `ready → picked_up` requires staff approval; CI confirms Guest/Member/Staff access rules.

## Findings and remediation in this PR

1. **High: no cross-session brute-force protection on password login.** Added `login_rate_limit_allowed()` using the already-deployed `laundry__order_rate_limits_tbl`, the existing `RATE_LIMIT_SECRET` and keyed HMACs. Thresholds are **60 login attempts per server-observed IP per 15 minutes**, and **12 per normalized email per 15 minutes**. Both successful and failed attempts count. The limiter does not trust `X-Forwarded-For` and fails closed if the private key, IP or table is unavailable. An account quota may temporarily delay a legitimate user after repeated incorrect attempts; it is not a permanent ban.
2. **Medium: session authentication lacked an explicit server-side timeout.** Added **60-minute inactivity** and **12-hour maximum-age** checks, including guest QR and post-checkout sessions; expired sessions have their stored privileges and CSRF tokens discarded. Regenerated sessions start a fresh clock. Existing sessions lacking timestamps are initially allowed for a compatibility transition; all subsequent requests have timestamps.
3. **Low: CSRF field with an array value generated a string-conversion warning.** Reject non-string CSRF inputs before constant-time comparison.
4. **Low: login credential inputs had no explicit size guard.** Reject email over 150 bytes and password over 4096 bytes before querying/verifying hashes (after charging the login quota).

## Test plan

- `tests/security-hardening-integration.php` runs **only** with `APP_ENV=ci`, `DB_NAME=laundrytrack_ci` and independently confirms actual DB identity. It exercises both quotas, resets, different IPs/accounts, forged forwarding headers, missing HMAC key or table, and idle/absolute session timeout logic.
- `tests/final-system-smoke.py` exercises 13 bad logins and confirms the next valid, unrelated member login still works in disposable HTTP+MySQL CI.
- The existing PHP lint, signup/order/QR/admin pickup HTTP regressions, and backup→checksum→restore jobs must all pass on the PR head.

## Residual risks and follow-ups

- **Physical handoff** cannot be proven merely by a web checkbox. Staff must identify recipient in person before approving.
- **Guest QR links are bearer credentials**: possession enables viewing a single order's limited status, and a QR holder can submit a pickup request. Keep private, rotate if leaked. Staff must not treat QR alone as proof of the rightful recipient.
- **Account provisioning and password reset by Admin** do not yet invalidate all earlier login sessions on other browsers after a password or role change. Future work: store a session/security version in DB and verify it at protected entry points.
- **Registration** has client/server validation, but no distinct persistent signup quota; a targeted signup-abuse limiter could be added separately.
- **Centralized logging, automated encrypted offsite backup rotation, dependency integrity checks and periodic credential review** remain operational tasks.
- A shared IP (e.g. university NAT) can exhaust an IP quota for others; email quota also applies independently. Do not trust unverified proxy headers.
- Production session cookie currently uses path `/`. Tightening to the subdirectory requires an explicit cookie migration plan to avoid duplicate session cookies; deferred.
- Direct public-host checks of backup/.env disclosure and server configuration require a separately authorized operational verification; no credentials were read in this audit.
- Rate-limit table rows should be periodically pruned per `docs/order-rate-limiting.md`.

## Deployment (requires **no database schema migration**)

This PR reuses the rate-limit table and `RATE_LIMIT_SECRET` already provisioned for orders. Verify these exist on Angsila first; if not, login intentionally fails closed.

Changes to deploy to Angsila **after passing CI**:
- `laundrytrack_private/app/bootstrap.php`
- `laundrytrack_private/app/actions.php`

Stage both reviewed files and `php -l` them before installation. Keep timestamped backups outside `public_html`; do not change `.env`. Replace both files in the same quiet maintenance window. Check login with known existing Admin and Member, customer and guest order tracking, logout, and CSRF validation. Do not brute-force real accounts in production.

If login fails after rollout, restore BOTH backed-up PHP files; do not disable CSRF or expose `.env`.

**No GitHub CI scripts should ever run against real Angsila customer data.**
