# Real requested status, member progress and guest membership after pickup

## Goal

- Self-service orders start at a genuine database `requested` state (not just a renamed `received`).
- Admin workflow: `requested → received → washing → drying → ready → picked_up`; `requested → cancelled` and `received → cancelled` remain available. Customers can confirm pickup only from `ready`.
- The `requested → received` transition requires the Admin to tick an explicit physical hand-off confirmation; this is validated on the server.
- Admin-created orders still start at `received` because they are registered by staff as physically received.
- After a guest confirms pickup, their scoped tracking portal shows **สมัครสมาชิกและเก็บประวัติออร์เดอร์**; registering links that exact order to a new member account. The old guest QR token is revoked.
- The customer gets a prominent **ความคืบหน้าการซักรีด** page instead of dashboard, with a six-stage indicator, active orders at the top, recent history below, and **สร้างออร์เดอร์** shortcut. Member navigation: progress, history, create. Admin dashboard is unchanged.
- Member and guest pages poll for changes about every 30 seconds while the tab is visible. This is not phone push notification.

## CRITICAL deployment order

1. Take a new MySQL SQL export and backup live files. Do NOT copy or publish `.env`.
2. In phpMyAdmin, run these read-only queries and check the original column types BEFORE running the migration:

   ```sql
   SHOW COLUMNS FROM laundry__orders_tbl LIKE 'status';
   SHOW COLUMNS FROM laundry__status_history_tbl LIKE 'old_status';
   SHOW COLUMNS FROM laundry__status_history_tbl LIKE 'new_status';
   ```

3. **Verified on the target DB `s68160395`**: `orders.status` is `ENUM NOT NULL DEFAULT 'received'`; `status_history.old_status` is `VARCHAR(20) NULL`; `status_history.new_status` is `VARCHAR(20) NOT NULL`. The earlier migration assumption about history ENUMs was incorrect and has been corrected. **Do not alter the history columns.** The full old ENUM list was truncated in the UI, so the migration now reads the actual `COLUMN_TYPE` from `information_schema` and adds `'requested'` while retaining all existing enum values. If schema differs or requested already exists, stop and review instead of rerunning. Test on a cloned DB. MySQL DDL auto-commits.
4. Only then apply `laundrytrack_private/migrations/2026-11-11_requested_order_status.sql` once. It expands **only the orders.status ENUM**, then performs a guarded backfill of outstanding self-service requests. Staff-created, progressing or completed orders remain untouched.
5. Verify `SHOW COLUMNS` and `SELECT status,COUNT(*) ... GROUP BY status`.
6. Deploy the new PHP files and assets from the PR **after** the migration. Do not deploy the code first: it would try writing `requested` before the database accepts that state.
7. On Angsila, check `php -l` on all changed PHP files. Protect private files; ensure web-readable asset directories and published JS/CSS do not inherit `umask 077` permissions. Hard-refresh CSS/JS due to browser caching.

## Must-pass regression tests

- Guest self-order → `requested` in MySQL, on Guest portal and Admin.
- Member self-order → `requested` in MySQL and on member progress.
- Admin attempt to set `washing` from `requested` must be rejected. Check `requested → received` without tick is rejected, with tick is accepted, then `received → washing` succeeds normally.
- Admin-created order should start `received` and not require the special confirmation when changing to `washing`.
- Guest can track `requested`, `received`, `washing`, `drying`, `ready` and confirm `picked_up` from `ready`.
- After `picked_up`, Guest portal must show signup button. On successful signup, account owns the completed order, past token becomes invalid, and the order appears in member history. Test old token in a different browser.
- Existing member login navigates to **ความคืบหน้าการซักรีด**, not stats dashboard. History and self-order links work, progress updates with status changes.
- Admin dashboard, stats, customer/guest isolation, QR rotation, notifications and pickup still work.
- Test required fields, duplicate email, direct submission using another customer's order ID, stale CSRF, and reopening tracking after account linkage.

## Limitations before public promotion

- Phone number ownership and email ownership remain unverified.
- The QR tracking token is a bearer credential. Verification at physical pickup/PIN is recommended.
- The per-session public form cooldown/honeypot is **not** sufficient defense against many anonymous new sessions or distributed bots. Add server-/IP-based rate limiting before publishing the shop QR widely.
- New member signup currently supports **creating a new account**; it does not merge the guest order into an already-existing email account (would require proof of that account).
- Admin physical hand-off is represented by a status and history entry, not an independent receipt document or staff signature.
