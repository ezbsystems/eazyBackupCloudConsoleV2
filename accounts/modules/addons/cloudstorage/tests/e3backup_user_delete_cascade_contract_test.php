<?php

declare(strict_types=1);

/**
 * Contract tests for backup user delete cascade.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/e3backup_user_delete_cascade_contract_test.php
 */

$repoRoot = dirname(__DIR__, 4);
$failures = [];

function assert_contains(string $fileLabel, string $haystack, string $needle): void
{
    global $failures;
    if (strpos($haystack, $needle) === false) {
        $failures[] = "FAIL: {$fileLabel} missing marker: {$needle}";
    }
}

$files = [
    'lifecycle service' => $repoRoot . '/modules/addons/cloudstorage/lib/Client/E3BackupUserLifecycleService.php',
    'user scope' => $repoRoot . '/modules/addons/cloudstorage/lib/Client/E3BackupUserScope.php',
    'user delete api' => $repoRoot . '/modules/addons/cloudstorage/api/e3backup_user_delete.php',
    'users template' => $repoRoot . '/modules/addons/cloudstorage/templates/e3backup_users.tpl',
    'token create api' => $repoRoot . '/modules/addons/cloudstorage/api/e3backup_token_create.php',
    'orphan remediation' => $repoRoot . '/modules/addons/cloudstorage/lib/Admin/E3BackupOrphanRemediation.php',
    'ms365 scheduler' => $repoRoot . '/modules/addons/ms365backup/lib/Ms365Backup/Ms365JobScheduler.php',
    'agent scheduler' => $repoRoot . '/crons/s3cloudbackup_scheduler.php',
    'cloudbackup controller' => $repoRoot . '/modules/addons/cloudstorage/lib/Client/CloudBackupController.php',
];

foreach ($files as $label => $path) {
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$label} at {$path}";
        continue;
    }

    switch ($label) {
        case 'lifecycle service':
            assert_contains($label, $source, 'class E3BackupUserLifecycleService');
            assert_contains($label, $source, 'softDeleteJobsForUser');
            assert_contains($label, $source, "AddCancelRequest");
            assert_contains($label, $source, 'ensureDeletedAtColumn');
            assert_contains($label, $source, 'deletedUsername');
            assert_contains($label, $source, 'original_username');
            assert_contains($label, $source, 'disconnectMs365IfNeeded');
            if (strpos($source, 'validateBulkDeletePhrase') !== false) {
                $failures[] = 'FAIL: lifecycle service should not accept bulk DELETE phrase on apply';
            }
            if (strpos($source, '!$dryRun && !$skipConfirm') === false) {
                $failures[] = 'FAIL: lifecycle service should skip confirm validation on dry_run';
            }
            break;
        case 'user scope':
            assert_contains($label, $source, 'applyNotDeletedScope');
            assert_contains($label, $source, 'ensureDeletedAtColumn');
            assert_contains($label, $source, 'deletedUsername');
            assert_contains($label, $source, 'isDeletedUser');
            assert_contains($label, $source, 'isSchedulable');
            break;
        case 'user delete api':
            assert_contains($label, $source, 'E3BackupUserLifecycleService::deleteUser');
            assert_contains($label, $source, 'confirm_phrase');
            assert_contains($label, $source, 'dry_run');
            if (preg_match("/s3_backup_users[^\\n]*->delete\\(/", $source)) {
                $failures[] = 'FAIL: user delete api still hard-deletes s3_backup_users';
            }
            break;
        case 'users template':
            assert_contains($label, $source, 'loadDeleteImpactPreview');
            assert_contains($label, $source, "confirm_phrase: 'DELETE ' + (user.username || '')");
            break;
        case 'token create api':
            assert_contains($label, $source, 'isDeletedUser');
            assert_contains($label, $source, 'Backup user is not active');
            break;
        case 'orphan remediation':
            assert_contains($label, $source, 'findOrphanJobs');
            assert_contains($label, $source, 'findOrphanVaults');
            break;
        case 'ms365 scheduler':
            assert_contains($label, $source, 'isBackupUserSchedulable');
            break;
        case 'agent scheduler':
            assert_contains($label, $source, 'E3BackupUserScope::isSchedulable');
            break;
        case 'cloudbackup controller':
            assert_contains($label, $source, 'skip_confirm');
            assert_contains($label, $source, 'skip_notification');
            break;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "e3backup-user-delete-cascade-contract-ok\n";
exit(0);
