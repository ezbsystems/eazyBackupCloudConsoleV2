<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/CloudBackupBootstrapService.php';
require_once __DIR__ . '/../lib/Client/RepositoryService.php';
require_once __DIR__ . '/../lib/Client/KopiaRetentionSourceService.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\AwsS3Validator;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupBootstrapService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionRoutingService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionSourceService;
use WHMCS\Module\Addon\CloudStorage\Client\RepositoryService;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use WHMCS\Database\Capsule;

/**
 * Normalize a JSON input that may arrive HTML-entity encoded.
 * Returns a canonical JSON string, or null for empty/invalid input.
 */
function normalizeJsonString($value): ?string
{
    if (is_array($value)) {
        return json_encode($value);
    }
    if (!is_string($value)) {
        return null;
    }
    $raw = trim($value);
    if ($raw === '') {
        return null;
    }
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
    foreach ($candidates as $cand) {
        $tmp = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            return json_encode($tmp);
        }
    }
    return null;
}

function normalizeJsonPayload($value): ?string
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value);
    }
    if (!is_string($value)) {
        return null;
    }
    $raw = trim($value);
    if ($raw === '') {
        return null;
    }
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
    foreach ($candidates as $cand) {
        $tmp = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($tmp);
        }
    }
    return null;
}

function decodeJsonArray($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return [];
    }
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
    foreach ($candidates as $cand) {
        $tmp = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            return $tmp;
        }
    }
    return [];
}

function sanitizePathInput(string $value): string
{
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $trimmed = trim($decoded);
    return trim($trimmed, " \t\n\r\0\x0B\"'");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Session timeout.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Product not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$jobId = isset($_POST['job_id']) ? trim((string) $_POST['job_id']) : '';
if ($jobId === '' || !UuidBinary::isUuid($jobId)) {
    $response = new JsonResponse([
        'status' => 'fail',
        'code' => 'invalid_identifier_format',
        'message' => 'job_id must be a valid UUID.',
    ], 400);
    $response->send();
    exit();
}

// Get encryption key from module config
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Cloudstorage module configuration not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$encryptionKey = $module->where('setting', 'cloudbackup_encryption_key')->pluck('value')->first();
if (empty($encryptionKey)) {
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
}

if (empty($encryptionKey)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Encryption key not configured.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Load existing job for merge/ownership check
$existingJob = CloudBackupController::getJob($jobId, $loggedInUserId);
if (!$existingJob) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Job not found or access denied'], 200);
    $response->send();
    exit();
}
$existingRepositoryId = trim((string) ($existingJob['repository_id'] ?? ''));
if ($existingRepositoryId !== '' && isset($_POST['repository_id']) && trim((string) $_POST['repository_id']) !== $existingRepositoryId) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Repository identity is immutable for this job.'], 200);
    $response->send();
    exit();
}

// MSP tenant authorization check
$accessCheck = MspController::validateJobAccess($jobId, $loggedInUserId);
if (!$accessCheck['valid']) {
    $response = new JsonResponse([
        'status' => 'fail',
        'message' => $accessCheck['message']
    ], 200);
    $response->send();
    exit();
}

// Prepare update data
$updateData = [];
// Disk image fields
$diskSourceVolume = $_POST['disk_source_volume'] ?? '';
$diskImageFormat = $_POST['disk_image_format'] ?? 'vhdx';
$diskTempDir = $_POST['disk_temp_dir'] ?? '';

if (isset($_POST['name'])) {
    $updateData['name'] = $_POST['name'];
}
if (isset($_POST['source_display_name'])) {
    $updateData['source_display_name'] = $_POST['source_display_name'];
}

// Map per-source path inputs for consistency
$sourceTypeForPath = $_POST['source_type'] ?? ($existingJob['source_type'] ?? '');
if (!isset($_POST['source_path']) || $_POST['source_path'] === '') {
    $mapped = '';
    if ($sourceTypeForPath === 'aws') {
        $mapped = $_POST['aws_path'] ?? '';
    } elseif ($sourceTypeForPath === 's3_compatible') {
        $mapped = $_POST['s3_path'] ?? '';
    } elseif ($sourceTypeForPath === 'sftp') {
        $mapped = $_POST['sftp_path'] ?? '';
    } elseif ($sourceTypeForPath === 'google_drive') {
        $mapped = $_POST['gdrive_path'] ?? '';
    } elseif ($sourceTypeForPath === 'dropbox') {
        $mapped = $_POST['dropbox_path'] ?? '';
    } elseif ($sourceTypeForPath === 'local_agent') {
        $mapped = $_POST['local_source_path'] ?? '';
    }
    $_POST['source_path'] = $mapped;
}
if (isset($_POST['source_path'])) {
    $_POST['source_path'] = sanitizePathInput((string) $_POST['source_path']);
}

