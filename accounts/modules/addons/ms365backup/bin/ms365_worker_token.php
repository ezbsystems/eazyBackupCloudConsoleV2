<?php
declare(strict_types=1);

/**
 * Print the MS365 worker API token (for ops / Proxmox template setup).
 *
 * Usage: php modules/addons/ms365backup/bin/ms365_worker_token.php
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365EngineConfig;

$token = Ms365EngineConfig::workerToken();
if ($token === '') {
    fwrite(STDERR, "ms365_worker_token is not configured in ms365backup addon settings.\n");
    exit(1);
}

echo $token . PHP_EOL;
exit(0);
