<?php
declare(strict_types=1);

/**
 * Standalone integration test for the real MySQL-backed limiter.
 * Deliberately refuses to run against any database other than the
 * disposable 'laundrytrack_ci' schema. Never run this against production.
 *
 * Run via .github/workflows/php-rate-limit-tests.yml.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../laundrytrack_private/app/bootstrap.php';

function verify(bool $condition, string $description): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$description}\n");
        exit(1);
    }
    echo "PASS: {$description}\n";
}

if (setting('APP_ENV') !== 'ci' || setting('DB_NAME') !== 'laundrytrack_ci') {
    fwrite(STDERR, "REFUSED: this test only runs on disposable CI schema\n");
    exit(1);
}

$pdo = db();
verify($pdo->query('SELECT DATABASE()')->fetchColumn() === 'laundrytrack_ci', 'isolated database guard');
$pdo->exec('DELETE FROM laundry__order_rate_limits_tbl');

$secret = setting('RATE_LIMIT_SECRET');
verify(strlen($secret) >= 32, 'private test HMAC secret present');

function idHash(string $kind, string $identity): string {
    return hash_hmac('sha256', $kind . ':' . $identity, setting('RATE_LIMIT_SECRET'));
}
function countFor(string $scope, string $hash): int {
    $query = db()->prepare('SELECT attempt_count FROM laundry__order_rate_limits_tbl WHERE scope=? AND identity_hash=?');
    $query->execute([$scope, $hash]);
    $val = $query->fetchColumn();
    return $val === false ? -1 : (int)$val;
}
function updateCounter(string $scope, string $hash, int $count, int $start): void {
    $query = db()->prepare('UPDATE laundry__order_rate_limits_tbl SET attempt_count=?,window_started_at=? WHERE scope=? AND identity_hash=?');
    $query->execute([$count, $start, $scope, $hash]);
    verify($query->rowCount() === 1, "counter fixture updated: {$scope}");
}

$_SESSION = [];
$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
$guestHash = idHash('guest-ip', '198.51.100.21');

for ($i = 1; $i <= 8; ++$i) {
    verify(order_rate_limit_allowed(), "guest request {$i}/8 accepted");
}
verify(!order_rate_limit_allowed(), 'guest ninth 15-minute request rejected');
verify(countFor('guest-15m', $guestHash) === 8, 'rejected guest request does not increment short counter');
verify(countFor('guest-24h', $guestHash) === 8, 'rejected guest request does not increment daily counter');

// A spoofed proxy header is ignored; the server-observed remote IP is authoritative.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99';
verify(!order_rate_limit_allowed(), 'guest cannot evade limit using forwarded-for header');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

$_SERVER['REMOTE_ADDR'] = '198.51.100.22';
verify(order_rate_limit_allowed(), 'another guest IP has an independent quota');

$_SESSION['user'] = ['id' => 2001, 'role' => 'customer'];
$memberHash = idHash('member', '2001');
for ($i = 1; $i <= 10; ++$i) {
    verify(order_rate_limit_allowed(), "member request {$i}/10 accepted");
}
verify(!order_rate_limit_allowed(), 'member eleventh 15-minute request rejected');
verify(countFor('member-15m', $memberHash) === 10, 'rejected member request not counted');

$_SESSION['user'] = ['id' => 2002, 'role' => 'customer'];
verify(order_rate_limit_allowed(), 'another member account has an independent quota');

// Check the 24h ceilings without creating fake orders or waiting for the window.
unset($_SESSION['user']);
$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
updateCounter('guest-15m', $guestHash, 0, time());
updateCounter('guest-24h', $guestHash, 29, time());
verify(order_rate_limit_allowed(), 'guest 30th daily request accepted');
verify(!order_rate_limit_allowed(), 'guest 31st daily request rejected');
verify(countFor('guest-24h', $guestHash) === 30, 'guest daily cap persists');

$_SESSION['user'] = ['id' => 2001, 'role' => 'customer'];
updateCounter('member-15m', $memberHash, 0, time());
updateCounter('member-24h', $memberHash, 49, time());
verify(order_rate_limit_allowed(), 'member 50th daily request accepted');
verify(!order_rate_limit_allowed(), 'member 51st daily request rejected');

// Expired window renews; a new window must not inherit the previous cap.
updateCounter('member-15m', $memberHash, 10, time() - 901);
updateCounter('member-24h', $memberHash, 50, time() - 86401);
verify(order_rate_limit_allowed(), 'expired 15-minute and daily windows reset');
verify(countFor('member-15m', $memberHash) === 1, 'short window reset to one attempt');
verify(countFor('member-24h', $memberHash) === 1, 'daily window reset to one attempt');

// When the request lacks a valid server-observed IP, guest ordering fails closed.
unset($_SESSION['user']);
$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
verify(!order_rate_limit_allowed(), 'invalid remote IP fails closed');

// Missing/weak secret must never silently allow requests.
$realSecret = $_ENV['RATE_LIMIT_SECRET'];
$_ENV['RATE_LIMIT_SECRET'] = 'weak';
$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
verify(!order_rate_limit_allowed(), 'missing or weak private secret fails closed');
$_ENV['RATE_LIMIT_SECRET'] = $realSecret;

// Missing database table fails closed (only in disposable CI schema).
$pdo->exec('RENAME TABLE laundry__order_rate_limits_tbl TO laundry__order_rate_limits_tbl_offline');
try {
    verify(!order_rate_limit_allowed(), 'unavailable database counter table fails closed');
} finally {
    $pdo->exec('RENAME TABLE laundry__order_rate_limits_tbl_offline TO laundry__order_rate_limits_tbl');
}

echo "ALL RATE LIMIT INTEGRATION TESTS PASSED\n";
