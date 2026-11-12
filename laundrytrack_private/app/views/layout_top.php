<?php
$user = current_user();
$nav = is_admin() ? [
    ['dashboard', 'ภาพรวม', '▦'],
    ['manage-orders', 'จัดการออร์เดอร์', '☷'],
    ['orders', 'รายการซักทั้งหมด', '▤'],
    ['new-order', 'สร้างออร์เดอร์', '+'],
    ['services', 'บริการและราคา', '◈'],
    ['customers', 'ลูกค้า', '♙'],
] : [
    ['progress', 'ความคืบหน้าการซักรีด', '◉'],
    ['orders', 'ประวัติรายการ', '▤'],
    ['self-order', 'สร้างออร์เดอร์', '+'],
];
$readyAlertCount = 0;
if ($user && !is_admin()) {
    $readyStmt = db()->prepare("SELECT COUNT(*) FROM laundry__orders_tbl WHERE customer_id=? AND status='ready'");
    $readyStmt->execute([(int)$user['id']]);
    $readyAlertCount = (int)$readyStmt->fetchColumn();
}
$flashMessage = take_flash();
?><!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="LaundryTrack ระบบจัดการร้านซักรีดและติดตามสถานะ">
    <title><?= e(ucfirst(str_replace('-', ' ', (string)$page))) ?> | LaundryTrack</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/vendor/qrcode.min.js" defer></script>
    <script src="assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">ข้ามไปยังเนื้อหา</a>
<div class="site-shell">
<?php if ($user): ?>
    <aside class="sidebar" id="site-navigation" aria-label="เมนูนำทางหลัก">
        <div class="mobile-sidebar-head"><strong>เมนูหลัก</strong><button class="mobile-nav-close" type="button" data-mobile-close aria-label="ปิดเมนูนำทาง">×</button></div>
        <a class="brand" href="<?= e(url(is_admin() ? 'dashboard' : 'progress')) ?>"><span class="brand-logo">◉</span><span>Laundry<span class="brand-accent">Track</span><small>Order Management</small></span></a>
        <p class="menu-title">เมนูหลัก</p>
        <nav class="nav" aria-label="เมนูหลัก">
            <?php foreach ($nav as [$dest, $label, $glyph]): ?>
            <a class="nav-item <?= $page === $dest ? 'active' : '' ?>" href="<?= e(url($dest)) ?>" <?= $page === $dest ? 'aria-current="page"' : '' ?>><span class="nav-glyph"><?= e($glyph) ?></span><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="sidebar-profile account-profile-link" href="<?= e(url('account')) ?>" aria-label="ตั้งค่าบัญชีของ <?= e($user['full_name']) ?>"><span class="avatar" aria-hidden="true"><?= e(mb_substr_safe($user['full_name'], 0, 1)) ?></span><span class="account-profile-text"><strong><?= e($user['full_name']) ?></strong><small><?= $user['role'] === 'admin' ? 'ผู้ดูแลร้าน' : 'ลูกค้า' ?> · จัดการบัญชี</small></span><span class="account-profile-arrow" aria-hidden="true">›</span></a>
        <form method="post" action="index.php" class="sidebar-logout"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn btn-ghost btn-block" type="submit">ออกจากระบบ</button></form>
    </aside>
    <button class="mobile-nav-backdrop" type="button" data-mobile-backdrop aria-label="ปิดเมนูนำทาง" hidden></button>
<?php endif; ?>
    <div class="content-area <?= $user ? '' : 'guest-area' ?>">
        <header class="topbar"><?php if ($user): ?><button class="mobile-menu-toggle" type="button" data-mobile-menu aria-controls="site-navigation" aria-expanded="false" aria-label="เปิดเมนูนำทาง"><span aria-hidden="true">☰</span><span>เมนู</span></button><?php endif; ?><div class="topbar-heading"><span class="topbar-kicker">LAUNDRY MANAGEMENT</span><strong><?= $user ? 'จัดการซักรีดได้ในที่เดียว' : 'LaundryTrack' ?></strong></div><div class="topbar-actions"><?php if ($user && !is_admin()): ?><a id="customer-notifications" class="notification-bell" href="<?= e(url('progress')) ?>" data-feed="<?= e(url('notifications-feed')) ?>" data-count="<?= $readyAlertCount ?>" aria-label="การแจ้งเตือน: มี <?= $readyAlertCount ?> ออร์เดอร์ที่พร้อมรับผ้า">🔔 แจ้งเตือน <span class="notification-count" data-ready-badge <?= $readyAlertCount === 0 ? 'hidden' : '' ?>><?= $readyAlertCount ?></span></a><?php endif; ?><span class="topbar-tag"><?= $user ? ($user['role'] === 'admin' ? 'ADMIN PANEL' : 'CUSTOMER PORTAL') : 'WELCOME' ?></span></div></header>
        <main class="main-content" id="main-content" tabindex="-1">
        <?php if ($flashMessage): ?><div class="alert <?= $flashMessage['kind'] === 'error' ? 'alert-error' : 'alert-success' ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
