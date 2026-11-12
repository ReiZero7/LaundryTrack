<?php
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$stmt = db()->prepare('SELECT o.*,pr.requested_at AS pickup_requested_at,pr.confirmed_at AS pickup_confirmed_at,u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone,cu.full_name AS creator_name,cu.role AS creator_role FROM laundry__orders_tbl o JOIN laundry__users_tbl u ON u.id=o.customer_id JOIN laundry__users_tbl cu ON cu.id=o.created_by LEFT JOIN laundry__pickup_requests_tbl pr ON pr.order_id=o.id WHERE o.id=?' . (is_admin() ? '' : ' AND o.customer_id=?'));
$stmt->execute(is_admin() ? [$id] : [$id, current_user()['id']]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); echo '<div class="empty-state"><h2>ไม่พบออร์เดอร์หรือไม่มีสิทธิ์เข้าถึง</h2><a href="' . e(url('orders')) . '" class="btn btn-primary">กลับไปรายการ</a></div>'; return; }
$items = order_service_items($id);
$completedServiceCount = count(array_filter($items, static fn(array $item): bool => $item['work_status'] === 'done'));
$allServicesComplete = count($items) > 0 && $completedServiceCount === count($items);
$canManageServiceWork = is_admin() && in_array($order['status'], ['received','washing','drying'], true);
$stmt = db()->prepare('SELECT h.*,u.full_name AS actor FROM laundry__status_history_tbl h JOIN laundry__users_tbl u ON u.id=h.changed_by WHERE h.order_id=? ORDER BY h.id');
$stmt->execute([$id]);
$history = $stmt->fetchAll();
$trackingNotice = $_SESSION['tracking_token_notice'] ?? null;
if (!is_admin() || !is_array($trackingNotice) || (int)($trackingNotice['order_id'] ?? 0) !== (int)$order['id']) {
    $trackingNotice = null;
} else {
    unset($_SESSION['tracking_token_notice']); // The secret will not be retrievable later.
}
$selfServiceWaitingDropoff = $order['status'] === 'requested';
$transitions = ['requested' => ['received','cancelled'], 'received' => ['washing','cancelled'], 'washing' => ['drying'], 'drying' => ['ready']];
$nextStates = $transitions[$order['status']] ?? [];
// Completion cannot be offered until every selected service (e.g. ironing)
// is marked done. Server-side validation independently enforces this rule.
if ($order['status'] === 'drying' && !$allServicesComplete) $nextStates = [];
?>
<div class="page-heading"><div><p class="eyebrow">ORDER DETAIL</p><h1>ออร์เดอร์ <?= e($order['order_no']) ?></h1><p>สร้างเมื่อ <?= e(date('d/m/Y H:i', strtotime($order['received_at']))) ?></p></div><div class="order-heading-actions"><?php if (is_admin() && $order['customer_email'] === null): ?><a class="btn btn-light" href="#order-qr-tools">QR ของออร์เดอร์นี้</a><?php endif; ?><a class="btn btn-light" href="<?= e(url(is_admin() ? 'manage-orders' : 'orders')) ?>">← <?= is_admin() ? 'กลับหน้าจัดการออร์เดอร์' : 'กลับรายการ' ?></a></div></div>
<?php if ($selfServiceWaitingDropoff): ?>
<section class="panel order-self-identity">
    <strong>🧺 ออร์เดอร์จากลูกค้า: ยังรอตรวจรับผ้าจริง</strong>
    <p>ลูกค้าส่งคำสั่งบริการผ่านเว็บไซต์แล้ว แต่ร้านต้องตรวจสอบและรับผ้าจริงก่อนเปลี่ยนเป็นรับผ้าแล้ว</p>
