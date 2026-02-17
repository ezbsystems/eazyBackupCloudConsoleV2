<?php
/**
 * Start a bare-metal disk restore using a recovery session token.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/CloudBackupController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function respondError(string $code, string $message, int $httpCode = 200, array $extra = []): void
{
    respond(array_merge([
        'status' => 'fail',
        'code' => $code,
        'message' => $message,
    ], $extra), $httpCode);
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

function getTableColumns(string $table): array
{
    try {
        $cols = Capsule::schema()->getColumnListing($table);
        if (!is_array($cols)) {
            return [];
        }
        return array_fill_keys($cols, true);
    } catch (\Throwable $e) {
        return [];
    }
}

function getEnumValues(string $table, string $column): array
{
    try {
        $rows = Capsule::select("SHOW COLUMNS FROM `{$table}` WHERE Field = ?", [$column]);
        if (empty($rows)) {
            return [];
        }
        $type = (string) ($rows[0]->Type ?? '');
        if (stripos($type, 'enum(') !== 0) {
            return [];
        }
        if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
            return [];
        }
        $inner = $m[1];
        $parts = str_getcsv($inner, ',', "'");
        $out = [];
        foreach ($parts as $p) {
            $v = trim((string) $p);
            if ($v !== '') {
                $out[] = $v;
            }
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

function chooseEngineForRun(array $runColumns): ?string
{
    if (!isset($runColumns['engine'])) {
        return null;
    }
    $allowed = getEnumValues('s3_cloudbackup_runs', 'engine');
    if (empty($allowed)) {
        return 'disk_image';
    }
    if (in_array('disk_image', $allowed, true)) {
        return 'disk_image';
    }
    if (in_array('kopia', $allowed, true)) {
        return 'kopia';
    }
    return $allowed[0] ?? null;
}

function chooseRunTypeForRun(array $runColumns): ?string
{
    if (!isset($runColumns['run_type'])) {
        return null;
    }
    $allowed = getEnumValues('s3_cloudbackup_runs', 'run_type');
    // Common modern value.
    if (empty($allowed)) {
        return 'disk_restore';
    }
    if (in_array('disk_restore', $allowed, true)) {
        return 'disk_restore';
    }
    // Legacy-compatible fallbacks.
    if (in_array('restore', $allowed, true)) {
        return 'restore';
    }
    if (in_array('backup', $allowed, true)) {
        return 'backup';
    }
    return $allowed[0] ?? null;
}

$body = getBodyJson();
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));
$targetDisk = trim((string) ($_POST['target_disk'] ?? ($body['target_disk'] ?? '')));
$targetDiskBytes = isset($_POST['target_disk_bytes']) ? (int) $_POST['target_disk_bytes'] : (int) ($body['target_disk_bytes'] ?? 0);
$options = is_array($body['options'] ?? null) ? $body['options'] : [];

if ($sessionToken === '') {
    respondError('invalid_request', 'session_token is required', 400);
}
if ($targetDisk === '') {
    respondError('invalid_request', 'target_disk is required', 400);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
    respondError('schema_upgrade_required', 'Recovery tokens not supported', 500);
}
if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
    respondError('schema_upgrade_required', 'Restore runs are not supported on this installation', 500);
}

$tokenRow = Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('session_token', $sessionToken)
    ->first();
if (!$tokenRow) {
    respondError('invalid_session', 'Invalid session token', 403);
}
if (!empty($tokenRow->session_expires_at) && strtotime((string) $tokenRow->session_expires_at) < time()) {
    respondError('session_expired', 'Session token expired', 403);
}
if (!empty($tokenRow->session_run_id)) {
    respondError('session_already_bound', 'Session token is already bound to a restore run', 403, [
        'restore_run_id' => (int) $tokenRow->session_run_id,
    ]);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $tokenRow->restore_point_id)
    ->where('client_id', $tokenRow->client_id)
    ->first();
if (!$restorePoint) {
    respondError('not_found', 'Restore point not found', 404);
}

if (($restorePoint->status ?? '') === 'metadata_incomplete') {
    respondError('metadata_incomplete', 'Restore metadata is incomplete for this restore point. Create a fresh disk image backup and try again.', 400);
}
if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respondError('invalid_restore_point', 'Restore point is not available for recovery', 400);
}

$restoreEngine = strtolower((string) ($restorePoint->engine ?? ''));
$restoreLayout = trim((string) ($restorePoint->disk_layout_json ?? ''));
if ($restoreEngine === 'disk_image' && $restoreLayout === '') {
    respondError('invalid_restore_point', 'Restore point is missing disk layout metadata. Create a new disk image backup and retry.', 400);
}

$jobId = (int) ($restorePoint->job_id ?? 0);
if ($jobId <= 0) {
    respondError('invalid_state', 'Restore point missing job reference', 400);
}

$job = Capsule::table('s3_cloudbackup_jobs')
    ->where('id', $jobId)
    ->where('client_id', $restorePoint->client_id)
    ->first();
if (!$job) {
    respondError('not_found', 'Backup job not found', 404);
}

$runColumns = getTableColumns('s3_cloudbackup_runs');
$tokenColumns = getTableColumns('s3_cloudbackup_recovery_tokens');

$runData = [];
if (isset($runColumns['job_id'])) {
    $runData['job_id'] = $jobId;
}
if (isset($runColumns['client_id'])) {
    $runData['client_id'] = $restorePoint->client_id;
}
if (isset($runColumns['agent_id'])) {
    $runData['agent_id'] = (int) ($restorePoint->agent_id ?? ($job->agent_id ?? 0)) ?: null;
}
if (isset($runColumns['trigger_type'])) {
    $runData['trigger_type'] = 'manual';
}
if (isset($runColumns['engine'])) {
    $engineValue = chooseEngineForRun($runColumns);
    if ($engineValue !== null) {
        $runData['engine'] = $engineValue;
    }
}
if (isset($runColumns['status'])) {
    $runData['status'] = 'running';
}
if (isset($runColumns['cancel_requested'])) {
    $runData['cancel_requested'] = 0;
}
if (isset($runColumns['validation_mode'])) {
    $runData['validation_mode'] = 'none';
}
if (isset($runColumns['validation_status'])) {
    $runData['validation_status'] = 'not_run';
}
if (isset($runColumns['progress_pct'])) {
    $runData['progress_pct'] = 0;
}
if (isset($runColumns['started_at'])) {
    $runData['started_at'] = date('Y-m-d H:i:s');
}
if (isset($runColumns['created_at'])) {
    $runData['created_at'] = date('Y-m-d H:i:s');
}
if (isset($runColumns['stats_json'])) {
    $runData['stats_json'] = json_encode([
        'type' => 'disk_restore',
        'restore_point_id' => (int) $restorePoint->id,
        'target_disk' => $targetDisk,
        'target_disk_bytes' => $targetDiskBytes,
        'options' => $options,
    ]);
}

if (!isset($runData['job_id']) || !isset($runData['status'])) {
    respondError('schema_upgrade_required', 'Run table schema is missing required columns', 500, [
        'missing_columns' => array_values(array_filter([
            !isset($runData['job_id']) ? 'job_id' : null,
            !isset($runData['status']) ? 'status' : null,
        ])),
    ]);
}

$runTypeValue = chooseRunTypeForRun($runColumns);
if ($runTypeValue !== null) {
    $runData['run_type'] = $runTypeValue;
}
$hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');
if ($hasRunUuidColumn) {
    $runData['run_uuid'] = CloudBackupController::generateUuid();
}

try {
    $restoreRunId = 0;
    Capsule::connection()->transaction(function () use (&$restoreRunId, $runData, $tokenRow, $tokenColumns) {
        $lockedToken = Capsule::table('s3_cloudbackup_recovery_tokens')
            ->where('id', (int) $tokenRow->id)
            ->lockForUpdate()
            ->first();
        if (!$lockedToken) {
            throw new \RuntimeException('missing_token');
        }
        if (!empty($lockedToken->session_run_id)) {
            throw new \RuntimeException('session_already_bound');
        }

        $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
        $startedIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $startedUA = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $tokenUpdate = [];
        if (isset($tokenColumns['used_at'])) {
            $tokenUpdate['used_at'] = date('Y-m-d H:i:s');
        }
        if (isset($tokenColumns['session_run_id'])) {
            $tokenUpdate['session_run_id'] = $restoreRunId;
        }
        if (isset($tokenColumns['started_at'])) {
            $tokenUpdate['started_at'] = date('Y-m-d H:i:s');
        }
        if (isset($tokenColumns['started_ip'])) {
            $tokenUpdate['started_ip'] = $startedIp;
        }
        if (isset($tokenColumns['started_user_agent'])) {
            $tokenUpdate['started_user_agent'] = $startedUA;
        }
        if (isset($tokenColumns['updated_at'])) {
            $tokenUpdate['updated_at'] = date('Y-m-d H:i:s');
        }

        if (!empty($tokenUpdate)) {
            Capsule::table('s3_cloudbackup_recovery_tokens')
                ->where('id', (int) $lockedToken->id)
                ->update($tokenUpdate);
        }
    });

    if ($restoreRunId <= 0) {
        respondError('server_error', 'Failed to create restore run', 500);
    }

    respond([
        'status' => 'success',
        'restore_run_id' => $restoreRunId,
        'restore_run_uuid' => $runData['run_uuid'] ?? null,
    ]);
} catch (\Throwable $e) {
    if ($e instanceof \RuntimeException && $e->getMessage() === 'session_already_bound') {
        respondError('session_already_bound', 'Session token is already bound to a restore run', 403);
    }
    $logPayload = [
        'token_id' => (int) ($tokenRow->id ?? 0),
        'restore_point_id' => (int) ($restorePoint->id ?? 0),
        'job_id' => $jobId,
        'run_engine' => $runData['engine'] ?? null,
        'run_columns_count' => count($runColumns),
        'token_columns_count' => count($tokenColumns),
    ];
    $logResult = [
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
    ];
    if ($e instanceof \Illuminate\Database\QueryException) {
        $logResult['sql'] = $e->getSql();
        $logResult['bindings'] = $e->getBindings();
    }
    logModuleCall('cloudstorage', 'cloudbackup_recovery_start_restore_error', $logPayload, $logResult);

    $message = 'Failed to create restore run';
    $code = 'server_error';
    $extra = [];
    if ($e instanceof \Illuminate\Database\QueryException) {
        $code = 'schema_upgrade_required';
        $message = 'Database schema mismatch while creating restore run. Please run module upgrade.';
        $extra['error_hint'] = substr((string) $e->getMessage(), 0, 240);
    }
    respondError($code, $message, 500, $extra);
}
