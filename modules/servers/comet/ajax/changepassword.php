<?php

use \WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\EazybackupObcMs365;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['result' => 'error', 'message' => 'Invalid request method']);
        exit;
    }

    $serviceId = isset($_POST['serviceId']) ? (int)$_POST['serviceId'] : 0;
    $newpassword = isset($_POST['newpassword']) ? (string)$_POST['newpassword'] : '';

    if ($serviceId <= 0 || $newpassword === '') {
        echo json_encode(['result' => 'error', 'message' => 'Missing serviceId or newpassword']);
        exit;
    }

    // Read service and username
    $service = Capsule::table('tblhosting')
        ->select('username', 'packageid')
        ->find($serviceId);
    if (is_null($service)) {
        echo json_encode(['result' => 'error', 'message' => 'Service not found']);
        exit;
    }
    $username = $service->username;
    $packageId = $service->packageid;

    // Use Comet Admin API to reset without needing current password
    $params = comet_ServiceParams($serviceId);
    $params['username'] = $username;
    $server = comet_Server($params);

    try {
        $resp = $server->AdminResetUserPassword($params['username'], 'Password', $newpassword);
        if (is_object($resp) && property_exists($resp, 'Status') && (int)$resp->Status >= 400) {
            // Fallback to 2-argument form if available (SDK/version differences)
            try {
                $resp2 = $server->AdminResetUserPassword($params['username'], $newpassword);
                if (is_object($resp2) && property_exists($resp2, 'Status') && (int)$resp2->Status >= 400) {
                    $msg2 = isset($resp2->Message) ? $resp2->Message : 'Password reset failed';
                    echo json_encode(['result' => 'error', 'message' => $msg2, 'code' => (int)$resp2->Status]);
                    exit;
                }
            } catch (\Throwable $e2) {
                $msg = isset($resp->Message) ? $resp->Message : $e2->getMessage();
                echo json_encode(['result' => 'error', 'message' => $msg]);
                exit;
            }
        }
    } catch (\Throwable $e) {
        // Fallback to 2-argument form if the 3-arg call is unsupported
        try {
            $resp2 = $server->AdminResetUserPassword($params['username'], $newpassword);
            if (is_object($resp2) && property_exists($resp2, 'Status') && (int)$resp2->Status >= 400) {
                $msg2 = isset($resp2->Message) ? $resp2->Message : 'Password reset failed';
                echo json_encode(['result' => 'error', 'message' => $msg2, 'code' => (int)$resp2->Status]);
                exit;
            }
        } catch (\Throwable $e2) {
            echo json_encode(['result' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    // Persist credentials in WHMCS
    try { comet_UpdateServiceCredentials(['serviceid' => $serviceId, 'username' => $username, 'password' => $newpassword]); } catch (\Throwable $e) {}

    // Microsoft 365 packages: trigger container login prompt
    if (in_array((string)$packageId, ['52', '57'], true)) {
        $installResult = EazybackupObcMs365::loginPromptInContainer($username, $username, $newpassword, $packageId);
        if (isset($installResult['error'])) {
            $message = "Login prompt failed: " . $installResult['error'];
            logModuleCall(
                "eazybackup",
                'loginPromptInContainer',
                [$username, $newpassword, $packageId],
                $message
            );
            echo json_encode(['result' => 'error', 'message' => $installResult['error']]);
            exit;
        }
    }

    echo json_encode(['result' => 'success']);
    exit;

} catch (\Throwable $e) {
    echo json_encode(['result' => 'error', 'message' => $e->getMessage()]);
    exit;
}
