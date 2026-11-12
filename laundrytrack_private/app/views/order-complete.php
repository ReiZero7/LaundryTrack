<?php
if (current_user()) go('dashboard');
$pending = $_SESSION['new_self_order'] ?? null;
$token = is_array($pending) ? ($pending['token'] ?? null) : null;
$valid = is_string($token) && tracking_hash($token) !== null
    && hash_equals((string)($pending['token_hash'] ?? ''), tracking_hash($token));
$order = null;
if ($valid) {
    $stmt = db()->prepare("SELECT o.order_no,o.status,o.due_at
        FROM laundry__orders_tbl o
        INNER JOIN laundry__users_tbl u ON u.id=o.customer_id
        WHERE o.id=? AND o.customer_id=? AND o.tracking_token_hash=? AND u.email IS NULL LIMIT 1");
    $stmt->execute([(int)$pending['order_id'], (int)$pending['customer_id'], $pending['token_hash']]);
    $order = $stmt->fetch() ?: null;
}
?>
<?php if (!$order): ?>
<div class="empty-state"><h1>ไม่พบรายการสั่งบริการล่าสุด</h1><p>กรุณาสร้างออร์เดอร์ใหม่ หรือติดต่อร้านหากมีการบันทึกไปแล้ว</p><a class="btn btn-primary" href="<?= e(url('order-start')) ?>">เริ่มสั่งบริการ</a></div>
<?php else: ?>
<?php $link = tracking_link($token); ?>
<div class="page-heading"><div><p class="eyebrow">✅ สั่งสำเร็จ</p>
<h1>✅ สั่งซักสำเร็จ</h1>
<p>🧺 นำผ้ามาส่งที่ร้านเพื่อเริ่มงาน</p>
</div></div>
<section class="panel guest-tracking-card">
    <div class="panel-header"><h2>หมายเลข <?= e($order['order_no']) ?></h2><span class="status <?= e(status_class($order['status'])) ?>"><?= e(order_status_label($order['status'], 'customer')) ?></span></div>
    <p class="muted">🔒 เก็บ QR นี้ไว้ติดตาม · อย่าแชร์</p>
    <div class="walkin-qr-output" data-qr-link="<?= e($link) ?>">
        <div id="order-qr-code" aria-label="QR Code ติดตามออร์เดอร์"></div>
        <label>ลิงก์ติดตามของคุณ <input type="text" id="order-tracking-url" readonly value="<?= e($link) ?>"></label>
        <button class="btn btn-light" id="copy-order-tracking-url" type="button">📋 คัดลอก</button>
    </div>
    <p><a class="btn btn-light" href="<?= e(url('track', ['token' => $token])) ?>">📍 ติดตามงาน</a></p>
</section>
<section class="panel self-after-order">
    <h2>👤 สมัครสมาชิก?</h2>
    <p>เก็บประวัติออร์เดอร์ในบัญชีของคุณ</p>
    <div class="self-after-actions">
        <a class="btn btn-primary" href="<?= e(url('join-after-order')) ?>">สมัครสมาชิก</a>
        <a class="btn btn-light" href="<?= e(url('track', ['token' => $token])) ?>">ติดตามด้วย QR</a>
    </div>
</section>
<?php endif; ?>
