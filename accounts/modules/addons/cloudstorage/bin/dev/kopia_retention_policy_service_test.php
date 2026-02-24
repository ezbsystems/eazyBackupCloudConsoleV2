<?php
declare(strict_types=1);

/**
 * Unit test for KopiaRetentionPolicyService.
 * TDD: validates policy structure (Comet-tier keys) and effective policy resolution
 * (active => override, retired => vault default).
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_policy_service_test.php
 * (from repo root or worktree root)
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionPolicyService.php';

use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;

$failures = [];

function assertEqual($expected, $actual, string $label, array &$failures): void
{
    if ($expected === $actual) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true);
}

function assertArraysEqual(array $expected, array $actual, string $label, array &$failures): void
{
    if ($expected === $actual) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected " . json_encode($expected) . ", got " . json_encode($actual);
}

echo "KopiaRetentionPolicyService tests\n";
echo str_repeat('-', 60) . "\n";

// --- validate() tests ---

$validPolicy = [
    'hourly' => 24,
    'daily' => 7,
    'weekly' => 4,
    'monthly' => 12,
    'yearly' => 3,
];
[$valid, $errors] = KopiaRetentionPolicyService::validate($validPolicy);
assertEqual(true, $valid, 'validate: valid policy returns true', $failures);
assertEqual([], $errors, 'validate: valid policy has no errors', $failures);

$invalidPolicy = [
    'hourly' => -1,
    'daily' => 7,
];
[$valid, $errors] = KopiaRetentionPolicyService::validate($invalidPolicy);
assertEqual(false, $valid, 'validate: negative hourly returns false', $failures);
if (count($errors) > 0) {
    echo "  OK: validate: negative hourly produces errors\n";
} else {
    $failures[] = "validate: negative hourly should produce errors";
}

$invalidPolicy2 = [
    'hourly' => 24,
    'daily' => 'seven',  // non-integer
];
[$valid, $errors] = KopiaRetentionPolicyService::validate($invalidPolicy2);
assertEqual(false, $valid, 'validate: non-integer daily returns false', $failures);

// --- resolveEffectivePolicy() tests ---

$vaultDefault = [
    'hourly' => 24,
    'daily' => 7,
    'weekly' => 4,
    'monthly' => 12,
    'yearly' => 3,
];
$jobOverride = [
    'hourly' => 48,
    'daily' => 14,
    'weekly' => 8,
    'monthly' => 24,
    'yearly' => 5,
];

// active + override => use override
$effective = KopiaRetentionPolicyService::resolveEffectivePolicy($jobOverride, $vaultDefault, 'active');
assertArraysEqual($jobOverride, $effective, 'resolveEffectivePolicy: active + override uses override', $failures);

// retired + override => fall back to vault default (override ignored)
$effective = KopiaRetentionPolicyService::resolveEffectivePolicy($jobOverride, $vaultDefault, 'retired');
assertArraysEqual($vaultDefault, $effective, 'resolveEffectivePolicy: retired + override falls back to vault default', $failures);

// active + null override => use vault default
$effective = KopiaRetentionPolicyService::resolveEffectivePolicy(null, $vaultDefault, 'active');
assertArraysEqual($vaultDefault, $effective, 'resolveEffectivePolicy: active + null override uses vault default', $failures);

// active + empty override => use vault default
$effective = KopiaRetentionPolicyService::resolveEffectivePolicy([], $vaultDefault, 'active');
assertArraysEqual($vaultDefault, $effective, 'resolveEffectivePolicy: active + empty override uses vault default', $failures);

echo str_repeat('-', 60) . "\n";

if (!empty($failures)) {
    foreach ($failures as $f) {
        echo "FAIL: {$f}\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
