# Multi-service order completion and account deletion UX (PR #16)

## Root cause and intended behavior

The previous Guest/Member/Admin order creation code already looped over all posted `service_id[]` and `quantity[]` pairs and inserted multiple `laundry__order_items_tbl` rows in the same transaction. However, **only one overall status** (requested → received → washing → drying → ready) existed for the entire order. It offered no independent staff completion confirmation for a second service such as **รีดผ้า**. An order could therefore reach **ready** even if the ironing portion had not been performed.

The fix preserves multi-line order storage and pricing, and adds a **separate work status for every order line**: `pending`, `working`, `done`. Staff manage them individually in Admin Order Detail. When service name contains the Thai word `รีด`, UI explicitly displays `รอรีดผ้า`, `กำลังรีดผ้า`, `รีดผ้าเสร็จแล้ว`. This name detection is **display-only** and never controls business permissions.

**Critical rule:** When advancing from `drying` to `ready`, the backend verifies that **all** linked order items exist and all have work status `done`. A single completed laundry service cannot finish a multi-service order with pending ironing. Parent order rows are locked in the same order for staff task updates and order status transitions, so concurrent requests cannot bypass the gate. Existing pickup approval continues to require the customer request and staff handoff confirmation.

Guest QR and Member progress views show each service and individual progress; the existing authenticated/QR-bound 30-second feeds update them without exposing user identity on anonymous QR landing pages. Admin oldest-first queue displays finished/total service counts.

## Account deletion simplification

Replace typing `DELETE` with a **required checkbox**, using the existing compact-touch label style. Current password verification, CSRF, Admin last-account protection, open-order block, historical order anonymization and session invalidation remain unchanged. The server requires `delete_confirmed=yes` and cannot be bypassed by omitting the checkbox.

## Production data and migration safety

This release **requires one additive MySQL migration BEFORE new code is installed**. It creates a new table `laundry__order_item_progress_tbl`, then inserts one row per existing order item. Historical `ready` and `picked_up` orders are initialized to `done`; all other order lines are initialized to `pending` so staff verify completion. The migration does **not** change or delete any existing customer, order or order-item rows. Rerunning backfill does not overwrite existing progress.

**Important operational impact:** Every active order, including older one-service orders, must now have its service line(s) marked `done` by staff before it can reach `ready`. This prevents silently assuming ironing or other work is finished. Existing already-ready orders are not blocked from pickup. Staff should review their active backlog before deploying.

After creating and validating a fresh private backup, stage the following files from a pinned reviewed merge commit:

**Migration dependencies, run before application deploy:**
- `laundrytrack_private/migrations/2026-11-12_order_item_progress.sql` (new)
- `laundrytrack_private/scripts/apply_order_item_progress_migration.php` (new)
- Guarded CLI invocation on Angsila: `php ~/laundrytrack_private/scripts/apply_order_item_progress_migration.php --backup-verified`. It refuses any database other than `s68160395`. Verify the migration output, schema, and aggregate row counts. No real customer records should be printed.

**Existing files updated (back up before replacing):**
- `laundrytrack_private/app/bootstrap.php`
- `laundrytrack_private/app/actions.php`
- `laundrytrack_private/app/views/account.php`
- `laundrytrack_private/app/views/order.php`
- `laundrytrack_private/app/views/manage-orders.php`
- `laundrytrack_private/app/views/progress.php`
- `laundrytrack_private/app/views/track-portal.php`
- `public_html/laundrytrack/index.php`
- `public_html/laundrytrack/assets/app.js`
- `public_html/laundrytrack/assets/style.css`

Deploy all PHP/JS/CSS files together in a planned quiet window AFTER the migration, with original mode/ownership preserved and a rollback copy of every existing file. The new progress table is harmless to old code in a rollback, so leave it rather than dropping it.

**Do not run any destructive or synthetic production order tests.** Production QA should inspect existing multi-service orders, mark specific lines done only after staff have actually completed them, and verify a non-destructive pending-item ready attempt is rejected. Use disposable CI/test accounts and orders for full integration tests; never close real customer work as a test.

## Automated regression coverage

- Disposable MySQL fixture has active wash and ironing services.
- Guest + Member + Admin order-create paths each persist both service rows with correct total.
- Staff can mark lines separately, and the backend refuses `drying→ready` until all are `done`.
- Guest and Member scoped progress feeds and views show second ironing task as `กำลังรีดผ้า`.
- Member cannot manipulate staff service-task progress.
- Existing staff pickup confirmation, account closure, session handling, CSRF and backup/restore tests remain active.
- Account deletion requires password + checkbox, not typed `DELETE`.

## Limitations

The order's top-level stage still uses the existing generic `washing/drying` sequence for backwards compatibility. The **per-service** task status is the definitive indicator of whether the ironing or another separate service has actually finished; don't interpret the global `drying` label as proof ironing is done. Historical orders that were incorrectly closed before this feature cannot be automatically repaired; review such orders manually if necessary.
