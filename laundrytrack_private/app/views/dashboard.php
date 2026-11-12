<?php
$userId = (int) current_user()['id'];
$admin = is_admin();
$filter = $admin ? '' : ' WHERE customer_id = ?';
$stmt = db()->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(status NOT IN (\'cancelled\',\'picked_up\')),0) AS processing, COALESCE(SUM(status = \'ready\'),0) AS ready_count, COALESCE(SUM(IF(status = \'cancelled\', 0, total_amount)),0) AS amount FROM laundry__orders_tbl' . $filter);
$stmt->execute($admin ? [] : [$userId]);
$stats = $stmt->fetch();
$stmt = db()->prepare('SELECT o.id,o.order_no,o.received_at,o.status,o.total_amount,u.full_name,cu.role AS creator_role FROM laundry__orders_tbl o JOIN laundry__users_tbl u ON u.id=o.customer_id JOIN laundry__users_tbl cu ON cu.id=o.created_by' . ($admin ? '' : ' WHERE o.customer_id=?') . ' ORDER BY o.id DESC LIMIT 6');
$stmt->execute($admin ? [] : [$userId]);
$recent = $stmt->fetchAll();
$readyNotifications = [];
if (!$admin && (int)$stats['ready_count'] > 0) {
    $stmt = db()->prepare("SELECT id, order_no FROM laundry__orders_tbl WHERE customer_id=? AND status='ready' ORDER BY id DESC LIMIT 10");
    $stmt->execute([$userId]);
    $readyNotifications = $stmt->fetchAll();
}
?>
<div class="page-heading"><div><p class="eyebrow">🏠 ภาพรวม</p><h1>ภาพรวมร้าน</h1><p>สวัสดี <?= e(current_user()['full_name']) ?> 👋</p></div><?php if ($admin): ?><div class="dashboard-admin-actions"><a class="btn btn-primary" href="<?= e(url('manage-orders')) ?>">📋 ดูงานค้าง</a><a class="btn btn-light" href="<?= e(url('new-order')) ?>">➕ สร้างออร์เดอร์</a></div><?php endif; ?></div>
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
    <div class="metric-card"><p>🧾 ออร์เดอร์</p><strong><?= (int)$stats['total'] ?></strong><span>ทั้งหมด</span><div class="metric-icon">▤</div></div>
    <div class="metric-card"><p>🫧 กำลังทำ</p><strong><?= (int)$stats['processing'] ?></strong><span>งานยังไม่ปิด</span><div class="metric-icon">↺</div></div>
    <div class="metric-card"><p>✅ พร้อมรับ</p><strong id="pickup-live-metric"><?= (int)$stats['ready_count'] ?></strong><span>รอรับผ้า</span><div class="metric-icon">✓</div></div>
    <div class="metric-card"><p>💰 รวมค่าบริการ</p><strong>฿<?= money($stats['amount']) ?></strong><span>ไม่ใช่ยอดรับเงินจริง</span><div class="metric-icon">฿</div></div>
</div>
<div class="panel"><div class="panel-header"><div><h2>🧺 ออร์เดอร์ล่าสุด</h2></div><a class="text-link" href="<?= e(url($admin ? 'manage-orders' : 'orders')) ?>"><?= $admin ? 'ดูงานค้าง →' : 'ดูทั้งหมด →' ?></a></div>
    <div class="table-wrap"><table><thead><tr><th>ออร์เดอร์</th><?php if ($admin): ?><th>ลูกค้า</th><?php endif; ?><th>วันที่</th><th>ยอด</th><th>สถานะ</th><th></th></tr></thead><tbody>
        <?php foreach ($recent as $r): ?><tr><td><strong><?= e($r['order_no']) ?></strong></td><?php if ($admin): ?><td><?= e($r['full_name']) ?></td><?php endif; ?><td><?= e(date('d/m/Y', strtotime($r['received_at']))) ?></td><td>฿<?= money($r['total_amount']) ?></td><td><span class="status <?= status_class($r['status']) ?>"><?= e(order_status_label($r['status'], $r['creator_role'])) ?></span></td><td><a class="text-link" href="<?= e(url('order', ['id' => (int)$r['id']])) ?>">ดู →</a></td></tr><?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="6" class="empty-cell">ยังไม่มีรายการซักรีด</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
