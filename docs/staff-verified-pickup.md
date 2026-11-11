# LaundryTrack — Staff-verified pickup (PR #11)

## Changed behavior

- A customer/QR visitor **requests** pickup while the order is `ready`. This does not change order status.
- The request is stored **once** in `laundry__pickup_requests_tbl` with request source, customer/guest identity, and timestamp. Repeat requests do not duplicate the row.
- The Admin order page visibly shows pending requests and requires a checkbox attesting that staff **physically inspected and handed over the laundry**.
- Only an authorized admin action `staff-confirm-pickup` may atomically transition `ready → picked_up`. Its actor is recorded in `laundry__status_history_tbl`, and the request row stores `confirmed_by` and `confirmed_at`.
- A QR token alone cannot finalize an order, approve an order, or claim a new member account. For a guest who requested the handoff through the QR session, linking an account after Admin confirmation requires the **same browser session** and occurs within one hour **after staff approval**. The existing immediate signup for an order just created in that browser remains unchanged.
- Status feeds expose a boolean pickup-request flag for their own authenticated member or own guest QR session. Order status stays `ready` while awaiting staff. Existing `picked_up` orders are not modified.

## Required additive database migration

`laundrytrack_private/migrations/2026-11-11_staff_verified_pickup.sql` adds one table, leaving existing customers and order statuses unchanged. **Never deploy the new PHP files before applying this migration.**

**On Angsila after backing up and verifying your production DB:**

```bash
cd ~/laundrytrack_pr6_check
git fetch --no-tags origin main

mkdir -p ~/laundrytrack_private/migrations ~/laundrytrack_private/scripts
umask 077
git show FETCH_HEAD:laundrytrack_private/migrations/2026-11-11_staff_verified_pickup.sql > ~/laundrytrack_private/migrations/2026-11-11_staff_verified_pickup.sql
git show FETCH_HEAD:laundrytrack_private/scripts/apply_pickup_migration.php > ~/laundrytrack_private/scripts/apply_pickup_migration.php
php -l ~/laundrytrack_private/scripts/apply_pickup_migration.php
php ~/laundrytrack_private/scripts/apply_pickup_migration.php
```

Expect `PICKUP MIGRATION OK` and `Database: s68160395`. The script is CLI-only, restricts the database identity to `s68160395`, and checks schema column names. `CREATE TABLE IF NOT EXISTS` is designed to be safely repeatable. If it reports failed, **stop and investigate** before deploying.

**Only after the migration succeeds**, deploy updated application files:

Private `~/laundrytrack_private/`:
- `app/bootstrap.php`
- `app/actions.php`
- `app/views/order.php`
- `app/views/orders.php`
- `app/views/progress.php`
- `app/views/track-portal.php`

Public `~/public_html/laundrytrack/`:
- `index.php`
- `assets/app.js`

For each file, copy the reviewed version from the merged main commit, retaining its relative path. Back up the old PHP/view/JS files to a **private** directory first. Do not copy local test fixtures or the CI `.env` to production; preserve the production `~/laundrytrack_private/.env`.

For example, from a verified local repository checkout, use `git show FETCH_HEAD:laundrytrack_private/app/actions.php` and redirect into a temporary file in `~/laundrytrack_private/app/`, syntax-check it, then safely replace the old copy. Deploy all updated source files in the same maintenance window. Test live order flows with fresh **TEST** orders. No CI test should hit Angsila.

## Manual acceptance checks

1. Guest makes a TEST order, Admin marks `requested → received → washing → drying → ready`.
2. Before guest request, Admin cannot finalize pickup even with forged `handoff_confirmed=yes`.
3. Guest submits pickup request: status **remains `ready`**; repeat click does not add another request; guest sees pending guidance.
4. Member submits request for own ready order; cannot request another member's; member cannot invoke staff approval.
5. Admin order page shows pending pickup and requires physical handoff checkbox. Without checkbox, status stays `ready`.
6. Admin verifies and confirms; status changes to `picked_up`. History records the Admin as actor; duplicate confirmation creates no additional history event.
7. Guest who requested the handoff in the same browser can link their order to a new member account for one hour after approval; a second QR-only session cannot claim it. QR is revoked after linking.
8. Check member and guest status feeds; pending request is visible without revealing names/phone numbers in public QR view.
9. Existing orders in `picked_up` remain unchanged; new requests are only available for `ready` orders.

**Known limitation:** Staff must actually examine the laundry and identify the legitimate recipient. The website records an explicit staff attestation but cannot independently prove physical handoff. Do not interpret a QR scan as proof of possession.

## CI and rollout policy

- `.github/workflows/final-system-smoke.yml` creates only disposable MySQL `laundrytrack_ci`, runs the additive migration and tests Guest/Member/Admin pickup boundaries.
- `.github/workflows/database-backup-restore.yml` includes the pickup table in the isolated backup and restore checks.
- CI results validate the code on synthetic data. **They do not execute this migration or deploy PHP on Angsila.** Production database migration and manual acceptance remain separate steps.
- This PR is for a security-sensitive change. Merge after CI passes and review the rollout order carefully; do not live-deploy partially.
