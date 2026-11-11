<?php
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$stmt = db()->prepare('SELECT o.*,u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone,cu.full_name AS creator_name FROM laundry__orders_tbl o JOIN laundry__users_tbl u ON u.id=o.customer_id JOIN laundry__users_tbl cu ON cu.id=o.created_by WHERE o.id=?' . (is_admin() ? '' : ' AND o.customer_id=?'));
$stmt->execute(is_admin() ? [$id] : [$id, current_user()['id']]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); echo '<div class="empty-state"><h2>ไม่พบออร์เดอร์หรือไม่มีสิทธิ์เข้าถึง</h2><a href="' . e(url('orders')) . '" class="btn btn-primary">กลับไปรายการ</a></div>'; return; }
$stmt = db()->prepare('SELECT oi.*,s.name,s.unit FROM laundry__order_items_tbl oi JOIN laundry__services_tbl s ON s.id=oi.service_id WHERE oi.order_id=? ORDER BY oi.id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();
$stmt = db()->prepare('SELECT h.*,u.full_name AS actor FROM laundry__status_history_tbl h JOIN laundry__users_tbl u ON u.id=h.changed_by WHERE h.order_id=? ORDER BY h.id');
$stmt->execute([$id]);
$history = $stmt->fetchAll();
$trackingNotice = $_SESSION['tracking_token_notice'] ?? null;
if (!is_admin() || !is_array($trackingNotice) || (int)($trackingNotice['order_id'] ?? 0) !== (int)$order['id']) {
    $trackingNotice = null;
} else {
    unset($_SESSION['tracking_token_notice']); // The secret will not be retrievable later.
}
$transitions = ['received' => ['washing','cancelled'], 'washing' => ['drying'], 'drying' => ['ready']];
$nextStates = $transitions[$order['status']] ?? [];
?>
<div class="page-heading"><div><p class="eyebrow">ORDER DETAIL</p><h1>ออร์เดอร์ <?= e($order['order_no']) ?></h1><p>สร้างเมื่อ <?= e(date('d/m/Y H:i', strtotime($order['received_at']))) ?></p></div><a class="btn btn-light" href="<?= e(url('orders')) ?>">← กลับรายการ</a></div>
<div class="detail-grid"><section class="panel"><div class="panel-header"><h2>ข้อมูลออร์เดอร์</h2><span class="status <?= status_class($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></div><div class="info-grid"><div><small>ลูกค้า</small><strong><?= e($order['customer_name']) ?></strong></div><div><small>อีเมล</small><strong><?= e($order['customer_email']) ?></strong></div><div><small>เบอร์โทร</small><strong><?= e($order['customer_phone'] ?: '—') ?></strong></div><div><small>กำหนดรับผ้า</small><strong><?= e(date('d/m/Y H:i', strtotime($order['due_at']))) ?></strong></div></div><h3 class="subheading">รายการบริการ</h3><div class="table-wrap"><table><thead><tr><th>บริการ</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th></tr></thead><tbody><?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?></td><td><?= money($i['quantity']) ?> <?= e($i['unit']) ?></td><td>฿<?= money($i['unit_price']) ?></td><td>฿<?= money($i['subtotal']) ?></td></tr><?php endforeach; ?></tbody></table></div><div class="order-totals"><span>ค่าบริการรวม</span><strong>฿<?= money($order['total_amount']) ?></strong></div><?php if ($order['notes']): ?><p class="note-box"><strong>หมายเหตุ:</strong> <?= nl2br(e($order['notes'])) ?></p><?php endif; ?></section>
<?php if (is_admin() && $order['customer_email'] === null): ?>
<section class="panel walkin-qr-panel">
    <div class="panel-header"><h2>QR Code ติดตามออร์เดอร์</h2></div>
    <?php if ($trackingNotice): ?>
        <?php $secretTrackUrl = tracking_link((string)$trackingNotice['token']); ?>
        <p class="muted">ส่ง QR Code หรือลิงก์นี้ให้ลูกค้า เก็บเป็นความลับ หากต้องการสร้างใหม่ลิงก์เดิมจะใช้ไม่ได้</p>
        <div class="walkin-qr-output" data-qr-link="<?= e($secretTrackUrl) ?>">
            <div id="order-qr-code" aria-label="QR Code สำหรับติดตามออร์เดอร์นี้"></div>
            <label>ลิงก์ติดตามเฉพาะออร์เดอร์
                <input id="order-tracking-url" type="text" readonly value="<?= e($secretTrackUrl) ?>">
            </label>
            <button type="button" class="btn btn-light" id="copy-order-tracking-url">คัดลอกลิงก์</button>
        </div>
    <?php else: ?>
        <p class="muted">ลิงก์ติดตามแสดงเพียงครั้งเดียวตอนสร้างออร์เดอร์หรือสร้างใหม่ หากไม่ได้บันทึกไว้ ให้สร้างลิงก์ใหม่</p>
    <?php endif; ?>
    <form method="post" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order-tracking-rotate">
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
        <button class="btn btn-light" type="submit">สร้าง QR / ลิงก์ใหม่</button>
    </form>
</section>
<?php endif; ?>
<section class="panel"><div class="panel-header"><h2>ติดตามสถานะ</h2></div><ol class="timeline"><?php foreach ($history as $h): ?><li><div class="timeline-dot"></div><strong><?= e(status_label($h['new_status'])) ?></strong><small><?= e(date('d/m/Y H:i', strtotime($h['changed_at']))) ?> • <?= e($h['actor']) ?></small><?php if ($h['note']): ?><p><?= e($h['note']) ?></p><?php endif; ?></li><?php endforeach; ?></ol>
<?php if (!is_admin() && $order['status'] === 'ready'): ?>
<div class="pickup-confirm">
    <h3>🔔 ผ้าของคุณพร้อมรับแล้ว!</h3>
    <p>เมื่อมารับผ้าจากร้านและตรวจสอบเรียบร้อยแล้ว กรุณากดยืนยันการรับผ้าด้านล่าง</p>
    <form method="post" action="index.php" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order-confirm-pickup">
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
        <button type="submit" class="btn btn-primary btn-block">✓ ยืนยันว่าได้รับผ้าแล้ว</button>
    </form>
</div>
<?php elseif (is_admin() && $order['status'] === 'ready'): ?>
<p class="pickup-admin-note">🔔 แจ้งลูกค้าแล้วว่าผ้าพร้อมรับ กำลังรอลูกค้ายืนยันการรับผ้า</p>
<?php endif; ?>
<?php if (is_admin() && $nextStates): ?><div class="change-status"><h3>เปลี่ยนสถานะ</h3><form method="post" action="index.php" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="order-status"><input type="hidden" name="id" value="<?= $id ?>"><select name="status" required><?php foreach ($nextStates as $status): ?><option value="<?= e($status) ?>"><?= e(status_label($status)) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-primary btn-block">อัปเดตสถานะ</button></form></div><?php endif; ?></section></div>
