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
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\AwsS3Validator;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupBootstrapService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionSourceService;
use WHMCS\Module\Addon\CloudStorage\Client\RepositoryService;
use WHMCS\Database\Capsule;

function respondJson(array $data, int $status = 200): void
{
    $response = new JsonResponse($data, $status);
    $response->send();
    exit();
}

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
    respondJson(['status' => 'fail', 'message' => 'Session timeout.'], 200);
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    respondJson(['status' => 'fail', 'message' => 'Product not found.'], 200);
}

// Get encryption key from module config
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);
if (count($module) == 0) {
    respondJson(['status' => 'fail', 'message' => 'Cloudstorage module configuration not found.'], 200);
}
$encryptionKey = $module->where('setting', 'cloudbackup_encryption_key')->pluck('value')->first();
if (empty($encryptionKey)) {
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
}
if (empty($encryptionKey)) {
    respondJson(['status' => 'fail', 'message' => 'Encryption key not configured.'], 200);
}

$sourceType = $_POST['source_type'] ?? '';
$name = trim($_POST['name'] ?? '');
$sourceDisplayName = trim($_POST['source_display_name'] ?? '');
$tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : 0;

if ($name === '') {
    respondJson(['status' => 'fail', 'message' => 'Job name is required.'], 200);
}
if ($sourceType === '') {
    respondJson(['status' => 'fail', 'message' => 'Source type is required.'], 200);
}

