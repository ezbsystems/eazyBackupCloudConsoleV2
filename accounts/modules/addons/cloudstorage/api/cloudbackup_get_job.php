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
use WHMCS\Database\Capsule;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $resp = new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200);
    $resp->send();
    exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    $resp = new JsonResponse(['status' => 'fail', 'message' => 'Product not found.'], 200);
    $resp->send();
    exit();
}

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
if ($jobId <= 0) {
    $resp = new JsonResponse(['status' => 'fail', 'message' => 'Invalid job id.'], 200);
    $resp->send();
    exit();
}

// Load job with ownership check
$job = CloudBackupController::getJob($jobId, $loggedInUserId);
if (!$job) {
    $resp = new JsonResponse(['status' => 'fail', 'message' => 'Job not found or access denied.'], 200);
    $resp->send();
    exit();
}

// Get encryption key from module config
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    $resp = new JsonResponse(['status' => 'fail', 'message' => 'Cloudstorage module configuration not found.'], 200);
    $resp->send();
    exit();
}

$encryptionKey = $module->where('setting', 'cloudbackup_encryption_key')->pluck('value')->first();
if (empty($encryptionKey)) {
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
}

// Decrypt source_config_enc
$safeSource = [];
$decryptedConfig = [];
try {
    $dec = CloudBackupController::decryptSourceConfig($job, $encryptionKey);
    if (is_string($dec)) {
        $dec = json_decode($dec, true);
    }
    if (!is_array($dec)) {
        $dec = [];
    }
    $decryptedConfig = $dec;
    $type = $job['source_type'];
    if ($type === 's3_compatible') {
        $safeSource = [
            'type'       => 's3_compatible',
            'endpoint'   => isset($dec['endpoint']) ? $dec['endpoint'] : null,
            'bucket'     => isset($dec['bucket']) ? $dec['bucket'] : null,
            'region'     => isset($dec['region']) ? $dec['region'] : null,
            'has_access' => (isset($dec['access_key']) && !empty($dec['access_key'])),
            'has_secret' => (isset($dec['secret_key']) && !empty($dec['secret_key'])),
        ];
    } elseif ($type === 'aws') {
        $safeSource = [
            'type'       => 'aws',
            'bucket'     => isset($dec['bucket']) ? $dec['bucket'] : null,
            'region'     => isset($dec['region']) ? $dec['region'] : null,
            'has_access' => (isset($dec['access_key']) && !empty($dec['access_key'])),
            'has_secret' => (isset($dec['secret_key']) && !empty($dec['secret_key'])),
        ];
    } elseif ($type === 'sftp') {
        $safeSource = [
            'type'     => 'sftp',
            'host'     => isset($dec['host']) ? $dec['host'] : null,
            'port'     => isset($dec['port']) ? $dec['port'] : null,
            'user'     => isset($dec['user']) ? $dec['user'] : null,
            'has_pass' => (isset($dec['pass']) && !empty($dec['pass'])),
        ];
    } elseif ($type === 'local_agent') {
        // For local_agent, include editable config fields (no secrets)
        $safeSource = [
            'type'                 => 'local_agent',
            'include_glob'         => $dec['include_glob'] ?? null,
            'exclude_glob'         => $dec['exclude_glob'] ?? null,
            'bandwidth_limit_kbps' => $dec['bandwidth_limit_kbps'] ?? null,
            // Indicate if network credentials are stored (don't expose them)
            'has_network_password' => isset($dec['network_credentials']['password']) && !empty($dec['network_credentials']['password']),
            'network_username'     => isset($dec['network_credentials']['username']) ? '[saved]' : null,
            'network_domain'       => $dec['network_credentials']['domain'] ?? null,
        ];
    } else {
        $safeSource = ['type' => $type];
    }
} catch (\Exception $e) {
    $safeSource = ['type' => $job['source_type']];
}

