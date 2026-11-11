<?php
declare(strict_types=1);

/**
 * Security regression tests: only disposable laundrytrack_ci MySQL.
 * No calls or writes to Angsila production.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../laundrytrack_private/app/bootstrap.php';

function check_security(bool $condition, string $description): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$description}\n"); exit(1); }
    echo "PASS: {$description}\n";
}
check_security(setting('APP_ENV') === 'ci' && setting('DB_NAME') === 'laundrytrack_ci',
    'disposable security test config');
check_security(db()->query('SELECT DATABASE()')->fetchColumn() === 'laundrytrack_ci',
    'live MySQL database identity guard');

$now = 1900000000;
check_security(!session_is_expired([], $now), 'legacy session without timestamps is accepted for migration');
check_security(!session_is_expired(['session_started_at'=>$now-43200,'session_last_seen'=>$now-3600], $now),
    'sessions at boundary are still valid');
check_security(session_is_expired(['session_started_at'=>$now-43201,'session_last_seen'=>$now-10], $now),
    'absolute 12-hour session timeout');
check_security(session_is_expired(['session_started_at'=>$now-100,'session_last_seen'=>$now-3601], $now),
    'idle 60-minute session timeout');
check_security(session_is_expired(['session_started_at'=>$now-100,'session_last_seen'=>$now+20], $now),
    'future session timestamps are rejected');
check_security(session_is_expired(['session_started_at'=>'invalid','session_last_seen'=>$now], $now),
    'malformed session timestamps are rejected');

$pdo = db();
$pdo->exec("DELETE FROM laundry__order_rate_limits_tbl WHERE scope LIKE 'login-%'");
$_SERVER['REMOTE_ADDR'] = '198.51.100.91';
check_security(strlen(setting('RATE_LIMIT_SECRET')) >= 32, 'strong login HMAC secret present');
$email = 'lockout@example.test';
for ($i = 1; $i <= 12; ++$i) {
    check_security(login_rate_limit_allowed($email), "account quota {$i}/12 accepted");
}
check_security(!login_rate_limit_allowed($email), '13th account login rejected');
$hash = hash_hmac('sha256', 'login-email:' . $email, setting('RATE_LIMIT_SECRET'));
$stmt = $pdo->prepare("SELECT attempt_count FROM laundry__order_rate_limits_tbl
    WHERE scope='login-email-15m' AND identity_hash=?");
$stmt->execute([$hash]);
check_security((int)$stmt->fetchColumn() === 12, 'denied attempts do not exceed account count');

// Changing IP does not reset the 12-attempt per-account quota.
$_SERVER['REMOTE_ADDR'] = '198.51.100.92';
check_security(!login_rate_limit_allowed($email), 'same target account blocked from second IP');
check_security(login_rate_limit_allowed('other@example.test'), 'unrelated account can still log in');
// Forwarded headers never alter identity.
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.250';
check_security(!login_rate_limit_allowed($email), 'spoofed forwarding header cannot bypass account quota');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

// Exercise IP-only quota with fresh account keys each time.
$_SERVER['REMOTE_ADDR'] = '198.51.100.93';
for ($i = 1; $i <= 60; ++$i) {
    if (!login_rate_limit_allowed("account{$i}@example.test")) {
        fwrite(STDERR, "FAIL: IP quota prematurely rejected attempt {$i}\n");
        exit(1);
    }
}
check_security(!login_rate_limit_allowed('new-account@example.test'),
    '61st request from same IP is blocked');
$_SERVER['REMOTE_ADDR'] = '198.51.100.94';
check_security(login_rate_limit_allowed('new-account@example.test'),
    'independent IP can attempt different email');

// Expired account window resets without affecting other scope identities.
$pdo->prepare("UPDATE laundry__order_rate_limits_tbl SET window_started_at=?
    WHERE scope='login-email-15m' AND identity_hash=?")->execute([time() - 901, $hash]);
$_SERVER['REMOTE_ADDR'] = '198.51.100.95';
check_security(login_rate_limit_allowed($email), 'expired account quota resets');
$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
check_security(!login_rate_limit_allowed($email), 'invalid REMOTE_ADDR fails closed');

// Fail closed if secret missing/weak.
$secret = $_ENV['RATE_LIMIT_SECRET'];
$_ENV['RATE_LIMIT_SECRET'] = 'weak';
$_SERVER['REMOTE_ADDR'] = '198.51.100.96';
check_security(!login_rate_limit_allowed($email), 'weak HMAC secret fails closed');
$_ENV['RATE_LIMIT_SECRET'] = $secret;

// Counter table unavailable, still fail closed rather than permitting guesses.
$pdo->exec("RENAME TABLE laundry__order_rate_limits_tbl TO laundry__order_rate_limits_tbl_offline");
try {
    check_security(!login_rate_limit_allowed($email), 'login fails closed when quota table is offline');
} finally {
    $pdo->exec("RENAME TABLE laundry__order_rate_limits_tbl_offline TO laundry__order_rate_limits_tbl");
}
echo "ALL SECURITY HARDENING INTEGRATION TESTS PASSED\n";