// Map per-source path inputs for consistency
if (!isset($_POST['source_path']) || $_POST['source_path'] === '') {
    $mapped = '';
    if ($sourceType === 'aws') {
        $mapped = $_POST['aws_path'] ?? '';
    } elseif ($sourceType === 's3_compatible') {
        $mapped = $_POST['s3_path'] ?? '';
    } elseif ($sourceType === 'sftp') {
        $mapped = $_POST['sftp_path'] ?? '';
    } elseif ($sourceType === 'google_drive') {
        $mapped = $_POST['gdrive_path'] ?? '';
    } elseif ($sourceType === 'dropbox') {
        $mapped = $_POST['dropbox_path'] ?? '';
    } elseif ($sourceType === 'local_agent') {
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
$primarySourcePath = $_POST['source_path'] ?? '';
if (empty($sourcePaths) && $primarySourcePath !== '') {
    $sourcePaths[] = $primarySourcePath;
}

// Validate agent assignment for local_agent jobs when provided/required
$hasAgentUuidJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_uuid');
$agentUuidForJob = null;
$agentRow = null;
if (($sourceType === 'local_agent' || isset($_POST['agent_uuid'])) && $hasAgentUuidJobs) {
    $agentUuidForJob = trim((string) ($_POST['agent_uuid'] ?? ''));
    if ($agentUuidForJob === '') {
        respondJson(['status' => 'fail', 'message' => 'Agent is required for Local Agent jobs.'], 200);
    }
    $agentRow = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuidForJob)
        ->where('client_id', $loggedInUserId)
        ->where('status', 'active')
        ->first();
    if (!$agentRow) {
        respondJson(['status' => 'fail', 'message' => 'Selected agent not found or inactive.'], 200);
    }
    if (MspController::isMspClient($loggedInUserId) && $tenantId) {
        $tenant = MspController::getTenant($tenantId, $loggedInUserId);
        if (!$tenant) {
            respondJson(['status' => 'fail', 'message' => 'Tenant not found.'], 200);
        }
        if ((int) $agentRow->tenant_id !== (int) $tenantId) {
            respondJson(['status' => 'fail', 'message' => 'Agent does not belong to tenant.'], 200);
        }
    }
}

// Enforce S3-only destinations for now
if (isset($_POST['dest_type']) && $_POST['dest_type'] !== 's3') {
    respondJson(['status' => 'fail', 'message' => 'Only S3 destinations are supported at this time.'], 200);
}

$destBucketId = 0;
$destPrefix = '';
$s3UserId = 0;
$repositoryId = null;
$resolvedTenantId = $tenantId > 0 ? $tenantId : null;
if ($sourceType === 'local_agent') {
    if (!$agentUuidForJob) {
        respondJson(['status' => 'fail', 'message' => 'Agent is required for Local Agent jobs.'], 200);
    }
    $destResult = CloudBackupBootstrapService::ensureAgentDestination((string) $agentUuidForJob);
    if (($destResult['status'] ?? 'fail') !== 'success') {
        respondJson(['status' => 'fail', 'message' => $destResult['message'] ?? 'Failed to resolve policy destination.'], 200);
    }
    $dest = $destResult['destination'];
    $destBucketId = (int) ($dest->dest_bucket_id ?? 0);
    $destPrefix = (string) ($dest->root_prefix ?? '');
    $s3UserId = (int) ($dest->s3_user_id ?? 0);
    $resolvedTenantId = $dest->tenant_id !== null ? (int) $dest->tenant_id : ($agentRow->tenant_id ?? null);

    if ($destBucketId <= 0 || $s3UserId <= 0) {
        respondJson(['status' => 'fail', 'message' => 'Invalid policy destination mapping for agent.'], 200);
    }

    $repoResult = RepositoryService::createOrAttachForAgent(
        (int) ($agentRow->id ?? 0),
        (string) ($_POST['engine'] ?? 'kopia'),
        'managed_recovery',
        $loggedInUserId
    );
    $repoStatus = (string) ($repoResult['status'] ?? 'fail');
    if ($repoStatus === 'success') {
        $repository = $repoResult['repository'] ?? null;
        if (!$repository || empty($repository->repository_id)) {
            respondJson(['status' => 'fail', 'message' => 'Repository identity could not be resolved.'], 200);
        }
        $repositoryId = (string) $repository->repository_id;
        $destBucketId = (int) ($repository->bucket_id ?? $destBucketId);
        $destPrefix = (string) ($repository->root_prefix ?? $destPrefix);
        if ($repository->tenant_id !== null) {
            $resolvedTenantId = (int) $repository->tenant_id;
        }
    } elseif ($repoStatus === 'skip' || !RepositoryService::isFeatureReady()) {
        // Compatibility mode for partially upgraded environments: keep job creation working.
        logModuleCall('cloudstorage', 'cloudbackup_create_job_repository_fallback', [
            'client_id' => $loggedInUserId,
            'agent_uuid' => (string) $agentUuidForJob,
        ], $repoResult);
    } else {
        respondJson(['status' => 'fail', 'message' => $repoResult['message'] ?? 'Failed to initialize repository identity.'], 200);
    }
} else {
    $destBucketId = isset($_POST['dest_bucket_id']) ? (int) $_POST['dest_bucket_id'] : 0;
    if ($destBucketId <= 0) {
        respondJson(['status' => 'fail', 'message' => 'Destination bucket is required.'], 200);
    }

    // Resolve storage user and validate bucket ownership (parent + tenants)
    $username = $product->username;
    $user = DBController::getUser($username);
    if (!$user) {
        respondJson(['status' => 'fail', 'message' => 'Storage user not found'], 200);
    }
    $allowedUserIds = [$user->id];
    try {
        $childIds = Capsule::table('s3_users')->where('parent_id', $user->id)->pluck('id')->toArray();
        if (!empty($childIds)) {
            $allowedUserIds = array_values(array_unique(array_merge($allowedUserIds, $childIds)));
        }
    } catch (\Throwable $e) {}

    $bucket = Capsule::table('s3_buckets')
        ->where('id', $destBucketId)
        ->whereIn('user_id', $allowedUserIds)
        ->where('is_active', 1)
        ->first();
    if (!$bucket) {
        respondJson(['status' => 'fail', 'message' => 'Destination bucket not found, inactive, or access denied.'], 200);
    }
    $s3UserId = (int) $bucket->user_id;
    $destPrefix = isset($_POST['dest_prefix']) ? (string) $_POST['dest_prefix'] : '';
}

// Build or decode source_config
$sourceConfig = null;
if (isset($_POST['source_config']) && $_POST['source_config'] !== '') {
    $raw = $_POST['source_config'];
    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), trim($raw, "'\"")];
    foreach ($candidates as $cand) {
        $tmp = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $sourceConfig = $tmp;
            break;
        }
    }
    if ($sourceConfig === null) {
        respondJson(['status' => 'fail', 'message' => 'Invalid source configuration.'], 200);
    }
}

