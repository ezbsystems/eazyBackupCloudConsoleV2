<?php
/**
 * Shared bootstrap for agent hot-path API endpoints.
 * Applies rate limiting, then fast-path or full WHMCS init.
 */

require_once __DIR__ . '/../Client/AgentRateLimiter.php';
AgentRateLimiter::enforceOrExit();

/**
 * Whether the lightweight fast bootstrap should be used.
 */
function cloudstorage_agent_fast_bootstrap_enabled(): bool
{
    $env = getenv('CLOUDBACKUP_AGENT_FAST_BOOTSTRAP');
    if ($env !== false && $env !== '') {
        return !in_array(strtolower(trim((string) $env)), ['0', 'false', 'off', 'no'], true);
    }

    $devEnv = getenv('CLOUDBACKUP_DEV');
    if ($devEnv !== false && $devEnv !== '' && !in_array(strtolower(trim((string) $devEnv)), ['0', 'false', 'off', 'no'], true)) {
        return true;
    }

    static $moduleSetting = null;
    if ($moduleSetting === null) {
        $moduleSetting = cloudstorage_read_agent_module_flag('cloudbackup_agent_fast_bootstrap', false);
    }

    return $moduleSetting;
}

/**
 * @param mixed $default
 * @return mixed
 */
function cloudstorage_read_agent_module_flag(string $setting, $default)
{
    $accountsRoot = dirname(__DIR__, 5);
    $configFile = $accountsRoot . '/configuration.php';
    $vendorAutoload = $accountsRoot . '/vendor/autoload.php';
    if (!is_file($configFile) || !is_file($vendorAutoload)) {
        return $default;
    }

    try {
        require_once $vendorAutoload;
        require $configFile;

        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', $db_host ?? 'localhost', $db_name ?? '', $mysql_charset ?? 'utf8'),
            $db_username ?? '',
            $db_password ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $stmt = $pdo->prepare(
            'SELECT value FROM tbladdonmodules WHERE module = ? AND setting = ? LIMIT 1'
        );
        $stmt->execute(['cloudstorage', $setting]);
        $row = $stmt->fetch();
        if ($row === false || !isset($row['value']) || $row['value'] === '') {
            return $default;
        }
        $val = strtolower(trim((string) $row['value']));
        return in_array($val, ['1', 'on', 'yes', 'true'], true);
    } catch (\Throwable $e) {
        return $default;
    }
}

if (cloudstorage_agent_fast_bootstrap_enabled()) {
    require_once __DIR__ . '/agent_fast_init.php';
    if (!defined('CLOUDBACKUP_AGENT_FAST_BOOTSTRAP_ACTIVE')) {
        require_once dirname(__DIR__, 2) . '/api/agent_bootstrap_fallback.php';
    }
} else {
    require_once dirname(__DIR__, 2) . '/api/agent_bootstrap_fallback.php';
}
