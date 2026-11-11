<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function process_action(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
    require_csrf();
    $action = post_string('action');

    try {
        switch ($action) {
            case 'register':
                if (current_user()) go('dashboard');
                $name = post_string('full_name');
                $email = strtolower(post_string('email'));
                $phone = post_string('phone');
                $password = post_string('password');$password = is_string($_POST['password'] ?? null)
                ? $_POST['password']
                : '';
                if (text_length($name) < 2 || text_length($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || text_length($phone) > 20 || strlen($password) < 8) {
                    flash('กรอกชื่อ อีเมล และรหัสผ่านอย่างน้อย 8 ตัวอักษรให้ถูกต้อง', 'error'); go('register');
                }
                $stmt = db()->prepare('INSERT INTO laundry__users_tbl(full_name, email, phone, password_hash, role) VALUES(?,?,?,?,\'customer\')');
                try {
                    $stmt->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') { flash('อีเมลนี้ถูกใช้งานแล้ว', 'error'); go('register'); }
                    throw $e;
                }
                flash('สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ'); go('login');
            case 'login':
                if (current_user()) go('dashboard');
                $email = strtolower(post_string('email'));
                $password = is_string($_POST['password'] ?? null)
    			? $_POST['password']
    			: '';
                $stmt = db()->prepare('SELECT id, full_name, email, role, password_hash FROM laundry__users_tbl WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    flash('อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'error'); go('login');
                }
                session_regenerate_id(true);
                unset($user['password_hash']);
                $_SESSION['user'] = $user;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                flash('เข้าสู่ระบบสำเร็จ'); go('dashboard');
            case 'logout':
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
                }
                session_destroy();
                go('login');
            case 'service-save':
                require_admin();
                $id = post_int('id');
                $name = post_string('name');
                $unit = post_string('unit');
                $priceRaw = post_string('price');
                if ($name === '' || text_length($name) > 100 || $unit === '' || text_length($unit) > 20 || !is_numeric($priceRaw) || (float)$priceRaw < 0 || (float)$priceRaw > 99999999) {
                    flash('ข้อมูลบริการไม่ถูกต้อง', 'error'); go('services');
                }
                $price = number_format((float)$priceRaw, 2, '.', '');
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE laundry__services_tbl SET name=?,unit=?,price=? WHERE id=?');
                    $stmt->execute([$name, $unit, $price, $id]);
                } else {
                    $stmt = db()->prepare('INSERT INTO laundry__services_tbl(name,unit,price) VALUES(?,?,?)');
                    $stmt->execute([$name, $unit, $price]);
                }
                flash('บันทึกบริการเรียบร้อย'); go('services');
            case 'service-archive':
                require_admin();
                $id = post_int('id');
                if ($id > 0) db()->prepare('UPDATE laundry__services_tbl SET is_active=0 WHERE id=?')->execute([$id]);
                flash('ปิดบริการแล้ว (ประวัติออร์เดอร์เดิมยังอยู่)'); go('services');
            case 'service-restore':
                require_admin();
                $id = post_int('id');
                if ($id > 0) db()->prepare('UPDATE laundry__services_tbl SET is_active=1 WHERE id=?')->execute([$id]);
                flash('เปิดใช้งานบริการแล้ว'); go('services');
            case 'customer-save':
                require_admin();
                $id = post_int('id');
                $name = post_string('full_name');
                $phone = post_string('phone');
                $email = strtolower(post_string('email'));
                $password = post_string('password');
                if (text_length($name) < 2 || text_length($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || text_length($phone) > 20 || ($id === 0 && strlen($password) < 8) || ($password !== '' && strlen($password) < 8)) {
                    flash('ข้อมูลลูกค้าไม่ถูกต้อง (รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร)', 'error'); go('customers');
                }
                try {
                    if ($id > 0) {
                        $stmt = db()->prepare('UPDATE laundry__users_tbl SET full_name=?, phone=?, email=? WHERE id=? AND role=\'customer\'');
                        $stmt->execute([$name, $phone ?: null, $email, $id]);
                        if ($password !== '') db()->prepare('UPDATE laundry__users_tbl SET password_hash=? WHERE id=? AND role=\'customer\'')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                    } else {
                        db()->prepare('INSERT INTO laundry__users_tbl(full_name,phone,email,password_hash,role) VALUES(?,?,?,?,\'customer\')')->execute([$name, $phone ?: null, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') { flash('อีเมลนี้ถูกใช้งานแล้ว', 'error'); go('customers'); }
                    throw $e;
                }
                flash('บันทึกข้อมูลลูกค้าเรียบร้อย'); go('customers');

            case 'order-create':
                require_admin();
                $customerId = post_int('customer_id');
                $due = post_string('due_at');
                $notes = post_string('notes');
                $serviceIds = $_POST['service_id'] ?? [];
                $qtys = $_POST['quantity'] ?? [];
                if ($customerId < 1 || !is_array($serviceIds) || !is_array($qtys) || count($serviceIds) < 1 || count($serviceIds) > 15 || text_length($notes) > 2000) {
                    flash('กรุณาเลือกลูกค้าและบริการ', 'error'); go('new-order');
                }
                $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $due);
                if (!$dueDate || $dueDate->format('Y-m-d\TH:i') !== $due || $dueDate < new DateTimeImmutable('now')) {
                    flash('กำหนดรับผ้าไม่ถูกต้อง', 'error'); go('new-order');
                }
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT id FROM laundry__users_tbl WHERE id=? AND role=\'customer\'');
                    $stmt->execute([$customerId]);
                    if (!$stmt->fetch()) throw new DomainException('Customer not found');
                    $items = [];
                    $total = 0.0;
                    $serviceStmt = $pdo->prepare('SELECT id,price FROM laundry__services_tbl WHERE id=? AND is_active=1');
                    foreach ($serviceIds as $i => $rawId) {
                        $id = filter_var($rawId, FILTER_VALIDATE_INT);
                        $q = $qtys[$i] ?? null;
                        if (!$id || !is_scalar($q) || !is_numeric((string)$q) || (float)$q <= 0 || (float)$q > 10000) throw new DomainException('Invalid order item');
                        $quantity = round((float)$q, 2);
                        if ($quantity <= 0) throw new DomainException('Invalid quantity');
                        $serviceStmt->execute([$id]);
                        $service = $serviceStmt->fetch();
                        if (!$service) throw new DomainException('Service unavailable');
                        $price = (float) $service['price'];
                        $subtotal = round($price * $quantity, 2);
                        $items[] = [$id, number_format($quantity, 2, '.', ''), $service['price'], number_format($subtotal, 2, '.', '')];
                        $total += $subtotal;
                    }
                    $total = round($total, 2);
                    $orderNo = 'LT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
                    $stmt = $pdo->prepare('INSERT INTO laundry__orders_tbl(order_no,customer_id,created_by,due_at,total_amount,notes) VALUES(?,?,?,?,?,?)');
                    $stmt->execute([$orderNo, $customerId, current_user()['id'], $dueDate->format('Y-m-d H:i:s'), number_format($total, 2, '.', ''), $notes ?: null]);
                    $orderId = (int) $pdo->lastInsertId();
                    $insert = $pdo->prepare('INSERT INTO laundry__order_items_tbl(order_id,service_id,quantity,unit_price,subtotal) VALUES(?,?,?,?,?)');
                    foreach ($items as $item) $insert->execute(array_merge([$orderId], $item));
                    $pdo->prepare('INSERT INTO laundry__status_history_tbl(order_id,changed_by,old_status,new_status,note) VALUES(?,?,NULL,\'received\',?)')->execute([$orderId, current_user()['id'], 'บันทึกรับผ้า']);
                    $pdo->commit();
                    flash('สร้างออร์เดอร์ ' . $orderNo . ' เรียบร้อย'); go('order', ['id' => $orderId]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof DomainException) { flash('ข้อมูลออร์เดอร์ไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง', 'error'); go('new-order'); }
                    throw $e;
                }
            case 'order-status':
                require_admin();
                $orderId = post_int('id');
                $next = post_string('status');
                $transitions = [
                    'received' => ['washing', 'cancelled'],
                    'washing' => ['drying'],
                    'drying' => ['ready'],
                    'ready' => ['picked_up'],
                    'picked_up' => [],
                    'cancelled' => [],
                ];
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT status FROM laundry__orders_tbl WHERE id=? FOR UPDATE');
                    $stmt->execute([$orderId]);
                    $current = $stmt->fetchColumn();
                    if (!$current || !in_array($next, $transitions[(string)$current] ?? [], true)) throw new DomainException('Invalid status transition');
                    $pdo->prepare('UPDATE laundry__orders_tbl SET status=? WHERE id=?')->execute([$next, $orderId]);
                    $pdo->prepare('INSERT INTO laundry__status_history_tbl(order_id,changed_by,old_status,new_status) VALUES(?,?,?,?)')->execute([$orderId, current_user()['id'], $current, $next]);
                    $pdo->commit();
                    flash('อัปเดตสถานะเรียบร้อย'); go('order', ['id' => $orderId]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof DomainException) { flash('ไม่สามารถเปลี่ยนสถานะตามขั้นตอนที่เลือกได้', 'error'); go('orders'); }
                    throw $e;
                }
            default:
                http_response_code(400);
                exit('Unknown action');
        }
    } catch (Throwable $e) {
        handle_error($e, in_array($action, ['register','login'], true) ? 'login' : 'dashboard');
    }
}
