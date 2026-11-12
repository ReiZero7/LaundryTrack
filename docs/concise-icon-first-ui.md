# LaundryTrack: concise, icon-first UI refresh

The website now favors **short Thai action words plus visual pictograms** over long marketing/explanation paragraphs. This is a presentation-only update, not a change to order, account, QR or permissions logic.

## What changed

- Removed the full footer banner `LaundryTrack • PHP + MySQL • Final Project Demo` and the `Order Management` subtitle under the sidebar logo; the app brand `LaundryTrack` remains.
- Admin sidebar: **🏠 ภาพรวม**, **📋 งานค้าง**, **🧾 ออร์เดอร์ทั้งหมด**, **➕ สร้างออร์เดอร์**, **🫧 บริการ**, **👥 ลูกค้า**, **▦ QR ร้าน**.
- Member sidebar: **📍 ติดตามงาน**, **🧾 ประวัติ**, **➕ สั่งซัก**. Clicking the account name still opens account settings.
- Shortened homepage to one-sentence value proposition and an icon-first, 3-step workflow. Action cards are clearer on mobile.
- Admin metrics and oldest-first work queue use short labels, counts, dates and symbols instead of repeated explanation.
- Member and Guest order entry, detailed tracking, each-service progress and pickup copy use compact headings and buttons. Service progress still shows **every ordered service**.
- Shop QR, customer/accounts/services and authentication views use concise labels and visual symbols; native form labels are kept for accessibility.
- Responsive CSS refines icon chips, card density, keyboard focus and smaller-screen layout; no third-party icon libraries/CDNs.

## Explicit safeguards retained

- Order creation still requires physical laundry drop-off before staff acceptance.
- Staff must mark **every ordered service** (such as washing + ironing) complete before an order can be ready; UI changes do not bypass this.
- Staff-verified actual handoff is still required to finalize pickup.
- Personal QR tracking URLs remain order-scoped and confidential; the store QR remains public by purpose and its management is Admin-only.
- Account deletion still requires current password and explicit confirmation checkbox, with active-order/last-admin rules unchanged. Deletion and retention disclosures remain visible.
- CSRF, permissions, validation, history, and backend state transitions are unchanged.

## Verification and rollout

- `tests/mobile-ui-static.py` checks that demo branding is gone, navigation uses icons and short labels, service forms and privacy messages remain, and responsive/keyboard styles exist.
- Existing isolated CI tests must continue passing: PHP lint, JS syntax, login/CSRF/rate limiting, Guest/Member/Admin HTTP flows, pickup, service completion and backup/restore.
- No new PHP routes, JavaScript dependencies, SQL tables or database migrations.
- On Angsila, deploy only after staging and backing up changed *existing* live files against a pinned merged commit. Keep `~/laundrytrack_private/.env` out of the repository. Check desktop and 375px mobile layout and the live file content. Avoid creating fake orders, changing real task completion states or deleting live accounts merely for UI testing.
- UI cache: after deployment, force-refresh browser CSS using `Ctrl + F5`; if behind a caching proxy, clear only the stylesheet cache.
