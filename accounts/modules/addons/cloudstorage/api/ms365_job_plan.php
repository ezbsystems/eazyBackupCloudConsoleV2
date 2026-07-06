<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @return list<string>
 */
function ms365PlanDecodeJsonStringArray(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_map('strval', $value));
    }
    if (!is_string($value)) {
        return [];
    }
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }
    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), trim($raw, "'\"")];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_map('strval', $decoded));
        }
    }

    return [];
}

/**
 * @return array<string, mixed>
 */
function ms365PlanDecodeJsonObject(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return [];
    }
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }
    $candidates = [$raw, stripslashes($raw), html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), trim($raw, "'\"")];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$userId = trim((string) ($params['user_id'] ?? ''));
if ($userId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required'], 400))->send();
    exit;
}

$selectedIds = ms365PlanDecodeJsonStringArray($params['selected_resource_ids'] ?? '[]');
$scopeOverrides = ms365PlanDecodeJsonObject($params['scope_overrides'] ?? '{}');

if ($selectedIds === []) {
    (new JsonResponse([
        'status' => 'success',
        'plan' => [
            'runnable' => [],
            'deferred' => [],
            'dedup_groups' => [],
            'warnings' => [],
            'summary' => ['runnable' => 0, 'deferred' => 0],
        ],
        'billing' => [
            'protected_users' => 0,
            'onedrive_overage_gib' => 0,
            'pricing' => [
                'protected_user_price_cad' => 0.0,
                'onedrive_overage_per_gib_cad' => 0.0,
                'estimated_monthly_cad' => 0.0,
            ],
            'trial_status' => null,
            'inventory_stale' => false,
            'member_resolution_pending' => false,
            'breakdown' => [],
        ],
    ]))->send();
    exit;
}

try {
    $result = Ms365E3Controller::planJob($clientId, $userId, $selectedIds, $scopeOverrides);
    (new JsonResponse([
        'status' => 'success',
        'plan' => $result['plan'],
        'billing' => $result['billing'] ?? [],
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_job_plan')->send();
}
