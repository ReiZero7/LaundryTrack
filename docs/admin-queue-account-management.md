# Admin Order Queue & Self-Service Account Management

## Goals and UX

**Admin:** A new **จัดการออร์เดอร์** page is separate from the historical **รายการซักทั้งหมด** page. The queue only shows statuses `requested,received,washing,drying,ready`; completed `picked_up` and `cancelled` remain in history. It sorts by `received_at ASC, id ASC` so the oldest outstanding work stays first. Staff see counts of pending dropoffs, ready items and pending physical pickup confirmations. Filters include status, customer/order search, and an explicit "ลูกค้าขอรับผ้าแล้ว" option. The queue paginates 50 items per page without silently discarding the oldest tasks, and links to the existing role-guarded order detail workflow. **Order state transitions remain unchanged** (including physical dropoff and pickup verification).

**Admin and Member:** The new **บัญชีของฉัน** page lets each logged-in user change their OWN password (current password required; new password >=12 characters and confirmation required), or request permanent self-deletion (current password plus the typed confirmation `DELETE`). Users cannot enter an arbitrary account ID to alter someone else.

## Account deletion semantics — IMPORTANT

The customer database contains real order and staff audit history. A physical `DELETE` of a referenced user can violate foreign keys, destroy attribution or make existing records inaccessible.

This implementation applies the following rules:
- **Account with no references in orders, status history or pickup request records:** physically delete its row.
- **Account referenced by those records:** delete access credentials (replace password with an unrelated random hash; set email/phone to NULL) and anonymize full name as **บัญชีที่ลบแล้ว**. Retain the role and numeric user ID strictly for historical foreign key links and audit preservation. The previous email and phone no longer work for login. This is **account closure/anonymization, not deletion of every order, note or business transaction**.
- **Customer with any non-final order:** deletion is blocked until the order is `picked_up` or `cancelled`. This prevents unpaid/undelivered work becoming orphaned.
- **Last active Admin:** deletion is blocked. Two Admin accounts cannot simultaneously delete the last one because the Admin rows are locked in the transaction.
- **Password changes and account deletion:** invalidate existing browser sessions on other devices. Every HTTP request revalidates `id,role,email,password_hash` against the live MySQL user row using a session hash fingerprint, and reads fresh account name; sessions issued before this feature are logged out once after deployment. A password change regenerates the current session ID and CSRF token while keeping that browser signed in.
- **Limitations:** Personal data entered inside order `notes`, exported reports or old SQL backups is **not automatically erased**. Any legal/privacy erasure workflow for retained business records requires separate policy review and backup handling. Users are explicitly informed in the account UI.

## Security

- No change to underlying authorization and no direct access to other users' profile changes.
- POST actions require existing CSRF protection; password operations use `password_verify()` / `password_hash()`; form passwords are never echoed or logged.
- Account deletion and password change run under MySQL transactions with locked account rows.
- Admin queue is guarded both by the router and `require_admin()`; search terms are bound by prepared statements. LIMIT/OFFSET are strictly computed integers.
- Existing order completion and staff pickup rules remain unchanged.
- No database schema change is required: `users.email` is nullable because the application already supports guest accounts.
- In the rare case that another unknown production foreign key prevents actual account deletion, the transaction rolls back and the account remains (fail closed).

## CI and manual acceptance

Automated: PHP lint; 3 disposable MySQL/HTTP workflows including oldest-first queue sorting, staff-only queue access, exclusion of completed orders, role-aware account page, wrong-password denial, password change, invalidation of earlier browser sessions, permanent deletion of an unreferenced account, anonymization and order preservation of referenced accounts, refusal while customer orders are open, last-Admin guard, and Admin deletion with another active Admin remaining.

Manual acceptance (use **synthetic/TEST accounts only**, and never delete real customers during QA):
1. Admin navigation shows separate **จัดการออร์เดอร์** and **รายการซักทั้งหมด**. Confirm pending oldest order is at top, filter/search and paging work.
2. Completed orders are only in historical list; ready orders with pickup requests are prominent.
3. Member cannot open Admin work queue.
4. Both Admin and Member see their own **บัญชีของฉัน** page; change password with known test credentials. Other logged-in test browser sessions become invalid.
5. Customer TEST account with unfinished order cannot delete; after completion can remove/anonymize it and historical order stays readable to Admin.
6. A second Admin TEST account can self-delete, but the final active Admin cannot.
7. Mobile viewport and desktop navigation remain usable.

## Production deployment order (after CI passes)

**No migration**. Updated existing production files:
1. `~/laundrytrack_private/app/bootstrap.php`
2. `~/laundrytrack_private/app/actions.php`
3. `~/public_html/laundrytrack/index.php`
4. `~/laundrytrack_private/app/views/layout_top.php`
5. `~/laundrytrack_private/app/views/dashboard.php`
6. `~/laundrytrack_private/app/views/order.php`
7. `~/laundrytrack_private/app/views/home.php`
8. `~/public_html/laundrytrack/assets/style.css`

**New production files (no preexisting backup):**
9. `~/laundrytrack_private/app/views/manage-orders.php`
10. `~/laundrytrack_private/app/views/account.php`

Use a pinned merged Git SHA, stage all ten files outside `public_html`, back up the eight preexisting files privately with their permissions, lint every PHP file, and deploy all ten in one planned quiet maintenance window. Old logged-in sessions will be asked to sign in again due to the new server-side session fingerprint. Do not modify production `.env`, run synthetic test scripts against production, or delete any real account to validate the feature.

Rollback: restore eight previous files and remove the two new page files, after making sure the new pages were not deliberately modified; keep the backup for recovery. Changing the code back will restore the old session behavior, but will **not undo** any real password changes or account deletions already completed under the new code.
