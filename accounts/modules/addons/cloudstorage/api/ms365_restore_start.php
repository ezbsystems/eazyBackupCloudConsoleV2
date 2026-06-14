<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @return array<string, mixed>|null
 */
function ms365DecodeJsonObject(mixed $value): ?array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return null;
    }
    $raw = trim($value);
    if ($raw === '') {
        return null;
    }
    $candidates = [
        $raw,
        stripslashes($raw),
        html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        rawurldecode($raw),
    ];
    foreach ($candidates as $cand) {
        $decoded = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$userIdRaw = '';
$jobId = '';
$selection = null;

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_contains($contentType, 'application/json')) {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput ?: '', true);
    if (is_array($body)) {
        $userIdRaw = trim((string) ($body['user_id'] ?? ''));
        $jobId = trim((string) ($body['job_id'] ?? ''));
        $selection = ms365DecodeJsonObject($body['selection'] ?? $body['selection_json'] ?? null);
    }
} else {
    $userIdRaw = trim((string) ($_POST['user_id'] ?? ''));
    $jobId = trim((string) ($_POST['job_id'] ?? ''));
    $selection = ms365DecodeJsonObject($_POST['selection_json'] ?? '');
}

if ($userIdRaw === '' || $jobId === '' || $selection === null) {
    $message = 'user_id, job_id, and selection_json are required';
    if ($userIdRaw !== '' && $jobId !== '' && $selection === null) {
        $message = 'selection_json must be valid JSON';
    }
    (new JsonResponse(['status' => 'fail', 'message' => $message], 400))->send();
    exit;
}

try {
    $clientId = (int) $ca->getUserID();
    $user = Ms365E3Controller::resolveBackupUser($clientId, $userIdRaw);
    $result = Ms365E3Controller::startRestore($clientId, $user['id'], $jobId, $selection);
    (new JsonResponse([
        'status' => 'success',
        'batch_run_id' => $result['batch_run_id'],
        'restore_run_ids' => $result['restore_run_ids'],
        'job_id' => $jobId,
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_restore_start')->send();
}