// Normalize source_paths (multi-select from Local Agent file browser)
$sourcePaths = [];
if (isset($_POST['source_paths'])) {
    if (is_array($_POST['source_paths'])) {
        $sourcePaths = array_values(array_filter(array_map('strval', $_POST['source_paths']), fn($p) => trim($p) !== ''));
    } elseif (is_string($_POST['source_paths'])) {
        $raw = trim($_POST['source_paths']);
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);
        }
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sourcePaths = array_values(array_filter(array_map('strval', $decoded), fn($p) => trim($p) !== ''));
        } elseif ($raw !== '') {
            $sourcePaths = array_values(array_filter(array_map('trim', explode(';', $raw)), fn($p) => $p !== ''));
        }
    }
}
$sourcePaths = array_values(array_filter(array_map('sanitizePathInput', $sourcePaths), fn($p) => $p !== ''));
$primarySourcePath = $_POST['source_path'] ?? ($existingJob['source_path'] ?? '');
if (empty($sourcePaths) && $primarySourcePath !== '') {
    $sourcePaths[] = $primarySourcePath;
}
$hasSourcePathsJson = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_paths_json');
$hypervEnabled = isset($_POST['hyperv_enabled']) ? (int) $_POST['hyperv_enabled'] : 0;
$hypervConfigJson = normalizeJsonPayload($_POST['hyperv_config'] ?? null);
$hypervVmIds = decodeJsonArray($_POST['hyperv_vm_ids'] ?? null);
$hypervVms = decodeJsonArray($_POST['hyperv_vms'] ?? null);

// Validate agent assignment for local_agent jobs when provided/required
$hasAgentUuidJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_uuid');
$agentUuidForJob = null;
$isLocalAgentJob = (($sourceTypeForPath === 'local_agent') || (($existingJob['source_type'] ?? '') === 'local_agent'));
$policyDestination = null;
$repositoryRecord = null;
if (($sourceTypeForPath === 'local_agent' || isset($_POST['agent_uuid'])) && $hasAgentUuidJobs) {
    $agentUuidForJob = isset($_POST['agent_uuid']) ? trim((string) $_POST['agent_uuid']) : (string) ($existingJob['agent_uuid'] ?? '');
    if ($agentUuidForJob === '') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Agent is required for Local Agent jobs.'], 200);
        $response->send();
        exit();
    }
    $agentRow = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuidForJob)
        ->where('client_id', $loggedInUserId)
        ->where('status', 'active')
        ->first();
    if (!$agentRow) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Selected agent not found or inactive.'], 200);
        $response->send();
        exit();
    }
    $updateData['agent_uuid'] = $agentUuidForJob;
}
if ($isLocalAgentJob) {
    if (!$agentUuidForJob && !empty($existingJob['agent_uuid'])) {
        $agentUuidForJob = (string) $existingJob['agent_uuid'];
    }
    if ($agentUuidForJob === '') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Agent is required for Local Agent jobs.'], 200);
        $response->send();
        exit();
    }
    $destResult = CloudBackupBootstrapService::ensureAgentDestination((string) $agentUuidForJob);
    if (($destResult['status'] ?? 'fail') !== 'success') {
        $response = new JsonResponse(['status' => 'fail', 'message' => $destResult['message'] ?? 'Failed to resolve policy destination.'], 200);
        $response->send();
        exit();
    }
    $policyDestination = $destResult['destination'];

    if ($existingRepositoryId !== '') {
        $repositoryRecord = RepositoryService::getByRepositoryId($existingRepositoryId);
        if (!$repositoryRecord && RepositoryService::isFeatureReady()) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Repository identity could not be resolved for this job.'], 200);
            $response->send();
            exit();
        }
    } else {
        $repoRes = RepositoryService::createOrAttachForAgent(
            (int) ($agentRow->id ?? 0),
            (string) ($_POST['engine'] ?? ($existingJob['engine'] ?? 'kopia')),
            'managed_recovery',
            $loggedInUserId
        );
        $repoStatus = (string) ($repoRes['status'] ?? 'fail');
        if ($repoStatus === 'success') {
            $repositoryRecord = $repoRes['repository'] ?? null;
            if (!$repositoryRecord || empty($repositoryRecord->repository_id)) {
                $response = new JsonResponse(['status' => 'fail', 'message' => 'Repository identity could not be resolved for this job.'], 200);
                $response->send();
                exit();
            }
            if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id')) {
                $updateData['repository_id'] = (string) $repositoryRecord->repository_id;
            }
        } elseif ($repoStatus === 'skip' || !RepositoryService::isFeatureReady()) {
            logModuleCall('cloudstorage', 'cloudbackup_update_job_repository_fallback', [
                'client_id' => $loggedInUserId,
                'job_id' => $jobId,
                'agent_uuid' => (string) $agentUuidForJob,
            ], $repoRes);
            $repositoryRecord = null;
        } else {
            $response = new JsonResponse(['status' => 'fail', 'message' => $repoRes['message'] ?? 'Failed to initialize repository identity.'], 200);
            $response->send();
            exit();
        }
    }
}

