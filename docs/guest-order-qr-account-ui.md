# LaundryTrack UI polish — Guest order, store QR, profile and Member/Guest entry

This revision changes **presentation and navigation only**. It does not modify the database, login identity model, access control for order data, pickup authorization, or QR token lifecycle.

## Changes

1. **Guest order detail matches member structure.** The customer/order details panel and status timeline stay together in the same `detail-grid` for both Member and Guest orders. Guest-only one-time tracking QR and its secret link stay available to authorized Admin users but move into a separate full-width section **below** the detail grid. The header offers a shortcut to this section. No QR token is exposed in public Member or Guest tracking pages.
2. **Smaller confirmation checkboxes.** Physical dropoff and staff pickup confirmations use compact 17px checkboxes centered in a full-size tappable label, with focus and checked states. The required explicit staff confirmation remains mandatory.
3. **QR and link leave Dashboard.** The Admin dashboard now presents stats and recent orders without the store QR block. Admin sidebar gets **QR และลิงก์ร้าน**, an Admin-only `shop-qr` page with the same local QR renderer, shareable shop link and a link to Order Start. The shop QR is public by purpose, not the private QR for an individual order. Member/Guest access to the management page is denied by the router.
4. **Account settings from the account name.** The standalone "บัญชีของฉัน" sidebar navigation row is removed. Clicking the avatar/name in the sidebar or the signed-in name in the topbar opens the same protected `account` page. All password and deletion validation remain unchanged.
5. **Order Start shows two choices only.** Anonymous customers see only **Member** (sign in then self-order) and **Guest** (order without registering). Existing logged-in Member/Admin direct redirects remain for account safety and compatibility.

## Testing

- New semantic checks added to `tests/mobile-ui-static.py`: exactly two Order Start cards, Dashboard QR removed, dedicated Admin QR renderer/route, account profile link without menu row, Guest QR beneath the shared detail grid, compact checkbox CSS.
- End-to-end disposable MySQL HTTP smoke: anonymous order-start choices, Admin-only store QR and link, Dashboard no QR, Guest Admin order detail layout, account name shortcut, role enforcement for shop QR.
- Existing CI covers PHP lint, JS parse, Guest/Member/Admin orders and pickup, account change/deletion security, backups and isolated restore.

## Manual deployment QA

- Check at approx. 375px and wide desktop view: Admin guest/member orders have the same two-panel detail/timeline layout where space permits; Guest QR appears beneath and only for Admin looking at a guest order.
- Confirm staff receipt/pickup checkboxes are visually smaller yet can be selected via their full label; no order should close without the confirmed checkbox.
- Verify Admin Dashboard has no share QR section. Use sidebar **QR และลิงก์ร้าน** to copy QR/link and open the anonymous Order Start page in a private browser to see Member/Guest.
- Verify clicking your name both in the sidebar and in the topbar opens Account Settings. Standalone account nav row is gone.
- Verify Member cannot visit `index.php?page=shop-qr`; guest is redirected to Login.
- Test non-destructively only; do not reset tracking QR tokens for real users or delete real accounts during UI QA.

## Production deploy scope

**Existing private views**
- `laundrytrack_private/app/views/layout_top.php`
- `laundrytrack_private/app/views/dashboard.php`
- `laundrytrack_private/app/views/order.php`
- `laundrytrack_private/app/views/order-start.php`

**Public**
- `public_html/laundrytrack/index.php`
- `public_html/laundrytrack/assets/style.css`

**New private view**
- `laundrytrack_private/app/views/shop-qr.php`

That is six updated files and one new Admin view. No schema migration. After CI and PR merge, stage from the pinned merge commit in a private directory, back up the six existing live files, check the new file does not already exist, PHP-lint every staged PHP file, then deploy in one low-traffic window. To undo, restore the six backups and remove only the newly added `shop-qr.php` file; verify it matches the staged new file before removing it.

Keep backups private, preserve file permissions, and avoid running synthetic CI tests against production customer data.
