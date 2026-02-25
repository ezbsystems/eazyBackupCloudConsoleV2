<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionHookService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// #region agent log
function debugLog(string $message, array $data, string $hypothesisId): void
{
    $entry = [
        'id' => uniqid('log_', true),
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => 'agent_update_run.php:debug',
        'message' => $message,
        'data' => $data,
        'runId' => isset($data['run_id']) ? ('run_' . $data['run_id']) : 'run_unknown',
        'hypothesisId' => $hypothesisId,
    ];
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug.log', json_encode($entry) . PHP_EOL, FILE_APPEND);
}
// #endregion

function respond(array $data, int $httpCode = 200): void
{
    $response = new JsonResponse($data, $httpCode);
    $response->send();
    exit;
}

function authenticateAgent(): object
{
    $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentUuid || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function updateAgentMetadata(string $agentUuid, array $body): void
{
    $version = trim((string) ($_POST['agent_version'] ?? ($body['agent_version'] ?? '')));
    $os = trim((string) ($_POST['agent_os'] ?? ($body['agent_os'] ?? '')));
    $arch = trim((string) ($_POST['agent_arch'] ?? ($body['agent_arch'] ?? '')));
    $build = trim((string) ($_POST['agent_build'] ?? ($body['agent_build'] ?? '')));

    if ($version === '' && $os === '' && $arch === '' && $build === '') {
        return;
    }

    $update = ['updated_at' => Capsule::raw('NOW()')];
    $hasAny = false;
    if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version') && $version !== '') {
        $update['agent_version'] = $version;
        $hasAny = true;
    }
    if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os') && $os !== '') {
        $update['agent_os'] = $os;
        $hasAny = true;
    }
    if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch') && $arch !== '') {
        $update['agent_arch'] = $arch;
        $hasAny = true;
    }
    if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_build') && $build !== '') {
        $update['agent_build'] = $build;
        $hasAny = true;
    }
    if (Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'metadata_updated_at')) {
        $update['metadata_updated_at'] = Capsule::raw('NOW()');
    }

    if ($hasAny) {
        Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agentUuid)
            ->update($update);
    }
}

function recordForcedRunFailureEvent(int $runId, string $summary): void
{
    try {
        $nowMicro = microtime(true);
        Capsule::table('s3_cloudbackup_run_events')->insert([
            'run_id' => $runId,
            'ts' => date('Y-m-d H:i:s.u', $nowMicro),
            'type' => 'error',
            'level' => 'error',
            'code' => 'AGENT_START_FAILURE',
            'message_id' => 'AGENT_START_FAILURE',
            'params_json' => json_encode(['summary' => $summary]),
        ]);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'agent_update_run_event_insert_error', ['run_id' => $runId], $e->getMessage());
    }
}

function sanitizeBranding(string $text): string
{
    if ($text === '') {
        return $text;
    }
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $patterns = [
        '/\bKopia\b/i' => 'eazyBackup',
        '/\bkopia\b/i' => 'eazyBackup',
        '/kopia:/i' => 'backup engine:',
        '/kopia\s+upload/i' => 'upload',
        '/kopia\s+error/i' => 'backup error',
    ];
    foreach ($patterns as $pattern => $replacement) {
        $decoded = preg_replace($pattern, $replacement, $decoded);
    }
    return $decoded;
}

$body = getBodyJson();
$runId = $_POST['run_id'] ?? ($body['run_id'] ?? null);
if (!$runId) {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}

$agent = authenticateAgent();
updateAgentMetadata((string) $agent->agent_uuid, $body);

$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
    ->where('r.id', $runId)
    ->select('r.id', 'r.status', 'r.job_id', 'r.stats_json', 'j.client_id')
    ->first();

if (!$run || (int)$run->client_id !== (int)$agent->client_id) {
    respond(['status' => 'fail', 'message' => 'Run not found or unauthorized'], 403);
}

