<?php
declare(strict_types=1);

/**
 * Unit test for KopiaRetentionHookService.
 * Verifies shouldEnqueueFromRun and shouldRetireOnJobDelete hooks.
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_hooks_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Client/KopiaRetentionHookService.php';

use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionHookService;

$failures = [];

function assertEqual($expected, $actual, string $label, array &$failures): void
{
    if ($expected === $actual) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true);
}

echo "KopiaRetentionHookService tests\n";
echo str_repeat('-', 60) . "\n";

// shouldEnqueueFromRun('success','local_agent','kopia') => true
$result = KopiaRetentionHookService::shouldEnqueueFromRun('success', 'local_agent', 'kopia');
assertEqual(true, $result, "shouldEnqueueFromRun('success','local_agent','kopia') => true", $failures);

// shouldEnqueueFromRun('success','aws','sync') => false
$result = KopiaRetentionHookService::shouldEnqueueFromRun('success', 'aws', 'sync');
assertEqual(false, $result, "shouldEnqueueFromRun('success','aws','sync') => false", $failures);

// shouldRetireOnJobDelete('local_agent','disk_image') => true
$result = KopiaRetentionHookService::shouldRetireOnJobDelete('local_agent', 'disk_image');
assertEqual(true, $result, "shouldRetireOnJobDelete('local_agent','disk_image') => true", $failures);

echo str_repeat('-', 60) . "\n";

if (!empty($failures)) {
    foreach ($failures as $f) {
        echo "FAIL: {$f}\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