if (isset($_POST['source_path'])) {
    // Do not overwrite an existing source_path with empty string (common when editing without revisiting Source step).
    $sp = trim((string)$_POST['source_path']);
    if ($sp !== '' || !empty($sourcePaths)) {
        $updateData['source_path'] = $_POST['source_path'];
    }
}
if ($hasSourcePathsJson && !empty($sourcePaths)) {
    $updateData['source_paths_json'] = json_encode($sourcePaths, JSON_UNESCAPED_SLASHES);
}
if ($isLocalAgentJob) {
    if ($policyDestination) {
        $policyBucketId = (int) ($policyDestination->dest_bucket_id ?? 0);
        $policyPrefix = (string) ($policyDestination->root_prefix ?? '');
        $lockedBucketId = $policyBucketId;
        $lockedPrefix = $policyPrefix;
        if ($repositoryRecord) {
            $lockedBucketId = (int) ($repositoryRecord->bucket_id ?? $policyBucketId);
            $lockedPrefix = (string) ($repositoryRecord->root_prefix ?? $policyPrefix);
            if ($policyBucketId !== $lockedBucketId || trim($policyPrefix, '/') !== trim($lockedPrefix, '/')) {
                $response = new JsonResponse(['status' => 'fail', 'message' => 'Agent destination no longer matches repository identity.'], 200);
                $response->send();
                exit();
            }
        }
        if (isset($_POST['dest_bucket_id']) && (int) $_POST['dest_bucket_id'] !== $lockedBucketId) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Destination bucket is policy-managed and cannot be edited.'], 200);
            $response->send();
            exit();
        }
        if (isset($_POST['dest_prefix']) && trim((string) $_POST['dest_prefix'], '/') !== trim($lockedPrefix, '/')) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Destination prefix is policy-managed and cannot be edited.'], 200);
            $response->send();
            exit();
        }
        $updateData['dest_bucket_id'] = $lockedBucketId;
        $updateData['dest_prefix'] = trim($lockedPrefix, '/');
        $updateData['s3_user_id'] = (int) ($policyDestination->s3_user_id ?? 0);
        if ($repositoryRecord && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id')) {
            $updateData['repository_id'] = (string) $repositoryRecord->repository_id;
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id')) {
            $updateData['tenant_id'] = $repositoryRecord && $repositoryRecord->tenant_id !== null
                ? (int) $repositoryRecord->tenant_id
                : ($policyDestination->tenant_id !== null ? (int) $policyDestination->tenant_id : null);
        }
    }
} else {
    if (isset($_POST['dest_bucket_id'])) {
        $updateData['dest_bucket_id'] = $_POST['dest_bucket_id'];
    }
    if (isset($_POST['dest_prefix'])) {
        // Destination prefix is optional; allow empty string.
        $updateData['dest_prefix'] = (string)$_POST['dest_prefix'];
    }
}
if (!$isLocalAgentJob && $existingRepositoryId !== '') {
    $repositoryRecord = RepositoryService::getByRepositoryId($existingRepositoryId);
    if (!$repositoryRecord && RepositoryService::isFeatureReady()) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Repository identity could not be resolved for this job.'], 200);
        $response->send();
        exit();
    }
    if ($repositoryRecord) {
        $lockedBucketId = (int) ($repositoryRecord->bucket_id ?? 0);
        $lockedPrefix = trim((string) ($repositoryRecord->root_prefix ?? ''), '/');
        if (isset($_POST['dest_bucket_id']) && (int) $_POST['dest_bucket_id'] !== $lockedBucketId) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Repository destination bucket is immutable.'], 200);
            $response->send();
            exit();
        }
        if (isset($_POST['dest_prefix']) && trim((string) $_POST['dest_prefix'], '/') !== $lockedPrefix) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Repository destination prefix is immutable.'], 200);
            $response->send();
            exit();
        }
        $updateData['dest_bucket_id'] = $lockedBucketId;
        $updateData['dest_prefix'] = $lockedPrefix;
        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id')) {
            $updateData['repository_id'] = (string) $repositoryRecord->repository_id;
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id')) {
            $updateData['tenant_id'] = $repositoryRecord->tenant_id !== null ? (int) $repositoryRecord->tenant_id : null;
        }
    }
}
if (isset($_POST['dest_local_path'])) {
    $updateData['dest_local_path'] = $_POST['dest_local_path'];
}
// Enforce S3-only destinations for now
if (isset($_POST['dest_type'])) {
    if ($_POST['dest_type'] !== 's3') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Only S3 destinations are supported at this time.'], 200);
        $response->send();
        exit();
    }
    $updateData['dest_type'] = 's3';
}
if (isset($_POST['bucket_auto_create'])) {
    $updateData['bucket_auto_create'] = isset($_POST['bucket_auto_create']) ? 1 : 0;
}
if (isset($_POST['backup_mode'])) {
    $updateData['backup_mode'] = $_POST['backup_mode'];
}
if (isset($_POST['engine'])) {
    $updateData['engine'] = $_POST['engine'];
}
if (($updateData['engine'] ?? '') === 'disk_image') {
    if ($diskSourceVolume === '') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Disk source volume is required for disk image backups.'], 200);
        $response->send();
        exit();
    }
    if ($diskImageFormat === '') {
        $diskImageFormat = 'vhdx';
    }
}
if (isset($_POST['encryption_enabled'])) {
    $updateData['encryption_enabled'] = (int)$_POST['encryption_enabled'];
}
if (isset($_POST['validation_mode'])) {
    $updateData['validation_mode'] = $_POST['validation_mode'];
}
if (isset($_POST['schedule_type'])) {
    $updateData['schedule_type'] = $_POST['schedule_type'];
}
if (isset($_POST['schedule_time'])) {
    $updateData['schedule_time'] = $_POST['schedule_time'];
}
if (isset($_POST['schedule_weekday'])) {
    $updateData['schedule_weekday'] = isset($_POST['schedule_weekday']) ? (int)$_POST['schedule_weekday'] : null;
}
if (isset($_POST['timezone'])) {
    $updateData['timezone'] = $_POST['timezone'];
}
if (isset($_POST['retention_mode'])) {
    $updateData['retention_mode'] = $_POST['retention_mode'];
}
if (isset($_POST['retention_value'])) {
    $updateData['retention_value'] = isset($_POST['retention_value']) ? (int)$_POST['retention_value'] : null;
}
if (isset($_POST['retention_json'])) {
    $norm = normalizeJsonString($_POST['retention_json']);
    if ($norm !== null) {
        $updateData['retention_json'] = $norm;
    }
}
if (isset($_POST['policy_json'])) {
    $norm = normalizeJsonString($_POST['policy_json']);
    if ($norm !== null) {
        $updateData['policy_json'] = $norm;
    }
}
if (isset($_POST['schedule_json'])) {
    $norm = normalizeJsonString($_POST['schedule_json']);
    if ($norm !== null) {
        $updateData['schedule_json'] = $norm;
    }
}
if (isset($_POST['bandwidth_limit_kbps'])) {
    $updateData['bandwidth_limit_kbps'] = isset($_POST['bandwidth_limit_kbps']) ? (int)$_POST['bandwidth_limit_kbps'] : null;
}
if (isset($_POST['parallelism'])) {
    $updateData['parallelism'] = isset($_POST['parallelism']) ? (int)$_POST['parallelism'] : null;
}
if (isset($_POST['encryption_mode'])) {
    $updateData['encryption_mode'] = $_POST['encryption_mode'];
}
if (isset($_POST['compression'])) {
    $updateData['compression'] = $_POST['compression'];
}
if (($updateData['engine'] ?? $existingJob['engine'] ?? '') === 'disk_image') {
    $updateData['disk_source_volume'] = $diskSourceVolume;
    $updateData['disk_image_format'] = $diskImageFormat;
    $updateData['disk_temp_dir'] = $diskTempDir;
}
if (($updateData['engine'] ?? '') === 'hyperv' || $hypervEnabled) {
    $updateData['hyperv_enabled'] = 1;
    if ($hypervConfigJson !== null) {
        $updateData['hyperv_config'] = $hypervConfigJson;
    }
}
if (isset($_POST['compression_enabled'])) {
    $updateData['compression_enabled'] = (int) $_POST['compression_enabled'];
}
if (isset($_POST['notify_override_email'])) {
    $updateData['notify_override_email'] = $_POST['notify_override_email'];
}
if (isset($_POST['notify_on_success'])) {
    $updateData['notify_on_success'] = isset($_POST['notify_on_success']) ? 1 : 0;
}
if (isset($_POST['notify_on_warning'])) {
    $updateData['notify_on_warning'] = isset($_POST['notify_on_warning']) ? 1 : 0;
}
if (isset($_POST['notify_on_failure'])) {
    $updateData['notify_on_failure'] = isset($_POST['notify_on_failure']) ? 1 : 0;
}
if (isset($_POST['status'])) {
    $updateData['status'] = $_POST['status'];
}

