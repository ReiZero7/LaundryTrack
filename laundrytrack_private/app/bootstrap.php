<?php
declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/..';

/** Minimal .env reader for KEY=VALUE lines; no Composer dependency. */
function load_env(string $path): void {
    if (!is_file($path)) {
        throw new RuntimeException('ไม่พบไฟล์ .env กรุณาคัดลอก .env.example เป็น .env แล้วตั้งค่าฐานข้อมูล');
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_starts_with($line, 'export ')) $line = substr($line, 7);
        $pair = explode('=', $line, 2);
        if (count($pair) !== 2 || !preg_match('/^[A-Z_][A-Z0-9_]*$/', trim($pair[0]))) continue;
        $key = trim($pair[0]);
        $value = trim($pair[1]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        }
        $_ENV[$key] = $value;
    }
}

load_env(PROJECT_ROOT . '/.env');
date_default_timezone_set(setting('APP_TIMEZONE', 'Asia/Bangkok'));

function setting(string $key, string $default = ''): string {
    return (string) ($_ENV[$key] ?? $default);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PHP ยังไม่ได้เปิดใช้งาน extension pdo_mysql');
    }
    $host = setting('DB_HOST', '127.0.0.1');
    $port = setting('DB_PORT', '3306');
    $dbname = setting('DB_NAME', 'laundrytrack_db');
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, setting('DB_USER'), setting('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('laundrytrack_session');
    session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $page, array $query = []): string {
    return 'index.php?' . http_build_query(array_merge(['page' => $page], $query));
}
function go(string $page, array $query = []): never {
    header('Location: ' . url($page, $query), true, 303);
    exit;
}
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): void {
    if (!current_user()) go('login');
}
function is_admin(): bool {
    return (current_user()['role'] ?? '') === 'admin';
}
function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); exit('Forbidden'); }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function require_csrf(): void {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? '')) || empty($_SESSION['csrf'])) {
        http_response_code(419);
        exit('Invalid CSRF token. Refresh the page and try again.');
    }
}
function flash(string $message, string $kind = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
}
function take_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
function text_length(string $v): int {
    return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : preg_match_all('/./us', $v);
}
function post_string(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function post_int(string $key): int {
    return filter_var($_POST[$key] ?? 0, FILTER_VALIDATE_INT) ?: 0;
}
function handle_error(Throwable $e, string $page = 'dashboard'): never {
    error_log('[LaundryTrack] ' . $e->getMessage());
    flash('ดำเนินการไม่สำเร็จ โปรดตรวจสอบข้อมูลและลองใหม่อีกครั้ง', 'error');
    go($page);
}
function mb_substr_safe(string $v, int $start, int $length): string {
    if (function_exists('mb_substr')) return mb_substr($v, $start, $length, 'UTF-8');
    $chars = preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY);
    return implode('', array_slice($chars ?: [], $start, $length));
}
function tracking_hash(string $token): ?string {
    // Exactly 32 random bytes in lowercase hex. No order IDs as auth secrets.
    return preg_match('/^[a-f0-9]{64}$/D', $token) ? hash('sha256', $token) : null;
}
function shop_order_link(): string {
    return 'https://angsila.informatics.buu.ac.th/~68160395/laundrytrack/index.php?page=order-start';
}
function tracking_link(string $token): string {
    // Derived from configured actual web origin; avoid Host header injection.
    // This deployment is fixed at the university public URL.
    return 'https://angsila.informatics.buu.ac.th/~68160395/laundrytrack/index.php?page=track&token=' . rawurlencode($token);
}
function guest_tracking_order(): ?array {
    $guest = $_SESSION['guest_track'] ?? null;
    if (!is_array($guest) || !is_int($guest['order_id'] ?? null) || !is_string($guest['token_hash'] ?? null)) return null;
    $stmt = db()->prepare("SELECT o.id,o.order_no,o.status,o.due_at,o.customer_id
        FROM laundry__orders_tbl o
        INNER JOIN laundry__users_tbl u ON u.id=o.customer_id
        WHERE o.id=? AND o.tracking_token_hash=? AND u.email IS NULL
        LIMIT 1");
    $stmt->execute([$guest['order_id'], $guest['token_hash']]);
    $order = $stmt->fetch();
    return $order ?: null;
}
function money(float|string|null $amount): string {
    return number_format((float)($amount ?? 0), 2);
}
function status_label(string $status): string {
    return [
        'received' => 'รับผ้าแล้ว', 'washing' => 'กำลังซัก', 'drying' => 'กำลังอบ/ตาก',
        'ready' => 'พร้อมรับผ้า', 'picked_up' => 'รับผ้าคืนแล้ว', 'cancelled' => 'ยกเลิก',
    ][$status] ?? $status;
}
function status_class(string $status): string {
    return match ($status) {
        'ready','picked_up' => 'is-good', 'cancelled' => 'is-muted',
        'washing','drying' => 'is-progress', default => 'is-pending',
    };
}