if ($sourceConfig === null && $sourceType === 'local_agent') {
    $bw = isset($_POST['local_bandwidth_limit_kbps']) ? (int)$_POST['local_bandwidth_limit_kbps'] : null;
    $sourceConfig = [
        'include_glob' => $_POST['local_include_glob'] ?? null,
        'exclude_glob' => $_POST['local_exclude_glob'] ?? null,
        'bandwidth_limit_kbps' => $bw,
    ];
    $netUser = $_POST['network_username'] ?? null;
    $netPass = $_POST['network_password'] ?? null;
    $netDomain = $_POST['network_domain'] ?? '';
    if (!empty($netUser) && !empty($netPass)) {
        $encryptedUser = HelperController::encryptKey($netUser, $encryptionKey);
        $encryptedPass = HelperController::encryptKey($netPass, $encryptionKey);
        $sourceConfig['network_credentials'] = [
            'username' => $encryptedUser,
            'password' => $encryptedPass,
            'domain' => $netDomain,
        ];
    }
}

if ($sourceConfig === null) {
    $sourceConfig = [];
}

$hypervEnabled = isset($_POST['hyperv_enabled']) ? (int) $_POST['hyperv_enabled'] : 0;
$hypervConfigJson = normalizeJsonPayload($_POST['hyperv_config'] ?? null);
$hypervVmIds = decodeJsonArray($_POST['hyperv_vm_ids'] ?? null);
$hypervVms = decodeJsonArray($_POST['hyperv_vms'] ?? null);

// Validate AWS/S3-compatible source if provided
if (in_array($sourceType, ['aws', 's3_compatible'], true) && is_array($sourceConfig)) {
    $check = AwsS3Validator::validateBucketExists([
        'endpoint'   => $sourceConfig['endpoint'] ?? null,
        'region'     => $sourceConfig['region'] ?? 'us-east-1',
        'bucket'     => $sourceConfig['bucket'] ?? '',
        'access_key' => $sourceConfig['access_key'] ?? '',
        'secret_key' => $sourceConfig['secret_key'] ?? '',
    ]);
    if (($check['status'] ?? 'fail') !== 'success') {
        $msg = 'Source bucket validation failed';
        if (!empty($check['message'])) {
            $msg .= ': ' . $check['message'];
        }
        respondJson(['status' => 'fail', 'message' => $msg], 200);
    }
}

// Disk image fields (optional)
$diskSourceVolume = $_POST['disk_source_volume'] ?? '';
$diskImageFormat = $_POST['disk_image_format'] ?? 'vhdx';
$diskTempDir = $_POST['disk_temp_dir'] ?? '';

if (($_POST['engine'] ?? '') === 'disk_image') {
    if ($diskSourceVolume === '') {
        respondJson(['status' => 'fail', 'message' => 'Disk source volume is required for disk image backups.'], 200);
    }
    if ($diskImageFormat === '') {
        $diskImageFormat = 'vhdx';
    }
    if (empty($_POST['source_path'])) {
        $_POST['source_path'] = $diskSourceVolume;
    }
}

