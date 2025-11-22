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
try {
    $dec = CloudBackupController::decryptSourceConfig($job, $encryptionKey);
    if (is_string($dec)) {
        $dec = json_decode($dec, true);
    }
    if (!is_array($dec)) {
        $dec = [];
    }
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
    'encryption_enabled'    => (int) $job['encryption_enabled'],
    'compression_enabled'   => (int) $job['compression_enabled'],
    'retention_mode'        => $job['retention_mode'],
    'retention_value'       => $job['retention_value'],
    'notify_override_email' => $job['notify_override_email'],
    'notify_on_success'     => (int) $job['notify_on_success'],
    'notify_on_warning'     => (int) $job['notify_on_warning'],
    'notify_on_failure'     => (int) $job['notify_on_failure'],
    'status'                => $job['status'],
];

$resp = new JsonResponse([
    'status' => 'success',
    'job'    => $outJob,
    'source' => $safeSource,
], 200);
$resp->send();
exit();


