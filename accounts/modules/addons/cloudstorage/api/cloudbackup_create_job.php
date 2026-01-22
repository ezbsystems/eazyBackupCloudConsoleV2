<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

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
if (!empty($sourcePaths)) {
    $primarySourcePath = $sourcePaths[0];
    $_POST['source_path'] = $primarySourcePath;
} elseif ($primarySourcePath !== '') {
    $sourcePaths[] = $primarySourcePath;
}

// Validate agent assignment for local_agent jobs when provided/required
$hasAgentIdJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');
$agentIdForJob = null;
if (($sourceType === 'local_agent' || isset($_POST['agent_id'])) && $hasAgentIdJobs) {
    $agentIdForJob = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    if ($agentIdForJob <= 0) {
        respondJson(['status' => 'fail', 'message' => 'Agent is required for Local Agent jobs.'], 200);
    }
    $agentRow = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentIdForJob)
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
    'dest_prefix' => $_POST['dest_prefix'] ?? '',
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

if ($agentIdForJob) {
    $jobData['agent_id'] = $agentIdForJob;
}
if (!empty($sourcePaths)) {
    $jobData['source_paths_json'] = json_encode($sourcePaths, JSON_UNESCAPED_SLASHES);
}
if ($diskSourceVolume !== '') {
    $jobData['disk_source_volume'] = $diskSourceVolume;
    $jobData['disk_image_format'] = $diskImageFormat;
    $jobData['disk_temp_dir'] = $diskTempDir;
}

$result = CloudBackupController::createJob($jobData, $encryptionKey);
respondJson($result, 200);