// --- Reconstruct/Merge source_config if needed ---
$postedSourceType = $_POST['source_type'] ?? $existingJob['source_type'] ?? null;
$reconstructed = null;

// Try to use provided source_config if valid JSON
if (isset($_POST['source_config']) && $_POST['source_config'] !== '') {
    $raw = $_POST['source_config'];
    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), trim($raw, "'\"")];
    foreach ($candidates as $cand) {
        $tmp = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $reconstructed = $tmp;
            break;
        }
    }
}

// Merge with existing decrypted config for missing secrets/fields or reconstruct from per-field inputs
try {
    $existingDec = CloudBackupController::decryptSourceConfig($existingJob, $encryptionKey);
    if (is_string($existingDec)) {
        $existingDec = json_decode($existingDec, true);
    }
    if (!is_array($existingDec)) {
        $existingDec = [];
    }
} catch (\Exception $e) {
    $existingDec = [];
}

if ($postedSourceType === 'aws') {
    $ak = $_POST['aws_access_key'] ?? $_POST['aws_access_key_id'] ?? null;
    $sk = $_POST['aws_secret_key'] ?? $_POST['aws_secret_access_key'] ?? null;
    $bucket = $_POST['aws_bucket'] ?? ($reconstructed['bucket'] ?? null) ?? ($existingDec['bucket'] ?? null);
    $region = $_POST['aws_region'] ?? ($reconstructed['region'] ?? null) ?? ($existingDec['region'] ?? null);
    $reconstructed = [
        'access_key' => (isset($ak) && $ak !== '') ? $ak : ($reconstructed['access_key'] ?? ($existingDec['access_key'] ?? '')),
        'secret_key' => (isset($sk) && $sk !== '') ? $sk : ($reconstructed['secret_key'] ?? ($existingDec['secret_key'] ?? '')),
        'bucket'     => $bucket,
        'region'     => $region,
    ];
} elseif ($postedSourceType === 's3_compatible') {
    $endpoint = $_POST['s3_endpoint'] ?? ($reconstructed['endpoint'] ?? ($existingDec['endpoint'] ?? null));
    $ak = $_POST['s3_access_key'] ?? null;
    $sk = $_POST['s3_secret_key'] ?? null;
    $bucket = $_POST['s3_bucket'] ?? ($reconstructed['bucket'] ?? ($existingDec['bucket'] ?? null));
    $region = $_POST['s3_region'] ?? ($reconstructed['region'] ?? ($existingDec['region'] ?? 'ca-central-1'));
    $reconstructed = [
        'endpoint'   => $endpoint,
        'access_key' => (isset($ak) && $ak !== '') ? $ak : ($reconstructed['access_key'] ?? ($existingDec['access_key'] ?? '')),
        'secret_key' => (isset($sk) && $sk !== '') ? $sk : ($reconstructed['secret_key'] ?? ($existingDec['secret_key'] ?? '')),
        'bucket'     => $bucket,
        'region'     => $region,
    ];
} elseif ($postedSourceType === 'sftp') {
    $host = $_POST['sftp_host'] ?? ($reconstructed['host'] ?? ($existingDec['host'] ?? null));
    $port = isset($_POST['sftp_port']) ? (int)$_POST['sftp_port'] : ($reconstructed['port'] ?? ($existingDec['port'] ?? 22));
    $user = $_POST['sftp_username'] ?? ($reconstructed['user'] ?? ($existingDec['user'] ?? null));
    $pass = $_POST['sftp_password'] ?? null;
    $reconstructed = [
        'host' => $host,
        'port' => $port,
        'user' => $user,
        'pass' => (isset($pass) && $pass !== '') ? $pass : ($reconstructed['pass'] ?? ($existingDec['pass'] ?? '')),
    ];
} elseif ($postedSourceType === 'google_drive') {
    // Switch to minimal config: only root_folder_id lives on the job
    $root = $_POST['gdrive_root_folder_id'] ?? $_POST['root_folder_id'] ?? ($reconstructed['root_folder_id'] ?? ($existingDec['root_folder_id'] ?? null));
    $team = $_POST['gdrive_team_drive'] ?? ($reconstructed['team_drive'] ?? ($existingDec['team_drive'] ?? null));
    $tmp = ['root_folder_id' => $root];
    if (!empty($team)) {
        $tmp['team_drive'] = $team;
    }
    $reconstructed = $tmp;
    // Allow changing linked connection
    if (isset($_POST['source_connection_id'])) {
        $updateData['source_connection_id'] = (int) $_POST['source_connection_id'];
    }
} elseif ($postedSourceType === 'dropbox') {
    $tok = $_POST['dropbox_token'] ?? null;
    $root = $_POST['dropbox_root'] ?? ($reconstructed['root'] ?? ($existingDec['root'] ?? null));
    $reconstructed = [
        'token' => (isset($tok) && $tok !== '') ? $tok : ($reconstructed['token'] ?? ($existingDec['token'] ?? '')),
        'root'  => $root,
    ];
} elseif ($postedSourceType === 'local_agent') {
    // Reconstruct local_agent source_config with network credentials
    $inc = $_POST['local_include_glob'] ?? ($reconstructed['include_glob'] ?? ($existingDec['include_glob'] ?? null));
    $exc = $_POST['local_exclude_glob'] ?? ($reconstructed['exclude_glob'] ?? ($existingDec['exclude_glob'] ?? null));
    $bw = isset($_POST['local_bandwidth_limit_kbps']) ? (int)$_POST['local_bandwidth_limit_kbps'] : ($reconstructed['bandwidth_limit_kbps'] ?? ($existingDec['bandwidth_limit_kbps'] ?? null));
    $reconstructed = [
        'include_glob' => $inc,
        'exclude_glob' => $exc,
        'bandwidth_limit_kbps' => $bw,
    ];

    // Handle network share credentials for UNC paths
    $netUser = $_POST['network_username'] ?? null;
    $netPass = $_POST['network_password'] ?? null;
    $netDomain = $_POST['network_domain'] ?? ($reconstructed['network_credentials']['domain'] ?? ($existingDec['network_credentials']['domain'] ?? ''));

    // Only update if new credentials provided, otherwise preserve existing
    if (!empty($netUser) && !empty($netPass)) {
        // Encrypt credentials before storage using HelperController
        $encryptedUser = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::encryptKey($netUser, $encryptionKey);
        $encryptedPass = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::encryptKey($netPass, $encryptionKey);
        $reconstructed['network_credentials'] = [
            'username' => $encryptedUser,
            'password' => $encryptedPass,
            'domain' => $netDomain,
        ];
    } elseif (isset($existingDec['network_credentials'])) {
        // Preserve existing encrypted credentials
        $reconstructed['network_credentials'] = $existingDec['network_credentials'];
    }
}

