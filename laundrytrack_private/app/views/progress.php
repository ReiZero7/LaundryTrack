<?php
require_login();
if (is_admin()) go('dashboard');
$customerId = (int)current_user()['id'];
$stmt = db()->prepare("SELECT id,order_no,status,due_at,total_amount,received_at
    FROM laundry__orders_tbl
    WHERE customer_id=? AND status NOT IN ('picked_up','cancelled')
    ORDER BY id DESC LIMIT 8");
$stmt->execute([$customerId]);
$activeOrders = $stmt->fetchAll();
$stmt = db()->prepare("SELECT id,order_no,status,due_at,total_amount,received_at
    FROM laundry__orders_tbl
    WHERE customer_id=? AND status IN ('picked_up','cancelled')
    ORDER BY id DESC LIMIT 4");
$stmt->execute([$customerId]);
$recentHistory = $stmt->fetchAll();
$steps = [
    'requested' => 'รอส่งผ้า', 'received' => 'รับผ้าแล้ว',
    'washing' => 'กำลังซัก', 'drying' => 'กำลังอบ/ตาก',
    'ready' => 'พร้อมรับ', 'picked_up' => 'รับผ้าคืนแล้ว',
];
?>
<div class="member-progress-page" id="member-progress-page" data-feed="<?= e(url('progress-feed')) ?>">
    <section class="member-progress-hero">
        <p class="eyebrow">YOUR LAUNDRY JOURNEY</p>
        <h1>ความคืบหน้าการซักรีด</h1>
        <p>สวัสดี <?= e(current_user()['full_name']) ?> ตรวจสอบทุกขั้นตอนการซักรีดของคุณได้ที่นี่</p>
        <div class="member-progress-hero-actions">
            <a href="<?= e(url('self-order')) ?>" class="btn btn-primary">+ สร้างออร์เดอร์</a>
            <a href="<?= e(url('orders')) ?>" class="btn btn-light">ดูประวัติรายการ</a>
        </div>
    </section>

    <?php if (!$activeOrders): ?>
        <section class="panel member-empty-progress">
            <div class="member-progress-empty-icon">🧺</div>
            <h2>ยังไม่มีออร์เดอร์ที่กำลังดำเนินการ</h2>
            <p>เลือกบริการซักรีดและสร้างออร์เดอร์ใหม่ได้เลย</p>
            <a class="btn btn-primary" href="<?= e(url('self-order')) ?>">สร้างออร์เดอร์แรก</a>
        </section>
    <?php else: ?>
        <section class="member-progress-intro"><h2>กำลังดำเนินการ</h2><span><?= count($activeOrders) ?> ออร์เดอร์ล่าสุด</span></section>
        <div class="member-progress-list">
        <?php foreach ($activeOrders as $o): ?>
            <?php $stage = order_progress_stage($o['status']); ?>
            <article class="panel member-progress-card" data-progress-order="<?= (int)$o['id'] ?>">
                <div class="member-progress-card-head">
                    <div><p class="eyebrow">LAUNDRY ORDER</p><h2><?= e($o['order_no']) ?></h2></div>
                    <span class="status <?= e(status_class($o['status'])) ?>" data-progress-status><?= e(status_label($o['status'])) ?></span>
                </div>
                <div class="member-progress-highlight">
                    <div class="member-progress-highlight-title"><span>สถานะล่าสุด</span><strong data-progress-main-label><?= e(status_label($o['status'])) ?></strong></div>
                    <span data-progress-count><?= $stage ?>/6 ขั้นตอน</span>
                </div>
                <div class="member-progress-track" role="progressbar" aria-label="ความคืบหน้าของออร์เดอร์ <?= e($o['order_no']) ?>" aria-valuemin="0" aria-valuemax="6" aria-valuenow="<?= $stage ?>">
                    <div class="member-progress-track-fill" data-progress-fill style="width:<?= (int)(100*$stage/6) ?>%"></div>
                </div>
                <ol class="member-step-grid" aria-label="ขั้นตอนการซักรีด">
                <?php foreach ($steps as $code => $label): ?>
                    <?php $step = order_progress_stage($code); ?>
                    <li class="member-progress-step <?= $step <= $stage ? 'completed' : '' ?> <?= $step === $stage ? 'current' : '' ?>" data-progress-step="<?= $step ?>">
                        <span class="member-step-dot" aria-hidden="true"><?= $step < $stage ? '✓' : $step ?></span>
                        <span><?= e($label) ?></span>
                    </li>
                <?php endforeach; ?>
                </ol>
                <?php if ($o['status'] === 'requested'): ?>
                <p class="member-progress-guidance" data-progress-guidance>กรุณานำผ้ามาส่งที่ร้าน เมื่อพนักงานตรวจรับแล้วสถานะจะอัปเดต</p>
                <?php elseif ($o['status'] === 'ready'): ?>
                <p class="member-progress-guidance" data-progress-guidance>🔔 ผ้าพร้อมรับแล้ว กรุณารับผ้าและยืนยันในหน้ารายละเอียด</p>
                <?php else: ?>
                <p class="member-progress-guidance" data-progress-guidance>ระบบอัปเดตความคืบหน้าทุกประมาณ 30 วินาทีขณะเปิดเว็บไซต์</p>
                <?php endif; ?>
                <div class="member-progress-foot">
                    <span>กำหนดรับประมาณ <?= e(date('d/m/Y H:i', strtotime($o['due_at']))) ?></span>
                    <a class="btn btn-primary" href="<?= e(url('order', ['id' => (int)$o['id']])) ?>">ดูรายละเอียด<?= $o['status'] === 'ready' ? ' / ยืนยันรับผ้า' : '' ?></a>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="panel member-recent-history">
        <div class="panel-header"><div><h2>ประวัติรายการล่าสุด</h2><p>ออร์เดอร์ที่รับคืนแล้วหรือยกเลิก</p></div><a class="text-link" href="<?= e(url('orders')) ?>">ดูประวัติทั้งหมด →</a></div>
        <?php if (!$recentHistory): ?>
            <p class="muted">ยังไม่มีรายการที่เสร็จสิ้น</p>
        <?php else: ?>
        <div class="member-history-list">
            <?php foreach ($recentHistory as $h): ?>
            <a class="member-history-entry" href="<?= e(url('order', ['id' => (int)$h['id']])) ?>">
                <span><strong><?= e($h['order_no']) ?></strong><small><?= e(date('d/m/Y', strtotime($h['received_at']))) ?></small></span>
                <span class="status <?= e(status_class($h['status'])) ?>"><?= e(status_label($h['status'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
