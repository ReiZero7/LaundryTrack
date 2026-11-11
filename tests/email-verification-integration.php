<?php
declare(strict_types=1);
/** CI-only verification tests. Explicit schema guards prevent live DB usage. */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require_once __DIR__ . '/../laundrytrack_private/app/bootstrap.php';

function check(bool $success, string $name): void {
    if (!$success) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
    echo "PASS: {$name}\n";
}
if (setting('APP_ENV') !== 'ci' || setting('DB_NAME') !== 'laundrytrack_ci') {
    fwrite(STDERR, "REFUSED: email tests run only in disposable CI schema\n");
    exit(1);
}
$pdo = db();
check($pdo->query('SELECT DATABASE()')->fetchColumn() === 'laundrytrack_ci', 'disposable DB guard');
check((int)$pdo->query('SELECT COUNT(*) FROM laundry__users_tbl WHERE id IN (1,2) AND email_verified_at IS NOT NULL')->fetchColumn() === 2,
    'migration preserves access for old customer and admin');
check($pdo->query('SELECT email_verified_at FROM laundry__users_tbl WHERE id=3')->fetchColumn() === null,
    'walk-in guest stays without email verification');

function newCustomer(string $address): int {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO laundry__users_tbl
        (full_name,email,password_hash,role,email_verified_at)
        VALUES('New member',?,'test-hash','customer',NULL)");
    $stmt->execute([$address]);
    return (int)$pdo->lastInsertId();
}
function verified(int $id): bool {
    $stmt = db()->prepare('SELECT email_verified_at FROM laundry__users_tbl WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetchColumn() !== null;
}
function expectExpired(string $token, string $name): void {
    try {
        complete_email_verification($token);
        check(false, $name);
    } catch (DomainException $e) {
        check(true, $name);
    }
}

$account = newCustomer('normal@example.test');
check(!verified($account), 'new account starts unverified');
$pdo->beginTransaction();
$token = create_email_verification($pdo, $account);
$pdo->commit();
check(preg_match('/^[a-f0-9]{64}$/D', $token) === 1, 'random email token format');
$q = $pdo->prepare('SELECT token_hash FROM laundry__email_verifications_tbl WHERE user_id=?');
$q->execute([$account]);
$stored = $q->fetchColumn();
check($stored === hash('sha256', $token) && $stored !== $token, 'database stores only hashed token');
check(send_email_verification('normal@example.test', $token), 'CI-only fake sender permits independent email tests');
check(($GLOBALS['laundry_ci_verification_token'] ?? null) === $token,
    'CI-only fake sender captures token without sending an actual message');

expectExpired(str_repeat('a', 64), 'incorrect token rejected');
check(!verified($account), 'invalid token did not verify account');
check(complete_email_verification($token) === 'verified', 'valid token verifies account');
check(verified($account), 'verified timestamp written');
expectExpired($token, 'consumed token cannot be reused');
$q->execute([$account]);
check($q->fetchColumn() === false, 'token row deleted after confirmation');

$expired = newCustomer('expired@example.test');
$pdo->beginTransaction();
$expiredToken = create_email_verification($pdo, $expired);
$pdo->commit();
$pdo->prepare("UPDATE laundry__email_verifications_tbl
    SET expires_at=UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE user_id=?")->execute([$expired]);
expectExpired($expiredToken, 'expired token cannot activate customer');
check(!verified($expired), 'expired token left account unverified');

$guestToken = bin2hex(random_bytes(32));
$guestHash = tracking_hash($guestToken);
$stmt = $pdo->prepare("INSERT INTO laundry__orders_tbl(customer_id,tracking_token_hash,status)
    VALUES(3,?,'picked_up')");
$stmt->execute([$guestHash]);
$orderId = (int)$pdo->lastInsertId();
$newOwner = newCustomer('former-guest@example.test');
$pdo->beginTransaction();
$linkToken = create_email_verification($pdo, $newOwner, [
    'order_id' => $orderId,
    'customer_id' => 3,
    'token_hash' => $guestHash,
]);
$pdo->commit();
$q = $pdo->prepare('SELECT customer_id,tracking_token_hash FROM laundry__orders_tbl WHERE id=?');
$q->execute([$orderId]);
$before = $q->fetch();
check((int)$before['customer_id'] === 3 && $before['tracking_token_hash'] === $guestHash,
    'order remains in guest ownership before email confirmation');
check(complete_email_verification($linkToken) === 'linked', 'verified email transfers guest order');
$q->execute([$orderId]);
$after = $q->fetch();
check((int)$after['customer_id'] === $newOwner && $after['tracking_token_hash'] === null,
    'guest QR revoked only after successful verified claim');
check(verified($newOwner), 'verified former guest may sign in');
expectExpired($linkToken, 'claimed order token cannot be reused');

$guestToken2 = bin2hex(random_bytes(32));
$guestHash2 = tracking_hash($guestToken2);
$stmt->execute([$guestHash2]);
$rotatedOrder = (int)$pdo->lastInsertId();
$pendingOwner = newCustomer('rotated@example.test');
$pdo->beginTransaction();
$rotatedVerification = create_email_verification($pdo, $pendingOwner, [
    'order_id' => $rotatedOrder,
    'customer_id' => 3,
    'token_hash' => $guestHash2,
]);
$pdo->commit();
$pdo->prepare('UPDATE laundry__orders_tbl SET tracking_token_hash=? WHERE id=?')
    ->execute([hash('sha256', 'new-token-hash'), $rotatedOrder]);
check(complete_email_verification($rotatedVerification) === 'claim_unavailable',
    'rotated guest QR cannot transfer order');
$q->execute([$rotatedOrder]);
check((int)$q->fetch()['customer_id'] === 3, 'rotated order ownership untouched');
check(verified($pendingOwner), 'email still verified if QR claim was rotated');

echo "ALL EMAIL VERIFICATION INTEGRATION TESTS PASSED\n";