$fields = [
    'status',
    'progress_pct',
    'bytes_transferred',
    'bytes_processed',    // Bytes read/hashed from source (for dedup progress tracking)
    'bytes_total',
    'objects_transferred',
    'objects_total',
    'speed_bytes_per_sec',
    'eta_seconds',
    'current_item',
    'log_excerpt',
    'error_summary',
    'validation_status',
    'validation_log_excerpt',
    'started_at',
    'finished_at',
    'progress_json',
    'stats_json',
    'log_ref',
];

$update = [];
foreach ($fields as $field) {
    if (!array_key_exists($field, $body)) {
        continue;
    }
    $val = $body[$field];
    // Normalize timestamps from RFC3339/ISO8601 into MySQL DATETIME
    if (in_array($field, ['started_at', 'finished_at'], true)) {
        if ($val !== null && $val !== '') {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $update[$field] = date('Y-m-d H:i:s', $ts);
            } else {
                // Record parse failure for diagnostics but continue processing
                logModuleCall('cloudstorage', 'agent_update_run_invalid_ts', ['run_id' => $runId, 'agent_uuid' => $agent->agent_uuid, 'field' => $field, 'value' => $val], 'invalid_timestamp');
            }
        }
        continue;
    }
    if (in_array($field, ['progress_json', 'stats_json'], true)) {
        if ($field === 'stats_json' && is_array($val)) {
            $existingStats = [];
            if (!empty($run->stats_json) && is_string($run->stats_json)) {
                $decodedExisting = json_decode($run->stats_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedExisting)) {
                    $existingStats = $decodedExisting;
                }
            }
            // Merge incremental stats updates so metadata (e.g. disk_layout) is preserved.
            $mergedStats = array_replace_recursive($existingStats, $val);
            $update[$field] = json_encode($mergedStats);
        } else {
            $update[$field] = is_array($val) ? json_encode($val) : $val;
        }
        continue;
    }
    if (in_array($field, ['error_summary', 'log_excerpt', 'validation_log_excerpt'], true) && is_string($val)) {
        $sanitized = sanitizeBranding($val);
        if ($field === 'error_summary' && strlen($sanitized) > 700) {
            $sanitized = substr($sanitized, 0, 699) . '…';
        }
        $update[$field] = $sanitized;
    } else {
        $update[$field] = $val;
    }
}

// #region agent log
if (!empty($update)) {
    debugLog('agent_update_run', [
        'run_id' => (int) $runId,
        'status' => $update['status'] ?? null,
        'progress_pct' => $update['progress_pct'] ?? null,
        'bytes_processed' => $update['bytes_processed'] ?? null,
        'bytes_transferred' => $update['bytes_transferred'] ?? null,
        'bytes_total' => $update['bytes_total'] ?? null,
        'eta_seconds' => $update['eta_seconds'] ?? null,
        'speed_bps' => $update['speed_bytes_per_sec'] ?? null,
    ], 'H1');
}
// #endregion

// Allow manifest_id alias to log_ref (e.g., agents posting manifest separately)
if (array_key_exists('manifest_id', $body) && !array_key_exists('log_ref', $body)) {
    $update['log_ref'] = $body['manifest_id'];
}

$hasUpdatedAtColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');

if (empty($update)) {
    if ($hasUpdatedAtColumn) {
        // Treat an empty payload as a heartbeat when updated_at exists
        $update['updated_at'] = Capsule::raw('NOW()');
    } else {
        logModuleCall('cloudstorage', 'agent_update_run_no_fields', ['run_id' => $runId, 'agent_uuid' => $agent->agent_uuid], ['body_keys' => array_keys($body)]);
        respond(['status' => 'success', 'message' => 'No fields to update']);
    }
}

// Touch updated_at when the column exists (older schemas may not have it)
if ($hasUpdatedAtColumn) {
    $update['updated_at'] = Capsule::raw('NOW()');
}

// Force terminal failure if the agent reports an error summary before a terminal status
$terminalStatuses = ['success', 'failed', 'warning', 'cancelled', 'partial_success'];
$incomingStatus = isset($body['status']) ? strtolower($body['status']) : null;
$currentStatus = isset($run->status) ? strtolower($run->status) : null;
$errorSummaryText = '';
if (isset($body['error_summary']) && is_string($body['error_summary'])) {
    $errorSummaryText = trim($body['error_summary']);
}
$shouldForceFailure = !in_array($currentStatus, $terminalStatuses, true)
    && !in_array($incomingStatus, $terminalStatuses, true)
    && $errorSummaryText !== '';
