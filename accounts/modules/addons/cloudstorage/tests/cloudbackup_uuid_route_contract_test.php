<?php
/**
 * Route contract tests for cloud backup job/run UUID-only identity.
 * Asserts cloud backup API endpoints do not cast job_id/run_id to int.
 * Fails until route cutover (Task 3+) is applied.
 */

$apiDir = __DIR__ . '/../api';

$cloudbackupApis = [
    'cloudbackup_list_runs.php',
    'cloudbackup_start_run.php',
    'cloudbackup_get_run_logs.php',
    'cloudbackup_get_run_events.php',
    'cloudbackup_cancel_run.php',
    'cloudbackup_request_command.php',
    'cloudbackup_update_job.php',
    'cloudbackup_get_job.php',
    'cloudbackup_delete_job.php',
    'cloudbackup_list_jobs.php',
    'cloudbackup_progress.php',
    'cloudbackup_recovery_token_exchange.php',
    'cloudbackup_recovery_get_run_status.php',
    'cloudbackup_recovery_update_run.php',
    'cloudbackup_recovery_cancel_restore.php',
];

$forbiddenPatterns = [
    '(int)$_GET[\'job_id\']',
    '(int)$_POST[\'job_id\']',
    '(int)$_REQUEST[\'job_id\']',
    '(int) $_GET[\'job_id\']',
    '(int) $_POST[\'job_id\']',
    '(int) $_REQUEST[\'job_id\']',
    '(int)$_GET[\'run_id\']',
    '(int)$_POST[\'run_id\']',
    '(int)$_REQUEST[\'run_id\']',
    '(int) $_GET[\'run_id\']',
    '(int) $_POST[\'run_id\']',
    '(int) $_REQUEST[\'run_id\']',
    'validateJobAccess((int)',
    'validateJobAccess((int)$',
];

foreach ($cloudbackupApis as $file) {
    $path = $apiDir . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        throw new RuntimeException("failed to read $file");
    }
    foreach ($forbiddenPatterns as $pattern) {
        if (strpos($src, $pattern) !== false) {
            throw new RuntimeException("$file still casts job/run identity to int: $pattern");
        }
    }
}

// Pages that receive job_id/run_id for cloud backup (scoped to cloud backup only)
$cloudbackupPages = [
    'cloudbackup_live.php' => ['(int) \$_GET[\'job_id\']', '(int)$_GET[\'run_id\']'],
    'cloudbackup_hyperv.php' => ['(int) \$_GET[\'job_id\']'],
    'e3backup_runs.php' => [],
    'e3backup_hyperv.php' => ['(int) \$_GET[\'job_id\']'],
    'admin/cloudbackup_admin.php' => ['(int)$_GET[\'cancel_run\']', '(int)$_GET[\'get_run_logs\']'],
];

$pagesDir = __DIR__ . '/../pages';
foreach ($cloudbackupPages as $file => $patterns) {
    $path = $pagesDir . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        continue;
    }
    foreach ($patterns as $pattern) {
        if (strpos($src, $pattern) !== false) {
            throw new RuntimeException("$file still casts job/run identity to int: $pattern");
        }
    }
}

echo "route-contract-ok\n";
