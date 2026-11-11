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
    if (current_user() && in_array($page, ['home', 'login', 'register'], true)) go('dashboard');
    $publicPages = ['home', 'login', 'register'];
    if (!in_array($page, $publicPages, true)) require_login();
    $adminOnly = ['services', 'customers', 'new-order'];
    if (in_array($page, $adminOnly, true)) require_admin();
    $allowed = ['home','login','register','dashboard','services','customers','new-order','orders','order'];
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