$jobData = [
    'client_id' => $loggedInUserId,
    's3_user_id' => $s3UserId,
    'name' => $name,
    'source_type' => $sourceType,
    'source_display_name' => $sourceDisplayName !== '' ? $sourceDisplayName : $name,
    'source_config' => $sourceConfig,
    'source_connection_id' => isset($_POST['source_connection_id']) ? (int)$_POST['source_connection_id'] : null,
    'source_path' => $_POST['source_path'] ?? '',
    'dest_bucket_id' => $destBucketId,
    'dest_prefix' => $destPrefix,
    'backup_mode' => $_POST['backup_mode'] ?? 'sync',
    'engine' => $_POST['engine'] ?? 'sync',
    'dest_type' => 's3',
    'dest_local_path' => $_POST['dest_local_path'] ?? null,
    'bucket_auto_create' => isset($_POST['bucket_auto_create']) ? 1 : 0,
    'schedule_type' => $_POST['schedule_type'] ?? 'manual',
    'schedule_time' => $_POST['schedule_time'] ?? null,
    'schedule_weekday' => isset($_POST['schedule_weekday']) ? (int)$_POST['schedule_weekday'] : null,
    'schedule_cron' => $_POST['schedule_cron'] ?? null,
    'schedule_json' => normalizeJsonString($_POST['schedule_json'] ?? null),
    'timezone' => $_POST['timezone'] ?? null,
    'encryption_enabled' => isset($_POST['encryption_enabled']) ? (int)$_POST['encryption_enabled'] : 0,
    'compression_enabled' => isset($_POST['compression_enabled']) ? (int)$_POST['compression_enabled'] : 0,
    'validation_mode' => $_POST['validation_mode'] ?? 'none',
    'retention_mode' => $_POST['retention_mode'] ?? 'none',
    'retention_value' => isset($_POST['retention_value']) ? (int)$_POST['retention_value'] : null,
    'retention_json' => normalizeJsonString($_POST['retention_json'] ?? null),
    'policy_json' => normalizeJsonString($_POST['policy_json'] ?? null),
    'bandwidth_limit_kbps' => isset($_POST['bandwidth_limit_kbps']) ? (int)$_POST['bandwidth_limit_kbps'] : null,
    'parallelism' => isset($_POST['parallelism']) ? (int)$_POST['parallelism'] : null,
    'encryption_mode' => $_POST['encryption_mode'] ?? null,
    'compression' => $_POST['compression'] ?? null,
    'notify_override_email' => $_POST['notify_override_email'] ?? null,
    'notify_on_success' => isset($_POST['notify_on_success']) ? 1 : 0,
    'notify_on_warning' => isset($_POST['notify_on_warning']) ? 1 : 0,
    'notify_on_failure' => isset($_POST['notify_on_failure']) ? 1 : 0,
    'status' => $_POST['status'] ?? 'active',
];

if ($agentUuidForJob) {
    $jobData['agent_uuid'] = $agentUuidForJob;
}
if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id')) {
    $jobData['tenant_id'] = $resolvedTenantId;
}
if ($repositoryId !== null && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id')) {
    $jobData['repository_id'] = $repositoryId;
}
if (!empty($sourcePaths)) {
    $jobData['source_paths_json'] = json_encode($sourcePaths, JSON_UNESCAPED_SLASHES);
}
if ($diskSourceVolume !== '') {
    $jobData['disk_source_volume'] = $diskSourceVolume;
    $jobData['disk_image_format'] = $diskImageFormat;
    $jobData['disk_temp_dir'] = $diskTempDir;
}
if (($jobData['engine'] ?? '') === 'hyperv' || $hypervEnabled) {
    $jobData['hyperv_enabled'] = 1;
    if ($hypervConfigJson !== null) {
        $jobData['hyperv_config'] = $hypervConfigJson;
    }
}

// Validate Kopia retention policy for Local Agent / Kopia-family jobs when retention_json provided
$retentionJson = $jobData['retention_json'] ?? null;
if ($retentionJson !== null && $retentionJson !== '') {
    $isKopiaFamily = ($sourceType === 'local_agent') || in_array($jobData['engine'] ?? '', ['kopia', 'disk_image', 'hyperv'], true);
    if ($isKopiaFamily) {
        $decoded = json_decode($retentionJson, true);
        if (is_array($decoded)) {
            [$valid, $errors] = KopiaRetentionPolicyService::validate($decoded);
            if (!$valid) {
                $msg = !empty($errors) ? implode('; ', $errors) : 'Invalid retention policy';
                respondJson(['status' => 'fail', 'message' => $msg], 200);
            }
        }
    }
}

$result = CloudBackupController::createJob($jobData, $encryptionKey);
if (is_array($result) && ($result['status'] ?? '') === 'success') {
    $jobId = (int) ($result['job_id'] ?? 0);
    if ($jobId > 0 && ($jobData['engine'] ?? '') === 'hyperv') {
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
                    Capsule::table('s3_hyperv_vms')
                        ->where('job_id', $jobId)
                        ->update(['backup_enabled' => 0]);
                    foreach ($vmList as $vm) {
                        $existing = Capsule::table('s3_hyperv_vms')
                            ->where('job_id', $jobId)
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
                                'job_id' => $jobId,
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
            logModuleCall('cloudstorage', 'create_job_hyperv_vms', ['job_id' => $jobId], $e->getMessage());
        }
    }
    if ($jobId > 0 && $sourceType === 'local_agent' && in_array($jobData['engine'] ?? '', ['kopia', 'disk_image', 'hyperv'], true) && !empty($repositoryId)) {
        KopiaRetentionSourceService::ensureRepoSourceForJob($jobId);
    }
}
respondJson($result, 200);
