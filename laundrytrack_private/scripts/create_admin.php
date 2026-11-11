<?php
declare(strict_types=1);
// Never expose this script under public/. Execute from a terminal ONLY.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../app/bootstrap.php';

function ask(string $label): string {
    fwrite(STDOUT, $label);
    return trim((string)fgets(STDIN));
}
try {
    $name = ask('Admin full name: ');
    $email = strtolower(ask('Admin email: '));
    $password = ask('Admin password (visible while typing; use a private terminal): ');
    if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        fwrite(STDERR, "Name/email invalid or password shorter than 12 characters.\n"); exit(1);
    }
    $stmt = db()->prepare('INSERT INTO laundry__users_tbl(full_name,email,password_hash,role) VALUES(?,?,?,\'admin\')');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Admin created. Sign in from the web page.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Could not create admin. Check database and duplicate email.\n");
    error_log($e->getMessage());
    exit(1);
}
