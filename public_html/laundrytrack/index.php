<?php
declare(strict_types=1);

try {
    // Local: project/public/ ; Shared hosting: public_html/laundrytrack/ + ~/LaundryTrackPrivate/
    $appBootstrap = is_file(__DIR__ . '/../app/bootstrap.php')
        ? __DIR__ . '/../app/bootstrap.php'
        : __DIR__ . '/../../laundrytrack_private/app/bootstrap.php';
    require_once $appBootstrap;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once PROJECT_ROOT . '/app/actions.php';
        process_action();
    }
    $page = $_GET['page'] ?? 'home';
    if (!is_string($page) || !preg_match('/^[a-z-]+$/', $page)) $page = 'home';

    // Authenticated, read-only endpoint for customer in-page notifications.
    if ($page === 'notifications-feed') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        if (!current_user()) {
            http_response_code(401);
            echo json_encode(['error' => 'unauthorized']);
            exit;
        }
        if (is_admin()) {
            http_response_code(403);
            echo json_encode(['error' => 'customer_only']);
            exit;
        }

        $customerId = (int) current_user()['id'];
        $stmt = db()->prepare("SELECT COUNT(*) FROM laundry__orders_tbl WHERE customer_id=? AND status='ready'");
        $stmt->execute([$customerId]);
        $readyCount = (int) $stmt->fetchColumn();

        $stmt = db()->prepare("SELECT id, order_no FROM laundry__orders_tbl WHERE customer_id=? AND status='ready' ORDER BY id DESC LIMIT 10");
        $stmt->execute([$customerId]);
        $readyOrders = [];
        foreach ($stmt->fetchAll() as $readyOrder) {
            $readyOrders[] = [
                'id' => (int) $readyOrder['id'],
                'order_no' => (string) $readyOrder['order_no'],
            ];
        }
        echo json_encode(
            ['ready_count' => $readyCount, 'orders' => $readyOrders],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        exit;
    }
    if (in_array($page, ['track', 'track-portal', 'track-feed'], true)) {
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }
    if ($page === 'track-feed') {
        header('Content-Type: application/json; charset=utf-8');
        if (current_user()) { http_response_code(403); echo '{"error":"guest_only"}'; exit; }
        $trackedOrder = guest_tracking_order();
        if (!$trackedOrder) { http_response_code(401); echo '{"error":"invalid_guest_session"}'; exit; }
        echo json_encode([
            'id' => (int)$trackedOrder['id'],
            'status' => $trackedOrder['status'],
            'status_label' => status_label($trackedOrder['status']),
            'ready' => $trackedOrder['status'] === 'ready'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($page === 'order-complete' || $page === 'join-after-order') {
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
    }
    if (current_user() && in_array($page, ['home', 'login', 'register'], true)) go('dashboard');
    $publicPages = ['home', 'login', 'register', 'track', 'track-portal', 'order-start', 'self-order', 'order-complete', 'join-after-order'];
    if (!in_array($page, $publicPages, true)) require_login();
    $adminOnly = ['services', 'customers', 'new-order'];
    if (in_array($page, $adminOnly, true)) require_admin();
    $allowed = ['home','login','register','dashboard','services','customers','new-order','orders','order','track','track-portal','order-start','self-order','order-complete','join-after-order'];
    if (!in_array($page, $allowed, true)) { http_response_code(404); $page = 'not-found'; }
    require PROJECT_ROOT . '/app/views/layout_top.php';
    require PROJECT_ROOT . '/app/views/' . $page . '.php';
    require PROJECT_ROOT . '/app/views/layout_bottom.php';
} catch (Throwable $e) {
    error_log('[LaundryTrack] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><title>LaundryTrack setup</title><body style="font:16px system-ui;max-width:720px;margin:70px auto;padding:24px"><h1>LaundryTrack ยังไม่พร้อมใช้งาน</h1><p>ตรวจสอบไฟล์ .env, ฐานข้อมูล MySQL และ PHP extension pdo_mysql ตาม README.md</p><p>ข้อผิดพลาดฉบับละเอียดถูกบันทึกไว้ใน PHP error log (ไม่แสดงรหัสผ่านต่อผู้ใช้)</p></body></html>';
}
