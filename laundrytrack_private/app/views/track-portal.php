<?php
$guestOrder = current_user() ? null : guest_tracking_order();
$guestServices = $guestOrder ? order_service_items((int)$guestOrder['id']) : [];
?>
<?php if (!$guestOrder): ?>
<div class="empty-state">
    <h2>ไม่มีสิทธิ์ติดตามออร์เดอร์ในหน้านี้</h2>
    <p>กรุณาใช้ QR Code จากร้านเพื่อเข้าสู่โหมดติดตามอีกครั้ง</p>
    <a class="btn btn-primary" href="<?= e(url('home')) ?>">กลับหน้าหลัก</a>
</div>
<?php else: ?>
<div class="page-heading"><div>
    <p class="eyebrow">📍 ติดตามผ้า</p>
    <h1>สถานะออร์เดอร์</h1>
    <p>🔄 อัปเดตทุก 30 วินาทีขณะเปิดหน้า</p>
</div></div>
<p class="form-help" role="note">🔔 พร้อมรับ → แจ้งรับที่ร้าน → ✅ พนักงานยืนยันส่งมอบ</p>
<div class="guest-tracking-grid">
    <section class="panel guest-tracking-card"
        id="guest-track-panel" data-feed="<?= e(url('track-feed')) ?>"
        data-initial-status="<?= e($guestOrder['status']) ?>">
        <div class="panel-header">
            <h2>หมายเลข <?= e($guestOrder['order_no']) ?></h2>
            <span id="guest-order-status" class="status <?= e(status_class($guestOrder['status'])) ?>"><?= e(order_status_label($guestOrder['status'], $guestOrder['creator_role'])) ?></span>
        </div>
        <div class="info-grid">
            <div><small>⏰ รับประมาณ</small><strong><?= e(date('d/m/Y H:i', strtotime($guestOrder['due_at']))) ?></strong></div>
            <div><small>🔄 อัปเดต</small><strong>ทุก 30 วินาที</strong></div>
        </div>
        <section class="guest-service-progress" aria-label="สถานะงานของแต่ละบริการ">
            <h3>🫧 บริการที่สั่ง</h3>
            <p class="muted">✅ ต้องทำครบทุกบริการก่อนพร้อมรับ</p>
            <ul class="service-progress-list">
                <?php foreach ($guestServices as $service): ?>
                <li data-guest-service-item="<?= (int)$service['id'] ?>">
                    <strong><?= e($service['name']) ?></strong>
                    <span class="service-work-badge service-work-<?= e($service['work_status']) ?>" data-service-progress-label><?= e(service_work_label($service['work_status'], $service['name'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <p id="guest-ready-message" class="guest-ready-message" role="status" <?= $guestOrder['status'] === 'ready' ? '' : 'hidden' ?>>🔔 ผ้าพร้อมรับแล้ว</p>
        <p id="guest-pickup-pending" class="pickup-admin-note" role="status" <?= $guestOrder['status'] === 'ready' && $guestOrder['pickup_requested_at'] ? '' : 'hidden' ?>>✅ แจ้งรับแล้ว · รอพนักงานส่งมอบ</p>
        <form id="guest-pickup-form" method="post" action="index.php" class="form-stack" <?= $guestOrder['status'] === 'ready' && !$guestOrder['pickup_requested_at'] ? '' : 'hidden' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-confirm-pickup">
            <p>📍 ถึงร้านแล้ว? กดแจ้งพนักงาน</p>
            <button class="btn btn-primary" type="submit">🔔 แจ้งรับผ้า</button>
        </form>
        <p class="muted">🔒 QR นี้ใช้ดูออร์เดอร์ของคุณ · อย่าแชร์</p>
        <?php if ($guestOrder['status'] === 'picked_up' && ($eligibleClaim = membership_claim()) !== null
                  && $eligibleClaim['source'] === 'guest'
                  && $eligibleClaim['order_id'] === (int)$guestOrder['id']): ?>
        <div class="self-after-order">
            <h3>✅ รับผ้าแล้ว · สมัครสมาชิก?</h3>
            <p>เก็บประวัติงานซักไว้ในบัญชีใหม่</p>
            <a class="btn btn-primary" href="<?= e(url('join-after-order')) ?>">👤 สมัครสมาชิก</a>
        </div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <h2>↩ ออกจากการติดตาม</h2>
        <p class="muted">กลับเข้ามาได้ด้วย QR เดิม (หากยังใช้ได้)</p>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="guest-track-logout">
            <button class="btn btn-light" type="submit">ออกจากหน้านี้</button>
        </form>
    </section>
</div>
<?php endif; ?>
