# Customer self-service ordering (PR #4)

## New flow

**Shop-wide QR or link**: Admin Dashboard displays one reusable QR pointing to
`https://angsila.informatics.buu.ac.th/~68160395/laundrytrack/index.php?page=order-start`.

1. Customer scans the **shop QR** (not an existing order's tracking QR), or opens the link.
2. The chooser asks **มีสมาชิกแล้ว** / **สร้างออร์เดอร์ทันที**.
3. Existing member: email/password login with a fixed internal `next=self-order` destination, then creates an order under their own customer ID and can use the existing member dashboard.
4. Nonmember: enters name and phone, chooses active services, quantities, approximate due date and optional notes, then submits.
5. The server re-queries prices and service activity, creates a no-email customer row with an unguessable password, creates an order in the same transaction with a random 32-byte tracking token, and records a status-history entry with the new customer ID.
6. Confirmation page shows order number, a per-order **secret tracking QR**, link, and **ต้องการสมัครสมาชิกไหม?**
7. **ไม่สมัคร**: customer saves the secret QR/link and uses existing guest order tracking and ready notifications. **สมัครสมาชิก**: customer enters email/password; a new real member account is created and the newly placed order is atomically re-associated with that member. The old guest tracking QR is revoked; the member accesses the order in their authenticated dashboard.

The shop-wide QR/link is shareable with anyone. The order-specific QR/link is a **secret bearer credential**, must not be posted publicly.

## Database

This revision needs **no new database migration** if PR #3's migration has already been applied:
- `laundry__users_tbl.email` must allow `NULL`.
- `laundry__orders_tbl.tracking_token_hash` must exist and be unique, nullable CHAR(64).
- The existing `laundry__orders_tbl.created_by` and `laundry__status_history_tbl.changed_by` must allow referencing a customer ID. They are foreign keys in the reference design, not role-restricted.

The order record is initially stored with the existing `received` status because this legacy project has no `requested` status. **Do not interpret this as physical laundry receipt**; both the checkout and history texts explain that clothes must still be handed to the shop. The owner/admin must check the garments before starting washing. A separate `requested` status and acceptance workflow should be a future enhancement.

## Before deploying to Angsila

1. Back up database to SQL and live public and private code. Keep `.env` private.
2. Clone PR branch `feature/customer-self-service-order` into a **new** directory.
3. Validate `php -l` of all changed/new PHP files and JavaScript syntax locally, or manually inspect errors in the browser.
4. Deploy only changed files (do not overwrite the working `.env`).
5. Existing site directories must be traversable by Apache; with shared hosting verify web-accessible assets folders are `755`, asset files are `644`. Do not apply such permissions to private config or backup directories.
6. Hard-refresh `app.js`/CSS after deployment as they may be cached.
7. Use dummy data for each test below before opening the shop QR publicly.

## Functional tests

- Admin Dashboard shows the reusable QR and its URL ending `page=order-start`. It must **not** contain an order-specific token.
- An anonymous user scans shop QR and sees the choice screen.
- Member path: choose login, enter credentials, return to own self-order page, create order, see it in own order list and in admin.
- Guest path: choose order now, enter name + phone + 1+ active services, see confirmation with *secret order tracking QR*, copy link; verify order in admin.
- Guest can open link in a separate browser, enter guest tracking, see status transitions/ready notification, confirm pickup with test order.
- Guest can optionally register from the success page with email and password: should then see order under new member account, and previous per-order guest QR should no longer work.
- Test validation: empty phone, invalid quantity, tampered price, disabled service, stale CSRF, duplicate email for registration, direct POST attempting to set customer_id, and login redirect parameter outside allowlist.
- Verify a guest's order cannot access other customers' orders; public tracking page must not reveal full name, phone or email.
- Verify admin can still create manual Walk-in orders through `new-order` and all PR #1–#3 flows still work.
- Check browser Network for PHP 500 or missing assets.

## Security and operational limits

- The public self-service endpoint is accessible to everyone, so **deploy rate limiting / anti-abuse controls before advertising it widely**. This PR includes one-per-minute **per-session** guest throttling, a honeypot and CSRF, but those are not adequate against repeated fresh sessions and bots. Consider front-end reverse-proxy/WAF rate limits plus server-side per-IP and per-device checks. Do not store or publish raw tracking tokens in logs.
- Anyone possessing the **order-specific** QR can access that order's guest portal and mark it picked up. Use a physically separate PIN or identity confirmation for actual pickup before production use.
- No verified phone ownership or email ownership is implemented; the registration step is optional and not email-verified.
- This is *in-page polling* (~30 seconds while website visible), not remote push/SMS.
- Self-order should be regarded as a **request**, not an assurance laundry was physically handed over. Admin still controls status advancement.

## Review follow-up: physical drop-off confirmation

After the 6/6 functional flow tests, PR #4 now shows self-service customer-originated orders in their initial `received` state as **รอลูกค้านำผ้ามาส่ง** across customer/admin lists, detail, public QR page, guest portal, and guest polling. The underlying DB enum remains `received` (no database migration). The administrator must check the **ยืนยันว่าร้านได้รับผ้าจริงแล้ว** checkbox when advancing a self-created order from `received` to `washing`; the POST handler enforces the acknowledgement in addition to the view. The `cancelled` transition does **not** require this checkbox. Admin-created walk-in/member orders retain the original received-to-washing behavior.

This safeguards status transitions but does **not** persist a separate time-stamped physical handoff status. A proper `requested → received → washing` state with a migration remains the recommended future design.

### Regression cases added

- New guest self-order shows **รอลูกค้านำผ้ามาส่ง** on its success page, Guest QR track page and orders listings.
- New member self-order shows the same label on member order details and admin listings.
- Admin opens a customer-created order; clicking `washing` without checking dropoff acknowledgement must be rejected; checking it must advance the order.
- Admin can cancel customer-created requests without checking the physical-receipt acknowledgement.
- Admin-created order should still move received-to-washing without the new acknowledgement.
- Older PR #3 QR tracking and auto status update must still work.
