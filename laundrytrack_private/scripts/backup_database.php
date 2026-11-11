<?php
declare(strict_types=1);

/**
 * LaundryTrack private CLI backup.
 *
 * Run only on a trusted shell:
 *   php ~/laundrytrack_private/scripts/backup_database.php
 *
 * Exports the configured DB using mysqldump into ~/laundrytrack_backups/mysql.
 * NEVER restores, deletes, or updates database content. Requires MySQL client.
 * Credentials are stored in a short-lived chmod-0600 option file, not argv.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once __DIR__ . '/../app/bootstrap.php';

$previousUmask = umask(0077);
$optionFile = null;
$partialFile = null;
$finalFile = null;
$checksumPath = null;
$completed = false;

try {
    $database = setting('DB_NAME');
    $hostname = setting('DB_HOST', '127.0.0.1');
    $port = setting('DB_PORT', '3306');
    $username = setting('DB_USER');
    $password = setting('DB_PASS');

    if (!preg_match('/^[A-Za-z0-9_]+$/D', $database)
        || $hostname === '' || $username === '' || $password === ''
        || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
        throw new RuntimeException('Invalid private MySQL configuration');
    }
    foreach ([$hostname, $username, $password] as $value) {
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new RuntimeException('Unsupported newline or NUL in MySQL credentials');
        }
    }
    // Read-only check: avoid accidentally dumping a different default schema.
    $connectedDatabase = (string)db()->query('SELECT DATABASE()')->fetchColumn();
    if ($connectedDatabase !== $database) {
        throw new RuntimeException('Configured database does not match connected database');
    }

    $privateRoot = realpath(PROJECT_ROOT);
    if (!is_string($privateRoot) || !is_dir($privateRoot)) {
        throw new RuntimeException('Private project directory not found');
    }
    // PROJECT_ROOT is ~/laundrytrack_private on Angsila; backup is a sibling,
    // NOT under ~/public_html. Follow neither pre-existing backup symlinks.
    $backupTop = dirname($privateRoot) . '/laundrytrack_backups';
    $directory = $backupTop . '/mysql';
    foreach ([$backupTop, $directory] as $path) {
        if (is_link($path)) throw new RuntimeException('Backup directory cannot be a symlink');
        if (!is_dir($path) && !mkdir($path, 0700)) {
            throw new RuntimeException('Cannot create private backup directory');
        }
        if (!chmod($path, 0700)) {
            throw new RuntimeException('Cannot restrict permissions on backup directory');
        }
    }
    if (!function_exists('proc_open')) {
        throw new RuntimeException('PHP proc_open is unavailable on this host');
    }

    // mysql option-file syntax supports double-quoted values; escape quotes
    // and backslashes to preserve passwords without using shell commands.
    $quoteOption = static fn(string $value): string =>
        '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';

    $optionFile = tempnam($directory, '.mysql-client-');
    $partialFile = tempnam($directory, '.incomplete-');
    if ($optionFile === false || $partialFile === false) {
        throw new RuntimeException('Cannot create temporary private backup files');
    }
    chmod($optionFile, 0600);
    chmod($partialFile, 0600);

    $mysqlOptions = "[client]\n"
        . 'host=' . $quoteOption($hostname) . "\n"
        . 'port=' . (int)$port . "\n"
        . 'user=' . $quoteOption($username) . "\n"
        . 'password=' . $quoteOption($password) . "\n"
        . "default-character-set=utf8mb4\n";
    if (file_put_contents($optionFile, $mysqlOptions, LOCK_EX) !== strlen($mysqlOptions)) {
        throw new RuntimeException('Cannot safely write temporary MySQL credentials');
    }

    $command = [
        'mysqldump',
        '--defaults-extra-file=' . $optionFile, // MUST be the first option.
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--no-tablespaces',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        $database,
    ];
    $spec = [
        0 => ['pipe', 'r'],
        1 => ['file', $partialFile, 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $process = @proc_open($command, $spec, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('mysqldump cannot start; check MySQL client installation');
    }
    fclose($pipes[0]);
    // Never display raw stderr: it may contain sensitive server information.
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    clearstatcache(true, $partialFile);
    if ($exitCode !== 0 || !is_file($partialFile) || filesize($partialFile) < 128) {
        throw new RuntimeException('mysqldump did not produce a complete backup (exit ' . $exitCode . ')');
    }

    $finalFile = $directory . '/laundrytrack_' . $database . '_'
        . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
    if (!rename($partialFile, $finalFile)) {
        throw new RuntimeException('Could not finalize backup file');
    }
    $partialFile = null;
    chmod($finalFile, 0600);

    $sha = hash_file('sha256', $finalFile);
    if ($sha === false) throw new RuntimeException('Could not hash database backup');
    $checksumPath = $finalFile . '.sha256';
    $checksumContent = $sha . '  ' . basename($finalFile) . "\n";
    if (file_put_contents($checksumPath, $checksumContent, LOCK_EX) !== strlen($checksumContent)) {
        throw new RuntimeException('Could not write checksum file');
    }
    chmod($checksumPath, 0600);

    $completed = true;
    fwrite(STDOUT, "BACKUP OK\n");
    fwrite(STDOUT, 'Database: ' . $database . "\n");
    fwrite(STDOUT, 'File: ' . $finalFile . "\n");
    fwrite(STDOUT, 'Bytes: ' . filesize($finalFile) . "\n");
    fwrite(STDOUT, 'SHA256: ' . $sha . "\n");
    fwrite(STDOUT, "Restore has NOT been performed. Do not share this SQL file publicly.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "BACKUP FAILED: " . $e->getMessage() . "\n");
} finally {
    if (is_string($optionFile) && is_file($optionFile)) @unlink($optionFile);
    if (is_string($partialFile) && is_file($partialFile)) @unlink($partialFile);
    if (!$completed) {
        if (is_string($checksumPath) && is_file($checksumPath)) @unlink($checksumPath);
        if (is_string($finalFile) && is_file($finalFile)) @unlink($finalFile);
    }
    umask($previousUmask);
}

if (!$completed) exit(1);
