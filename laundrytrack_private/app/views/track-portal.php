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
<p class="form-help" role="note">เมื่อสถานะแสดงว่า “พร้อมรับผ้า” ให้แจ้งความประสงค์รับผ้าผ่านหน้านี้ จากนั้นพนักงานต้องตรวจสอบและยืนยันการส่งมอบที่ร้านก่อนสถานะจะเปลี่ยนเป็น “รับผ้าคืนแล้ว”</p>
<div class="guest-tracking-grid">
    <section class="panel guest-tracking-card"
        id="guest-track-panel" data-feed="<?= e(url('track-feed')) ?>"
        data-initial-status="<?= e($guestOrder['status']) ?>">
        <div class="panel-header">
            <h2>หมายเลข <?= e($guestOrder['order_no']) ?></h2>
            <span id="guest-order-status" class="status <?= e(status_class($guestOrder['status'])) ?>"><?= e(order_status_label($guestOrder['status'], $guestOrder['creator_role'])) ?></span>
        </div>
        <div class="info-grid">
            <div><small>กำหนดรับผ้าโดยประมาณ</small><strong><?= e(date('d/m/Y H:i', strtotime($guestOrder['due_at']))) ?></strong></div>
            <div><small>การอัปเดต</small><strong>อัตโนมัติทุก 30 วินาที</strong></div>
        </div>
        <p id="guest-ready-message" class="guest-ready-message" role="status" <?= $guestOrder['status'] === 'ready' ? '' : 'hidden' ?>>🔔 ผ้าพร้อมรับแล้ว กรุณามารับผ้าที่ร้าน</p>
        <p id="guest-pickup-pending" class="pickup-admin-note" role="status" <?= $guestOrder['status'] === 'ready' && $guestOrder['pickup_requested_at'] ? '' : 'hidden' ?>>✓ แจ้งความประสงค์รับผ้าแล้ว กรุณารอพนักงานตรวจสอบและยืนยันส่งมอบจริง</p>
        <form id="guest-pickup-form" method="post" action="index.php" class="form-stack" <?= $guestOrder['status'] === 'ready' && !$guestOrder['pickup_requested_at'] ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-confirm-pickup">
            <p>เมื่อถึงร้าน ให้แจ้งพนักงานว่าต้องการรับผ้า พนักงานจะตรวจสอบและยืนยันส่งมอบจริง</p>
            <button class="btn btn-primary" type="submit">แจ้งพนักงานว่าต้องการรับผ้า</button>
        </form>
        <p class="muted">ลิงก์จาก QR เป็นสิทธิ์เข้าถึงเฉพาะออร์เดอร์นี้ อย่าแชร์ลิงก์หรือ QR ให้ผู้อื่น</p>
        <?php if ($guestOrder['status'] === 'picked_up' && ($eligibleClaim = membership_claim()) !== null
                  && $eligibleClaim['source'] === 'guest'
                  && $eligibleClaim['order_id'] === (int)$guestOrder['id']): ?>
        <div class="self-after-order">
            <h3>รับผ้าเรียบร้อยแล้ว ต้องการสมัครสมาชิกไหม?</h3>
            <p>เก็บออร์เดอร์นี้ไว้เป็นประวัติในบัญชีสมาชิกใหม่ แม้รับผ้าคืนแล้วก็สมัครได้</p>
            <a class="btn btn-primary" href="<?= e(url('join-after-order')) ?>">สมัครสมาชิกและเก็บประวัติออร์เดอร์</a>
        </div>
        <?php endif; ?>
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
