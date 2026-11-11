<?php
// Generate a hash locally, NEVER serve this script from public/.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
fwrite(STDOUT, "Enter a new account password (visible while typing, use a private terminal): ");
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "Use at least 12 characters.\n");
    exit(1);
}
fwrite(STDOUT, password_hash($password, PASSWORD_DEFAULT) . "\n");