</section>
<?php endif; ?>
<div class="detail-grid"><section class="panel"><div class="panel-header"><h2>ข้อมูลออร์เดอร์</h2><span class="status <?= status_class($order['status']) ?>"><?= e(order_status_label($order['status'], $order['creator_role'])) ?></span></div><div class="info-grid"><div><small>ลูกค้า</small><strong><?= e($order['customer_name']) ?></strong></div><div><small>อีเมล</small><strong><?= e($order['customer_email'] ?: '— (Guest)') ?></strong></div><div><small>เบอร์โทร</small><strong><?= e($order['customer_phone'] ?: '—') ?></strong></div><div><small>กำหนดรับผ้า</small><strong><?= e(date('d/m/Y H:i', strtotime($order['due_at']))) ?></strong></div></div><h3 class="subheading">รายการบริการ</h3><div class="table-wrap" role="region" aria-label="ตารางรายการบริการของออร์เดอร์" tabindex="0"><table><thead><tr><th>บริการ</th><th>จำนวน</th><th>ราคา/หน่วย</th><th>รวม</th><th>สถานะบริการ</th></tr></thead><tbody><?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?></td><td><?= money($i['quantity']) ?> <?= e($i['unit']) ?></td><td>฿<?= money($i['unit_price']) ?></td><td>฿<?= money($i['subtotal']) ?></td><td><span class="service-work-badge service-work-<?= e($i['work_status']) ?>"><?= e(service_work_label($i['work_status'], $i['name'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><p class="mobile-table-hint">ตารางรายการบริการสามารถเลื่อนซ้าย–ขวาเพื่อดูข้อมูลทั้งหมดได้</p>
<div class="service-work-summary" role="status">ดำเนินการบริการเสร็จ <strong><?= $completedServiceCount ?>/<?= count($items) ?></strong> รายการ
<?php if (!$allServicesComplete && $order['status'] === 'drying'): ?>
<span class="service-work-warning">ต้องทำทุกรายการให้ครบก่อนเปลี่ยนเป็นพร้อมรับผ้า</span>
<?php endif; ?>
</div>
<?php if ($canManageServiceWork): ?>
<section class="service-work-manager" aria-labelledby="service-work-title">
  <h3 id="service-work-title">ความคืบหน้าแต่ละบริการ</h3>
  <p class="muted">แต่ละบริการมีสถานะของตัวเอง เช่น ซักอบเสร็จแล้ว แต่รีดผ้ายังรอดำเนินการ</p>
  <?php foreach ($items as $item): ?>
  <form method="post" action="index.php" class="service-work-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="order-item-progress">
    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
    <label><strong><?= e($item['name']) ?></strong>
      <select name="work_status" aria-label="สถานะบริการ <?= e($item['name']) ?>">
        <option value="pending" <?= $item['work_status'] === 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
        <option value="working" <?= $item['work_status'] === 'working' ? 'selected' : '' ?>>กำลังดำเนินการ</option>
        <option value="done" <?= $item['work_status'] === 'done' ? 'selected' : '' ?>>เสร็จแล้ว</option>
      </select>
    </label>
    <button class="btn btn-light" type="submit">บันทึกสถานะ</button>
  </form>
  <?php endforeach; ?>
</section>
<?php endif; ?><div class="order-totals"><span>ค่าบริการรวม</span><strong>฿<?= money($order['total_amount']) ?></strong></div><?php if ($order['notes']): ?><p class="note-box"><strong>หมายเหตุ:</strong> <?= nl2br(e($order['notes'])) ?></p><?php endif; ?></section>
<section class="panel"><div class="panel-header"><h2>ติดตามสถานะ</h2></div><ol class="timeline"><?php foreach ($history as $h): ?><li><div class="timeline-dot"></div><strong><?= e(order_status_label($h['new_status'], $order['creator_role'])) ?></strong><small><?= e(date('d/m/Y H:i', strtotime($h['changed_at']))) ?> • <?= e($h['actor']) ?></small><?php if ($h['note']): ?><p><?= e($h['note']) ?></p><?php endif; ?></li><?php endforeach; ?></ol>
<?php if (!is_admin() && $order['status'] === 'ready'): ?>
<div class="pickup-confirm">
    <h3>🔔 ผ้าของคุณพร้อมรับแล้ว!</h3>
    <?php if ($order['pickup_requested_at']): ?>
        <p>✓ แจ้งความประสงค์รับผ้าแล้ว กรุณารอให้พนักงานตรวจสอบและยืนยันส่งมอบที่ร้าน</p>
    <?php else: ?>
        <p>เมื่อมาถึงร้าน กรุณากดแจ้งความประสงค์รับผ้า จากนั้นพนักงานจะตรวจสอบและยืนยันส่งมอบจริง</p>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="order-confirm-pickup">
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <button type="submit" class="btn btn-primary btn-block">แจ้งพนักงานว่าต้องการรับผ้า</button>
        </form>
    <?php endif; ?>
</div>
<?php elseif (is_admin() && $order['status'] === 'ready'): ?>
    <?php if ($order['pickup_requested_at']): ?>
    <div class="pickup-confirm">
        <h3>🧺 ลูกค้าแจ้งความประสงค์รับผ้าแล้ว</h3>
        <p>ตรวจสอบออร์เดอร์และส่งมอบผ้าจริงก่อนยืนยันปิดออร์เดอร์</p>
        <form method="post" action="index.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="staff-confirm-pickup">
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <label class="dropoff-confirm-check"><input type="checkbox" name="handoff_confirmed" value="yes" required> ยืนยันว่าได้ตรวจสอบและส่งมอบผ้าให้ลูกค้าจริงแล้ว</label>
            <button type="submit" class="btn btn-primary btn-block">พนักงานยืนยันส่งมอบและปิดออร์เดอร์</button>
        </form>
    </div>
    <?php else: ?>
    <p class="pickup-admin-note">🔔 ผ้าพร้อมรับแล้ว กำลังรอลูกค้าส่งคำขอรับผ้า</p>
    <?php endif; ?>
<?php elseif ($order['status'] === 'picked_up'): ?>
<p class="pickup-admin-note">✓ ออร์เดอร์นี้ปิดการรับผ้าแล้ว โปรดตรวจสอบผู้ยืนยันส่งมอบในประวัติสถานะ</p>
<?php endif; ?>
<?php if (is_admin() && $nextStates): ?><div class="change-status"><h3>เปลี่ยนสถานะ</h3><form method="post" action="index.php" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="order-status"><input type="hidden" name="id" value="<?= $id ?>"><select name="status" required><?php foreach ($nextStates as $status): ?><option value="<?= e($status) ?>"><?= e(status_label($status)) ?></option><?php endforeach; ?></select><?php if ($selfServiceWaitingDropoff): ?>
    <label class="dropoff-confirm-check"><input type="checkbox" name="dropoff_confirmed" value="yes"> ยืนยันว่าร้านได้รับผ้าจริงแล้ว (ต้องตรวจรับก่อนเปลี่ยนเป็นรับผ้าแล้ว)</label>
<?php endif; ?>
<button type="submit" class="btn btn-primary btn-block">อัปเดตสถานะ</button></form></div><?php endif; ?></section></div>

<!-- Guest QR controls are outside the two-column detail grid so both
     guest and member order details retain identical information/timeline layout. -->
<?php if (is_admin() && $order['customer_email'] === null): ?>
<div class="order-qr-below">
<section id="order-qr-tools" class="panel walkin-qr-panel">
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
</div>
<?php endif; ?>
