<?php
/**
 * Route contract tests for cloud backup job/run UUID-only identity.
 * Asserts cloud backup API endpoints do not cast job_id/run_id to int.
 * Fails until route cutover (Task 3+) is applied.
 */

$apiDir = __DIR__ . '/../api';

$cloudbackupApis = [
    'cloudbackup_list_runs.php',
    'cloudbackup_list_snapshots.php',
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
    'intval($_GET[\'job_id\'])',
    'intval($_POST[\'job_id\'])',
    'intval($_REQUEST[\'job_id\'])',
    '(int)$_GET[\'run_id\']',
    '(int)$_POST[\'run_id\']',
    '(int)$_REQUEST[\'run_id\']',
    '(int) $_GET[\'run_id\']',
    '(int) $_POST[\'run_id\']',
    '(int) $_REQUEST[\'run_id\']',
    'intval($_GET[\'run_id\'])',
    'intval($_POST[\'run_id\'])',
    'intval($_REQUEST[\'run_id\'])',
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
// Note: cloudbackup_hyperv.php, e3backup_hyperv.php, cloudbackup_live.php, admin/cloudbackup_admin.php in Task 7 UI scope; excluded here for Task 6 API cutover
$cloudbackupPages = [
    'e3backup_runs.php' => [],
];

$pagesDir = __DIR__ . '/../pages';
foreach ($cloudbackupPages as $file => $patterns) {
    $path = $pagesDir . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        throw new RuntimeException("failed to read $path");
    }
    foreach ($patterns as $pattern) {
        if (strpos($src, $pattern) !== false) {
            throw new RuntimeException("$file still casts job/run identity to int: $pattern");
        }
    }
}

// Agent run/command APIs (Task 5 scope) - must use UUID for run_id/job_id, no int casts
$agentApis = [
    'agent_next_run.php',
    'agent_update_run.php',
    'agent_push_events.php',
    'agent_poll_commands.php',
    'agent_poll_pending_commands.php',
    'agent_complete_command.php',
    'admin_cloudbackup_request_command.php',
    'admin_cloudbackup_agent_diagnostics.php',
];

$agentForbiddenPatterns = [
    '(int)$_POST[\'run_id\']',
    '(int) $_POST[\'run_id\']',
    '(int)$_GET[\'run_id\']',
    '(int) $_GET[\'run_id\']',
    'intval($_POST[\'run_id\'])',
    'intval($_GET[\'run_id\'])',
    "->where('r.id',",
    "->where('j.id',",
    "->where('r.id'",
    "->where('j.id'",
];

foreach ($agentApis as $file) {
    $path = $apiDir . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        throw new RuntimeException("failed to read $file");
    }
    foreach ($agentForbiddenPatterns as $pattern) {
        if (strpos($src, $pattern) !== false) {
            throw new RuntimeException("$file still uses numeric run/job identity: $pattern");
        }
    }
    // Require invalid_identifier_format for APIs that accept run_id/job_id as input
    $needsUuidValidation = in_array($file, ['agent_update_run.php', 'agent_push_events.php', 'agent_poll_commands.php', 'admin_cloudbackup_request_command.php'], true);
    if ($needsUuidValidation && strpos($src, 'invalid_identifier_format') === false) {
        throw new RuntimeException("$file missing invalid_identifier_format for UUID validation");
    }
}

// Recovery/restore APIs (Task 6 scope) - must use UUID for run_id, no int casts
$recoveryApis = [
    'cloudbackup_recovery_update_run.php',
    'cloudbackup_recovery_push_events.php',
    'cloudbackup_recovery_get_run_status.php',
    'cloudbackup_recovery_get_run_events.php',
    'cloudbackup_recovery_poll_cancel.php',
    'cloudbackup_recovery_cancel_restore.php',
    'cloudbackup_recovery_refresh_session.php',
    'cloudbackup_recovery_debug_log.php',
    'cloudbackup_recovery_debug_tail.php',
];

$recoveryForbiddenPatterns = [
    '(int) $runId',
    '(int)$runId',
    "->where('id', (int) \$runId",
    "->where('id', (int)\$runId",
    '(int) $tokenRow->session_run_id',
    '(int)$tokenRow->session_run_id',
];

foreach ($recoveryApis as $file) {
    $path = $apiDir . '/' . $file;
    if (!is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false) {
        throw new RuntimeException("failed to read $file");
    }
    foreach ($recoveryForbiddenPatterns as $pattern) {
        if (strpos($src, $pattern) !== false) {
            throw new RuntimeException("$file still uses numeric run identity: $pattern");
        }
    }
    if (strpos($src, 'invalid_identifier_format') === false) {
        throw new RuntimeException("$file missing invalid_identifier_format for UUID validation");
    }
}

echo "route-contract-ok\n";
