<?php
/**
 * Lightweight WHMCS-free bootstrap for high-frequency agent API endpoints.
 *
 * Environment variables:
 *   CLOUDBACKUP_AGENT_FAST_BOOTSTRAP — 1/true to prefer this path (checked in agent_bootstrap.php)
 *   CLOUDBACKUP_REDIS_URL            — redis://[:password@]host:port[/db]
 *   CLOUDBACKUP_REDIS_LIVENESS_ENABLED — 1 to dual-write liveness keys
 *   CLOUDBACKUP_LIVENESS_REDIS_TTL   — Redis TTL seconds (default 180)
 *   AGENT_HEARTBEAT_DEBOUNCE_SECONDS — MySQL last_seen_at debounce override
 */

if (!defined('WHMCS')) {
    define('WHMCS', true);
}

$accountsRoot = dirname(__DIR__, 5);
$vendorAutoload = $accountsRoot . '/vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    require_once dirname(__DIR__, 2) . '/api/agent_bootstrap_fallback.php';
    return;
}

require_once $vendorAutoload;

$configFile = $accountsRoot . '/configuration.php';
if (!is_file($configFile)) {
    require_once dirname(__DIR__, 2) . '/api/agent_bootstrap_fallback.php';
    return;
}

require $configFile;

use Illuminate\Database\Capsule\Manager as CapsuleManager;

$capsule = new CapsuleManager();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $db_host ?? 'localhost',
    'database' => $db_name ?? '',
    'username' => $db_username ?? '',
    'password' => $db_password ?? '',
    'charset' => $mysql_charset ?? 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

if (!class_exists('WHMCS\\Database\\Capsule', false)) {
    class_alias(CapsuleManager::class, 'WHMCS\\Database\\Capsule');
}

if (!function_exists('logModuleCall')) {
    function logModuleCall(
        $module,
        $action,
        $requestData,
        $responseData,
        $processedData = '',
        $replaceVariables = []
    ): void {
        $line = sprintf(
            "[%s] cloudstorage.%s request=%s response=%s\n",
            date('c'),
            (string) $action,
            is_string($requestData) ? $requestData : json_encode($requestData),
            is_string($responseData) ? $responseData : json_encode($responseData)
        );
        $logDir = dirname(__DIR__, 5) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logDir . '/cloudstorage_agent_fast.log', $line, FILE_APPEND | LOCK_EX);
    }
}

if (!class_exists('CloudStorageFastSettingShim', false)) {
    class CloudStorageFastSettingShim
    {
        /** @var array<string,string> */
        private static $cache = [];

        public static function getValue(string $key): string
        {
            if (array_key_exists($key, self::$cache)) {
                return self::$cache[$key];
            }
            try {
                $val = CapsuleManager::table('tblconfiguration')
                    ->where('setting', $key)
                    ->value('value');
                self::$cache[$key] = (string) ($val ?? '');
            } catch (\Throwable $e) {
                self::$cache[$key] = '';
            }
            return self::$cache[$key];
        }
    }
}

if (!class_exists('WHMCS\\Config\\Setting', false)) {
    class_alias(CloudStorageFastSettingShim::class, 'WHMCS\\Config\\Setting');
}

$clientBase = dirname(__DIR__) . '/Client';
$adminBase = dirname(__DIR__) . '/Admin';

spl_autoload_register(static function (string $class) use ($clientBase, $adminBase): void {
    $map = [
        'WHMCS\\Module\\Addon\\CloudStorage\\Client\\' => $clientBase . '/',
        'WHMCS\\Module\\Addon\\CloudStorage\\Admin\\' => $adminBase . '/',
    ];
    foreach ($map as $prefix => $base) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = $base . $relative . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }
});

define('CLOUDBACKUP_AGENT_FAST_BOOTSTRAP_ACTIVE', true);
