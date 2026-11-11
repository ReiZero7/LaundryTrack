<?php
$user = current_user();
$nav = [
    ['dashboard', 'ภาพรวม', '▦'],
    ['orders', is_admin() ? 'รายการซักรีดทั้งหมด' : 'รายการของฉัน', '▤'],
];
if (is_admin()) {
    $nav[] = ['new-order', 'สร้างออร์เดอร์', '+'];
    $nav[] = ['services', 'บริการและราคา', '◈'];
    $nav[] = ['customers', 'ลูกค้า', '♙'];
}
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
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="site-shell">
<?php if ($user): ?>
    <aside class="sidebar">
        <a class="brand" href="<?= e(url('dashboard')) ?>"><span class="brand-logo">◉</span><span>Laundry<span class="brand-accent">Track</span><small>Order Management</small></span></a>
        <p class="menu-title">เมนูหลัก</p>
        <nav class="nav">
            <?php foreach ($nav as [$dest, $label, $glyph]): ?>
            <a class="nav-item <?= $page === $dest ? 'active' : '' ?>" href="<?= e(url($dest)) ?>"><span class="nav-glyph"><?= e($glyph) ?></span><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-profile"><span class="avatar"><?= e(mb_substr_safe($user['full_name'], 0, 1)) ?></span><div><strong><?= e($user['full_name']) ?></strong><small><?= $user['role'] === 'admin' ? 'ผู้ดูแลร้าน' : 'ลูกค้า' ?></small></div></div>
        <form method="post" action="index.php" class="sidebar-logout"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn btn-ghost btn-block" type="submit">ออกจากระบบ</button></form>
    </aside>
<?php endif; ?>
    <div class="content-area <?= $user ? '' : 'guest-area' ?>">
        <header class="topbar"><div><span class="topbar-kicker">LAUNDRY MANAGEMENT</span><strong><?= $user ? 'จัดการซักรีดได้ในที่เดียว' : 'LaundryTrack' ?></strong></div><div class="topbar-actions"><?php if ($readyAlertCount > 0): ?><a class="notification-bell" href="<?= e(url('dashboard')) ?>" aria-label="มี <?= $readyAlertCount ?> ออร์เดอร์ที่พร้อมรับผ้า">🔔 พร้อมรับผ้า <span class="notification-count"><?= $readyAlertCount ?></span></a><?php endif; ?><span class="topbar-tag"><?= $user ? ($user['role'] === 'admin' ? 'ADMIN PANEL' : 'CUSTOMER PORTAL') : 'WELCOME' ?></span></div></header>
        <main class="main-content">
        <?php if ($flashMessage): ?><div class="alert <?= $flashMessage['kind'] === 'error' ? 'alert-error' : 'alert-success' ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
