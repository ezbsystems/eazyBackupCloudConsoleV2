<?php
declare(strict_types=1);

/**
 * Shared bootstrap for ms365backup CLI scripts.
 */

$autoloads = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];
foreach ($autoloads as $a) {
    if (is_file($a)) {
        require $a;
        break;
    }
}

require_once __DIR__ . '/../ms365backup_autoload.php';

function ms365_load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

function ms365_cfg(string $key, string $default = ''): string
{
    $v = getenv($key);
    return ($v === false) ? $default : $v;
}

function ms365_db(): PDO
{
    $dsn = ms365_cfg('DB_DSN', 'mysql:host=127.0.0.1;dbname=whmcs;charset=utf8mb4');
    $user = ms365_cfg('DB_USER', 'whmcs');
    $pass = ms365_cfg('DB_PASS', '');
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function ms365_log_line(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

ms365_load_env('/var/www/eazybackup.ca/.env');
