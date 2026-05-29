<?php
/**
 * Dry-run preview of what the e3 Cloud Backup rater would bill a given client.
 *
 * Usage:
 *   php /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/bin/dev/e3cb_billing_dry_run.php <client_id> [--json]
 *
 * Read-only - does not write to any table. Safe to run on production for
 * validating per-client pricing overrides before saving them.
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

$argv = $_SERVER['argv'] ?? [];
if (count($argv) < 2) {
    fwrite(STDERR, "Usage: php " . basename(__FILE__) . " <client_id> [--json]\n");
    exit(2);
}
$clientId = (int) $argv[1];
if ($clientId <= 0) {
    fwrite(STDERR, "Invalid client_id\n");
    exit(2);
}
$json = in_array('--json', $argv, true);

require __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../lib/Provision/E3CloudBackupProductBootstrap.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupPricing.php';
require_once __DIR__ . '/../../lib/Admin/E3CloudBackupBilling.php';

use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupBilling;

$preview = E3CloudBackupBilling::dryRun($clientId);

if ($json) {
    echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "===== e3 Cloud Backup - Dry Run for client #{$clientId} =====\n";
echo "Service ID:   " . ($preview['service_id'] ?: '(none)') . "\n";
echo "Currency ID:  {$preview['currency_id']}\n";
echo "Trial status: " . ($preview['trial_status'] ?? '(no trial state)') . "\n";
echo "Billing window: {$preview['window']['start']} -> {$preview['window']['end']}\n";
echo "\n";

printf("%-18s %6s %12s %14s %-22s %s\n", "Metric", "Qty", "Unit Price", "Line Amount", "Source", "Tier");
echo str_repeat('-', 95) . "\n";
foreach ($preview['lines'] as $line) {
    printf(
        "%-18s %6d %12.4f %14.2f %-22s %s\n",
        $line['metric_label'],
        $line['qty'],
        $line['unit_price'],
        $line['line_amount'],
        $line['source'],
        $line['tier_label'] ?? ''
    );
}
echo str_repeat('-', 95) . "\n";
printf("%-50s %14.2f\n", "TOTAL (billable, after trial zeroing):", $preview['total_billable']);
printf("%-50s %14.2f\n", "TOTAL (what it would be at paid status):", $preview['total_if_paid']);
echo "\n";
