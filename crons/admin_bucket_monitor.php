<?php
/**
 * Bucket Size History Collection Cron
 * 
 * Collects bucket sizes from Ceph RadosGW and stores them for historical tracking
 * 
 * Schedule: Every 30 minutes (0,30 * * * *)
 * 
 * This is independent from the s3Billing.php cron to avoid interference
 */

require_once __DIR__ . '/../init.php';

use WHMCS\Module\Addon\CloudStorage\Admin\BucketSizeMonitor;
use WHMCS\Database\Capsule;

try {
    // Collect bucket sizes (multi-cluster aware)
    $result = BucketSizeMonitor::collectAllBucketSizes();

    // Log result
    $message = '[Bucket Size History Cron] ' . $result['message'];
    error_log($message);

    if ($result['status'] === 'success') {
        // Additional success logging
        error_log("[Bucket Size History Cron] Collection details - Total buckets: {$result['total_buckets']}, Total users: {$result['total_users']}");
    } else {
        // Log failure
        error_log("[Bucket Size History Cron] FAILED: {$result['message']}");
        exit(1);
    }

} catch (Exception $e) {
    $message = '[Bucket Size History Cron] Exception: ' . $e->getMessage();
    error_log($message);
    exit(1);
}