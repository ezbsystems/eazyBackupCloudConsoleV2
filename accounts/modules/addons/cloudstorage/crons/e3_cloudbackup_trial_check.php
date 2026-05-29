<?php
/**
 * e3 Cloud Backup daily trial-check cron.
 *
 * Recommended schedule:
 *   30 3 * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/e3_cloudbackup_trial_check.php >/dev/null 2>&1
 *
 * For each row in s3_cloudbackup_trial_state, runs the state machine in
 * E3CloudBackupTrial::evaluateAll(). Idempotent. Safe to invoke ad-hoc.
 */

require __DIR__ . '/../../../../init.php';

require_once __DIR__ . '/../lib/Provision/E3CloudBackupProductBootstrap.php';
require_once __DIR__ . '/../lib/Admin/E3CloudBackupTrial.php';

use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupTrial;

$startedAt = microtime(true);
$result = E3CloudBackupTrial::evaluateAll();
$elapsed = round(microtime(true) - $startedAt, 3);

try {
    logModuleCall('cloudstorage', 'e3cb_trial_check_cron', [
        'elapsed_seconds' => $elapsed,
    ], $result, [], []);
} catch (\Throwable $_) {
}

if (php_sapi_name() === 'cli' && getenv('CLOUDSTORAGE_VERBOSE') === '1') {
    echo "e3cb trial check: " . json_encode($result) . " elapsed=" . $elapsed . "s\n";
}
