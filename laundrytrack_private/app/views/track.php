<?php
// Public read-only QR landing page. Never expose personal customer details.
$rawToken = $_GET['token'] ?? '';
$tokenHash = is_string($rawToken) ? tracking_hash($rawToken) : null;
$publicTrackOrder = null;
if ($tokenHash !== null) {
    $stmt = db()->prepare("SELECT o.order_no,o.status,o.due_at
        FROM laundry__orders_tbl o
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
    <p class="eyebrow">GUEST ORDER TRACKING</p>
    <h1>ติดตามสถานะการซักรีด</h1>
    <p>ตรวจสอบออร์เดอร์ของคุณผ่าน QR Code จากร้าน</p>
</div></div>
<section class="panel guest-tracking-card">
    <div class="panel-header"><h2>ออร์เดอร์ <?= e($publicTrackOrder['order_no']) ?></h2>
        <span class="status <?= e(status_class($publicTrackOrder['status'])) ?>"><?= e(status_label($publicTrackOrder['status'])) ?></span>
    </div>
    <div class="info-grid">
        <div><small>วันที่คาดว่าจะรับผ้า</small><strong><?= e(date('d/m/Y H:i', strtotime($publicTrackOrder['due_at']))) ?></strong></div>
        <div><small>สถานะล่าสุด</small><strong><?= e(status_label($publicTrackOrder['status'])) ?></strong></div>
    </div>
    <p class="muted">หน้า QR นี้แสดงเฉพาะสถานะและกำหนดรับผ้า ไม่แสดงชื่อ เบอร์โทร หรือรายละเอียดส่วนตัว</p>
    <?php if (!current_user()): ?>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-track-login">
            <input type="hidden" name="token" value="<?= e($rawToken) ?>">
            <button class="btn btn-primary" type="submit">🔔 เข้าสู่โหมดติดตามและรับการแจ้งเตือน</button>
        </form>
        <p class="muted">เข้าโหมดติดตามได้โดยไม่ต้องมีอีเมลหรือรหัสผ่าน การแจ้งเตือนจะอัปเดตขณะเปิดเว็บไซต์เท่านั้น</p>
    <?php else: ?>
        <p class="muted">คุณกำลังเข้าสู่ระบบสมาชิกอยู่ หากต้องการติดตามแบบลูกค้าหน้าร้าน กรุณาออกจากบัญชีสมาชิกก่อน</p>
    <?php endif; ?>
</section>
<?php endif; ?>
