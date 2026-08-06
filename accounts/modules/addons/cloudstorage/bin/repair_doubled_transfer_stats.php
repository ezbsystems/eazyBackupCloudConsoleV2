<?php

/**
 * One-shot repair for transfer stats doubled by the old cluster-wide RGW usage dump.
 *
 * Usage:
 *   php accounts/modules/addons/cloudstorage/bin/repair_doubled_transfer_stats.php
 *   php accounts/modules/addons/cloudstorage/bin/repair_doubled_transfer_stats.php --dry-run
 */

require dirname(__DIR__, 4) . '/init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$module = Capsule::table('tbladdonmodules')->where('module', 'cloudstorage')->get();
$endpoint = (string) $module->where('setting', 's3_endpoint')->pluck('value')->first();
$accessKey = (string) $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$secretKey = (string) $module->where('setting', 'ceph_secret_key')->pluck('value')->first();

if ($endpoint === '' || $accessKey === '' || $secretKey === '') {
    fwrite(STDERR, "Missing cloudstorage S3/Ceph admin settings\n");
    exit(1);
}

$billing = new S3Billing();

if ($dryRun) {
    // Reuse detection only: call repair path via reflection of checks without writes.
    // For dry-run we still invoke repairDoubledTransferStats is unsafe; instead scan.
    require_once dirname(__DIR__) . '/lib/Admin/AdminOps.php';
    require_once dirname(__DIR__) . '/lib/Client/HelperController.php';

    $latest = Capsule::select("
        SELECT t.bucket_id, t.user_id, t.bytes_sent, t.bytes_received,
               b.name AS bucket_name, u.username, u.tenant_id, u.ceph_uid
        FROM s3_transfer_stats t
        INNER JOIN (
            SELECT bucket_id, MAX(id) AS id FROM s3_transfer_stats GROUP BY bucket_id
        ) latest ON latest.id = t.id
        INNER JOIN s3_buckets b ON b.id = t.bucket_id
        INNER JOIN s3_users u ON u.id = t.user_id
    ");

    $wouldRepair = [];
    foreach ($latest as $row) {
        $base = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::stripCephTenantPrefix(
            \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephBaseUid($row)
        );
        if ($base === '') {
            continue;
        }
        $tenantId = trim((string) ($row->tenant_id ?? ''));
        $uid = $tenantId !== '' ? ($tenantId . '$' . $base) : $base;
        $data = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::getUsage($endpoint, $accessKey, $secretKey, [
            'uid' => $uid,
            'show_entries' => true,
        ]);
        if (($data['status'] ?? '') !== 'success') {
            continue;
        }
        $liveSent = 0;
        $liveReceived = 0;
        foreach (($data['data']['entries'] ?? []) as $entry) {
            foreach (($entry['buckets'] ?? []) as $bucketData) {
                if ((string) ($bucketData['bucket'] ?? '') !== (string) $row->bucket_name) {
                    continue;
                }
                foreach (($bucketData['categories'] ?? []) as $category) {
                    $liveSent += (int) ($category['bytes_sent'] ?? 0);
                    $liveReceived += (int) ($category['bytes_received'] ?? 0);
                }
            }
        }
        $sentDoubled = S3Billing::isApproximatelyDoubled((int) $row->bytes_sent, $liveSent);
        $recvDoubled = S3Billing::isApproximatelyDoubled((int) $row->bytes_received, $liveReceived);
        if ($sentDoubled || $recvDoubled) {
            $wouldRepair[] = [
                'bucket_id' => (int) $row->bucket_id,
                'bucket' => (string) $row->bucket_name,
                'db_sent' => (int) $row->bytes_sent,
                'live_sent' => $liveSent,
                'db_received' => (int) $row->bytes_received,
                'live_received' => $liveReceived,
            ];
        }
    }

    echo json_encode([
        'dry_run' => true,
        'checked' => count($latest),
        'would_repair' => count($wouldRepair),
        'buckets' => $wouldRepair,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$result = $billing->repairDoubledTransferStats($endpoint, $accessKey, $secretKey);
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit(0);
