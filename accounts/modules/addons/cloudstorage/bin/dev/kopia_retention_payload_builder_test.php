<?php
declare(strict_types=1);

/**
 * Unit test for KopiaRetentionPayloadBuilder.
 * TDD: validates payload structure (repo_id, operation_id, operation_token, effective_policy).
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_payload_builder_test.php
 * (from repo root or worktree root)
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionPayloadBuilder.php';

use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPayloadBuilder;

$failures = [];

function assertEqual($expected, $actual, string $label, array &$failures): void
{
    if ($expected === $actual) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true);
}

function assertKeyEquals($expected, array $arr, string $key, string $label, array &$failures): void
{
    if (!array_key_exists($key, $arr)) {
        $failures[] = "{$label}: key '{$key}' missing in payload";
        return;
    }
    if ($arr[$key] === $expected) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected {$key}=" . var_export($expected, true) . ", got " . var_export($arr[$key], true);
}

echo "KopiaRetentionPayloadBuilder tests\n";
echo str_repeat('-', 60) . "\n";

$repoId = 1001;
$operationId = 42;
$operationToken = 'op-token-' . uniqid('', true);
$effectivePolicy = [
    'hourly' => 24,
    'daily' => 30,
    'weekly' => 8,
    'monthly' => 12,
    'yearly' => 3,
];

$payload = KopiaRetentionPayloadBuilder::build($repoId, $operationId, $operationToken, $effectivePolicy);

assertEqual(true, is_array($payload), 'build returns array', $failures);
assertKeyEquals($repoId, $payload, 'repo_id', 'repo_id present and correct', $failures);
assertKeyEquals($operationId, $payload, 'operation_id', 'operation_id present and correct', $failures);
assertKeyEquals($operationToken, $payload, 'operation_token', 'operation_token present and correct', $failures);
assertEqual(true, isset($payload['effective_policy']), 'effective_policy key present', $failures);
assertEqual($effectivePolicy, $payload['effective_policy'] ?? null, 'effective_policy equals input', $failures);

echo str_repeat('-', 60) . "\n";

if (!empty($failures)) {
    foreach ($failures as $f) {
        echo "FAIL: {$f}\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