if ($shouldForceFailure) {
    logModuleCall('cloudstorage', 'agent_update_run_force_failure', [
        'run_id' => $runId,
        'current_status' => $run->status,
        'incoming_status' => $incomingStatus,
    ], $errorSummaryText);
    $update['status'] = 'failed';
    if (!isset($update['finished_at'])) {
        $update['finished_at'] = date('Y-m-d H:i:s');
    }
    if (!isset($update['error_summary']) || $update['error_summary'] === '') {
        $update['error_summary'] = $errorSummaryText;
    }
    if (!isset($update['progress_pct'])) {
        $update['progress_pct'] = 0;
    }
    recordForcedRunFailureEvent($runId, $errorSummaryText);
}

// Handle disk_manifests_json for Hyper-V and disk image backups
if (array_key_exists('disk_manifests_json', $body) && is_array($body['disk_manifests_json'])) {
    $hasDiskManifests = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'disk_manifests_json');
    if ($hasDiskManifests) {
        $update['disk_manifests_json'] = json_encode($body['disk_manifests_json']);
    }
}

// Handle Hyper-V results
if (isset($body['hyperv_results']) && is_array($body['hyperv_results'])) {
    try {
        // Get the job for this run to find VM mappings
        $job = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
            ->where('r.id', $runId)
            ->select('j.id as job_id')
            ->first();
        
        foreach ($body['hyperv_results'] as $vmResult) {
            $vmId = $vmResult['vm_id'] ?? null;
            $vmName = $vmResult['vm_name'] ?? '';
            $backupType = $vmResult['backup_type'] ?? 'Full';
            $checkpointId = $vmResult['checkpoint_id'] ?? '';
            $rctIds = $vmResult['rct_ids'] ?? [];
            $diskManifests = $vmResult['disk_manifests'] ?? [];
            $totalBytes = $vmResult['total_bytes'] ?? 0;
            $changedBytes = $vmResult['changed_bytes'] ?? 0;
            $consistencyLevel = $vmResult['consistency_level'] ?? 'Application';
            $durationSeconds = $vmResult['duration_seconds'] ?? 0;
            $error = $vmResult['error'] ?? null;
            
            if (!$vmId) {
                continue;
            }
            
            // Skip creating records for VMs that failed - they have no valid backup data
            if (!empty($error)) {
                // Log the failed VM for diagnostics
                logModuleCall('cloudstorage', 'agent_update_run_hyperv_vm_failed', [
                    'run_id' => $runId,
                    'vm_id' => $vmId,
                    'vm_name' => $vmName,
                ], $error);
                continue;
            }
            
            // Create or update checkpoint record
            if ($checkpointId) {
                // Deactivate old checkpoints for this VM
                Capsule::table('s3_hyperv_checkpoints')
                    ->where('vm_id', $vmId)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'merged_at' => Capsule::raw('NOW()')]);
                
                // Create new checkpoint record
                Capsule::table('s3_hyperv_checkpoints')->insert([
                    'vm_id' => $vmId,
                    'run_id' => $runId,
                    'checkpoint_id' => $checkpointId,
                    'checkpoint_name' => 'EazyBackup_' . date('Ymd_His'),
                    'checkpoint_type' => $consistencyLevel === 'Application' ? 'Production' : 'Standard',
                    'rct_ids' => json_encode($rctIds),
                    'is_active' => true,
                    'created_at' => Capsule::raw('NOW()'),
                ]);
            }
            
            // Create backup point record
            $manifestId = '';
            if (!empty($diskManifests)) {
                $manifestId = reset($diskManifests); // Use first disk's manifest as primary
            }
            
            // Find parent backup for incremental
            $parentBackupId = null;
            if ($backupType === 'Incremental') {
                $parentBackup = Capsule::table('s3_hyperv_backup_points')
                    ->where('vm_id', $vmId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $parentBackupId = $parentBackup->id ?? null;
            }
            
            // Handle warnings from the agent
            $warnings = $vmResult['warnings'] ?? [];
            $warningCode = $vmResult['warning_code'] ?? null;
            $hasWarnings = !empty($warnings);

            // Insert backup point record
            $backupPointData = [
                'vm_id' => $vmId,
                'run_id' => $runId,
                'backup_type' => $backupType,
                'manifest_id' => $manifestId,
                'parent_backup_id' => $parentBackupId,
                'disk_manifests' => json_encode($diskManifests),
                'total_size_bytes' => $totalBytes,
                'changed_size_bytes' => $changedBytes,
                'duration_seconds' => $durationSeconds,
                'consistency_level' => $consistencyLevel,
                'created_at' => Capsule::raw('NOW()'),
            ];

            // Add warnings columns if schema supports them
            if (Capsule::schema()->hasColumn('s3_hyperv_backup_points', 'warnings_json')) {
                $backupPointData['warnings_json'] = json_encode($warnings);
                $backupPointData['warning_code'] = $warningCode;
                $backupPointData['has_warnings'] = $hasWarnings;
            }

            Capsule::table('s3_hyperv_backup_points')->insert($backupPointData);
            
            // Update VM's RCT enabled status based on whether we got RCT IDs
            if (!empty($rctIds)) {
                Capsule::table('s3_hyperv_vms')
                    ->where('id', $vmId)
                    ->update(['rct_enabled' => true, 'updated_at' => Capsule::raw('NOW()')]);
            }
        }
    } catch (\Throwable $e) {
        // Log but don't fail the update
        logModuleCall('cloudstorage', 'agent_update_run_hyperv_error', [
            'run_id' => $runId,
            'agent_uuid' => $agent->agent_uuid,
        ], $e->getMessage());
    }
}

