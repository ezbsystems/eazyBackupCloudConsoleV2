<?php

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

// Read the configured Cloud Storage product ID from addon settings. Falls back to
// the historical hardcoded value (48) if the setting is empty so an unconfigured
// install does not silently stop billing.
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
    $packageId = 48; // legacy fallback
}

$s3billingObject = new S3Billing();
$result = $s3billingObject->gatherBillingData($packageId);