if (is_array($reconstructed)) {
    $updateData['source_config'] = $reconstructed;
}
// --- end merge ---

// Validate destination bucket if it's being changed
if (!$isLocalAgentJob && isset($_POST['dest_bucket_id'])) {
    $username = $product->username;
    $user = DBController::getUser($username);
    if (!$user) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Storage user not found'], 200);
        $response->send();
        exit();
    }
    $allowedUserIds = [$user->id];
    try {
        $childIds = Capsule::table('s3_users')->where('parent_id', $user->id)->pluck('id')->toArray();
        if (!empty($childIds)) {
            $allowedUserIds = array_values(array_unique(array_merge($allowedUserIds, $childIds)));
        }
    } catch (\Throwable $e) {}

    $bucket = Capsule::table('s3_buckets')
        ->where('id', $_POST['dest_bucket_id'])
        ->whereIn('user_id', $allowedUserIds)
        ->where('is_active', 1)
        ->first();
    if (!$bucket) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Destination bucket not found, inactive, or access denied'], 200);
        $response->send();
        exit();
    }
    // Keep job storage owner aligned with the bucket owner
    $updateData['s3_user_id'] = (int) $bucket->user_id;
}

// Validate AWS/S3-compatible source on update if present
if (in_array($postedSourceType, ['aws', 's3_compatible'], true) && is_array($reconstructed)) {
    $check = AwsS3Validator::validateBucketExists([
        'endpoint'   => $reconstructed['endpoint'] ?? null,
        'region'     => $reconstructed['region'] ?? 'us-east-1',
        'bucket'     => $reconstructed['bucket'] ?? '',
        'access_key' => $reconstructed['access_key'] ?? '',
        'secret_key' => $reconstructed['secret_key'] ?? '',
    ]);
    if (($check['status'] ?? 'fail') !== 'success') {
        $msg = 'Source bucket validation failed';
        if (!empty($check['message'])) {
            $msg .= ': ' . $check['message'];
        }
        $response = new JsonResponse(['status' => 'fail', 'message' => $msg], 200);
        $response->send();
        exit();
    }
}

