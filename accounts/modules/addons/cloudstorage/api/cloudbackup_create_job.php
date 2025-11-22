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

$username = $product->username;
$user = DBController::getUser($username);

if (is_null($user)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'User not found.'
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

// Map per-source path inputs to a common source_path (path is optional across sources)
$sourceTypeForPath = $_POST['source_type'] ?? '';
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
    }
    $_POST['source_path'] = $mapped;
}

// Validate POST data (defer source_config validation to reconstruction step below)
// Note: source_path is optional (root if empty)
$requiredFields = ['name', 'source_type', 'source_display_name', 'dest_bucket_id', 'dest_prefix'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $jsonData = [
            'status' => 'fail',
            'message' => "Missing required field: {$field}"
        ];
        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }
}

// Reconstruct source_config if not provided or failed to decode
$sourceConfig = null;
if (isset($_POST['source_config']) && $_POST['source_config'] !== '') {
    $raw = $_POST['source_config'];
    // Attempt multiple decode strategies to handle different encodings/escaping
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sourceConfig = $decoded;
            break;
        }
    }
}

if (!$sourceConfig) {
    $sourceType = $_POST['source_type'];
    if ($sourceType === 'aws') {
        // Support either aws_access_key or aws_access_key_id naming; same for secret
        $ak = $_POST['aws_access_key'] ?? $_POST['aws_access_key_id'] ?? null;
        $sk = $_POST['aws_secret_key'] ?? $_POST['aws_secret_access_key'] ?? null;
        $bucket = $_POST['aws_bucket'] ?? null;
        $region = $_POST['aws_region'] ?? null;
        if ($ak && $sk && $bucket && $region) {
            $sourceConfig = [
                'access_key' => $ak,
                'secret_key' => $sk,
                'bucket'     => $bucket,
                'region'     => $region,
            ];
        }
    } elseif ($sourceType === 's3_compatible') {
        $endpoint = $_POST['s3_endpoint'] ?? null;
        $ak = $_POST['s3_access_key'] ?? null;
        $sk = $_POST['s3_secret_key'] ?? null;
        $bucket = $_POST['s3_bucket'] ?? null;
        $region = $_POST['s3_region'] ?? 'us-east-1';
        if ($endpoint && $ak && $sk && $bucket) {
            $sourceConfig = [
                'endpoint'   => $endpoint,
                'access_key' => $ak,
                'secret_key' => $sk,
                'bucket'     => $bucket,
                'region'     => $region,
            ];
        }
    } elseif ($sourceType === 'sftp') {
        $host = $_POST['sftp_host'] ?? null;
        $port = isset($_POST['sftp_port']) ? (int) $_POST['sftp_port'] : 22;
        $user = $_POST['sftp_username'] ?? null;
        $pass = $_POST['sftp_password'] ?? '';
        if ($host && $user) {
            $sourceConfig = [
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'pass' => $pass,
            ];
        }
    } elseif ($sourceType === 'google_drive') {
        // Require a saved Google Drive connection
        $sourceConnectionId = isset($_POST['source_connection_id']) ? (int) $_POST['source_connection_id'] : 0;
        if ($sourceConnectionId <= 0) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Missing Google Drive connection. Please connect Google Drive first.'
            ];
            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
        // Verify ownership and active status
        $conn = Capsule::table('s3_cloudbackup_sources')
            ->where('id', $sourceConnectionId)
            ->where('client_id', $loggedInUserId)
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->first();
        if (!$conn) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Google Drive connection not found or inactive.'
            ];
            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }
        // Minimal config stored on job (root folder if provided)
        $rootFolderId = $_POST['gdrive_root_folder_id'] ?? $_POST['root_folder_id'] ?? null;
        $sourceConfig = [
            'root_folder_id' => $rootFolderId,
        ];
        // Attach connection id for persistence
        $_POST['__resolved_source_connection_id'] = $sourceConnectionId;
    }
}

if (!$sourceConfig) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Invalid or missing source_config for the selected source_type.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Validate AWS/S3-compatible source bucket and credentials at save time
$st = $_POST['source_type'] ?? '';
if (in_array($st, ['aws', 's3_compatible'], true)) {
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
        $response = new JsonResponse(['status' => 'fail', 'message' => $msg], 200);
        $response->send();
        exit();
    }
}

// Verify destination bucket ownership
$bucket = Capsule::table('s3_buckets')
    ->where('id', $_POST['dest_bucket_id'])
    ->where('user_id', $user->id)
    ->where('is_active', 1)
    ->first();

if (!$bucket) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Destination bucket not found, inactive, or access denied.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Prepare job data
$jobData = [
    'client_id' => $loggedInUserId,
    's3_user_id' => $user->id,
    'name' => $_POST['name'],
    'source_type' => $_POST['source_type'],
    'source_display_name' => $_POST['source_display_name'],
    'source_config' => $sourceConfig,
    'source_path' => $_POST['source_path'] ?? '',
    'dest_bucket_id' => $_POST['dest_bucket_id'],
    'dest_prefix' => $_POST['dest_prefix'],
    'backup_mode' => $_POST['backup_mode'] ?? 'sync',
    'encryption_enabled' => isset($_POST['encryption_enabled']) ? (int)$_POST['encryption_enabled'] : 0,
    'validation_mode' => $_POST['validation_mode'] ?? 'none',
    'schedule_type' => $_POST['schedule_type'] ?? 'manual',
    'schedule_time' => $_POST['schedule_time'] ?? null,
    'schedule_weekday' => isset($_POST['schedule_weekday']) ? (int)$_POST['schedule_weekday'] : null,
    'timezone' => $_POST['timezone'] ?? null,
    'retention_mode' => $_POST['retention_mode'] ?? 'none',
    'retention_value' => isset($_POST['retention_value']) ? (int)$_POST['retention_value'] : null,
    'notify_override_email' => $_POST['notify_override_email'] ?? null,
    'notify_on_success' => isset($_POST['notify_on_success']) ? 1 : 0,
    'notify_on_warning' => isset($_POST['notify_on_warning']) ? 1 : 0,
    'notify_on_failure' => isset($_POST['notify_on_failure']) ? 1 : 0,
];
if (($_POST['source_type'] ?? '') === 'google_drive') {
    $jobData['source_connection_id'] = (int) ($_POST['__resolved_source_connection_id'] ?? ($_POST['source_connection_id'] ?? 0));
}

$result = CloudBackupController::createJob($jobData, $encryptionKey);

$response = new JsonResponse($result, 200);
$response->send();
exit();

