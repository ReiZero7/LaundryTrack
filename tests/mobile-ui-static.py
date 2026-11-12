#!/usr/bin/env python3
"""Static mobile UI regression checks. No external network or production database."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def source(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


layout = source("laundrytrack_private/app/views/layout_top.php")
orders = source("laundrytrack_private/app/views/orders.php")
home = source("laundrytrack_private/app/views/home.php")
self_order = source("laundrytrack_private/app/views/self-order.php")
new_order = source("laundrytrack_private/app/views/new-order.php")
details = source("laundrytrack_private/app/views/order.php")
guest_portal = source("laundrytrack_private/app/views/track-portal.php")
dashboard = source("laundrytrack_private/app/views/dashboard.php")
order_start = source("laundrytrack_private/app/views/order-start.php")
shop_qr = source("laundrytrack_private/app/views/shop-qr.php")
router = source("public_html/laundrytrack/index.php")
account = source("laundrytrack_private/app/views/account.php")
actions = source("laundrytrack_private/app/actions.php")
progress = source("laundrytrack_private/app/views/progress.php")
migration = source("laundrytrack_private/migrations/2026-11-12_order_item_progress.sql")
style = source("public_html/laundrytrack/assets/style.css")
script = source("public_html/laundrytrack/assets/app.js")

checks = {
    "mobile navigation toggle with accessible expanded state":
        'data-mobile-menu' in layout and 'aria-expanded="false"' in layout
        and 'aria-controls="site-navigation"' in layout,
    "drawer close button, backdrop and nav landmark":
        all(x in layout for x in ('data-mobile-close', 'data-mobile-backdrop',
                                 'id="site-navigation"', 'aria-label="เมนูนำทางหลัก"')),
    "current page, skip link and focusable main content":
        all(x in layout for x in ('aria-current="page"', 'class="skip-link"',
                                 'id="main-content"', 'tabindex="-1"')),
    "menu supports keyboard escape, focus trap and background inertness":
        all(x in script for x in ("event.key === 'Escape'", "event.key === 'Tab'",
                                  "content.inert = showDrawer", "closeButton.focus()",
                                  "toggle.focus()")),
    "navigation remains available without JavaScript":
        'mobile-menu-ready' in script
        and 'html.mobile-menu-ready .sidebar' in style,
    "mobile responsive breakpoints and touch targets":
        all(x in style for x in ('@media (max-width:780px)', '@media (max-width:600px)',
                                'min-height:44px', 'font-size:16px')),
    "mobile order listing renders semantic table and labeled cards":
        all(x in orders for x in ('class="orders-mobile-table"',
                                 'data-label="หมายเลขออร์เดอร์"',
                                 'data-label="สถานะ"',
                                 'data-label="รายละเอียด"'))
        and '.orders-mobile-table tbody td::before' in style,
    "table headers remain available to screen readers":
        '.orders-mobile-table thead{position:absolute' in style,
    "service items are easier to edit on a narrow screen":
        '.order-item-row>label:first-child' in style
        and "next.querySelector('.service-select').focus()" in script,
    "three-step public how-to guide clarifies handoff":
        'class="usage-steps"' in home
        and 'พนักงานยืนยันส่งมอบจริง' in home,
    "member, guest and staff ordering guidance exists":
        'class="form-help"' in self_order
        and 'class="form-help"' in new_order
        and 'class="form-help"' in guest_portal,
    "order details table supports keyboard horizontal scrolling":
        'aria-label="ตารางรายการบริการของออร์เดอร์"' in details
        and 'tabindex="0"' in details
        and 'mobile-table-hint' in details,
    "account deletion no longer asks for typed DELETE":
        'name="delete_confirmed"' in account
        and 'name="delete_confirmation"' not in account
        and "post_string('delete_confirmed') !== 'yes'" in actions,
    "order detail shows each selected service and staff-selectable work states":
        "order_service_items($id)" in details
        and 'name="order-item-progress"' not in details
        and 'value="order-item-progress"' in details
        and 'value="done"' in details and 'value="working"' in details,
    "order ready state is gated on completion of all service work":
        "Unfinished services" in actions
        and "AND p.work_status='done'" not in actions
        and 'work_status' in migration,
    "Member and Guest live tracking show individual ironing service status":
        'data-member-service-item' in progress
        and 'data-guest-service-item' in guest_portal
        and "item.services" in script and "data.services" in script,
    "accessibility motion preference remains honored":
        '@media(prefers-reduced-motion:reduce)' in style,
    "Member and Guest order-start choices are the only two cards":
        order_start.count('class="panel self-start-card order-start-choice"') == 2
        and '<h2>Member</h2>' in order_start
        and '<h2>Guest</h2>' in order_start,
    "store QR removed from Dashboard and isolated to protected route":
        'id="order-qr-code"' not in dashboard
        and 'shop_order_link()' in shop_qr
        and "['services', 'customers', 'new-order', 'manage-orders', 'shop-qr']" in router,
    "QR generation reuses existing local, privacy-safe QR script":
        'class="shop-qr-preview walkin-qr-output" data-qr-link=' in shop_qr
        and 'walkin-qr-output[data-qr-link]' in script,
    "account settings link comes from profile name, not sidebar menu":
        'class="sidebar-profile account-profile-link"' in layout
        and "url('account')" in layout
        and "['account', 'บัญชีของฉัน'" not in layout,
    "guest QR outside shared order-detail grid keeps timeline alongside details":
        'class="detail-grid"' in details
        and 'class="order-qr-below"' in details
        and details.find('class="detail-grid"') < details.find('>ติดตามสถานะ</h2>')
        < details.find('class="order-qr-below"'),
    "confirmation checkboxes are compact and still have a tappable label":
        '.form-stack .dropoff-confirm-check input[type="checkbox"]' in style
        and 'width:17px!important' in style
        and '.form-stack .dropoff-confirm-check{' in style,
}

for name, passes in checks.items():
    if not passes:
        raise SystemExit(f"FAIL: {name}")
    print(f"PASS: {name}")

print(f"ALL MOBILE UI STATIC CHECKS PASSED: {len(checks)}")
