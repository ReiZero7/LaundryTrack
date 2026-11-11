<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$pdo = db();

$services = $pdo->query("
    SELECT name, unit, price
    FROM laundry__services_tbl
    WHERE is_active = 1
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_amount
    FROM laundry__orders_tbl
")->fetch(PDO::FETCH_ASSOC);

$data = [
    'generated_at' => date('c'),
    'services' => $services,
    'summary' => $summary
];

$file = __DIR__ .
    '/../../public_html/laundrytrack/dashboard_data.json';

$json = json_encode(
    $data,
    JSON_UNESCAPED_UNICODE |
    JSON_PRETTY_PRINT |
    JSON_THROW_ON_ERROR
);

if (file_put_contents($file, $json, LOCK_EX) === false) {
    throw new RuntimeException('Cannot write dashboard JSON');
}

chmod($file, 0644);

echo "Dashboard data exported successfully!\n";
echo "Services: " . count($services) . "\n";
echo "Orders: " . $summary['total_orders'] . "\n";
echo "Total amount: " . $summary['total_amount'] . " THB\n";
