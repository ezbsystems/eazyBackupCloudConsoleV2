<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

// Worker callback to send notification email immediately after a run completes.
// Auth: HMAC-like token computed as sha256(encryption_key . ':' . run_id)

function cs_json($payload) {
    $resp = new JsonResponse($payload, 200);
    $resp->send();
    exit();
}

try {
    $runId = isset($_POST['run_id']) ? (int) $_POST['run_id'] : 0;
    $token = $_POST['token'] ?? ($_SERVER['HTTP_X_WORKER_TOKEN'] ?? '');

    if ($runId <= 0) {
        cs_json(['status' => 'fail', 'message' => 'Invalid run_id']);
    }

    // Load module config
    $moduleRows = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);
    if (count($moduleRows) == 0) {
        cs_json(['status' => 'fail', 'message' => 'Module not configured']);
    }

    $encKey = $moduleRows->where('setting', 'cloudbackup_encryption_key')->pluck('value')->first();
    if (empty($encKey)) {
        $encKey = $moduleRows->where('setting', 'encryption_key')->pluck('value')->first();
    }
    if (empty($encKey)) {
        cs_json(['status' => 'fail', 'message' => 'Encryption key not configured']);
    }

    // Validate token
    $expected = hash('sha256', $encKey . ':' . $runId);
    if (!is_string($token) || !hash_equals($expected, $token)) {
        logModuleCall('cloudstorage', 'cloudbackup_notify_run', ['run_id' => $runId], 'Invalid token');
        cs_json(['status' => 'fail', 'message' => 'Unauthorized']);
    }

    // Resolve email template
    $templateSetting = $moduleRows->where('setting', 'cloudbackup_email_template')->pluck('value')->first();
    if (empty($templateSetting)) {
        logModuleCall('cloudstorage', 'cloudbackup_notify_run', ['run_id' => $runId], 'Template not configured');
        cs_json(['status' => 'skipped', 'message' => 'Template not configured']);
    }

    // Send notification immediately
    $result = CloudBackupController::sendRunNotification($runId, $templateSetting);
    logModuleCall('cloudstorage', 'cloudbackup_notify_run', ['run_id' => $runId], $result);
    cs_json($result);
} catch (\Exception $e) {
    logModuleCall('cloudstorage', 'cloudbackup_notify_run', [], $e->getMessage());
    cs_json(['status' => 'error', 'message' => 'Internal error']);
}