// Validate Kopia retention policy for Local Agent / Kopia-family jobs when retention_json provided
if (isset($updateData['retention_json']) && $updateData['retention_json'] !== null && $updateData['retention_json'] !== '') {
    $effectiveSourceType = $updateData['source_type'] ?? $existingJob['source_type'] ?? '';
    $effectiveEngine = $updateData['engine'] ?? $existingJob['engine'] ?? '';
    $isKopiaFamily = ($effectiveSourceType === 'local_agent') || in_array($effectiveEngine, ['kopia', 'disk_image', 'hyperv'], true);
    if ($isKopiaFamily) {
        $decoded = json_decode($updateData['retention_json'], true);
        if (is_array($decoded)) {
            [$valid, $errors] = KopiaRetentionPolicyService::validate($decoded);
            if (!$valid) {
                $msg = !empty($errors) ? implode('; ', $errors) : 'Invalid retention policy';
                $response = new JsonResponse(['status' => 'fail', 'message' => $msg], 200);
                $response->send();
                exit();
            }
        }
    }
}

$result = CloudBackupController::updateJob($jobId, $loggedInUserId, $updateData, $encryptionKey);
if (is_array($result) && ($result['status'] ?? '') === 'success') {
    if ((($updateData['engine'] ?? ($existingJob['engine'] ?? '')) === 'hyperv')) {
        try {
            if (Capsule::schema()->hasTable('s3_hyperv_vms')) {
                $vmList = [];
                if (!empty($hypervVms)) {
                    foreach ($hypervVms as $vm) {
                        if (!is_array($vm)) {
                            continue;
                        }
                        $vmId = (string) ($vm['id'] ?? $vm['vm_guid'] ?? $vm['vm_id'] ?? '');
                        $vmName = (string) ($vm['name'] ?? $vm['vm_name'] ?? $vmId);
                        if ($vmId !== '') {
                            $vmList[] = ['id' => $vmId, 'name' => $vmName];
                        }
                    }
                }
                if (empty($vmList) && !empty($hypervVmIds)) {
                    foreach ($hypervVmIds as $vmId) {
                        $vmId = (string) $vmId;
                        if ($vmId !== '') {
                            $vmList[] = ['id' => $vmId, 'name' => $vmId];
                        }
                    }
                }
                if (!empty($vmList)) {
                    $jobIdDbExpr = UuidBinary::toDbExpr(UuidBinary::normalize($jobId));
                    Capsule::table('s3_hyperv_vms')
                        ->whereRaw('job_id = ' . $jobIdDbExpr)
                        ->update(['backup_enabled' => 0]);
                    foreach ($vmList as $vm) {
                        $existing = Capsule::table('s3_hyperv_vms')
                            ->whereRaw('job_id = ' . $jobIdDbExpr)
                            ->where('vm_guid', $vm['id'])
                            ->first();
                        if ($existing) {
                            Capsule::table('s3_hyperv_vms')
                                ->where('id', $existing->id)
                                ->update([
                                    'vm_name' => $vm['name'],
                                    'backup_enabled' => 1,
                                    'updated_at' => Capsule::raw('NOW()'),
                                ]);
                        } else {
                            Capsule::table('s3_hyperv_vms')->insert([
                                'job_id' => Capsule::raw($jobIdDbExpr),
                                'vm_name' => $vm['name'],
                                'vm_guid' => $vm['id'],
                                'backup_enabled' => 1,
                                'created_at' => Capsule::raw('NOW()'),
                                'updated_at' => Capsule::raw('NOW()'),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'update_job_hyperv_vms', ['job_id' => $jobId], $e->getMessage());
        }
    }
    $effSourceType = $updateData['source_type'] ?? ($existingJob['source_type'] ?? '');
    $effEngine = $updateData['engine'] ?? ($existingJob['engine'] ?? '');
    $effRepoId = $updateData['repository_id'] ?? ($existingJob['repository_id'] ?? '');
    if ($effSourceType === 'local_agent' && in_array($effEngine, ['kopia', 'disk_image', 'hyperv'], true) && trim((string) $effRepoId) !== '') {
        KopiaRetentionSourceService::ensureRepoSourceForJob($jobId);
    }
}

// Align bucket lifecycle with retention and enforce versioning when keep_days
if (is_array($result) && ($result['status'] ?? 'fail') === 'success') {
    // Determine the effective retention mode/value after update
    $newRetentionMode = $updateData['retention_mode'] ?? ($existingJob['retention_mode'] ?? 'none');
    $newRetentionValue = $updateData['retention_value'] ?? ($existingJob['retention_value'] ?? null);

    // Enforce bucket versioning on effective destination bucket
    $effectiveBucketId = isset($updateData['dest_bucket_id'])
        ? (int)$updateData['dest_bucket_id']
        : (int)($existingJob['dest_bucket_id'] ?? 0);
    if ($effectiveBucketId > 0) {
        $ver = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController::ensureVersioningForBucketId($effectiveBucketId);
        logModuleCall('cloudstorage', 'update_job_enforce_versioning', ['dest_bucket_id' => $effectiveBucketId], $ver);
        if (!is_array($ver) || ($ver['status'] ?? 'fail') !== 'success') {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Unable to enable bucket versioning.'], 200);
            $response->send();
            exit();
        }
    }

    // Destination prefix is optional (may be blank).

    try {
        $lcRes = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController::manageLifecycleForJob($jobId);
        if ($newRetentionMode === 'keep_days' && (int)$newRetentionValue > 0) {
            $lcStatus = is_array($lcRes) ? ($lcRes['status'] ?? 'fail') : 'fail';
            if ($lcStatus !== 'success') {
                $treatSkippedAsSuccess = false;
                if ($lcStatus === 'skipped') {
                    $effectiveSourceType = $updateData['source_type'] ?? ($existingJob['source_type'] ?? '');
                    $effectiveEngine = $updateData['engine'] ?? ($existingJob['engine'] ?? 'kopia');
                    $effectiveJob = ['source_type' => $effectiveSourceType, 'engine' => $effectiveEngine];
                    // Repo-native jobs (Local Agent / Kopia-family): lifecycle intentionally skips
                    // because retention is handled by agent-side Kopia; object-prefix rules do not apply.
                    if (!KopiaRetentionRoutingService::isCloudObjectRetentionJob($effectiveJob)) {
                        $treatSkippedAsSuccess = true;
                    }
                }
                if (!$treatSkippedAsSuccess) {
                    $msg = is_array($lcRes) ? ($lcRes['message'] ?? 'Failed to apply lifecycle policy') : 'Failed to apply lifecycle policy';
                    $result = ['status' => 'fail', 'message' => 'Unable to enforce Keep N days retention: ' . $msg];
                }
            }
        }
    } catch (\Throwable $e) {
        if ($newRetentionMode === 'keep_days' && (int)$newRetentionValue > 0) {
            $result = ['status' => 'fail', 'message' => 'Unable to enforce Keep N days retention.'];
        }
    }
}

$response = new JsonResponse($result, 200);
$response->send();
exit();

