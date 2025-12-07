<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\AwsS3Validator;
use WHMCS\Database\Capsule;

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

$jobId = $_POST['job_id'] ?? null;
if (!$jobId) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Job ID is required.'
    ];
    $response = new JsonResponse($jsonData, 200);
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
$existingJob = CloudBackupController::getJob((int)$jobId, $loggedInUserId);
if (!$existingJob) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Job not found or access denied'], 200);
    $response->send();
    exit();
}

// Prepare update data
$updateData = [];
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

// Validate agent assignment for local_agent jobs when provided/required
$hasAgentIdJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');
$agentIdForJob = null;
if (($sourceTypeForPath === 'local_agent' || isset($_POST['agent_id'])) && $hasAgentIdJobs) {
    $agentIdForJob = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : ($existingJob['agent_id'] ?? 0);
    if ($agentIdForJob <= 0) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Agent is required for Local Agent jobs.'], 200);
        $response->send();
        exit();
    }
    $agentRow = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentIdForJob)
        ->where('client_id', $loggedInUserId)
        ->where('status', 'active')
        ->first();
    if (!$agentRow) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Selected agent not found or inactive.'], 200);
        $response->send();
        exit();
    }
    $updateData['agent_id'] = $agentIdForJob;
}

if (isset($_POST['source_path'])) {
    $updateData['source_path'] = $_POST['source_path'];
}
if (isset($_POST['dest_bucket_id'])) {
    $updateData['dest_bucket_id'] = $_POST['dest_bucket_id'];
}
if (isset($_POST['dest_prefix'])) {
    $updateData['dest_prefix'] = $_POST['dest_prefix'];
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
    $updateData['retention_json'] = is_array($_POST['retention_json']) ? json_encode($_POST['retention_json']) : $_POST['retention_json'];
}
if (isset($_POST['policy_json'])) {
    $updateData['policy_json'] = is_array($_POST['policy_json']) ? json_encode($_POST['policy_json']) : $_POST['policy_json'];
}
if (isset($_POST['schedule_json'])) {
    $updateData['schedule_json'] = is_array($_POST['schedule_json']) ? json_encode($_POST['schedule_json']) : $_POST['schedule_json'];
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
}

if (is_array($reconstructed)) {
    $updateData['source_config'] = $reconstructed;
}
// --- end merge ---

// Validate destination bucket if it's being changed
if (isset($_POST['dest_bucket_id'])) {
    $username = $product->username;
    $user = DBController::getUser($username);
    if (!$user) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Storage user not found'], 200);
        $response->send();
        exit();
    }
    $bucket = Capsule::table('s3_buckets')
        ->where('id', $_POST['dest_bucket_id'])
        ->where('user_id', $user->id)
        ->where('is_active', 1)
        ->first();
    if (!$bucket) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Destination bucket not found, inactive, or access denied'], 200);
        $response->send();
        exit();
    }
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

$result = CloudBackupController::updateJob($jobId, $loggedInUserId, $updateData, $encryptionKey);

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

    // Enforce destination prefix required: use posted or existing
    $effectivePrefix = isset($updateData['dest_prefix'])
        ? trim((string)$updateData['dest_prefix'])
        : trim((string)($existingJob['dest_prefix'] ?? ''));
    if ($effectivePrefix === '') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Destination Prefix is required.'], 200);
        $response->send();
        exit();
    }

    try {
        $lcRes = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController::manageLifecycleForJob((int)$jobId);
        if ($newRetentionMode === 'keep_days' && (int)$newRetentionValue > 0) {
            if (!is_array($lcRes) || ($lcRes['status'] ?? 'fail') !== 'success') {
                $msg = $lcRes['message'] ?? 'Failed to apply lifecycle policy';
                $result = ['status' => 'fail', 'message' => 'Unable to enforce Keep N days retention: ' . $msg];
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

