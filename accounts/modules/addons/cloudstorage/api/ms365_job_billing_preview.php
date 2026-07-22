<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/ms365_plan_request_helpers.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$clientId = (int) $ca->getUserID();
$params = ms365PlanReadRequestParams();
$userId = trim((string) ($params['user_id'] ?? ''));
if ($userId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required'], 400))->send();
    exit;
}

$selectAll = filter_var($params['select_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
$selectedIds = ms365PlanDecodeJsonStringArray($params['selected_resource_ids'] ?? '[]');
$scopeOverrides = ms365PlanDecodeJsonObject($params['scope_overrides'] ?? '{}');

if (!$selectAll && $selectedIds === []) {
    (new JsonResponse([
        'status' => 'success',
        'billing' => ms365PlanEmptyBilling(),
    ]))->send();
    exit;
}

try {
    if ($selectAll) {
        $result = Ms365E3Controller::previewJobBillingSelectAll($clientId, $userId);
    } else {
        $result = Ms365E3Controller::previewJobBilling($clientId, $userId, $selectedIds, $scopeOverrides);
    }
    (new JsonResponse([
        'status' => 'success',
        'billing' => $result['billing'] ?? [],
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_job_billing_preview')->send();
}
