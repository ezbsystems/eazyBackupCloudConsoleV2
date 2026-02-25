<?php
declare(strict_types=1);

/**
 * View model test for CloudBackupAdminController::getRepoRetentionOps.
 * TDD: asserts that getRepoRetentionOps returns an array.
 *
 * Run: php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_admin_view_model_test.php
 * (from repo root or worktree root)
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/Admin/CloudBackupAdminController.php';

use WHMCS\Module\Addon\CloudStorage\Admin\CloudBackupAdminController;

$failures = [];

function assertEqual($expected, $actual, string $label, array &$failures): void
{
    if ($expected === $actual) {
        echo "  OK: {$label}\n";
        return;
    }
    $failures[] = "{$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true);
}

echo "CloudBackupAdminController::getRepoRetentionOps tests\n";
echo str_repeat('-', 60) . "\n";

$result = CloudBackupAdminController::getRepoRetentionOps(['limit' => 1]);

assertEqual(true, is_array($result), 'getRepoRetentionOps returns array', $failures);

echo str_repeat('-', 60) . "\n";

if (!empty($failures)) {
    foreach ($failures as $f) {
        echo "FAIL: {$f}\n";
    }
    exit(1);
}

echo "PASS\n";
exit(0);
