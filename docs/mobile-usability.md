# Mobile UI/UX refresh — LaundryTrack

Scope: **presentation, accessibility and clarity only**; no SQL schema, permissions, pickup transitions or server-side business logic changed.

## Changes

- **Authenticated mobile navigation:** the existing desktop sidebar becomes an off-canvas menu below 781px with a clearly labeled touch button, close button and backdrop. It closes with Escape, backdrop tap, or page selection; keyboard Tab is constrained to the open menu, and page content is inert while open. The active page exposes `aria-current="page"`. If JavaScript is unavailable, the original horizontal navigation remains visible.
- **Orders page:** the existing accessible HTML table becomes labeled, vertically stacked cards at 600px or less, with a full-size details action and no horizontal table scrolling. Desktop retains the original data table and filters.
- **Order creation:** wider touch targets, iOS-friendly 16px form controls, single-column mobile service rows, an easier number input, auto-focus on an added service, and polite announcement of recalculated totals. Important steps are explained for self-order and staff walk-in orders.
- **Onboarding:** public homepage describes selecting services, drop-off, tracking and staff-approved pickup in three steps. QR guest tracking clarifies that asking for pickup is not proof of physical delivery.
- **Accessibility:** skip-to-content link, navigation landmarks, keyboard-accessible scrollable item table, retained screen-reader table headers, reduced-motion support and meaningful labels.
- Existing blue water / washing-machine / detergent theme is retained. No external image/CDN or tracking dependency is introduced.

## Automated verification

- `python3 tests/mobile-ui-static.py`: semantic controls, drawer keyboard behavior, responsive layouts, form guidance, table labels and fallback navigation.
- `node --check public_html/laundrytrack/assets/app.js`: JavaScript syntax.
- Existing PHP lint and Guest/Member/Admin HTTP smoke remain active. The smoke tests also confirm the mobile navigation and order-table markup.
- Existing isolated database backup and restore checks stay unchanged.
- **Important:** static and HTTP tests do not measure actual tap geometry or browser-specific visual layout. Perform the device checklist before production rollout.

## Manual device checklist

Use browser mobile emulation (e.g. 320, 375, 430, 768px wide) and at least one real iOS/Android phone:

1. Guest homepage: headline, three-step guide and CTA remain readable; no clipped text or document-wide horizontal scrollbar.
2. Login as Member: menu toggle is visible; opening drawer focuses Close; Tab cycles within drawer; Escape, Close and backdrop work; selecting a page navigates. Logout is reachable from drawer.
3. As Admin: all 5 sidebar destinations and logout remain reachable; dashboard cards stay legible.
4. Orders list: filters stack, each order displays a compact labeled card below 601px; use the details button and ensure item status and pickup pending guidance are visible.
5. Admin New Order and Member/Guest Self-Order: service selector and quantity work, add another item, remove item, totals recalculate, keypad is appropriate and inputs remain visible above the mobile keyboard.
6. Order detail: service table scrolls horizontally **within** its panel and can be keyboard-focused; pickup request still leaves status as `ready` until staff confirms.
7. Guest QR portal: pending/requested state and pickup instructions are readable; do not reveal customer personal data to anonymous QR viewers.
8. Widen viewport above 780px: desktop sidebar returns and no mobile overlay remains. With JavaScript disabled, the fallback navigation remains usable.
9. Check focus indication, text zoom to 200%, reduced motion and portrait/landscape rotation.

## Production rollout (when CI and mobile review pass)

**Only nine files changed on the live site:**

Private directory `~/laundrytrack_private/app/views/`:
- `layout_top.php`
- `orders.php`
- `home.php`
- `self-order.php`
- `new-order.php`
- `order.php`
- `track-portal.php`

Public directory `~/public_html/laundrytrack/assets/`:
- `style.css`
- `app.js`

Always stage files from a **pinned reviewed main commit** after merging, save their existing versions in a dated private backup directory, and check syntax for each PHP file. Compare each existing live file with its saved copy before deploying; copy the nine files together during low traffic. Existing `.env`, `bootstrap.php`, `actions.php`, MySQL data and migration history remain untouched.

If a browser displays old layout, reload without cache (or use a versioned CSS/JS URL after a properly coordinated deployment). If major visual or interaction issues appear, restore the nine private backup files and retest. Never run CI's synthetic-order scripts against Angsila production.
