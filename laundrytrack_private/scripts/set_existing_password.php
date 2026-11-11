<?php
// One-time CLI tool: set a password for an EXISTING user (e.g. admin id=1).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../app/bootstrap.php';
$id = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { fwrite(STDERR, "Usage: php scripts/set_existing_password.php USER_ID\n"); exit(1); }
$stmt = db()->prepare('SELECT id, email, role FROM laundry__users_tbl WHERE id=?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { fwrite(STDERR, "User not found.\n"); exit(1); }
fwrite(STDOUT, "Update password for {$row['role']} {$row['email']} (id {$row['id']})? Type YES to confirm: ");
if (trim((string)fgets(STDIN)) !== 'YES') exit(1);
fwrite(STDOUT, "New password, at least 12 characters (visible while typing): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12) { fwrite(STDERR, "Password too short\n"); exit(1); }
db()->prepare('UPDATE laundry__users_tbl SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT),$id]);
fwrite(STDOUT, "Password updated.\n");
