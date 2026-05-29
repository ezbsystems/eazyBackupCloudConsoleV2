<?php
/**
 * e3 Cloud Backup hourly metering + rating cron.
 *
 * Recommended schedule:
 *   15 * * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/crons/e3_cloudbackup_billing.php >/dev/null 2>&1
 *
 * Runs two passes:
 *   1. Meter: write one snapshot row per metric per active service.
 *   2. Rate:  compute MAX(qty) in the current billing window, resolve pricing,
 *             and upsert rated_lines rows.
 *
 * Never writes to WHMCS invoice / tblhostingconfigoptions structures - that's
 * Phase 3's job (applyToWhmcs + the InvoiceCreationPreEmail hook).
 */

require __DIR__ . '/../init.php';

require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Provision/E3CloudBackupProductBootstrap.php';
require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Admin/E3CloudBackupPricing.php';
require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Admin/E3CloudBackupBilling.php';

use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling;

$startedAt = microtime(true);

$meter = E3CloudBackupBilling::meterAll();
$rate  = E3CloudBackupBilling::rateAll();

$elapsed = round(microtime(true) - $startedAt, 3);

try {
    logModuleCall('cloudstorage', 'e3cb_billing_cron', [
        'elapsed_seconds' => $elapsed,
    ], [
        'meter' => $meter,
        'rate'  => $rate,
    ], [], []);
} catch (\Throwable $_) {
}

if (php_sapi_name() === 'cli' && getenv('CLOUDSTORAGE_VERBOSE') === '1') {
    echo "e3cb billing cron: meter=" . json_encode($meter)
        . " rate=" . json_encode($rate)
        . " elapsed=" . $elapsed . "s\n";
}
