<?php
declare(strict_types=1);
// CLI-only read-only DB connection verification, no secrets printed.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../app/bootstrap.php';
try {
    $pdo = db();
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName !== 's68160395') {
        fwrite(STDERR, "Warning: connected to '$dbName' rather than s68160395. Verify .env before writing.\n");
        exit(2);
    }
    $tables = ['users','services','orders','order_items','status_history'];
    foreach ($tables as $name) {
        $table = 'laundry__' . $name . '_tbl';
        $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
        fwrite(STDOUT, "OK: $table\n");
    }
    $stmt = $pdo->prepare("SELECT order_no, status, total_amount FROM laundry__orders_tbl WHERE order_no = ?");
    $stmt->execute(['LT-0001']);
    $order = $stmt->fetch();
    fwrite(STDOUT, $order ? "Sample order: {$order['order_no']} / {$order['status']} / {$order['total_amount']} THB\n" : "Sample order LT-0001 not found.\n");
    fwrite(STDOUT, "Connection and read-only checks passed.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Connection or table check failed. Verify .env, pdo_mysql, VPN, and hosting permissions.\n");
    error_log('[LaundryTrack check] ' . $e->getMessage());
    exit(1);
}
