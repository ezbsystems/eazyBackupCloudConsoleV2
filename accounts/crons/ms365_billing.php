<?php
/**
 * MS365 Backup daily metering + rating cron.
 *
 * Recommended schedule:
 *   0 6 * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/crons/ms365_billing.php >/dev/null 2>&1
 */

require __DIR__ . '/../init.php';

require_once __DIR__ . '/../modules/addons/ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365BillingService;

$startedAt = microtime(true);

$meter = Ms365BillingService::meterAll();
$rate = Ms365BillingService::rateAll();
$apply = ['services' => 0, 'updated' => 0, 'errors' => 0];

$pid = \Ms365Backup\Ms365BillingConfig::getPid();
if ($pid > 0) {
    try {
        $svcIds = \WHMCS\Database\Capsule::table('tblhosting')
            ->where('packageid', $pid)
            ->whereIn('domainstatus', ['Active', 'Suspended'])
            ->pluck('id');
        foreach ($svcIds as $sid) {
            $apply['services']++;
            try {
                $apply['updated'] += Ms365BillingService::applyToWhmcs((int) $sid);
            } catch (\Throwable $e) {
                $apply['errors']++;
            }
        }
    } catch (\Throwable $_) {
    }
}

$elapsed = round(microtime(true) - $startedAt, 3);

try {
    logModuleCall('ms365backup', 'ms365_billing_cron', ['elapsed_seconds' => $elapsed], [
        'meter' => $meter,
        'rate' => $rate,
        'apply' => $apply,
    ], [], []);
} catch (\Throwable $_) {
}

if (php_sapi_name() === 'cli' && getenv('MS365_VERBOSE') === '1') {
    echo 'ms365 billing cron: meter=' . json_encode($meter)
        . ' rate=' . json_encode($rate)
        . ' apply=' . json_encode($apply)
        . ' elapsed=' . $elapsed . "s\n";
}
