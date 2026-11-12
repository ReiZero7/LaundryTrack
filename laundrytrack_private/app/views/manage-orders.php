<?php
require_admin();
// The staff work queue is distinct from order history: active orders only,
// oldest submitted orders first, regardless of their current processing stage.
$allowedStatuses = ['requested','received','washing','drying','ready','pickup-pending'];
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$search = mb_substr_safe($search, 0, 100);
$filters = ["o.status NOT IN ('picked_up','cancelled')"];
$params = [];
if ($statusFilter === 'pickup-pending') {
    $filters[] = "o.status='ready' AND pr.requested_at IS NOT NULL AND pr.confirmed_at IS NULL";
} elseif ($statusFilter !== '') {
    $filters[] = 'o.status=?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $filters[] = '(o.order_no LIKE ? OR u.full_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$fromSql = ' FROM laundry__orders_tbl o
    JOIN laundry__users_tbl u ON u.id=o.customer_id
    LEFT JOIN laundry__pickup_requests_tbl pr ON pr.order_id=o.id';
$whereSql = ' WHERE ' . implode(' AND ', $filters);
$countStmt = db()->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pageSize = 50;
$pages = max(1, (int)ceil($total / $pageSize));
$requestedPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
$pageNumber = min(max(1, $requestedPage), $pages);
$offset = ($pageNumber - 1) * $pageSize;
$sql = 'SELECT o.id,o.order_no,o.received_at,o.due_at,o.status,
    o.total_amount,u.full_name,pr.requested_at AS pickup_requested_at'
    . $fromSql . $whereSql
    . ' ORDER BY o.received_at ASC,o.id ASC LIMIT ' . $pageSize . ' OFFSET ' . $offset;
$stmt = db()->prepare($sql);
$stmt->execute($params);
$queue = $stmt->fetchAll();
$serviceProgressByOrder = [];
if ($queue) {
    $ids = array_map(static fn(array $item): int => (int)$item['id'], $queue);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $workStmt = db()->prepare("SELECT oi.order_id,COUNT(*) AS total,
        COALESCE(SUM(p.work_status='done'),0) AS completed
        FROM laundry__order_items_tbl oi
        LEFT JOIN laundry__order_item_progress_tbl p ON p.order_item_id=oi.id
        WHERE oi.order_id IN ($marks) GROUP BY oi.order_id");
    $workStmt->execute($ids);
    foreach ($workStmt->fetchAll() as $work) {
        $serviceProgressByOrder[(int)$work['order_id']] = $work;
    }
}
$stats = db()->query("SELECT
    COUNT(*) AS active_total,
    COALESCE(SUM(o.status='requested'),0) AS dropoff_pending,
    COALESCE(SUM(o.status='ready'),0) AS ready_total,
    COALESCE(SUM(o.status='ready' AND pr.requested_at IS NOT NULL AND pr.confirmed_at IS NULL),0) AS handoff_pending
    FROM laundry__orders_tbl o
    LEFT JOIN laundry__pickup_requests_tbl pr ON pr.order_id=o.id
    WHERE o.status NOT IN ('picked_up','cancelled')")->fetch();
$linkQuery = ['q'=>$search, 'status'=>$statusFilter];
?>
<div class="page-heading">
    <div><p class="eyebrow">📋 งานวันนี้</p><h1>งานค้าง</h1>
      <p>งานเก่าขึ้นก่อน</p></div>
    <a class="btn btn-light" href="<?= e(url('orders')) ?>">🧾 ทุกออร์เดอร์</a>
</div>
<div class="queue-stats" aria-label="สรุปงานที่ต้องดำเนินการ">
  <div class="queue-stat"><span>📋 งานค้าง</span><strong><?= (int)$stats['active_total'] ?></strong></div>
  <div class="queue-stat"><span>🧺 รอรับผ้า</span><strong><?= (int)$stats['dropoff_pending'] ?></strong></div>
  <div class="queue-stat"><span>✅ พร้อมรับ</span><strong><?= (int)$stats['ready_total'] ?></strong></div>
  <div class="queue-stat queue-stat-attention"><span>🤝 รอส่งมอบ</span><strong><?= (int)$stats['handoff_pending'] ?></strong></div>
</div>
<section class="panel">
  <form method="get" action="index.php" class="filter-bar">
    <input type="hidden" name="page" value="manage-orders">
    <label class="filter-search">🔍 ค้นหา
      <input type="search" name="q" maxlength="100" value="<?= e($search) ?>" placeholder="หมายเลขออร์เดอร์ / ชื่อ">
    </label>
    <label>สถานะ
      <select name="status">
        <option value="">งานค้างทั้งหมด</option>
        <?php foreach (['requested','received','washing','drying','ready'] as $item): ?>
          <option value="<?= e($item) ?>" <?= $statusFilter === $item ? 'selected' : '' ?>><?= e(status_label($item)) ?></option>
        <?php endforeach; ?>
        <option value="pickup-pending" <?= $statusFilter === 'pickup-pending' ? 'selected' : '' ?>>ลูกค้าขอรับผ้าแล้ว</option>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">ค้นหา</button>
    <?php if ($search !== '' || $statusFilter !== ''): ?>
      <a class="btn btn-light" href="<?= e(url('manage-orders')) ?>">ล้าง</a>
    <?php endif; ?>
  </form>
  <p class="queue-sort-note">⏳ เก่าก่อน · <?= $total ?> งาน · หน้า <?= $pageNumber ?>/<?= $pages ?></p>
  <div class="queue-list">
    <?php foreach ($queue as $order): ?>
      <article class="queue-order">
        <div class="queue-order-main">
          <div class="queue-order-heading">
            <h2><?= e($order['order_no']) ?></h2>
            <span class="status <?= e(status_class($order['status'])) ?>"><?= e(status_label($order['status'])) ?></span>
          </div>
          <p>👤 <?= e($order['full_name']) ?></p>
          <div class="queue-order-meta">
            <span>📅 รับ <?= e(date('d/m/Y H:i', strtotime($order['received_at']))) ?></span>
            <span>⏰ รับคืน <?= e(date('d/m/Y H:i', strtotime($order['due_at']))) ?></span>
            <span>💰 ฿<?= money($order['total_amount']) ?></span>
            <?php $work = $serviceProgressByOrder[(int)$order['id']] ?? ['total'=>0,'completed'=>0]; ?>
            <span class="queue-service-count">✅ <?= (int)$work['completed'] ?>/<?= (int)$work['total'] ?> บริการ</span>
          </div>
          <?php if ($order['status'] === 'ready' && $order['pickup_requested_at']): ?>
            <p class="queue-pickup-alert">🔔 ลูกค้าขอรับแล้ว · ตรวจผ้าก่อนส่งมอบ</p>
          <?php elseif ($order['status'] === 'requested'): ?>
            <p class="queue-order-note">🧺 รอลูกค้านำผ้ามาส่ง</p>
          <?php endif; ?>
        </div>
        <a class="btn btn-primary" href="<?= e(url('order', ['id'=>(int)$order['id']])) ?>">จัดการ →</a>
      </article>
    <?php endforeach; ?>
    <?php if (!$queue): ?>
      <div class="empty-state"><h2>ไม่พบงานค้างตามเงื่อนไข</h2><p>ลองเปลี่ยนตัวกรอง</p></div>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="queue-pages" aria-label="หน้าของรายการงานค้าง">
      <?php if ($pageNumber > 1): ?>
        <a class="btn btn-light" href="<?= e(url('manage-orders', array_merge($linkQuery,['p'=>$pageNumber-1]))) ?>">← ก่อนหน้า</a>
      <?php endif; ?>
      <span>หน้า <?= $pageNumber ?> จาก <?= $pages ?></span>
      <?php if ($pageNumber < $pages): ?>
        <a class="btn btn-light" href="<?= e(url('manage-orders', array_merge($linkQuery,['p'=>$pageNumber+1]))) ?>">ถัดไป →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
