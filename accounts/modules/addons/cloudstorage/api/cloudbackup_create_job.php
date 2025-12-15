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

// Normalize/force local_agent when agent_id is provided (defensive server-side guard)
$sourceTypeForPath = $_POST['source_type'] ?? '';
$engineForJob = $_POST['engine'] ?? '';
$agentIdPosted = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
// Disk image specific
$diskSourceVolume = $_POST['disk_source_volume'] ?? '';
$diskImageFormat = $_POST['disk_image_format'] ?? 'vhdx';
$diskTempDir = $_POST['disk_temp_dir'] ?? '';
if ($agentIdPosted > 0) {
    // If source_type missing/blank or a legacy value, force to local_agent
    if ($sourceTypeForPath === '' || $sourceTypeForPath === 'local') {
        $sourceTypeForPath = 'local_agent';
        $_POST['source_type'] = 'local_agent';
    }
    // Kopia engine currently only supported for local agent; enforce to avoid worker pickup
    if ($engineForJob === 'kopia' && $sourceTypeForPath !== 'local_agent') {
        $sourceTypeForPath = 'local_agent';
        $_POST['source_type'] = 'local_agent';
    }
}

// Map per-source path inputs to a common source_path (path is optional across sources)
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

// Validate agent assignment for local_agent jobs
$agentIdForJob = null;
$agentRow = null;
$hasAgentIdJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');
if (($sourceTypeForPath ?? '') === 'local_agent' && $hasAgentIdJobs) {
    $agentIdForJob = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
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
    
    // MSP Validation: If MSP client, validate agent belongs to the expected tenant context
    require_once __DIR__ . '/../lib/Client/MspController.php';
    $isMspClient = \WHMCS\Module\Addon\CloudStorage\Client\MspController::isMspClient($loggedInUserId);
    
    if ($isMspClient && $agentRow) {
        $agentTenantId = $agentRow->tenant_id ?? null;
        
        // If a tenant was explicitly selected in the wizard, validate consistency
        $wizardTenantId = isset($_POST['wizard_tenant_id']) && $_POST['wizard_tenant_id'] !== '' 
            ? ((int)$_POST['wizard_tenant_id'] ?: null) 
            : null;
        
        // Handle "direct" selection (agents with no tenant)
        if (isset($_POST['wizard_tenant_id']) && $_POST['wizard_tenant_id'] === 'direct') {
            if ($agentTenantId !== null) {
                $response = new JsonResponse([
                    'status' => 'fail', 
                    'message' => 'Selected agent belongs to a tenant but "Direct" was chosen. Please select an agent without a tenant.'
                ], 200);
                $response->send();
                exit();
            }
        } elseif ($wizardTenantId !== null) {
            // Validate the tenant belongs to this MSP
            $tenant = \WHMCS\Module\Addon\CloudStorage\Client\MspController::getTenant($wizardTenantId, $loggedInUserId);
            if (!$tenant) {
                $response = new JsonResponse([
                    'status' => 'fail', 
                    'message' => 'Invalid tenant selected.'
                ], 200);
                $response->send();
                exit();
            }
            
            // Validate agent belongs to the selected tenant
            if ((int)$agentTenantId !== $wizardTenantId) {
                $response = new JsonResponse([
                    'status' => 'fail', 
                    'message' => 'Selected agent does not belong to the chosen tenant.'
                ], 200);
                $response->send();
                exit();
            }
        }
        // If no tenant filter was applied (empty string), allow any agent owned by this client
    }
}

// Validate POST data (defer source_config validation to reconstruction step below)
// Note: source_path is optional (root if empty), dest_prefix optional
$requiredFields = ['name', 'source_type', 'source_display_name', 'dest_bucket_id'];
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

