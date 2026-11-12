<?php
declare(strict_types=1);

/**
 * CLI-only idempotent migration: creates a separate per-service progress table,
 * then populates progress without altering existing order/customer rows.
 * A VERIFIED backup is mandatory before running against production.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../app/bootstrap.php';
try {
    $pdo = db();
    $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $ciOnly = setting('APP_ENV') === 'ci'
        && $name === 'laundrytrack_ci'
        && setting('DB_NAME') === 'laundrytrack_ci'
        && getenv('LAUNDRY_PROGRESS_CI_MIGRATION') === 'yes';
    $production = $name === 's68160395' && setting('DB_NAME') === 's68160395'
        && setting('APP_ENV') !== 'ci';
    if (!$production && !$ciOnly) {
        throw new RuntimeException('REFUSED: unexpected database');
    }
    $confirmation = $argv[1] ?? '';
    if ($production && $confirmation !== '--backup-verified') {
        throw new RuntimeException('REFUSED: first verify a recent backup, then pass --backup-verified');
    }
    $migration = __DIR__ . '/../migrations/2026-11-12_order_item_progress.sql';
    $sql = file_get_contents($migration);
    if ($sql === false) throw new RuntimeException('Migration file missing');

    // Execute one CREATE TABLE statement only; check expected structure BEFORE
    // inserting tracked states to avoid writing into an incompatible table.
    $ddl = explode(';', $sql)[0];
    // Strip SQL comments, as they are irrelevant to the DDL.
    $ddl = preg_replace('/^--.*$/m', '', $ddl);
    if (!is_string($ddl) || !str_contains($ddl, 'CREATE TABLE IF NOT EXISTS')) {
        throw new RuntimeException('Unexpected migration format');
    }
    $pdo->exec($ddl);
    $columns = $pdo->query('SHOW COLUMNS FROM laundry__order_item_progress_tbl')->fetchAll(PDO::FETCH_COLUMN);
    if ($columns !== ['order_item_id','work_status','updated_at','updated_by']) {
        throw new RuntimeException('Existing progress table differs; manual inspection required');
    }
    $types = $pdo->query('SHOW COLUMNS FROM laundry__order_item_progress_tbl')->fetchAll(PDO::FETCH_ASSOC);
    if (($types[1]['Type'] ?? '') !== "enum('pending','working','done')"
        || ($types[0]['Key'] ?? '') !== 'PRI') {
        throw new RuntimeException('Existing progress table has incorrect keys/types');
    }
    // Only write the brand-new progress table. Never overwrite existing work.
    $insert = "INSERT INTO laundry__order_item_progress_tbl (order_item_id,work_status)
        SELECT oi.id, IF(o.status IN ('ready','picked_up'),'done','pending')
        FROM laundry__order_items_tbl oi
        JOIN laundry__orders_tbl o ON o.id=oi.order_id
        ON DUPLICATE KEY UPDATE order_item_id=order_item_id";
    $pdo->exec($insert);
    $count = (int)$pdo->query('SELECT COUNT(*) FROM laundry__order_item_progress_tbl')->fetchColumn();
    $missing = (int)$pdo->query("SELECT COUNT(*)
        FROM laundry__order_items_tbl oi
        LEFT JOIN laundry__order_item_progress_tbl p ON p.order_item_id=oi.id
        WHERE p.order_item_id IS NULL")->fetchColumn();
    if ($missing !== 0) throw new RuntimeException('Some order item progress rows were not populated');
    echo "ORDER ITEM PROGRESS MIGRATION OK\n";
    echo "Database: " . $name . "\n";
    echo "Tracked service rows: " . $count . "\n";
    echo "No existing order/customer records were modified.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ORDER ITEM PROGRESS MIGRATION FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
