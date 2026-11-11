# PR #8 — Verified email before new customer login

**Draft / staging change only. Do not deploy on Angsila until the mail transport is confirmed and all CI checks plus manual delivery tests pass.** PR #7 and PR #6 are already merged to `main`. This branch modifies account registration, post-order Guest signup and login.

## Behavior

- Every **new** customer signup creates an account with `email_verified_at=NULL`. Customers cannot sign in until they complete email verification. Admin accounts are not subject to this customer-only check.
- A random 32-byte token is emailed; only its SHA-256 digest is stored in the database; tokens expire after 24 hours and can be used once.
- GET `?page=verify-email&token=...` only displays a confirmation form. The one-time token is consumed by a CSRF-protected POST, to avoid accidental confirmation by email-link scanners.
- Resend requires waiting 10 minutes; maximum 5 sends per 24h per pending account. The response does not reveal whether an address exists.
- Existing email-bearing accounts are **grandfathered** as verified by the SQL migration to avoid losing access, including admins. Existing Guest rows with NULL email remain unverified and cannot log in.
- Guest-to-member signup stores a pending claim **without** immediately transferring ownership or invalidating Guest QR. After successful verification, the order is transferred in a single database transaction only if the existing customer ID and QR token hash still match. If QR has been rotated or order transferred, email still verifies but the customer must contact the shop about linking.
- After verification, the customer **signs in normally**; following a verification link does not automatically log a browser in.
- Verification pages have `Cache-Control: private, no-store`, `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex`.

## Email transport caveat

The app uses built-in PHP `mail()` only; PHP returning true means the local transport accepted the message, **not** that the recipient received it. Set a valid authorized `MAIL_FROM_ADDRESS` in the private `~/laundrytrack_private/.env`, e.g. an address on a domain that the host permits to send. Verify actual delivery, SPF/DKIM and junk folder on **staging** first. The app does **not** have an SMTP client, third-party email API or queue. **Do not invent or paste the actual address or password into the repository or chats.**

Example private config key:

```dotenv
MAIL_FROM_ADDRESS=your-authorized-sender@example.org
```

The sample is a placeholder, not an authorized sender. `RATE_LIMIT_SECRET` remains unchanged. When sending fails, the account stays unverified; the customer can request a new link after the cooldown or contact the shop. Do not show the raw token on web pages or in logs.

**Important:** Before activating this feature for real users, verify with the university's hosting administrators that outgoing mail from PHP is allowed; otherwise supply an approved mail provider and implement the appropriate authenticated sending transport in a separate PR.

## Exact deployment order (NOT executed yet)

1. Confirm an authorized sender and successful test email receipt. Back up DB as `.sql` and site files, including a rollback plan. Choose a low-traffic maintenance window.
2. Check current column structures before migration, especially `laundry__users_tbl`. The migration adds `email_verified_at` and creates `laundry__email_verifications_tbl` and backfills existing email accounts. **MySQL DDL auto-commits.** Apply `laundrytrack_private/migrations/2026-11-11_email_verification.sql` once only, preferably first to an isolated copy.
3. Add `MAIL_FROM_ADDRESS` to private .env; keep it and other secrets outside public web root and GitHub.
4. Deploy **all** changed PHP files from this PR as one update: `bootstrap.php`, `actions.php`, public `index.php`, updated `register.php`, `login.php`, `join-after-order.php` and **new** `verify-email.php`, `verify-email-sent.php`, `resend-verification.php`. Do not deploy just one PHP file.
5. Run `php -l` on each and hard-refresh. Do not run `ALTER TABLE` again if the columns already exist. Existing pages and progress should still work.
6. Verify `SELECT COUNT(*) FROM laundry__users_tbl WHERE email IS NOT NULL AND email_verified_at IS NULL;` corresponds to new pending accounts only.
7. Perform staging acceptance: new signup cannot login, invalid/expired/reused tokens rejected; correct token accepted; guest QR remains valid before verify and revoked only after successful linking; resend cooldown; old customers and admins still log in; normal self-orders unaffected; delivery actually arrives in an inbox.

## CI and privacy

GitHub Actions runs a disposable `laundrytrack_ci` MySQL instance with fake local-only email delivery; the test harness refuses a non-CI database. No real mail is sent, no production `.env` is copied to Actions. Tests cover migration backfill, token hashing, one-time/expiry, deferred QR linking, and QR rotation safeguards.

### Limitations / follow-ups

- Preventing distributed or high-volume account creation should additionally use per-IP signup throttling / CAPTCHA; resend is only throttled per existing address.
- HTTPS verification URL is tied to the current university deployment path; update it if the app moves.
- Built-in PHP mail needs an approved working mail-transfer agent or explicit provider integration; do not assume delivery from a successful `mail()` call.
- Email proves mailbox access, not the actual identity of the person handing over laundry; QR pickup flow still needs physical checks by staff.
- This branch is **not** deployed; PR remains Draft until real mail receipt and behavior are tested.
