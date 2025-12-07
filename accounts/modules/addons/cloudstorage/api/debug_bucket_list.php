<?php
/**
 * Diagnostic endpoint to identify bucket listing bottlenecks.
 * This bypasses most WHMCS overhead to isolate the issue.
 * 
 * Usage: POST with bucket, username parameters
 * DELETE THIS FILE AFTER DEBUGGING
 */

// Timing helper
function elapsed($start) {
    return round((microtime(true) - $start) * 1000, 2) . 'ms';
}

$totalStart = microtime(true);
$timings = [];

// Step 1: Basic PHP setup (no WHMCS)
$step1 = microtime(true);
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('max_execution_time', 30);
$timings['1_php_setup'] = elapsed($step1);

// Step 2: Load WHMCS init (this starts session)
$step2 = microtime(true);
require_once __DIR__ . '/../../../../init.php';
$timings['2_whmcs_init'] = elapsed($step2);

if (!defined("WHMCS")) {
    die(json_encode(['error' => 'WHMCS not loaded']));
}

// Step 3: Close session IMMEDIATELY after init
$step3 = microtime(true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$timings['3_session_close'] = elapsed($step3);

// Step 4: Get POST params
$step4 = microtime(true);
$bucketName = $_POST['bucket'] ?? $_GET['bucket'] ?? '';
$username = $_POST['username'] ?? $_GET['username'] ?? '';
$timings['4_params'] = elapsed($step4);

if (empty($bucketName)) {
    echo json_encode(['error' => 'bucket param required', 'timings' => $timings]);
    exit;
}

// Step 5: Load required classes
$step5 = microtime(true);
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use Aws\S3\S3Client;
$timings['5_use_statements'] = elapsed($step5);

// Step 6: Get module settings from DB
$step6 = microtime(true);
try {
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->pluck('value', 'setting')
        ->toArray();
    $timings['6_db_settings'] = elapsed($step6);
} catch (Exception $e) {
    echo json_encode(['error' => 'DB query failed: ' . $e->getMessage(), 'timings' => $timings]);
    exit;
}

$s3Endpoint = $settings['s3_endpoint'] ?? '';
$encryptionKey = $settings['encryption_key'] ?? '';
$s3Region = $settings['s3_region'] ?? 'us-east-1';

// Step 7: Find user and get credentials
$step7 = microtime(true);
try {
    // Find user by username or use provided username
    $user = Capsule::table('s3_users')->where('username', $username)->first();
    if (!$user) {
        // Try to find any user with access to this bucket
        $bucket = Capsule::table('s3_buckets')->where('name', $bucketName)->first();
        if ($bucket) {
            $user = Capsule::table('s3_users')->where('id', $bucket->user_id)->first();
        }
    }
    
    if (!$user) {
        echo json_encode(['error' => 'User not found', 'timings' => $timings]);
        exit;
    }
    
    $userKeys = Capsule::table('s3_user_access_keys')->where('user_id', $user->id)->first();
    if (!$userKeys) {
        echo json_encode(['error' => 'User has no access keys', 'timings' => $timings]);
        exit;
    }
    $timings['7_user_lookup'] = elapsed($step7);
} catch (Exception $e) {
    echo json_encode(['error' => 'User lookup failed: ' . $e->getMessage(), 'timings' => $timings]);
    exit;
}

// Step 8: Decrypt credentials
$step8 = microtime(true);
try {
    $accessKey = HelperController::decryptKey($userKeys->access_key, $encryptionKey);
    $secretKey = HelperController::decryptKey($userKeys->secret_key, $encryptionKey);
    $timings['8_decrypt_keys'] = elapsed($step8);
} catch (Exception $e) {
    echo json_encode(['error' => 'Key decryption failed: ' . $e->getMessage(), 'timings' => $timings]);
    exit;
}

// Step 9: Create S3 Client (this is often slow due to SDK initialization)
$step9 = microtime(true);
try {
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1', // Force us-east-1 for non-AWS endpoints
        'endpoint' => $s3Endpoint,
        'credentials' => [
            'key' => $accessKey,
            'secret' => $secretKey,
        ],
        'use_path_style_endpoint' => true,
        'signature_version' => 'v4',
        'http' => [
            'connect_timeout' => 3.0,
            'timeout' => 5.0,
        ],
    ]);
    $timings['9_s3_client_create'] = elapsed($step9);
} catch (Exception $e) {
    echo json_encode(['error' => 'S3 client creation failed: ' . $e->getMessage(), 'timings' => $timings]);
    exit;
}

// Step 10: Make the actual S3 list call
$step10 = microtime(true);
try {
    $result = $s3Client->listObjectsV2([
        'Bucket' => $bucketName,
        'MaxKeys' => 10, // Just 10 objects for testing
        'Delimiter' => '/'
    ]);
    $timings['10_s3_list_call'] = elapsed($step10);
    
    $objects = [];
    if (isset($result['Contents'])) {
        foreach ($result['Contents'] as $obj) {
            $objects[] = [
                'key' => $obj['Key'],
                'size' => $obj['Size'] ?? 0,
            ];
        }
    }
    $folders = [];
    if (isset($result['CommonPrefixes'])) {
        foreach ($result['CommonPrefixes'] as $prefix) {
            $folders[] = $prefix['Prefix'];
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'S3 list failed: ' . $e->getMessage(),
        'error_class' => get_class($e),
        'timings' => $timings
    ]);
    exit;
}

$timings['total'] = elapsed($totalStart);

echo json_encode([
    'status' => 'success',
    'bucket' => $bucketName,
    'endpoint' => $s3Endpoint,
    'objects_count' => count($objects),
    'folders_count' => count($folders),
    'objects' => $objects,
    'folders' => $folders,
    'is_truncated' => $result['IsTruncated'] ?? false,
    'timings' => $timings,
    'timings_summary' => 'All times in milliseconds'
]);

