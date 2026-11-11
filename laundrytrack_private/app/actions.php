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
                // Whitelist internal destination; never accept a free-form redirect URL.
                $nextPage = post_string('next') === 'self-order' ? 'self-order' : 'dashboard';
                $email = strtolower(post_string('email'));
                $password = is_string($_POST['password'] ?? null)
    			? $_POST['password']
    			: '';
                $stmt = db()->prepare('SELECT id, full_name, email, role, password_hash FROM laundry__users_tbl WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    flash('อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'error'); go('login', $nextPage === 'self-order' ? ['next' => 'self-order'] : []);
                }
                session_regenerate_id(true);
                unset($user['password_hash']);
                $_SESSION['user'] = $user;
                unset($_SESSION['guest_track'], $_SESSION['guest_pickup_claim']);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                flash('เข้าสู่ระบบสำเร็จ');
                go(($user['role'] === 'customer') ? $nextPage : 'dashboard');
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
                $existingCustomer = null;
                if ($id > 0) {
                    $lookup = db()->prepare("SELECT email FROM laundry__users_tbl WHERE id=? AND role='customer'");
                    $lookup->execute([$id]);
                    $existingCustomer = $lookup->fetch();
                    if (!$existingCustomer) { flash('ไม่พบลูกค้าที่ต้องการแก้ไข', 'error'); go('customers'); }
                }
                $isWalkin = $existingCustomer && $existingCustomer['email'] === null;
                if (text_length($name) < 2 || text_length($name) > 100 || text_length($phone) > 20
                    || (!$isWalkin && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150))
                    || ($isWalkin && ($email !== '' || $password !== ''))
                    || ($id === 0 && strlen($password) < 8)
                    || ($password !== '' && strlen($password) < 8)) {
                    flash('ข้อมูลลูกค้าไม่ถูกต้อง (ลูกค้าหน้าร้านไม่ต้องกรอกอีเมล/รหัสผ่าน)', 'error');
                    go('customers');
                }
                try {
                    if ($id > 0) {
                        if ($isWalkin) {
                            $stmt = db()->prepare("UPDATE laundry__users_tbl SET full_name=?,phone=? WHERE id=? AND role='customer' AND email IS NULL");
                            $stmt->execute([$name, $phone ?: null, $id]);
                        } else {
                            $stmt = db()->prepare("UPDATE laundry__users_tbl SET full_name=?,phone=?,email=? WHERE id=? AND role='customer' AND email IS NOT NULL");
                            $stmt->execute([$name, $phone ?: null, $email, $id]);
                            if ($password !== '') db()->prepare("UPDATE laundry__users_tbl SET password_hash=? WHERE id=? AND role='customer' AND email IS NOT NULL")->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                        }
                    } else {
                        db()->prepare("INSERT INTO laundry__users_tbl(full_name,phone,email,password_hash,role) VALUES(?,?,?,?,'customer')")->execute([$name, $phone ?: null, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') { flash('อีเมลนี้ถูกใช้งานแล้ว', 'error'); go('customers'); }
                    throw $e;
                }
                flash('บันทึกข้อมูลลูกค้าเรียบร้อย'); go('customers');

            case 'self-order-create':
                // Customers may create their own orders. Admin creation remains a separate action.
                if (is_admin()) { http_response_code(403); exit('Forbidden'); }
                $member = current_user() !== null;
                if (!$member && time() - (int)($_SESSION['last_public_order_at'] ?? 0) < 60) {
                    flash('เพื่อป้องกันการสร้างรายการซ้ำ กรุณารอสักครู่ก่อนสร้างรายการใหม่', 'error');
                    go('self-order');
                }
                // Honeypot: ignore bots that fill hidden fields.
                if (post_string('website') !== '') { http_response_code(400); exit('Invalid form'); }
                $name = $member ? (string)current_user()['full_name'] : post_string('full_name');
                $phone = $member ? '' : post_string('phone');
                $due = post_string('due_at');
                $notes = post_string('notes');
                $serviceIds = $_POST['service_id'] ?? [];
                $qtys = $_POST['quantity'] ?? [];
                if ((!$member && (text_length($name) < 2 || text_length($name) > 100
                         || !preg_match('/^[0-9+()\s-]{8,20}$/D', $phone)))
                    || text_length($notes) > 2000
                    || !is_array($serviceIds) || !is_array($qtys)
                    || count($serviceIds) < 1 || count($serviceIds) > 15
                    || count($serviceIds) !== count($qtys)) {
                    flash('กรุณากรอกชื่อ เบอร์โทร และบริการให้ถูกต้อง', 'error');
                    go('self-order');
                }
                $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $due);
                if (!$dueDate || $dueDate->format('Y-m-d\TH:i') !== $due
                    || $dueDate < new DateTimeImmutable('now')
                    || $dueDate > new DateTimeImmutable('+90 days')) {
                    flash('กรุณาเลือกกำหนดรับผ้าภายใน 90 วันข้างหน้า', 'error');
                    go('self-order');
                }
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $items = [];
                    $total = 0.0;
                    $serviceStmt = $pdo->prepare('SELECT id,price FROM laundry__services_tbl WHERE id=? AND is_active=1');
                    foreach ($serviceIds as $i => $rawId) {
                        $id = filter_var($rawId, FILTER_VALIDATE_INT);
                        $q = $qtys[$i] ?? null;
                        if (!$id || !is_scalar($q) || !is_numeric((string)$q)
                            || (float)$q <= 0 || (float)$q > 10000) throw new DomainException('Invalid order item');
                        $quantity = round((float)$q, 2);
                        if ($quantity <= 0) throw new DomainException('Invalid quantity');
                        $serviceStmt->execute([$id]);
                        $service = $serviceStmt->fetch();
                        if (!$service) throw new DomainException('Service unavailable');
                        $subtotal = round((float)$service['price'] * $quantity, 2);
                        $items[] = [$id, number_format($quantity, 2, '.', ''), $service['price'], number_format($subtotal, 2, '.', '')];
                        $total += $subtotal;
                        if ($total > 99999999) throw new DomainException('Order total too large');
                    }
                    if (!$member) {
                        // Legacy FK constraints require a customer row; no email account is created.
                        $stmt = $pdo->prepare("INSERT INTO laundry__users_tbl(full_name,phone,email,password_hash,role) VALUES(?,?,NULL,?,'customer')");
                        $stmt->execute([$name, $phone, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
                        $customerId = (int)$pdo->lastInsertId();
                    } else {
                        $customerId = (int)current_user()['id'];
                        $stmt = $pdo->prepare("SELECT id FROM laundry__users_tbl WHERE id=? AND role='customer' AND email IS NOT NULL");
                        $stmt->execute([$customerId]);
                        if (!$stmt->fetch()) throw new DomainException('Invalid member account');
                    }
                    $token = $member ? null : bin2hex(random_bytes(32));
                    $orderNo = 'LT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
                    $stmt = $pdo->prepare("INSERT INTO laundry__orders_tbl(order_no,customer_id,created_by,due_at,total_amount,notes,tracking_token_hash,status) VALUES(?,?,?,?,?,?,?,'requested')");
                    $stmt->execute([$orderNo, $customerId, $customerId, $dueDate->format('Y-m-d H:i:s'),
                        number_format(round($total, 2), 2, '.', ''), $notes ?: null,
                        $token === null ? null : tracking_hash($token)]);
                    $orderId = (int)$pdo->lastInsertId();
                    $insert = $pdo->prepare('INSERT INTO laundry__order_items_tbl(order_id,service_id,quantity,unit_price,subtotal) VALUES(?,?,?,?,?)');
                    foreach ($items as $item) $insert->execute(array_merge([$orderId], $item));
                    $pdo->prepare("INSERT INTO laundry__status_history_tbl(order_id,changed_by,old_status,new_status,note) VALUES(?,?,NULL,'requested',?)")
                        ->execute([$orderId, $customerId, 'ลูกค้าสร้างคำขอสั่งบริการ กรุณานำผ้ามาส่งที่ร้าน']);
                    $pdo->commit();

                    if ($member) {
                        flash('สร้างออร์เดอร์สำเร็จ กรุณานำผ้ามาส่งที่ร้าน');
                        go('order', ['id' => $orderId]);
                    }
                    $_SESSION['last_public_order_at'] = time();
                    unset($_SESSION['guest_track'], $_SESSION['guest_pickup_claim']);
                    $_SESSION['new_self_order'] = [
                        'order_id' => $orderId,
                        'customer_id' => $customerId,
                        'token' => $token,
                        'token_hash' => tracking_hash($token),
                    ];
                    go('order-complete');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof DomainException) {
                        flash('สร้างออร์เดอร์ไม่สำเร็จ กรุณาตรวจสอบบริการและจำนวนอีกครั้ง', 'error');
                        go('self-order');
                    }
                    throw $e;
                }
            case 'self-order-join':
                // Claim only an order created in this browser session with its secret token.
                if (current_user()) go('dashboard');
                $pending = membership_claim();
                if (!$pending) {
                    flash('ไม่พบออร์เดอร์ที่สามารถเชื่อมกับบัญชีใหม่ได้', 'error');
                    go('order-start');
                }
                $email = strtolower(post_string('email'));
                $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || strlen($password) < 8
                    || strlen($password) > 4096) {
                    flash('กรุณากรอกอีเมลและรหัสผ่านอย่างน้อย 8 ตัวอักษร', 'error');
                    go('join-after-order');
                }
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT o.id,o.customer_id,u.full_name,u.phone
                        FROM laundry__orders_tbl o
                        INNER JOIN laundry__users_tbl u ON u.id=o.customer_id
                        WHERE o.id=? AND o.customer_id=? AND o.tracking_token_hash=?
                          AND u.role='customer' AND u.email IS NULL
                        FOR UPDATE");
                    $stmt->execute([$pending['order_id'], $pending['customer_id'], $pending['token_hash']]);
                    $guest = $stmt->fetch();
                    if (!$guest) throw new DomainException('Order no longer claimable');
                    $stmt = $pdo->prepare("INSERT INTO laundry__users_tbl(full_name,email,phone,password_hash,role) VALUES(?,?,?,?,'customer')");
                    $stmt->execute([$guest['full_name'], $email, $guest['phone'],
                        password_hash($password, PASSWORD_DEFAULT)]);
                    $accountId = (int)$pdo->lastInsertId();
                    // The old guest QR is revoked. Authenticated member portal now owns the order.
                    $stmt = $pdo->prepare('UPDATE laundry__orders_tbl SET customer_id=?, tracking_token_hash=NULL WHERE id=? AND customer_id=? AND tracking_token_hash=?');
                    $stmt->execute([$accountId, $pending['order_id'], $pending['customer_id'], $pending['token_hash']]);
                    if ($stmt->rowCount() !== 1) throw new DomainException('Order transfer failed');
                    $pdo->commit();
                    unset($_SESSION['new_self_order'], $_SESSION['guest_track'], $_SESSION['guest_pickup_claim']);
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    $_SESSION['user'] = ['id' => $accountId, 'full_name' => $guest['full_name'], 'email' => $email, 'role' => 'customer'];
                    flash('สมัครสมาชิกสำเร็จ ออร์เดอร์ของคุณเชื่อมกับบัญชีใหม่แล้ว');
                    go('order', ['id' => (int)$guest['id']]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof PDOException && $e->getCode() === '23000') {
                        flash('อีเมลนี้มีบัญชีอยู่แล้ว กรุณาใช้อีเมลอื่นหรือติดต่อร้าน', 'error');
                        go('join-after-order');
                    }
                    if ($e instanceof DomainException) {
                        flash('ไม่สามารถเชื่อมออร์เดอร์ได้ กรุณาตรวจสอบอีกครั้ง', 'error');
                        go('join-after-order');
                    }
                    throw $e;
                }
            case 'order-create':
                require_admin();
                $customerId = post_int('customer_id');
                $walkin = post_string('customer_mode') === 'walkin';
                $walkinName = post_string('walkin_name');
                $walkinPhone = post_string('walkin_phone');
                if ($walkin && (text_length($walkinName) < 2 || text_length($walkinName) > 100 || text_length($walkinPhone) > 20)) {
                    flash('กรุณากรอกชื่อลูกค้าหน้าร้านให้ถูกต้อง', 'error');
                    go('new-order');
                }
                $due = post_string('due_at');
                $notes = post_string('notes');
                $serviceIds = $_POST['service_id'] ?? [];
                $qtys = $_POST['quantity'] ?? [];
                if ((!$walkin && $customerId < 1) || !is_array($serviceIds) || !is_array($qtys) || count($serviceIds) < 1 || count($serviceIds) > 15 || text_length($notes) > 2000) {
                    flash('กรุณาเลือกลูกค้าและบริการ', 'error'); go('new-order');
                }
                $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $due);
                if (!$dueDate || $dueDate->format('Y-m-d\TH:i') !== $due || $dueDate < new DateTimeImmutable('now')) {
                    flash('กำหนดรับผ้าไม่ถูกต้อง', 'error'); go('new-order');
                }
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    if ($walkin) {
                        // Keep the foreign-key model intact, but create a no-email, non-login account.
                        $stmt = $pdo->prepare("INSERT INTO laundry__users_tbl(full_name,phone,email,password_hash,role) VALUES(?,?,NULL,?,'customer')");
                        $stmt->execute([$walkinName, $walkinPhone ?: null, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
                        $customerId = (int)$pdo->lastInsertId();
                    } else {
                        $stmt = $pdo->prepare("SELECT id FROM laundry__users_tbl WHERE id=? AND role='customer'");
                        $stmt->execute([$customerId]);
                        if (!$stmt->fetch()) throw new DomainException('Customer not found');
                    }
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
                    $rawTrackingToken = $walkin ? bin2hex(random_bytes(32)) : null;
                    $stmt = $pdo->prepare('INSERT INTO laundry__orders_tbl(order_no,customer_id,created_by,due_at,total_amount,notes,tracking_token_hash) VALUES(?,?,?,?,?,?,?)');
                    $stmt->execute([$orderNo, $customerId, current_user()['id'], $dueDate->format('Y-m-d H:i:s'), number_format($total, 2, '.', ''), $notes ?: null, $rawTrackingToken === null ? null : tracking_hash($rawTrackingToken)]);
                    $orderId = (int) $pdo->lastInsertId();
                    $insert = $pdo->prepare('INSERT INTO laundry__order_items_tbl(order_id,service_id,quantity,unit_price,subtotal) VALUES(?,?,?,?,?)');
                    foreach ($items as $item) $insert->execute(array_merge([$orderId], $item));
                    $pdo->prepare('INSERT INTO laundry__status_history_tbl(order_id,changed_by,old_status,new_status,note) VALUES(?,?,NULL,\'received\',?)')->execute([$orderId, current_user()['id'], 'บันทึกรับผ้า']);
                    $pdo->commit();
                    if ($rawTrackingToken !== null) {
                        $_SESSION['tracking_token_notice'] = ['order_id' => $orderId, 'token' => $rawTrackingToken];
                    }
                    flash('สร้างออร์เดอร์ ' . $orderNo . ' เรียบร้อย'); go('order', ['id' => $orderId]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof DomainException) { flash('ข้อมูลออร์เดอร์ไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง', 'error'); go('new-order'); }
                    throw $e;
                }
            case 'order-tracking-rotate':
                require_admin();
                $orderId = post_int('id');
                if ($orderId < 1) { flash('ออร์เดอร์ไม่ถูกต้อง', 'error'); go('orders'); }
                $rawToken = bin2hex(random_bytes(32));
                $stmt = db()->prepare("UPDATE laundry__orders_tbl o
                    INNER JOIN laundry__users_tbl u ON u.id=o.customer_id
                    SET o.tracking_token_hash=?
                    WHERE o.id=? AND u.email IS NULL");
                $stmt->execute([tracking_hash($rawToken), $orderId]);
                if ($stmt->rowCount() !== 1) {
                    flash('ไม่สามารถสร้าง QR ได้ (ใช้ได้เฉพาะออร์เดอร์ลูกค้าหน้าร้าน)', 'error');
                    go('order', ['id' => $orderId]);
                }
                $_SESSION['tracking_token_notice'] = ['order_id' => $orderId, 'token' => $rawToken];
                flash('สร้าง QR ใหม่แล้ว ลิงก์เก่าจะใช้ไม่ได้อีก');
                go('order', ['id' => $orderId]);
            case 'guest-track-login':
                if (current_user()) go('dashboard');
                $rawToken = post_string('token');
                $tokenHash = tracking_hash($rawToken);
                if ($tokenHash === null) { flash('ลิงก์ไม่ถูกต้อง', 'error'); go('home'); }
                $stmt = db()->prepare("SELECT o.id FROM laundry__orders_tbl o
                    INNER JOIN laundry__users_tbl u ON u.id=o.customer_id
                    WHERE o.tracking_token_hash=? AND u.email IS NULL LIMIT 1");
                $stmt->execute([$tokenHash]);
                $orderId = $stmt->fetchColumn();
                if (!$orderId) { flash('ลิงก์หมดอายุหรือไม่ถูกต้อง', 'error'); go('home'); }
                session_regenerate_id(true);
                unset($_SESSION['guest_pickup_claim']);
                $_SESSION['guest_track'] = ['order_id' => (int)$orderId, 'token_hash' => $tokenHash];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                go('track-portal');
            case 'guest-track-logout':
                // Leaving guest tracking revokes all outstanding session-bound
                // membership claims, including any original self-checkout claim.
                // Otherwise new_self_order could outlive the pickup proof.
                unset($_SESSION['guest_track'], $_SESSION['guest_pickup_claim'], $_SESSION['new_self_order']);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                session_regenerate_id(true);
                flash('ออกจากโหมดติดตามแล้ว');
                go('home');
            case 'guest-confirm-pickup':
                if (current_user()) { http_response_code(403); exit('Forbidden'); }
                $guestOrder = guest_tracking_order();
                if (!$guestOrder) { unset($_SESSION['guest_track']); flash('สิทธิ์ติดตามหมดอายุ', 'error'); go('home'); }
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE laundry__orders_tbl
                        SET status='picked_up'
                        WHERE id=? AND tracking_token_hash=? AND status='ready'");
                    $stmt->execute([(int)$guestOrder['id'], $_SESSION['guest_track']['token_hash']]);
                    if ($stmt->rowCount() !== 1) throw new DomainException('Order is not ready');
                    $pdo->prepare("INSERT INTO laundry__status_history_tbl
                        (order_id,changed_by,old_status,new_status,note)
                        VALUES(?,?,'ready','picked_up',?)")
                        ->execute([(int)$guestOrder['id'],(int)$guestOrder['customer_id'],'ลูกค้าหน้าร้านยืนยันรับผ้าผ่านลิงก์ติดตาม']);
                    $pdo->commit();
                    // Only this guest session can later attach the completed order.
                    $_SESSION['guest_pickup_claim'] = [
                        'order_id' => (int)$guestOrder['id'],
                        'token_hash' => (string)$_SESSION['guest_track']['token_hash'],
                        'confirmed_at' => time(),
                    ];
                    flash('ยืนยันรับผ้าเรียบร้อย');
                    go('track-portal');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof DomainException) { flash('ออร์เดอร์ยังไม่พร้อมรับหรือยืนยันไปแล้ว', 'error'); go('track-portal'); }
                    throw $e;
                }
            case 'order-confirm-pickup':
                require_login();
                // Only a customer can confirm their own ready order.
                if (is_admin()) { http_response_code(403); exit('Forbidden'); }
                $orderId = post_int('id');
                if ($orderId < 1) { flash('หมายเลขออร์เดอร์ไม่ถูกต้อง', 'error'); go('orders'); }

                $pdo = db();
                $pdo->beginTransaction();
                try {
                    // Atomic conditional update prevents other customers and duplicate confirmations.
                    $stmt = $pdo->prepare("UPDATE laundry__orders_tbl SET status='picked_up' WHERE id=? AND customer_id=? AND status='ready'");
                    $stmt->execute([$orderId, current_user()['id']]);
                    if ($stmt->rowCount() !== 1) throw new DomainException('Order not ready or not owned by customer');

                    $pdo->prepare("INSERT INTO laundry__status_history_tbl(order_id,changed_by,old_status,new_status,note) VALUES(?,?,'ready','picked_up',?)")
                        ->execute([$orderId, current_user()['id'], 'ลูกค้ายืนยันว่าได้รับผ้าแล้ว']);

                    $pdo->commit();
                    flash('ยืนยันรับผ้าเรียบร้อย ขอบคุณที่ใช้บริการ');
                    go('order', ['id' => $orderId]);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($e instanceof DomainException) {
                        flash('ไม่สามารถยืนยันรับผ้าได้ กรุณาตรวจสอบสถานะออร์เดอร์ของคุณ', 'error');
                        go('orders');
                    }
                    throw $e;
                }
            case 'order-status':
                require_admin();
                $orderId = post_int('id');
                $next = post_string('status');
                $transitions = [
                    'requested' => ['received', 'cancelled'],
                    'received' => ['washing', 'cancelled'],
                    'washing' => ['drying'],
                    'drying' => ['ready'],
                    // Customer alone confirms ready -> picked_up.
                    'picked_up' => [],
                    'cancelled' => [],
                ];
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT o.status,cu.role AS creator_role
                        FROM laundry__orders_tbl o JOIN laundry__users_tbl cu ON cu.id=o.created_by
                        WHERE o.id=? FOR UPDATE');
                    $stmt->execute([$orderId]);
                    $orderState = $stmt->fetch();
                    $current = $orderState['status'] ?? null;
                    if (!$current || !in_array($next, $transitions[(string)$current] ?? [], true)) throw new DomainException('Invalid status transition');
                    // Physical drop-off must be verified on requested -> received; no skipping.
                    if ($current === 'requested' && $next === 'received' && post_string('dropoff_confirmed') !== 'yes') {
                        throw new DomainException('Staff must verify physical laundry receipt');
                    }
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