// Kopia engine is only supported with Local Agent and requires an agent_id
if (($engineForJob ?? '') === 'kopia') {
    if (($sourceTypeForPath ?? '') !== 'local_agent') {
        // Force the source_type to local_agent when engine=kopia
        $sourceTypeForPath = 'local_agent';
        $_POST['source_type'] = 'local_agent';
    }
    if ($agentIdPosted <= 0) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Local Agent (Kopia) jobs require an Agent. Please select an agent.'], 200);
        $response->send();
        exit();
    }
}
if (($engineForJob ?? '') === 'disk_image') {
    if (($sourceTypeForPath ?? '') !== 'local_agent') {
        $sourceTypeForPath = 'local_agent';
        $_POST['source_type'] = 'local_agent';
    }
    if ($agentIdPosted <= 0) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Disk Image jobs require an Agent. Please select an agent.'], 200);
        $response->send();
        exit();
    }
}

// Validate disk image requirements when engine=disk_image
if (($engineForJob ?? '') === 'disk_image') {
    if ($diskSourceVolume === '') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Disk source volume is required for disk image backups.'], 200);
        $response->send();
        exit();
    }
    if ($diskImageFormat === '') {
        $diskImageFormat = 'vhdx';
    }
}

// Enforce S3-only destinations for now
$destType = $_POST['dest_type'] ?? 's3';
if ($destType !== 's3') {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Only S3 destinations are supported at this time.'], 200);
    $response->send();
    exit();
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
        $region = $_POST['s3_region'] ?? 'ca-central-1';
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
    } elseif ($sourceType === 'local_agent') {
        $path = $_POST['local_source_path'] ?? $_POST['source_path'] ?? '';
        $inc = $_POST['local_include_glob'] ?? null;
        $exc = $_POST['local_exclude_glob'] ?? null;
        $bw  = $_POST['local_bandwidth_limit_kbps'] ?? null;
        $sourceConfig = [
            'include_glob' => $inc,
            'exclude_glob' => $exc,
            'bandwidth_limit_kbps' => $bw,
        ];
        $_POST['source_path'] = $path;

        // Handle network share credentials for UNC paths
        $netUser = $_POST['network_username'] ?? '';
        $netPass = $_POST['network_password'] ?? '';
        $netDomain = $_POST['network_domain'] ?? '';
        if (!empty($netUser) && !empty($netPass)) {
            // Encrypt credentials before storage using HelperController
            $encryptedUser = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::encryptKey($netUser, $encryptionKey);
            $encryptedPass = \WHMCS\Module\Addon\CloudStorage\Client\HelperController::encryptKey($netPass, $encryptionKey);
            $sourceConfig['network_credentials'] = [
                'username' => $encryptedUser,
                'password' => $encryptedPass,
                'domain' => $netDomain, // Domain doesn't need encryption
            ];
        }
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
        'region'     => $sourceConfig['region'] ?? 'ca-central-1',
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

// Normalize JSON-ish fields that may arrive HTML-entity encoded
$normalizeJsonField = function ($val) {
    if ($val === null) {
        return null;
    }
    if (is_array($val)) {
        return json_encode($val);
    }
    $raw = (string) $val;
    if ($raw === '') {
        return null;
    }
    // decode common encodings
    $candidates = [
        $raw,
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded);
        }
    }
    // fallback: store as null to avoid invalid JSON insert
    logModuleCall('cloudstorage', 'create_job_json_normalize_failed', ['input' => $raw], json_last_error_msg(), null);
    return null;
};

$scheduleJsonNorm = $normalizeJsonField($_POST['schedule_json'] ?? null);
$retentionJsonNorm = $normalizeJsonField($_POST['retention_json'] ?? null);
$policyJsonNorm = $normalizeJsonField($_POST['policy_json'] ?? null);

