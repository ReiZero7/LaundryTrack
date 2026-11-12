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
    "accessibility motion preference remains honored":
        '@media(prefers-reduced-motion:reduce)' in style,
}

for name, passes in checks.items():
    if not passes:
        raise SystemExit(f"FAIL: {name}")
    print(f"PASS: {name}")

print(f"ALL MOBILE UI STATIC CHECKS PASSED: {len(checks)}")
