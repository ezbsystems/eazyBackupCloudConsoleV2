<?php
declare(strict_types=1);

/**
 * Unit test for KopiaRetentionRoutingService::isCloudObjectRetentionJob().
 * Verifies cloud-only retention routing: local_agent and kopia-family excluded,
 * cloud source types with sync engine allowed.
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_routing_test.php
 * (from repo root or worktree root)
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionRoutingService.php';

use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionRoutingService;

$failures = [];

function assertEqual($expected, $actual, string $label, array &$failures): void
{
    if ($expected === $actual) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true);
}

echo "KopiaRetentionRoutingService::isCloudObjectRetentionJob() tests\n";
echo str_repeat('-', 60) . "\n";

// local_agent + kopia => false (excluded)
$job = ['source_type' => 'local_agent', 'engine' => 'kopia'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(false, $result, 'local_agent + kopia => false', $failures);

// local_agent + disk_image => false (excluded)
$job = ['source_type' => 'local_agent', 'engine' => 'disk_image'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(false, $result, 'local_agent + disk_image => false', $failures);

// aws + sync => true (cloud allowlist)
$job = ['source_type' => 'aws', 'engine' => 'sync'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(true, $result, 'aws + sync => true', $failures);

// google_drive + sync => true (cloud allowlist)
$job = ['source_type' => 'google_drive', 'engine' => 'sync'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(true, $result, 'google_drive + sync => true', $failures);

// s3_compatible + sync => true (cloud allowlist)
$job = ['source_type' => 's3_compatible', 'engine' => 'sync'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(true, $result, 's3_compatible + sync => true', $failures);

// aws + kopia => false (kopia-family engine overrides cloud type)
$job = ['source_type' => 'aws', 'engine' => 'kopia'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(false, $result, 'aws + kopia => false', $failures);

// aws + hyperv => false (hyperv excluded, kopia-family)
$job = ['source_type' => 'aws', 'engine' => 'hyperv'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(false, $result, 'aws + hyperv => false', $failures);

// local_agent + hyperv => false (both exclude)
$job = ['source_type' => 'local_agent', 'engine' => 'hyperv'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(false, $result, 'local_agent + hyperv => false', $failures);

// unknown source_type + sync => false (not in allowlist)
$job = ['source_type' => 'unknown_source', 'engine' => 'sync'];
$result = KopiaRetentionRoutingService::isCloudObjectRetentionJob($job);
assertEqual(false, $result, 'unknown source_type + sync => false', $failures);

echo str_repeat('-', 60) . "\n";

if (!empty($failures)) {
    foreach ($failures as $f) {
        echo "FAIL: {$f}\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
