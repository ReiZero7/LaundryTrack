<?php
$guestOrder = current_user() ? null : guest_tracking_order();
?>
<?php if (!$guestOrder): ?>
<div class="empty-state">
    <h2>ไม่มีสิทธิ์ติดตามออร์เดอร์ในหน้านี้</h2>
    <p>กรุณาใช้ QR Code จากร้านเพื่อเข้าสู่โหมดติดตามอีกครั้ง</p>
    <a class="btn btn-primary" href="<?= e(url('home')) ?>">กลับหน้าหลัก</a>
</div>
<?php else: ?>
<div class="page-heading"><div>
    <p class="eyebrow">LAUNDRY TRACKING PORTAL</p>
    <h1>ติดตามสถานะแบบอัตโนมัติ</h1>
    <p>หน้าเว็บจะตรวจสอบสถานะออร์เดอร์นี้ทุก 30 วินาที ขณะเปิดแท็บอยู่</p>
</div></div>
<div class="guest-tracking-grid">
    <section class="panel guest-tracking-card"
        id="guest-track-panel" data-feed="<?= e(url('track-feed')) ?>"
        data-initial-status="<?= e($guestOrder['status']) ?>">
        <div class="panel-header">
            <h2>หมายเลข <?= e($guestOrder['order_no']) ?></h2>
            <span id="guest-order-status" class="status <?= e(status_class($guestOrder['status'])) ?>"><?= e(status_label($guestOrder['status'])) ?></span>
        </div>
        <div class="info-grid">
            <div><small>กำหนดรับผ้าโดยประมาณ</small><strong><?= e(date('d/m/Y H:i', strtotime($guestOrder['due_at']))) ?></strong></div>
            <div><small>การอัปเดต</small><strong>อัตโนมัติทุก 30 วินาที</strong></div>
        </div>
        <p id="guest-ready-message" class="guest-ready-message" role="status" <?= $guestOrder['status'] === 'ready' ? '' : 'hidden' ?>>🔔 ผ้าพร้อมรับแล้ว กรุณามารับผ้าที่ร้าน</p>
        <form id="guest-pickup-form" method="post" action="index.php" class="form-stack" <?= $guestOrder['status'] === 'ready' ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-confirm-pickup">
            <p>เมื่อมารับผ้าและตรวจสอบเรียบร้อยแล้ว จึงกดยืนยันรับผ้า</p>
            <button class="btn btn-primary" type="submit">✓ ยืนยันว่าได้รับผ้าแล้ว</button>
        </form>
        <p class="muted">ลิงก์จาก QR เป็นสิทธิ์เข้าถึงเฉพาะออร์เดอร์นี้ อย่าแชร์ลิงก์หรือ QR ให้ผู้อื่น</p>
    </section>
    <section class="panel">
        <h2>ออกจากโหมดติดตาม</h2>
        <p class="muted">สามารถสแกน QR Code เพื่อเข้ามาติดตามอีกครั้ง หากร้านยังไม่ได้เปลี่ยน QR</p>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-track-logout">
            <button class="btn btn-light" type="submit">ออกจากโหมดติดตาม</button>
        </form>
    </section>
</div>
<?php endif; ?>
