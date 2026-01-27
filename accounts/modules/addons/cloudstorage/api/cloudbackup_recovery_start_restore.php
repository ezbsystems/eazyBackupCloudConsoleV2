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

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$body = getBodyJson();
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));
$targetDisk = trim((string) ($_POST['target_disk'] ?? ($body['target_disk'] ?? '')));
$targetDiskBytes = isset($_POST['target_disk_bytes']) ? (int) $_POST['target_disk_bytes'] : (int) ($body['target_disk_bytes'] ?? 0);
$options = is_array($body['options'] ?? null) ? $body['options'] : [];

if ($sessionToken === '') {
    respond(['status' => 'fail', 'message' => 'session_token is required'], 400);
}
if ($targetDisk === '') {
    respond(['status' => 'fail', 'message' => 'target_disk is required'], 400);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
    respond(['status' => 'fail', 'message' => 'Recovery tokens not supported'], 500);
}

$tokenRow = Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('session_token', $sessionToken)
    ->first();
if (!$tokenRow) {
    respond(['status' => 'fail', 'message' => 'Invalid session token'], 403);
}
if (!empty($tokenRow->session_expires_at) && strtotime((string) $tokenRow->session_expires_at) < time()) {
    respond(['status' => 'fail', 'message' => 'Session token expired'], 403);
}
if (!empty($tokenRow->used_at)) {
    respond(['status' => 'fail', 'message' => 'Recovery token already used'], 403);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $tokenRow->restore_point_id)
    ->where('client_id', $tokenRow->client_id)
    ->first();
if (!$restorePoint) {
    respond(['status' => 'fail', 'message' => 'Restore point not found'], 404);
}

$jobId = (int) ($restorePoint->job_id ?? 0);
if ($jobId <= 0) {
    respond(['status' => 'fail', 'message' => 'Restore point missing job reference'], 400);
}

$job = Capsule::table('s3_cloudbackup_jobs')
    ->where('id', $jobId)
    ->where('client_id', $restorePoint->client_id)
    ->first();
if (!$job) {
    respond(['status' => 'fail', 'message' => 'Backup job not found'], 404);
}

$runData = [
    'job_id' => $jobId,
    'client_id' => $restorePoint->client_id,
    'agent_id' => null,
    'engine' => 'disk_image',
    'status' => 'running',
    'progress_pct' => 0,
    'started_at' => date('Y-m-d H:i:s'),
    'stats_json' => json_encode([
            'type' => 'disk_restore',
        'restore_point_id' => (int) $restorePoint->id,
        'target_disk' => $targetDisk,
        'target_disk_bytes' => $targetDiskBytes,
        'options' => $options,
    ]),
];

$hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
if ($hasRunTypeColumn) {
    $runData['run_type'] = 'disk_restore';
}
$hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');
if ($hasRunUuidColumn) {
    $runData['run_uuid'] = CloudBackupController::generateUuid();
}

try {
    $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
    $startedIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $startedUA = $_SERVER['HTTP_USER_AGENT'] ?? null;
    Capsule::table('s3_cloudbackup_recovery_tokens')
        ->where('id', $tokenRow->id)
        ->update([
            'used_at' => date('Y-m-d H:i:s'),
            'session_run_id' => $restoreRunId,
            'started_at' => date('Y-m-d H:i:s'),
            'started_ip' => $startedIp,
            'started_user_agent' => $startedUA,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    respond([
        'status' => 'success',
        'restore_run_id' => $restoreRunId,
        'restore_run_uuid' => $runData['run_uuid'] ?? null,
    ]);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Failed to create restore run'], 500);
}
