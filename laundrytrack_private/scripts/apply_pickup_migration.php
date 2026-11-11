<?php
declare(strict_types=1);

/**
 * CLI-only, additive migration for staff-verified pickup.
 * Run on Angsila only after verifying the database backup.
 * This adds a table; it does not rewrite orders or customer information.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../app/bootstrap.php';
try {
    $pdo = db();
    $connected = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $configured = setting('DB_NAME');
    if ($connected !== 's68160395' || $configured !== 's68160395') {
        throw new RuntimeException('REFUSED: connected DB must be s68160395');
    }
    $path = __DIR__ . '/../migrations/2026-11-11_staff_verified_pickup.sql';
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException('Pickup migration file not found');
    // It is a single CREATE TABLE IF NOT EXISTS statement (no DROP/UPDATE).
    $pdo->exec($sql);
    $cols = $pdo->query('SHOW COLUMNS FROM laundry__pickup_requests_tbl')
        ->fetchAll(PDO::FETCH_COLUMN);
    $expected = ['order_id','requested_at','requested_source','requested_by','confirmed_at','confirmed_by'];
    if ($cols !== $expected) {
        throw new RuntimeException('Existing pickup table differs from expected schema; manual inspection required');
    }
    fwrite(STDOUT, "PICKUP MIGRATION OK: laundry__pickup_requests_tbl\n");
    fwrite(STDOUT, "Database: " . $connected . "\n");
    fwrite(STDOUT, "No order statuses were changed.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "PICKUP MIGRATION FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
