# Walk-in orders, QR tracking and guest notifications

## What this feature does

Admins can select an existing customer or enter a walk-in name (phone optional), without requiring an email or registration. After saving an order, a **one-time** QR/link appears in the admin order details. The customer can scan it to view only that order's number, status and due date, then explicitly start a **limited guest tracking session** to receive live in-page notifications (30-second polling) and confirm pickup when ready.

The QR is a **secret bearer link**, not a verified personal identity. Anyone who possesses a copied QR/link can view the limited order status and start a guest session for that order, including pickup confirmation. Never share QR images publicly or put them in public screenshots. For stronger handover verification, check the physical receipt at the counter before releasing laundry.

This is NOT email, SMS, LINE, system push notification, or a notification when the website is closed.

## Deployment order (Angsila)

1. **Back up the MySQL database** and the entire existing LaundryTrack code tree. Do not upload database backups or .env to GitHub.
2. Apply **exactly once** the SQL in `laundrytrack_private/migrations/2026-11-11_walkin_qr_tracking.sql` through phpMyAdmin SQL (or an authorized MySQL client). Verify that `laundry__users_tbl.email` permits NULL and `laundry__orders_tbl.tracking_token_hash` exists with a UNIQUE index.
3. Check and compare the new feature files against your live files before copying. The SQL step must come **before** PHP code deployment.
4. Copy new PHP/JS/CSS files, including `assets/vendor/qrcode.min.js`. Do NOT copy .env from the repo and do not replace the working configuration.
5. Run `php -l` on changed PHP files. `node --check` can be run locally on Windows; Node.js is not needed on Angsila.
6. Create an order for a **new walk-in customer** with no email, check that it shows QR and a copyable link once. Scan on a separate browser and verify public tracking (no personal contact details).
7. Select “เข้าสู่โหมดติดตาม” on the QR page, confirm that the browser enters the restricted tracking portal, then let Admin move the order to ready. An in-page alert and updated status should appear within 30 seconds when the tab is visible.
8. Confirm pickup from the guest tracking portal. Verify `picked_up` and an audit row in `laundry__status_history_tbl`.
9. Test existing email/password customer accounts and old orders. Test token rotation (old QR must stop working, new QR works), no token page on an invalid QR, and no cross-order access.
10. **Only after all tests pass**, merge the Pull Request into main.

## Important security/design choices

- Email for walk-ins is truly **NULL**, not a fake email. A random, unknown password hash exists only to satisfy the legacy user table; this is not an email login account.
- Guest sessions are scoped to ONE order, not a whole customer account; they cannot list other orders or use admin features.
- `tracking_token_hash` is `SHA-256` of a cryptographically random 32-byte token. The raw token appears only in the one-time admin QR/link and the link the customer holds.
- On the admin order page, “สร้างลิงก์/QR ใหม่” rotates the token; all previous links and guest sessions for that order immediately become invalid.
- Existing orders have no QR until an admin generates one.
- Public QR landing pages expose only order number, current status, and approximate due date; no email, phone, personal name, or item notes.
- The route containing a secret token adds no-store and no-referrer headers. Because query-string tokens can still appear in access logs and user history, handle QR links as passwords.
- The authentication flow is a **magic-link guest session**, not identity-verified login. If strict identity proof is required, add a separately delivered PIN or verification before allowing pickup.
- On shared Angsila, ask the administrator to protect .env from other user accounts; chmod 644 is not a safe permanent secret-storage configuration.