// Normalize source_paths (multi-select from Local Agent file browser)
// Note: WHMCS may HTML-encode POST data, so we need to decode before JSON parsing
$sourcePaths = [];
if (isset($_POST['source_paths'])) {
    if (is_array($_POST['source_paths'])) {
        $sourcePaths = array_values(array_filter(array_map('strval', $_POST['source_paths']), fn($p) => trim($p) !== ''));
    } elseif (is_string($_POST['source_paths'])) {
        $raw = trim($_POST['source_paths']);
        // HTML-decode first (WHMCS encodes POST data)
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sourcePaths = array_values(array_filter(array_map('strval', $decoded), fn($p) => trim($p) !== ''));
        } elseif ($raw !== '') {
            $sourcePaths = array_values(array_filter(array_map('trim', explode(';', $raw)), fn($p) => $p !== ''));
        }
    }
}
// Ensure primary source_path stays in sync
// HTML-decode in case WHMCS encoded it
$primarySourcePath = $_POST['source_path'] ?? '';
if ($primarySourcePath !== '') {
    $primarySourcePath = html_entity_decode($primarySourcePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
if (!empty($sourcePaths)) {
    $primarySourcePath = $sourcePaths[0];
    $_POST['source_path'] = $primarySourcePath;
} elseif ($primarySourcePath !== '') {
    $sourcePaths[] = $primarySourcePath;
}
$hasSourcePathsJson = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_paths_json');

// Prepare job data
$jobData = [
    'client_id' => $loggedInUserId,
    's3_user_id' => $user->id,
    'name' => $_POST['name'],
    'source_type' => $_POST['source_type'],
    'source_display_name' => $_POST['source_display_name'],
    'source_config' => $sourceConfig,
    'source_path' => $primarySourcePath,
    'dest_bucket_id' => $_POST['dest_bucket_id'],
    'dest_prefix' => $_POST['dest_prefix'],
    'backup_mode' => $_POST['backup_mode'] ?? 'sync',
    'engine' => $_POST['engine'] ?? 'sync',
    'dest_type' => 's3', // enforce S3-only until schema supports local
    'dest_local_path' => $_POST['dest_local_path'] ?? null,
    'bucket_auto_create' => isset($_POST['bucket_auto_create']) ? 1 : 0,
    'schedule_json' => $scheduleJsonNorm,
    'encryption_enabled' => isset($_POST['encryption_enabled']) ? (int)$_POST['encryption_enabled'] : 0,
    'validation_mode' => $_POST['validation_mode'] ?? 'none',
    'schedule_type' => $_POST['schedule_type'] ?? 'manual',
    'schedule_time' => $_POST['schedule_time'] ?? null,
    'schedule_weekday' => isset($_POST['schedule_weekday']) ? (int)$_POST['schedule_weekday'] : null,
    'timezone' => $_POST['timezone'] ?? null,
    'retention_mode' => $_POST['retention_mode'] ?? 'none',
    'retention_value' => isset($_POST['retention_value']) ? (int)$_POST['retention_value'] : null,
    'retention_json' => $retentionJsonNorm,
    'policy_json' => $policyJsonNorm,
    'bandwidth_limit_kbps' => isset($_POST['bandwidth_limit_kbps']) ? (int)$_POST['bandwidth_limit_kbps'] : null,
    'parallelism' => isset($_POST['parallelism']) ? (int)$_POST['parallelism'] : null,
    'encryption_mode' => $_POST['encryption_mode'] ?? null,
    'compression' => $_POST['compression'] ?? null,
    'notify_override_email' => $_POST['notify_override_email'] ?? null,
    'notify_on_success' => isset($_POST['notify_on_success']) ? 1 : 0,
    'notify_on_warning' => isset($_POST['notify_on_warning']) ? 1 : 0,
    'notify_on_failure' => isset($_POST['notify_on_failure']) ? 1 : 0,
];
if ($hasSourcePathsJson) {
    $jobData['source_paths_json'] = json_encode($sourcePaths, JSON_UNESCAPED_SLASHES);
}
// Disk image fields (optional, only stored if provided)
if ($diskSourceVolume !== '') {
    $jobData['disk_source_volume'] = $diskSourceVolume;
}
$jobData['disk_image_format'] = $diskImageFormat;
if ($diskTempDir !== '') {
    $jobData['disk_temp_dir'] = $diskTempDir;
}

// Hyper-V specific fields
if (($engineForJob ?? '') === 'hyperv') {
    $jobData['hyperv_enabled'] = isset($_POST['hyperv_enabled']) ? 1 : 0;
    $hypervConfig = $_POST['hyperv_config'] ?? '';
    if ($hypervConfig !== '') {
        $jobData['hyperv_config'] = $hypervConfig;
    }
}

if (($st ?? '') === 'local_agent') {
    if ($hasAgentIdJobs) {
        $jobData['agent_id'] = $agentIdForJob;
    }
}
if (($_POST['source_type'] ?? '') === 'google_drive') {
    $jobData['source_connection_id'] = (int) ($_POST['__resolved_source_connection_id'] ?? ($_POST['source_connection_id'] ?? 0));
}

// Normalize destination prefix (optional). Ensure trailing slash when non-empty.
$destPrefix = isset($_POST['dest_prefix']) ? trim((string)$_POST['dest_prefix']) : '';
if ($destPrefix !== '') {
    $destPrefix = ltrim($destPrefix, '/');
    if ($destPrefix !== '' && substr($destPrefix, -1) !== '/') {
        $destPrefix .= '/';
    }
}
$_POST['dest_prefix'] = $destPrefix;

// Enforce versioning on the selected destination bucket
$ver = CloudBackupController::ensureVersioningForBucketId((int)$_POST['dest_bucket_id']);
logModuleCall('cloudstorage', 'create_job_enforce_versioning', ['dest_bucket_id' => (int)$_POST['dest_bucket_id']], $ver);
if (!is_array($ver) || ($ver['status'] ?? 'fail') !== 'success') {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Unable to enable bucket versioning.'], 200);
    $response->send();
    exit();
}

$debugPayload = [
    'client_id' => $loggedInUserId,
    'engine' => $jobData['engine'] ?? null,
    'source_type' => $jobData['source_type'] ?? null,
    'agent_id' => $jobData['agent_id'] ?? null,
    'dest_bucket_id' => $jobData['dest_bucket_id'] ?? null,
    'dest_prefix' => $jobData['dest_prefix'] ?? null,
    'schedule_json' => $jobData['schedule_json'] ?? null,
    'retention_json' => $jobData['retention_json'] ?? null,
    'policy_json' => $jobData['policy_json'] ?? null,
];
logModuleCall('cloudstorage', 'create_job_debug', $debugPayload, null, null);

try {
    $result = CloudBackupController::createJob($jobData, $encryptionKey);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'create_job_exception', $jobData, $e->getMessage(), $e->getTraceAsString());
    $result = ['status' => 'fail', 'message' => 'Create job exception. Please contact support.'];
}

if (!is_array($result) || ($result['status'] ?? 'fail') !== 'success') {
    logModuleCall('cloudstorage', 'create_job_failed', $jobData, $result, null);
}

// Align bucket lifecycle with retention and enforce versioning when keep_days
if (is_array($result) && ($result['status'] ?? 'fail') === 'success' && isset($result['job_id'])) {
    try {
        $lcRes = \WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController::manageLifecycleForJob((int)$result['job_id']);
        if (($jobData['retention_mode'] ?? 'none') === 'keep_days' && (int)($jobData['retention_value'] ?? 0) > 0) {
            if (!is_array($lcRes) || ($lcRes['status'] ?? 'fail') !== 'success') {
                $msg = $lcRes['message'] ?? 'Failed to apply lifecycle policy';
                $result = ['status' => 'fail', 'message' => 'Unable to enforce Keep N days retention: ' . $msg];
            }
        }
    } catch (\Throwable $e) {
        if (($jobData['retention_mode'] ?? 'none') === 'keep_days' && (int)($jobData['retention_value'] ?? 0) > 0) {
            $result = ['status' => 'fail', 'message' => 'Unable to enforce Keep N days retention.'];
        }
    }
    
    // Register Hyper-V VMs if this is a Hyper-V job
    // Log debug info for Hyper-V job creation
    if (($engineForJob ?? '') === 'hyperv') {
        logModuleCall('cloudstorage', 'create_job_hyperv_debug', [
            'engine' => $engineForJob,
            'has_hyperv_vm_ids' => isset($_POST['hyperv_vm_ids']),
            'hyperv_vm_ids_raw' => $_POST['hyperv_vm_ids'] ?? '(not set)',
            'hyperv_config_raw' => $_POST['hyperv_config'] ?? '(not set)',
            'source_paths' => $_POST['source_paths'] ?? '(not set)',
        ], null);
    }
    
    if (($engineForJob ?? '') === 'hyperv') {
        try {
            // Try hyperv_vm_ids first, then fall back to source_paths
            $hypervVmIds = null;
            $sourceUsed = 'none';
            
            if (isset($_POST['hyperv_vm_ids']) && $_POST['hyperv_vm_ids'] !== '') {
                // WHMCS may HTML-encode POST data, so decode it first
                $rawVmIds = $_POST['hyperv_vm_ids'];
                $decodedVmIds = html_entity_decode($rawVmIds, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $hypervVmIds = json_decode($decodedVmIds, true);
                $sourceUsed = 'hyperv_vm_ids';
            }
            
            // Fallback: try source_paths (frontend sets this to hyperv_vm_ids for hyperv engine)
            if ((!is_array($hypervVmIds) || empty($hypervVmIds)) && isset($_POST['source_paths'])) {
                $fallbackPaths = $_POST['source_paths'];
                if (is_string($fallbackPaths)) {
                    $fallbackPaths = json_decode($fallbackPaths, true);
                }
                if (is_array($fallbackPaths) && !empty($fallbackPaths)) {
                    // Check if these look like GUIDs (VM IDs)
                    $firstPath = $fallbackPaths[0] ?? '';
                    if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $firstPath)) {
                        $hypervVmIds = $fallbackPaths;
                        $sourceUsed = 'source_paths';
                    }
                }
            }
            
            logModuleCall('cloudstorage', 'create_job_hyperv_vm_ids_parsed', [
                'job_id' => $result['job_id'] ?? null,
                'hypervVmIds_decoded' => $hypervVmIds,
                'is_array' => is_array($hypervVmIds),
                'count' => is_array($hypervVmIds) ? count($hypervVmIds) : 0,
                'source_used' => $sourceUsed,
            ], null);
            
            if (is_array($hypervVmIds) && count($hypervVmIds) > 0) {
                $jobId = (int) $result['job_id'];
                $agentId = (int) ($_POST['agent_id'] ?? 0);
                
                // Parse hyperv_vms to get VM names (also HTML-decode)
                $vmNameMap = [];
                if (isset($_POST['hyperv_vms']) && $_POST['hyperv_vms'] !== '') {
                    $rawVms = html_entity_decode($_POST['hyperv_vms'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $vmsList = json_decode($rawVms, true);
                    if (is_array($vmsList)) {
                        foreach ($vmsList as $vmInfo) {
                            if (isset($vmInfo['id']) && isset($vmInfo['name'])) {
                                $vmNameMap[$vmInfo['id']] = $vmInfo['name'];
                            }
                        }
                    }
                }
                
                foreach ($hypervVmIds as $vmGuid) {
                    // Check if VM already exists
                    $existingVm = Capsule::table('s3_hyperv_vms')
                        ->where('job_id', $jobId)
                        ->where('vm_guid', $vmGuid)
                        ->first();
                    
                    if (!$existingVm) {
                        // Use actual VM name if available, otherwise use GUID as placeholder
                        $vmName = $vmNameMap[$vmGuid] ?? $vmGuid;
                        
                        $insertData = [
                            'job_id' => $jobId,
                            'vm_name' => $vmName,
                            'vm_guid' => $vmGuid,
                            'backup_enabled' => 1,
                            'created_at' => Capsule::raw('NOW()'),
                            'updated_at' => Capsule::raw('NOW()'),
                        ];
                        // Add agent_id if the column exists
                        if (Capsule::schema()->hasColumn('s3_hyperv_vms', 'agent_id')) {
                            $insertData['agent_id'] = $agentId > 0 ? $agentId : null;
                        }
                        Capsule::table('s3_hyperv_vms')->insert($insertData);
                    }
                }
                logModuleCall('cloudstorage', 'create_job_hyperv_vms_registered', [
                    'job_id' => $jobId,
                    'vm_count' => count($hypervVmIds),
                    'vm_names_found' => count($vmNameMap),
                ], null);
            }
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'create_job_hyperv_vms_error', [
                'job_id' => $result['job_id'] ?? null,
            ], $e->getMessage());
            // Don't fail the job creation, just log the error
        }
    }
}

$response = new JsonResponse($result, 200);
$response->send();
exit();