try {
    $affected = Capsule::table('s3_cloudbackup_runs')
        ->where('id', $runId)
        ->update($update);
    if ((int) $affected === 0) {
        logModuleCall('cloudstorage', 'agent_update_run_noop', ['run_id' => $runId, 'agent_uuid' => $agent->agent_uuid], ['update' => $update, 'affected' => $affected]);
    }

    $finalStatus = isset($update['status']) ? strtolower((string) $update['status']) : null;
    if ($finalStatus && in_array($finalStatus, ['success', 'warning'], true)) {
        try {
            CloudBackupController::recordRestorePointsForRun((int) $runId);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'agent_update_run_restore_points_error', ['run_id' => $runId], $e->getMessage());
        }
        $jobRow = Capsule::table('s3_cloudbackup_jobs')->where('id', $run->job_id)->first();
        if ($jobRow && KopiaRetentionHookService::shouldEnqueueFromRun(
            $finalStatus,
            (string) ($jobRow->source_type ?? ''),
            (string) ($jobRow->engine ?? '')
        )) {
            $repositoryId = trim((string) ($jobRow->repository_id ?? ''));
            if ($repositoryId !== '' && Capsule::schema()->hasTable('s3_kopia_repos')) {
                $repoRow = Capsule::table('s3_kopia_repos')->where('repository_id', $repositoryId)->first();
                if ($repoRow) {
                    $repoId = (int) $repoRow->id;
                    try {
                        KopiaRetentionOperationService::enqueue($repoId, 'retention_apply', ['repo_id' => $repoId], 'run-finish-' . $runId . '-retention');
                        KopiaRetentionOperationService::enqueue($repoId, 'maintenance_quick', ['repo_id' => $repoId], 'run-finish-' . $runId . '-maintenance');
                    } catch (\Throwable $e) {
                        logModuleCall('cloudstorage', 'agent_update_run_retention_enqueue_error', ['run_id' => $runId, 'repo_id' => $repoId], $e->getMessage());
                    }
                }
            }
        }
    }

    respond(['status' => 'success']);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_update_run_error', ['run_id' => $runId, 'agent_uuid' => $agent->agent_uuid], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Update failed'], 500);
}

