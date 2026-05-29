<?php
/**
 * Standalone admin endpoint for resetting a client's onboarding state.
 *
 * Used by external dev tools / curl. The Deprovision admin page wires the
 * same logic through its built-in AJAX router (cs_action=reset_onboarding).
 *
 * Auth: requires a WHMCS admin session.
 *
 * Method: POST or GET
 * Params: client_id (required)
 *
 * Returns JSON.
 */

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../lib/Admin/DeprovisionHelper.php';

use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;

header('Content-Type: application/json');

try {
    $isAdmin = !empty($_SESSION['adminid']);
} catch (\Throwable $e) {
    $isAdmin = false;
}
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['status' => 'fail', 'message' => 'admin_session_required']);
    exit;
}

$clientId = (int) ($_REQUEST['client_id'] ?? 0);
if ($clientId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'fail', 'message' => 'missing_client_id']);
    exit;
}

try {
    $counts = DeprovisionHelper::resetOnboarding($clientId);
    echo json_encode(['status' => 'success', 'client_id' => $clientId, 'counts' => $counts]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
}
