<?php
$admin = is_admin();
$statusFilter = $_GET['status'] ?? '';
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$validStatuses = ['received','washing','drying','ready','picked_up','cancelled'];
$where = [];
$params = [];
if (!$admin) { $where[] = 'o.customer_id=?'; $params[] = current_user()['id']; }
if (in_array($statusFilter, $validStatuses, true)) { $where[] = 'o.status=?'; $params[] = $statusFilter; }
if ($search !== '') { $where[] = '(o.order_no LIKE ? OR u.full_name LIKE ?)'; $like = '%' . $search . '%'; $params[] = $like; $params[] = $like; }
$sql = 'SELECT o.id,o.order_no,o.received_at,o.due_at,o.status,o.total_amount,u.full_name,cu.role AS creator_role FROM laundry__orders_tbl o JOIN laundry__users_tbl u ON u.id=o.customer_id JOIN laundry__users_tbl cu ON cu.id=o.created_by' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<div class="page-heading"><div><p class="eyebrow">ORDER HISTORY</p><h1><?= $admin ? 'รายการซักรีดทั้งหมด' : 'รายการซักรีดของฉัน' ?></h1><p>ตรวจสอบประวัติและสถานะการซักรีด</p></div><?php if ($admin): ?><a class="btn btn-primary" href="<?= e(url('new-order')) ?>">+ สร้างออร์เดอร์</a><?php endif; ?></div>
<section class="panel"><form method="get" action="index.php" class="filter-bar"><input type="hidden" name="page" value="orders"><label class="filter-search">ค้นหาหมายเลขออร์เดอร์<?= $admin ? ' / ชื่อลูกค้า' : '' ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="LT-... / ชื่อ"></label><label>สถานะ<select name="status"><option value="">ทั้งหมด</option><?php foreach ($validStatuses as $s): ?><option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select></label><button class="btn btn-light" type="submit">ค้นหา</button></form><div class="table-wrap"><table><thead><tr><th>หมายเลข</th><?php if ($admin): ?><th>ลูกค้า</th><?php endif; ?><th>รับผ้า</th><th>กำหนดรับ</th><th>ยอดรวม</th><th>สถานะ</th><th></th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><strong><?= e($o['order_no']) ?></strong></td><?php if ($admin): ?><td><?= e($o['full_name']) ?></td><?php endif; ?><td><?= e(date('d/m/Y', strtotime($o['received_at']))) ?></td><td><?= e(date('d/m/Y', strtotime($o['due_at']))) ?></td><td>฿<?= money($o['total_amount']) ?></td><td><span class="status <?= status_class($o['status']) ?>"><?= e(order_status_label($o['status'], $o['creator_role'])) ?></span></td><td><a class="text-link" href="<?= e(url('order', ['id' => (int)$o['id']])) ?>">ดูรายละเอียด</a></td></tr><?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="7" class="empty-cell">ไม่พบรายการซักรีด</td></tr><?php endif; ?></tbody></table></div><p class="footnote">แสดงสูงสุด 200 รายการล่าสุด</p></section>
