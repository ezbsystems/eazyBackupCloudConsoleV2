<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\RecoveryMediaBundleService;

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

function requestParam(array $body, string $key, $default = null)
{
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $body)) {
        return $body[$key];
    }
    return $default;
}

function authenticateAgent(): object
{
    $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? null;
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? null;
    if (!$agentUuid || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->first();
    if (!$agent || (string) $agent->status !== 'active' || (string) $agent->agent_token !== (string) $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }
    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);
    return $agent;
}

function base64urlPath(string $name): string
{
    $s = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name);
    $s = trim((string) $s, '-');
    if ($s === '') {
        $s = 'bundle.zip';
    }
    return $s;
}

$body = getBodyJson();
$agent = authenticateAgent();

$profile = RecoveryMediaBundleService::normalizeProfile((string) requestParam($body, 'profile', 'essential'));
$runId = (int) requestParam($body, 'run_id', 0);
$restorePointId = (int) requestParam($body, 'restore_point_id', 0);
$artifactName = trim((string) requestParam($body, 'artifact_name', 'drivers-' . $profile . '.zip'));
$artifactURL = trim((string) requestParam($body, 'artifact_url', ''));
$sha256 = strtolower(trim((string) requestParam($body, 'sha256', '')));
$backupFinishedAt = trim((string) requestParam($body, 'backup_finished_at', ''));
$warningMessage = trim((string) requestParam($body, 'warning_message', ''));
$status = trim((string) requestParam($body, 'status', 'ready'));
if ($status === '') {
    $status = 'ready';
}

$sizeBytes = (int) requestParam($body, 'size_bytes', 0);
$artifactPath = null;
$store = [];

if ($artifactURL === '') {
    $bin = null;
    if (isset($_FILES['bundle_file'])) {
        $upload = $_FILES['bundle_file'];
        if (!isset($upload['error']) || (int) $upload['error'] !== UPLOAD_ERR_OK) {
            $code = isset($upload['error']) ? (int) $upload['error'] : -1;
            respond(['status' => 'fail', 'message' => 'Bundle upload failed', 'upload_error' => $code], 400);
        }
        $tmpName = (string) ($upload['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            respond(['status' => 'fail', 'message' => 'Uploaded bundle file is invalid'], 400);
        }
        $bin = @file_get_contents($tmpName);
        if (!is_string($bin) || $bin === '') {
            respond(['status' => 'fail', 'message' => 'Unable to read uploaded bundle file'], 400);
        }
    } else {
        $bundleB64 = (string) requestParam($body, 'bundle_b64', '');
        if ($bundleB64 === '') {
            respond(['status' => 'fail', 'message' => 'bundle_file, bundle_b64 or artifact_url is required'], 400);
        }
        $decoded = base64_decode($bundleB64, true);
        if ($decoded === false) {
            respond(['status' => 'fail', 'message' => 'Invalid bundle_b64 payload'], 400);
        }
        $bin = $decoded;
    }
    if ($sizeBytes <= 0) {
        $sizeBytes = strlen($bin);
    }
    if ($sha256 === '') {
        $sha256 = hash('sha256', $bin);
    }
    if ($runId <= 0) {
        respond(['status' => 'fail', 'message' => 'run_id is required for bundle storage'], 400);
    }
    $store = RecoveryMediaBundleService::uploadBundleObjectForRun(
        (int) $agent->client_id,
        (int) $agent->id,
        $runId,
        $profile,
        $bin,
        $sha256
    );
    if (($store['status'] ?? 'fail') === 'unsupported') {
        respond([
            'status' => 'skip',
            'message' => (string) ($store['message'] ?? 'Destination is not supported for driver bundle storage'),
            'profile' => $profile,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
        ], 200);
    }
    if (($store['status'] ?? 'fail') !== 'success') {
        logModuleCall('cloudstorage', 'agent_upload_driver_bundle_store_fail', [
            'agent_uuid' => (string) $agent->agent_uuid,
            'run_id' => $runId,
            'profile' => $profile,
        ], (string) ($store['message'] ?? 'store failed'));
        respond(['status' => 'fail', 'message' => (string) ($store['message'] ?? 'Unable to persist bundle')], 500);
    }
    $artifactPath = (string) ($store['object_key'] ?? '');
    $artifactURL = (string) ($store['artifact_url'] ?? '');
}

if ($backupFinishedAt === '' && $runId > 0) {
    $runFinished = Capsule::table('s3_cloudbackup_runs')
        ->where('id', $runId)
        ->value('finished_at');
    if ($runFinished) {
        $backupFinishedAt = (string) $runFinished;
    }
}

if ($restorePointId <= 0 && $runId > 0 && Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
    $rp = Capsule::table('s3_cloudbackup_restore_points')
        ->where('client_id', (int) $agent->client_id)
        ->where('agent_uuid', (string) $agent->agent_uuid)
        ->where('run_id', $runId)
        ->orderByDesc('id')
        ->first();
    if ($rp) {
        $restorePointId = (int) $rp->id;
    }
}

try {
    $id = RecoveryMediaBundleService::upsertBundle([
        'client_id' => (int) $agent->client_id,
        'tenant_id' => $agent->tenant_id ?? null,
        'agent_id' => (int) $agent->id,
        'run_id' => $runId > 0 ? $runId : null,
        'restore_point_id' => $restorePointId > 0 ? $restorePointId : null,
        'profile' => $profile,
        'bundle_kind' => 'source',
        'status' => $status,
        'artifact_name' => $artifactName,
        'artifact_url' => $artifactURL,
        'artifact_path' => $artifactPath,
        'dest_bucket_id' => isset($store['dest_bucket_id']) ? (int) $store['dest_bucket_id'] : null,
        'dest_prefix' => isset($store['dest_prefix']) ? (string) $store['dest_prefix'] : null,
        's3_user_id' => isset($store['s3_user_id']) ? (int) $store['s3_user_id'] : null,
        'sha256' => $sha256,
        'size_bytes' => $sizeBytes > 0 ? $sizeBytes : null,
        'warning_message' => $warningMessage !== '' ? $warningMessage : null,
        'backup_finished_at' => $backupFinishedAt !== '' ? $backupFinishedAt : null,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_upload_driver_bundle', [
        'agent_uuid' => (string) $agent->agent_uuid,
        'run_id' => $runId,
        'profile' => $profile,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Failed to store bundle metadata'], 500);
}

respond([
    'status' => 'success',
    'bundle_id' => $id,
    'profile' => $profile,
    'artifact_url' => $artifactURL,
    'artifact_path' => $artifactPath,
    'dest_bucket_id' => isset($store['dest_bucket_id']) ? (int) $store['dest_bucket_id'] : null,
    'dest_prefix' => isset($store['dest_prefix']) ? (string) $store['dest_prefix'] : null,
    's3_user_id' => isset($store['s3_user_id']) ? (int) $store['s3_user_id'] : null,
    'sha256' => $sha256,
    'size_bytes' => $sizeBytes,
]);

