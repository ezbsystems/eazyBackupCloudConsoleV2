<?php

declare(strict_types=1);

/**
 * Contract tests for doubled transfer-stat detection used by the RGW usage repair.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/s3billing_transfer_double_repair_test.php
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/init.php';

use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

$failures = [];

if (!S3Billing::isApproximatelyDoubled(200, 100)) {
    $failures[] = 'exact 2x should detect as doubled';
}
if (!S3Billing::isApproximatelyDoubled(205, 100)) {
    $failures[] = '2.05x within default tolerance should detect';
}
if (S3Billing::isApproximatelyDoubled(150, 100)) {
    $failures[] = '1.5x should not detect as doubled';
}
if (S3Billing::isApproximatelyDoubled(100, 100)) {
    $failures[] = '1x should not detect as doubled';
}
if (S3Billing::isApproximatelyDoubled(0, 100)) {
    $failures[] = 'zero stored should not detect';
}
if (S3Billing::isApproximatelyDoubled(200, 0)) {
    $failures[] = 'zero live should not detect';
}

// Production evidence for client 2744 / rvmbackup1-cluster2
$dbSent = 28856003604916;
$liveSent = 14428001802458;
$dbRecv = 11733673676132;
$liveRecv = 5866836838066;
if (!S3Billing::isApproximatelyDoubled($dbSent, $liveSent)) {
    $failures[] = 'prod egress sample should detect as doubled';
}
if (!S3Billing::isApproximatelyDoubled($dbRecv, $liveRecv)) {
    $failures[] = 'prod ingress sample should detect as doubled';
}

$src = file_get_contents(dirname(__DIR__) . '/lib/Admin/S3Billing.php');
if (strpos($src, "'uid' => \$uid") === false) {
    $failures[] = 'exportBucketUsage must query usage per uid';
}
// Guard against regressing to a cluster-wide dump with no uid loop.
if (preg_match("/getUsage\\(\\$s3Endpoint,[^\\)]*\\$params\\)/", $src)
    && strpos($src, "foreach (\$users as \$user)") === false
) {
    $failures[] = 'exportBucketUsage must not use cluster-wide usage dump without per-uid loop';
}

if ($failures) {
    fwrite(STDERR, "FAIL s3billing_transfer_double_repair_test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "OK s3billing_transfer_double_repair_test\n";
exit(0);
