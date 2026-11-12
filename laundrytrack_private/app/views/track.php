<?php
// Public read-only QR landing page. Never expose personal customer details.
$rawToken = $_GET['token'] ?? '';
$tokenHash = is_string($rawToken) ? tracking_hash($rawToken) : null;
$publicTrackOrder = null;
if ($tokenHash !== null) {
    $stmt = db()->prepare("SELECT o.order_no,o.status,o.due_at,cu.role AS creator_role
        FROM laundry__orders_tbl o
        INNER JOIN laundry__users_tbl cu ON cu.id=o.created_by
        INNER JOIN laundry__users_tbl u ON u.id=o.customer_id
        WHERE o.tracking_token_hash=? AND u.email IS NULL LIMIT 1");
    $stmt->execute([$tokenHash]);
    $publicTrackOrder = $stmt->fetch() ?: null;
}
?>
<?php if (!$publicTrackOrder): ?>
<div class="empty-state">
    <h1>ลิงก์ติดตามไม่ถูกต้องหรือหมดอายุ</h1>
    <p>กรุณาติดต่อร้านเพื่อขอ QR Code ใหม่</p>
    <a class="btn btn-light" href="<?= e(url('home')) ?>">กลับหน้าหลัก</a>
</div>
<?php else: ?>
<div class="page-heading"><div>
    <p class="eyebrow">📍 ติดตามผ่าน QR</p>
    <h1>ติดตามสถานะ</h1>
    <p>สแกน QR เพื่อดูงานซัก</p>
</div></div>
<section class="panel guest-tracking-card">
    <div class="panel-header"><h2>ออร์เดอร์ <?= e($publicTrackOrder['order_no']) ?></h2>
        <span class="status <?= e(status_class($publicTrackOrder['status'])) ?>"><?= e(order_status_label($publicTrackOrder['status'], $publicTrackOrder['creator_role'])) ?></span>
    </div>
    <div class="info-grid">
        <div><small>⏰ วันรับผ้า</small><strong><?= e(date('d/m/Y H:i', strtotime($publicTrackOrder['due_at']))) ?></strong></div>
        <div><small>📍 สถานะ</small><strong><?= e(order_status_label($publicTrackOrder['status'], $publicTrackOrder['creator_role'])) ?></strong></div>
    </div>
    <p class="muted">🔒 แสดงเฉพาะสถานะและวันรับผ้า · ไม่มีข้อมูลส่วนตัว</p>
    <?php if (!current_user()): ?>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-track-login">
            <input type="hidden" name="token" value="<?= e($rawToken) ?>">
            <button class="btn btn-primary" type="submit">🔔 เริ่มติดตาม</button>
        </form>
        <p class="muted">🔄 อัปเดตขณะเปิดหน้า · ไม่ต้องเข้าสู่ระบบ</p>
    <?php else: ?>
        <p class="muted">👤 โปรดออกจากบัญชีสมาชิกก่อนติดตามแบบ Guest</p>
    <?php endif; ?>
</section>
<?php endif; ?>