// Whitelist job fields returned to client
$outJob = [
    'id'                    => (int) $job['id'],
    'client_id'             => (int) $job['client_id'],
    's3_user_id'            => (int) $job['s3_user_id'],
    'agent_id'              => isset($job['agent_id']) ? (int) $job['agent_id'] : null,
    'name'                  => $job['name'],
    'source_type'           => $job['source_type'],
    'source_display_name'   => $job['source_display_name'],
    'source_path'           => $job['source_path'],
    'dest_bucket_id'        => (int) $job['dest_bucket_id'],
    'dest_prefix'           => $job['dest_prefix'],
    'backup_mode'           => $job['backup_mode'],
    'schedule_type'         => $job['schedule_type'],
    'schedule_time'         => $job['schedule_time'],
    'schedule_weekday'      => $job['schedule_weekday'],
    'timezone'              => $job['timezone'],
    'encryption_enabled'    => (int) ($job['encryption_enabled'] ?? 0),
    'compression_enabled'   => (int) ($job['compression_enabled'] ?? 0),
    'retention_mode'        => $job['retention_mode'],
    'retention_value'       => $job['retention_value'],
    'notify_override_email' => $job['notify_override_email'],
    'notify_on_success'     => (int) ($job['notify_on_success'] ?? 0),
    'notify_on_warning'     => (int) ($job['notify_on_warning'] ?? 0),
    'notify_on_failure'     => (int) ($job['notify_on_failure'] ?? 0),
    'status'                => $job['status'],
];

// Include additional fields for edit mode (local_agent and all jobs)
// Engine & backup mode
$outJob['engine'] = $job['engine'] ?? 'sync';

// Source paths (for local_agent multi-folder selection)
$outJob['source_paths_json'] = $job['source_paths_json'] ?? null;

// Disk image fields
$outJob['disk_source_volume'] = $job['disk_source_volume'] ?? null;
$outJob['disk_image_format'] = $job['disk_image_format'] ?? 'vhdx';
$outJob['disk_temp_dir'] = $job['disk_temp_dir'] ?? null;

// Include/exclude globs (from source config or job-level)
$outJob['local_include_glob'] = $decryptedConfig['include_glob'] ?? ($job['local_include_glob'] ?? null);
$outJob['local_exclude_glob'] = $decryptedConfig['exclude_glob'] ?? ($job['local_exclude_glob'] ?? null);

// Policy fields
$outJob['bandwidth_limit_kbps'] = $job['bandwidth_limit_kbps'] ?? ($decryptedConfig['bandwidth_limit_kbps'] ?? null);
$outJob['parallelism'] = $job['parallelism'] ?? null;

// JSON config fields
$outJob['policy_json'] = $job['policy_json'] ?? null;
$outJob['retention_json'] = $job['retention_json'] ?? null;
$outJob['schedule_json'] = $job['schedule_json'] ?? null;

// Hyper-V fields
$outJob['hyperv_enabled'] = (int) ($job['hyperv_enabled'] ?? 0);
$outJob['hyperv_config'] = $job['hyperv_config'] ?? null;

// Tenant info (for MSP)
$outJob['tenant_id'] = $job['tenant_id'] ?? null;

// Try to fetch agent hostname if agent_id is set
if ($outJob['agent_id']) {
    try {
        $agent = Capsule::table('s3_cloudbackup_agents')
            ->where('id', $outJob['agent_id'])
            ->first(['hostname']);
        if ($agent) {
            $outJob['agent_hostname'] = $agent->hostname;
        }
    } catch (\Throwable $e) {
        // Ignore - agent_hostname is optional
    }
}

// Try to fetch bucket name if dest_bucket_id is set
if ($outJob['dest_bucket_id']) {
    try {
        $bucket = Capsule::table('s3_buckets')
            ->where('id', $outJob['dest_bucket_id'])
            ->first(['name']);
        if ($bucket) {
            $outJob['dest_bucket_name'] = $bucket->name;
        }
    } catch (\Throwable $e) {
        // Ignore - dest_bucket_name is optional
    }
}

$resp = new JsonResponse([
    'status' => 'success',
    'job'    => $outJob,
    'source' => $safeSource,
], 200);
$resp->send();
exit();


