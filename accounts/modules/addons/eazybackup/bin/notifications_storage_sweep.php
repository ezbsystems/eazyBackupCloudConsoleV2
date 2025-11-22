<?php
declare(strict_types=1);

// Cron entry point: daily storage sweep across active users

$autoloads = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];
foreach ($autoloads as $a) { if (is_file($a)) { require $a; break; } }

$whmcsInit = dirname(__DIR__, 4) . '/init.php';
if (is_file($whmcsInit)) { require_once $whmcsInit; }

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../lib/Notifications/bootstrap.php';

function eb_sweep_db(): PDO {
    // 1) Prefer WHMCS Capsule PDO when init.php is loaded
    try {
        if (class_exists('WHMCS\\Database\\Capsule')) {
            $pdo = \WHMCS\Database\Capsule::connection()->getPdo();
            if ($pdo instanceof PDO) {
                $pdo->exec("SET time_zone = '+00:00'");
                return $pdo;
            }
        }
    } catch (Throwable $e) { /* ignore and continue */ }

    // 2) Try WHMCS configuration.php for DB credentials
    try {
        $cfg = dirname(__DIR__, 4) . '/configuration.php';
        if (is_file($cfg)) {
            $vars = (static function($file) {
                $db_host = $db_username = $db_password = $db_name = null;
                require $file;
                return compact('db_host','db_username','db_password','db_name');
            })($cfg);
            if (!empty($vars['db_host']) && !empty($vars['db_name'])) {
                $dsn = 'mysql:host=' . $vars['db_host'] . ';dbname=' . $vars['db_name'] . ';charset=utf8mb4';
                $user = (string)($vars['db_username'] ?? '');
                $pass = (string)($vars['db_password'] ?? '');
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec("SET time_zone = '+00:00'");
                return $pdo;
            }
        }
    } catch (Throwable $e) { /* ignore and continue */ }

    // 3) Fallback to environment variables
    $dsn = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=whmcs;charset=utf8mb4';
    $user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'whmcs');
    $pass = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: '');
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

try {
    $pdo = eb_sweep_db();
    // Basic CLI flags: --user=<username> to target one user, --debug to enable verbose logs
    $argv = isset($argv) ? $argv : ($_SERVER['argv'] ?? []);
    $args = [];
    foreach ($argv as $a) { if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) { $args[$m[1]] = $m[2]; } elseif ($a === '--debug') { $args['debug'] = '1'; } }
    if (!empty($args['debug'])) { putenv('EB_NOTIFY_DEBUG=1'); }

    if (!empty($args['user'])) {
        $user = (string)$args['user'];
        echo "[notifications_storage_sweep] Scanning single user {$user}\n";
        eb_notifications_service()->scanStorageForUser($pdo, $user);
        echo "[notifications_storage_sweep] Completed for 1 user\n";
        exit(0);
    }

    $rows = Capsule::table('tblhosting')
        ->select('username')
        ->where('domainstatus','Active')
        ->whereIn('packageid', [52,57,53,54,58,60])
        ->whereNotNull('username')
        ->pluck('username');

    foreach ($rows as $un) {
        try { eb_notifications_service()->scanStorageForUser($pdo, (string)$un); } catch (\Throwable $e) { /* ignore */ }
    }
    echo "[notifications_storage_sweep] Completed for " . count($rows) . " users\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "[notifications_storage_sweep] ERROR: " . $e->getMessage() . "\n");
    exit(1);
}


