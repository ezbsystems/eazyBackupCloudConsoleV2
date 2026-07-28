<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/ms365_plan_request_helpers.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Ms365Backup\CustomerSelectionCodec;
use Symfony\Component\HttpFoundation\JsonResponse;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$params = ms365PlanReadRequestParams();
$userId = trim((string) ($params['user_id'] ?? ''));
if ($userId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required'], 400))->send();
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$selectAll = filter_var($params['select_all'] ?? false, FILTER_VALIDATE_BOOLEAN);
$summaryOnly = filter_var($params['summary_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
$selectedIds = ms365PlanDecodeJsonStringArray($params['selected_resource_ids'] ?? '[]');
$scopeOverrides = ms365PlanDecodeJsonObject($params['scope_overrides'] ?? '{}');
$billingExemptIds = ms365PlanDecodeJsonStringArray($params['billing_exempt_resource_ids'] ?? '[]');
$billingExemptKeyPresent = filter_var(
    $params['billing_exempt_key_present'] ?? true,
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE,
);
if ($billingExemptKeyPresent === null) {
    $billingExemptKeyPresent = true;
}

if (!$selectAll && $selectedIds === []) {
    (new JsonResponse([
        'status' => 'success',
        'plan' => [
            'runnable' => [],
            'deferred' => [],
            'dedup_groups' => [],
            'warnings' => [],
            'summary' => ['runnable' => 0, 'deferred' => 0],
        ],
        'billing' => ms365PlanEmptyBilling(),
    ]))->send();
    exit;
}

try {
    if ($summaryOnly) {
        if ($selectAll) {
            $result = Ms365E3Controller::planJobSummarySelectAll($clientId, $userId);
        } else {
            $result = Ms365E3Controller::planJobSummaryOnly($clientId, $userId, $selectedIds, $scopeOverrides);
        }
        (new JsonResponse([
            'status' => 'success',
            'plan' => $result['plan'],
        ]))->send();
        exit;
    }

    if ($selectAll) {
        $result = Ms365E3Controller::planJobSelectAll($clientId, $userId);
    } else {
        $result = Ms365E3Controller::planJob(
            $clientId,
            $userId,
            $selectedIds,
            $scopeOverrides,
            $billingExemptIds,
            $billingExemptKeyPresent,
        );
    }
    (new JsonResponse([
        'status' => 'success',
        'plan' => CustomerSelectionCodec::slimPlanForWizard($result['plan'] ?? []),
        'billing' => $result['billing'] ?? [],
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_job_plan')->send();
}
