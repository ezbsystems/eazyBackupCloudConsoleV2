<?php
declare(strict_types=1);

/**
 * Shared bootstrap for eazyBackup addon CLI scripts.
 * - Finds Composer autoload
 * - Loads /var/www/eazybackup.ca/.env
 * - Exposes cfg() and db()
 * - Simple logger
 */

////////////////////////
// Composer autoload //
////////////////////////
$autoloads = [
    __DIR__ . '/../vendor/autoload.php',          // addon-local vendor (preferred)
    dirname(__DIR__, 3) . '/vendor/autoload.php', // WHMCS root vendor (fallback)
];
$found = false;
foreach ($autoloads as $a) { if (is_file($a)) { require $a; $found = true; break; } }
if (!$found) {
    fwrite(STDERR, "[bootstrap] Composer autoload not found. Run composer in the addon dir.\n");
    exit(1);
}

/////////////////
// .env loader //
/////////////////
function loadEnv(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv("$k=$v"); $_ENV[$k] = $v; $_SERVER[$k] = $v;
    }
}
function cfg(string $key, string $default = ''): string {
    $v = getenv($key);
    return ($v === false) ? $default : $v;
}

/////////////////
// DB helper   //
/////////////////
function db(): PDO {
    $dsn  = cfg('DB_DSN',  'mysql:host=127.0.0.1;dbname=whmcs;charset=utf8mb4');
    $user = cfg('DB_USER', 'whmcs');
    $pass = cfg('DB_PASS', '');
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

/////////////////
// Logger      //
/////////////////
function logLine(string $msg): void {
    fwrite(STDOUT, $msg . PHP_EOL);
}

// Load env (adjust path if you ever move it)
loadEnv('/var/www/eazybackup.ca/.env');
