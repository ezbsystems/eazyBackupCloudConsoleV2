<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @return list<string>
 */
function ms365DecodeJsonStringArray(mixed $value): array
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
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
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
function ms365DecodeJsonObject(mixed $value): array
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
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        trim($raw, "'\""),
    ];
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    (new JsonResponse(['status' => 'fail', 'message' => 'POST required'], 405))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$userId = trim((string) ($_POST['user_id'] ?? ''));
$jobId = trim((string) ($_POST['job_id'] ?? ''));

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'selected_resource_ids' => ms365DecodeJsonStringArray($_POST['selected_resource_ids'] ?? '[]'),
    'scope_overrides' => ms365DecodeJsonObject($_POST['scope_overrides'] ?? '{}'),
    'schedule_frequency' => trim((string) ($_POST['schedule_frequency'] ?? 'once_daily')),
    'retention_tier' => trim((string) ($_POST['retention_tier'] ?? '1y')),
];

if (!class_exists(\Ms365Backup\Ms365RetentionTierPolicyService::class)) {
    require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';
}
if (!\Ms365Backup\Ms365RetentionTierPolicyService::isValidTier($payload['retention_tier'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid retention tier.'], 400))->send();
    exit;
}

try {
    $result = Ms365E3Controller::saveJob($clientId, $userId, $payload, $jobId !== '' ? $jobId : null);
    (new JsonResponse([
        'status' => 'success',
        'job_id' => $result['job_id'],
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_job_save')->send();
}
