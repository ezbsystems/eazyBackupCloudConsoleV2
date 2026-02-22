<?php

declare(strict_types=1);

/**
 * Dev test: AnnualEntitlementConfig behavior (manual-assist guardrail).
 * Run: php bin/dev/annual_entitlement_config_test.php
 */

require __DIR__ . '/../bootstrap.php';

use EazyBackup\Billing\AnnualEntitlementConfig;

$ok = true;

// mode() returns "manual"
$mode = AnnualEntitlementConfig::mode();
if ($mode !== 'manual') {
    echo "FAIL: mode() expected 'manual', got " . var_export($mode, true) . "\n";
    $ok = false;
}

// billableConfigIds() returns [67, 88, 89, 91, 60, 97, 99, 102]
$expected = [67, 88, 89, 91, 60, 97, 99, 102];
$ids = AnnualEntitlementConfig::billableConfigIds();
if ($ids !== $expected) {
    echo "FAIL: billableConfigIds() expected " . json_encode($expected) . ", got " . json_encode($ids) . "\n";
    $ok = false;
}

if ($ok) {
    echo "PASS\n";
    exit(0);
}

exit(1);
