<?php

/**
 * One-time (safe to re-run) reconcile: run a full S3 billing pass so live-zero-usage
 * cloud storage services drop to $0 under usage-gated base-fee pricing.
 *
 * Usage: php accounts/crons/s3billing_reconcile_zero_usage.php
 */

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

$packageId = 0;
try {
    $configured = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'pid_cloud_storage')
        ->value('value');
    $packageId = (int) $configured;
} catch (\Throwable $e) {
    $packageId = 0;
}
if ($packageId <= 0) {
    $packageId = 48;
}

echo "s3billing_reconcile_zero_usage: starting billing pass for pid={$packageId}\n";

$s3billingObject = new S3Billing();
$result = $s3billingObject->gatherBillingData($packageId);

$count = is_array($result['updateResults'] ?? null) ? count($result['updateResults']) : 0;
echo "s3billing_reconcile_zero_usage: completed ({$count} service update(s))\n";
