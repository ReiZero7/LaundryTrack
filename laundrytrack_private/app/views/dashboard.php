<?php
$userId = (int) current_user()['id'];
$admin = is_admin();
$filter = $admin ? '' : ' WHERE customer_id = ?';
$stmt = db()->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(status NOT IN (\'cancelled\',\'picked_up\')),0) AS processing, COALESCE(SUM(status = \'ready\'),0) AS ready_count, COALESCE(SUM(IF(status = \'cancelled\', 0, total_amount)),0) AS amount FROM laundry__orders_tbl' . $filter);
$stmt->execute($admin ? [] : [$userId]);
$stats = $stmt->fetch();
$stmt = db()->prepare('SELECT o.id,o.order_no,o.received_at,o.status,o.total_amount,u.full_name FROM laundry__orders_tbl o JOIN laundry__users_tbl u ON u.id=o.customer_id' . ($admin ? '' : ' WHERE o.customer_id=?') . ' ORDER BY o.id DESC LIMIT 6');
$stmt->execute($admin ? [] : [$userId]);
$recent = $stmt->fetchAll();
$readyNotifications = [];
if (!$admin && (int)$stats['ready_count'] > 0) {
    $stmt = db()->prepare("SELECT id, order_no FROM laundry__orders_tbl WHERE customer_id=? AND status='ready' ORDER BY id DESC LIMIT 10");
    $stmt->execute([$userId]);
    $readyNotifications = $stmt->fetchAll();
}
?>
<div class="page-heading"><div><p class="eyebrow">OVERVIEW</p><h1>สวัสดี, <?= e(current_user()['full_name']) ?> 👋</h1><p>ภาพรวมรายการซักรีดและความคืบหน้าล่าสุด</p></div><?php if ($admin): ?><a class="btn btn-primary" href="<?= e(url('new-order')) ?>">+ สร้างออร์เดอร์</a><?php endif; ?></div>
<?php if ($admin): ?>
<section class="panel self-service-shop-panel">
    <div class="panel-header"><div><h2>QR Code ให้ลูกค้าสร้างออร์เดอร์เอง</h2>
        <p>พิมพ์ QR นี้ติดหน้าร้าน ลูกค้าสแกนแล้วเลือกสมาชิกหรือสั่งบริการได้ทันที</p></div>
    </div>
    <?php $shopOrderUrl = shop_order_link(); ?>
    <div class="walkin-qr-output" data-qr-link="<?= e($shopOrderUrl) ?>">
        <div id="order-qr-code" aria-label="QR Code สำหรับเริ่มสร้างออร์เดอร์ด้วยตนเอง"></div>
        <label>ลิงก์สร้างออร์เดอร์ด้วยตนเอง
            <input id="order-tracking-url" type="text" readonly value="<?= e($shopOrderUrl) ?>">
        </label>
        <button class="btn btn-light" type="button" id="copy-order-tracking-url">คัดลอกลิงก์ร้าน</button>
    </div>
    <p class="muted">QR ประจำร้านนี้ใช้ร่วมกันได้ทุกคน และไม่ใช่ QR ลับสำหรับดูออร์เดอร์ใดออร์เดอร์หนึ่ง</p>
</section>
<?php endif; ?>
<?php if (!$admin): ?>
<section id="pickup-live-notice" class="pickup-notice" role="status" <?= $readyNotifications ? '' : 'hidden' ?>>
    <div class="pickup-notice-title">
        <span class="pickup-notice-icon" aria-hidden="true">🔔</span>
        <div>
            <h2>ผ้าของคุณพร้อมรับแล้ว!</h2>
            <p>มี <span id="pickup-live-count"><?= (int)$stats['ready_count'] ?></span> ออร์เดอร์พร้อมรับผ้า กรุณายืนยันหลังจากได้รับผ้าจริง</p>
        </div>
    </div>
    <div class="pickup-notice-list" id="pickup-live-list">
        <?php foreach ($readyNotifications as $notice): ?>
        <div class="pickup-notice-item" data-order-id="<?= (int)$notice['id'] ?>">
            <strong><?= e($notice['order_no']) ?></strong>
            <a class="btn btn-primary" href="<?= e(url('order', ['id' => (int)$notice['id']])) ?>">ดูรายละเอียดและยืนยันรับผ้า</a>
        </div>
        <?php endforeach; ?>
    </div>
    <a id="pickup-live-more" class="text-link" href="<?= e(url('orders', ['status' => 'ready'])) ?>" <?= (int)$stats['ready_count'] > count($readyNotifications) ? '' : 'hidden' ?>>ดูออร์เดอร์ที่พร้อมรับทั้งหมด</a>
</section>
<?php endif; ?>
<div class="metric-grid">
    <div class="metric-card"><p>ออร์เดอร์ทั้งหมด</p><strong><?= (int)$stats['total'] ?></strong><span>รายการในระบบ</span><div class="metric-icon">▤</div></div>
    <div class="metric-card"><p>กำลังดำเนินการ</p><strong><?= (int)$stats['processing'] ?></strong><span>รับแล้ว / กำลังซัก / พร้อมรับ</span><div class="metric-icon">↺</div></div>
    <div class="metric-card"><p>พร้อมรับผ้า</p><strong id="pickup-live-metric"><?= (int)$stats['ready_count'] ?></strong><span>รอลูกค้ามารับ</span><div class="metric-icon">✓</div></div>
    <div class="metric-card"><p>ยอดค่าบริการรวม</p><strong>฿<?= money($stats['amount']) ?></strong><span>ไม่รวมออร์เดอร์ยกเลิก (ไม่ใช่ยอดเงินรับจริง)</span><div class="metric-icon">฿</div></div>
</div>
<div class="panel"><div class="panel-header"><div><h2>รายการซักรีดล่าสุด</h2><p>ติดตามความคืบหน้าทุกออร์เดอร์ได้ทันที</p></div><a class="text-link" href="<?= e(url('orders')) ?>">ดูทั้งหมด →</a></div>
    <div class="table-wrap"><table><thead><tr><th>เลขออร์เดอร์</th><?php if ($admin): ?><th>ลูกค้า</th><?php endif; ?><th>วันที่รับผ้า</th><th>ยอดรวม</th><th>สถานะ</th><th></th></tr></thead><tbody>
        <?php foreach ($recent as $r): ?><tr><td><strong><?= e($r['order_no']) ?></strong></td><?php if ($admin): ?><td><?= e($r['full_name']) ?></td><?php endif; ?><td><?= e(date('d/m/Y', strtotime($r['received_at']))) ?></td><td>฿<?= money($r['total_amount']) ?></td><td><span class="status <?= status_class($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td><td><a class="text-link" href="<?= e(url('order', ['id' => (int)$r['id']])) ?>">รายละเอียด</a></td></tr><?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="6" class="empty-cell">ยังไม่มีรายการซักรีด</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
