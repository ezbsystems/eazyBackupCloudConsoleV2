<?php

use WHMCS\Database\Capsule;
use WHMCS\Session;

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

header('Content-Type: application/json');

try {
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) { echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']); exit; }

    $action    = (string)($post['action'] ?? '');
    $serviceId = (int)($post['serviceId'] ?? 0);
    $username  = (string)($post['username'] ?? '');

    if ($action === '' || $serviceId <= 0 || $username === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        exit;
    }

    // Ensure caller is authenticated and owns the service
    $clientId = 0;
    try { $clientId = (int) (Session::get('uid') ?: 0); } catch (\Throwable $e) { $clientId = 0; }
    if ($clientId <= 0) { $clientId = (int) ($_SESSION['uid'] ?? 0); }
    if ($clientId <= 0) { echo json_encode(['status' => 'error', 'message' => 'Not authenticated']); exit; }

    $account = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', $clientId)
        ->select('id', 'packageid', 'username')
        ->first();
    if (!$account || $account->username !== $username) {
        echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']);
        exit;
    }

    $params = comet_ServiceParams($serviceId);
    $params['username'] = $username;
    $server = comet_Server($params);

    switch ($action) {
        case 'resetPassword': {
            // For security, require a new password from the client; generate if not provided
            $newPassword = (string)($post['password'] ?? '');
            if ($newPassword === '') {
                // Generate a compliant password if not provided
                $newPassword = bin2hex(random_bytes(6)); // 12 hex chars
                if (!comet_ValidateBackupPassword($newPassword)) { $newPassword .= 'A!1'; }
            }

            // Call Comet Admin API: AdminResetUserPassword
            try {
                // The comet module expects AuthType 'Password'
                $params['AuthType'] = 'Password';
                $params['password'] = $newPassword;
                $resp = $server->AdminResetUserPassword($params['username'], $params['AuthType'], $params['password']);
                if (is_object($resp) && property_exists($resp, 'Status') && (int)$resp->Status >= 400) {
                    echo json_encode(['status' => 'error', 'message' => $resp->Message ?? 'Password reset failed', 'code' => $resp->Status]);
                    break;
                }

                // Persist new password to WHMCS service credentials (encrypted)
                try { comet_UpdateServiceCredentials(['serviceid' => $serviceId, 'username' => $username, 'password' => $newPassword]); } catch (\Throwable $e) {}

                echo json_encode(['status' => 'success', 'message' => 'Password reset successfully.', 'password' => $newPassword]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => 'Password reset failed.']);
            }
            break;
        }

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;


