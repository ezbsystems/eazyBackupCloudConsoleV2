<?php

declare(strict_types=1);

/**
 * Regression: ingress/egress charts must not double-count remigration first dumps
 * or multiply daily storage by sample count.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/transfer_cutover_dedup_test.php
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

$failures = [];

/**
 * Reproduce the first-dump inclusion bug with an isolated synthetic user.
 * Layout:
 *   - Legacy bucket (inactive) with real daily deltas before cutover
 *   - Current bucket A present from cutover day (first dump should be skipped)
 *   - Current bucket B first seen days AFTER cutover with a large cumulative
 *     first dump (the bug: previously included because first_seen != cutoverDate)
 */
$marker = 'xfer_cutover_dedup_' . substr(sha1((string) microtime(true)), 0, 10);

try {
    Capsule::connection()->beginTransaction();

    $userId = (int) Capsule::table('s3_users')->insertGetId([
        'name' => $marker,
        'username' => $marker . '_user',
        'parent_id' => null,
        'tenant_id' => null,
        'ceph_uid' => $marker,
        'is_active' => 1,
        'created_at' => '2026-01-01 00:00:00',
    ]);

    $legacyBucketId = (int) Capsule::table('s3_buckets')->insertGetId([
        'user_id' => $userId,
        'name' => $marker . '_legacy',
        's3_id' => $marker . '_legacy',
        'is_active' => 0,
        'created_at' => '2026-01-01 00:00:00',
    ]);
    $currentAId = (int) Capsule::table('s3_buckets')->insertGetId([
        'user_id' => $userId,
        'name' => $marker . '_current_a',
        's3_id' => $marker . '_current_a',
        'is_active' => 1,
        'created_at' => '2026-02-01 00:00:00',
    ]);
    $currentBId = (int) Capsule::table('s3_buckets')->insertGetId([
        'user_id' => $userId,
        'name' => $marker . '_current_b',
        's3_id' => $marker . '_current_b',
        'is_active' => 1,
        'created_at' => '2026-02-10 00:00:00',
    ]);

    $rows = [
        // Legacy deltas before cutover
        [$legacyBucketId, 100, 200, '2026-01-15 10:00:00'],
        [$legacyBucketId, 50, 25, '2026-01-20 10:00:00'],
        // Current A: cutover day first dump (lifetime-sized) + later real delta
        [$currentAId, 1000000, 2000000, '2026-02-01 12:00:00'], // first dump
        [$currentAId, 10, 20, '2026-02-02 12:00:00'],
        [$currentAId, 5, 5, '2026-02-03 12:00:00'],
        // Current B appears later with its own first dump (the doubling vector)
        [$currentBId, 500000, 800000, '2026-02-10 12:00:00'], // first dump
        [$currentBId, 7, 3, '2026-02-11 12:00:00'],
    ];

    foreach ($rows as [$bucketId, $sent, $recv, $at]) {
        Capsule::table('s3_transfer_stats_summary')->insert([
            'user_id' => $userId,
            'bucket_id' => $bucketId,
            'bytes_sent' => $sent,
            'bytes_received' => $recv,
            'ops' => 1,
            'successful_ops' => 1,
            'created_at' => $at,
        ]);
    }

    // Storage samples: two snapshots same day for one bucket — must not 2x MAX
    Capsule::table('s3_bucket_stats_summary')->insert([
        [
            'user_id' => $userId,
            'bucket_id' => $currentAId,
            'total_usage' => 1000,
            'created_at' => '2026-02-05 08:00:00',
        ],
        [
            'user_id' => $userId,
            'bucket_id' => $currentAId,
            'total_usage' => 1500,
            'created_at' => '2026-02-05 20:00:00',
        ],
        [
            'user_id' => $userId,
            'bucket_id' => $currentBId,
            'total_usage' => 400,
            'created_at' => '2026-02-05 09:00:00',
        ],
        [
            'user_id' => $userId,
            'bucket_id' => $currentBId,
            'total_usage' => 500,
            'created_at' => '2026-02-05 21:00:00',
        ],
    ]);

    $bc = new BucketController('http://example.test', 'admin', 'ak', 'sk', 'ca-central-1');

    $totals = $bc->getTotalUsageForBillingPeriod([$userId], '2026-01-01', '2026-02-28');
    // Expected:
    //   legacy: 100+50 sent, 200+25 recv
    //   current A after cutover (exclude first dump + cutover day): 10+5 sent, 20+5 recv
    //   current B (exclude first dump): 7 sent, 3 recv
    // Total sent = 100+50+10+5+7 = 172
    // Total recv = 200+25+20+5+3 = 253
    $expectedSent = 172;
    $expectedRecv = 253;

    if ((int) $totals['total_bytes_sent'] !== $expectedSent) {
        $failures[] = "transfer sent expected {$expectedSent}, got {$totals['total_bytes_sent']} (first-dump double-count?)";
    }
    if ((int) $totals['total_bytes_received'] !== $expectedRecv) {
        $failures[] = "transfer recv expected {$expectedRecv}, got {$totals['total_bytes_received']} (first-dump double-count?)";
    }

    $series = $bc->getTransferSummaryForRange([$userId], '2026-01-01', '2026-02-28');
    $seriesSent = 0;
    $seriesRecv = 0;
    foreach ($series as $row) {
        $seriesSent += (int) ($row['total_bytes_sent'] ?? 0);
        $seriesRecv += (int) ($row['total_bytes_received'] ?? 0);
    }
    if ($seriesSent !== $expectedSent) {
        $failures[] = "transfer series sent expected {$expectedSent}, got {$seriesSent}";
    }
    if ($seriesRecv !== $expectedRecv) {
        $failures[] = "transfer series recv expected {$expectedRecv}, got {$seriesRecv}";
    }

    $storage = $bc->getUserBucketSummary([$userId], '2026-02-05', '2026-02-05');
    $dayUsage = 0;
    foreach ($storage as $row) {
        if (($row['period'] ?? '') === '2026-02-05') {
            $dayUsage = (int) ($row['total_usage'] ?? 0);
        }
    }
    // Correct daily peak = MAX(A)=1500 + MAX(B)=500 = 2000, not 2*(1500+500)=4000
    if ($dayUsage !== 2000) {
        $failures[] = "storage daily usage expected 2000 (per-bucket MAX then SUM), got {$dayUsage}";
    }

    Capsule::connection()->rollBack();
} catch (Throwable $e) {
    try {
        Capsule::connection()->rollBack();
    } catch (Throwable $ignored) {
    }
    $failures[] = 'fixture/setup failed: ' . $e->getMessage();
}

if ($failures) {
    fwrite(STDERR, "FAIL transfer_cutover_dedup_test\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "OK transfer_cutover_dedup_test\n";
exit(0);
