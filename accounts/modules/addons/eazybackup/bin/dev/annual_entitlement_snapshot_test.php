<?php

declare(strict_types=1);

/**
 * Dev test: AnnualEntitlementSnapshotService::buildLedgerRow() behavior.
 * Run: php accounts/modules/addons/eazybackup/bin/dev/annual_entitlement_snapshot_test.php
 */

require __DIR__ . '/../bootstrap.php';

use EazyBackup\Billing\AnnualEntitlementSnapshotService;

$service = new AnnualEntitlementSnapshotService();
$ok = true;

// 1. PRORATION_REQUIRED case (plan): usage_qty=2 > max_paid_qty=1
$in = [
    'service_id' => 1001,
    'client_id' => 22,
    'username' => 'alice',
    'config_id' => 67,
    'usage_qty' => 2,
    'config_qty' => 1,
    'max_paid_qty' => 1,
    'next_due' => '2026-12-15',
];
$got = $service->buildLedgerRow($in);
if ($got['status'] !== 'PRORATION_REQUIRED') {
    echo "FAIL: PRORATION case expected status=PRORATION_REQUIRED, got " . ($got['status'] ?? 'null') . "\n";
    $ok = false;
}
if (($got['recommended_delta'] ?? null) !== 1) {
    echo "FAIL: PRORATION case expected recommended_delta=1, got " . json_encode($got['recommended_delta'] ?? null) . "\n";
    $ok = false;
}
if (($got['recommended_max_paid_qty'] ?? null) !== 2) {
    echo "FAIL: PRORATION case expected recommended_max_paid_qty=2, got " . json_encode($got['recommended_max_paid_qty'] ?? null) . "\n";
    $ok = false;
}
if (($got['cycle_start'] ?? '') !== '2025-12-16' || ($got['cycle_end'] ?? '') !== '2026-12-15') {
    echo "FAIL: PRORATION case expected cycle_start=2025-12-16 cycle_end=2026-12-15\n";
    $ok = false;
}

// 2. WITHIN_ENTITLEMENT case: usage_qty=1 < max_paid_qty=3
$in2 = [
    'service_id' => 1002,
    'usage_qty' => 1,
    'config_qty' => 2,
    'max_paid_qty' => 3,
    'next_due' => '2026-06-01',
];
$got2 = $service->buildLedgerRow($in2);
if ($got2['status'] !== 'WITHIN_ENTITLEMENT') {
    echo "FAIL: WITHIN_ENTITLEMENT case expected status=WITHIN_ENTITLEMENT, got " . ($got2['status'] ?? 'null') . "\n";
    $ok = false;
}
if (($got2['recommended_delta'] ?? null) !== 0) {
    echo "FAIL: WITHIN_ENTITLEMENT case expected recommended_delta=0, got " . json_encode($got2['recommended_delta'] ?? null) . "\n";
    $ok = false;
}
if (($got2['recommended_max_paid_qty'] ?? null) !== 3) {
    echo "FAIL: WITHIN_ENTITLEMENT case expected recommended_max_paid_qty=3, got " . json_encode($got2['recommended_max_paid_qty'] ?? null) . "\n";
    $ok = false;
}

// 3. Boundary: usage_qty=max_paid_qty -> WITHIN_ENTITLEMENT, delta=0
$in3 = [
    'usage_qty' => 5,
    'config_qty' => 5,
    'max_paid_qty' => 5,
    'next_due' => '2026-01-01',
];
$got3 = $service->buildLedgerRow($in3);
if ($got3['status'] !== 'WITHIN_ENTITLEMENT') {
    echo "FAIL: boundary case expected status=WITHIN_ENTITLEMENT, got " . ($got3['status'] ?? 'null') . "\n";
    $ok = false;
}
if (($got3['recommended_delta'] ?? null) !== 0) {
    echo "FAIL: boundary case expected recommended_delta=0, got " . json_encode($got3['recommended_delta'] ?? null) . "\n";
    $ok = false;
}
if (($got3['recommended_max_paid_qty'] ?? null) !== 5) {
    echo "FAIL: boundary case expected recommended_max_paid_qty=5, got " . json_encode($got3['recommended_max_paid_qty'] ?? null) . "\n";
    $ok = false;
}

// 4. Invalid input: missing next_due -> expects InvalidArgumentException
try {
    $service->buildLedgerRow(['service_id' => 1, 'usage_qty' => 1]);
    echo "FAIL: missing next_due expected InvalidArgumentException, got no exception\n";
    $ok = false;
} catch (\InvalidArgumentException $e) {
    // expected
}

// 5. Invalid input: next_due not a string
try {
    $service->buildLedgerRow(['next_due' => 20261215]);
    echo "FAIL: non-string next_due expected InvalidArgumentException, got no exception\n";
    $ok = false;
} catch (\InvalidArgumentException $e) {
    // expected
}

if ($ok) {
    echo "PASS\n";
    exit(0);
}

exit(1);
