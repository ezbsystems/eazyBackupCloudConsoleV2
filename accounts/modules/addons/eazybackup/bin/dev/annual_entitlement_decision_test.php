<?php

declare(strict_types=1);

/**
 * Dev test: AnnualEntitlementDecision evaluate() behavior.
 * Run: php bin/dev/annual_entitlement_decision_test.php
 */

require __DIR__ . '/../bootstrap.php';

use EazyBackup\Billing\AnnualEntitlementDecision;

$engine = new AnnualEntitlementDecision();
$ok = true;

$cases = [
    // Original required cases
    [2, 1, 1, 'PRORATION_REQUIRED', 1],
    [1, 2, 2, 'WITHIN_ENTITLEMENT', 0],
    [2, 1, 2, 'WITHIN_ENTITLEMENT', 0],
    // Boundary and zero cases
    [0, 0, 0, 'WITHIN_ENTITLEMENT', 0],
    [0, 1, 1, 'WITHIN_ENTITLEMENT', 0],
    [1, 0, 0, 'PRORATION_REQUIRED', 1],
    [1, 1, 1, 'WITHIN_ENTITLEMENT', 0],
    [0, 0, 1, 'WITHIN_ENTITLEMENT', 0],
    // Negative inputs (clamped to 0)
    [-1, 0, 0, 'WITHIN_ENTITLEMENT', 0],
    [0, -1, 0, 'WITHIN_ENTITLEMENT', 0],
];

foreach ($cases as [$usage, $config, $max, $wantStatus, $wantDelta]) {
    $got = $engine->evaluate($usage, $config, $max);
    if ($got['status'] !== $wantStatus || $got['delta_to_charge'] !== $wantDelta) {
        echo "FAIL: evaluate($usage,$config,$max) expected status=$wantStatus delta=$wantDelta, got " . json_encode($got) . "\n";
        $ok = false;
    }
}

if ($ok) {
    echo "PASS\n";
    exit(0);
}

exit(1);
