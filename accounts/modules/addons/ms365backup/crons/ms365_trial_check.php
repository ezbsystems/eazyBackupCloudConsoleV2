<?php
/**
 * MS365 Backup trial lifecycle cron.
 *
 * Recommended schedule:
 *   30 3 * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_trial_check.php >/dev/null 2>&1
 */

require dirname(__DIR__, 4) . '/init.php';

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Ms365BillingTrial;

$startedAt = microtime(true);
$result = Ms365BillingTrial::evaluateAll();
$elapsed = round(microtime(true) - $startedAt, 3);

try {
    logModuleCall('ms365backup', 'ms365_trial_cron', ['elapsed_seconds' => $elapsed], $result, [], []);
} catch (\Throwable $_) {
}

if (php_sapi_name() === 'cli' && getenv('MS365_VERBOSE') === '1') {
    echo 'ms365 trial cron: ' . json_encode($result) . ' elapsed=' . $elapsed . "s\n";
}
