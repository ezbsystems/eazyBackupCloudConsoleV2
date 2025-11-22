<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEventFormatter;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

$userId = $ca->getUserID();
$runId = isset($_GET['run_id']) ? (int) $_GET['run_id'] : 0;
$sinceId = isset($_GET['since_id']) ? (int) $_GET['since_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 250;
if ($limit <= 0 || $limit > 1000) {
    $limit = 250;
}

if ($runId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Run ID is required.'], 200))->send();
    exit;
}

// Ownership check
$run = CloudBackupController::getRun($runId, $userId);
if (!$run) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Run not found or access denied.'], 200))->send();
    exit;
}

// Fetch events
$query = Capsule::table('s3_cloudbackup_run_events')
    ->select(['id', 'ts', 'type', 'level', 'code', 'message_id', 'params_json'])
    ->where('run_id', '=', $runId)
    ->orderBy('id', 'asc');
if ($sinceId > 0) {
    $query->where('id', '>', $sinceId);
}
$events = $query->limit($limit)->get();

$out = [];
$hasProgressOrNoChanges = false;
foreach ($events as $e) {
    $params = [];
    if (!empty($e->params_json)) {
        $decoded = json_decode($e->params_json, true);
        if (is_array($decoded)) {
            $params = $decoded;
        }
    }
    $message = CloudBackupEventFormatter::render($e->message_id, $params);
    $out[] = [
        'id' => (int) $e->id,
        'ts' => (string) $e->ts,
        'type' => (string) $e->type,
        'level' => (string) $e->level,
        'code' => (string) $e->code,
        'message_id' => (string) $e->message_id,
        'params' => $params,
        'message' => $message,
    ];
    if (in_array((string)$e->message_id, ['PROGRESS_UPDATE','NO_CHANGES','SUMMARY_TOTAL'], true)) {
        $hasProgressOrNoChanges = true;
    }
}

// Append a synthetic summary line with total transferred only on initial load (since_id==0)
// and only for terminal run states, to avoid duplicates in polling.
try {
    $isInitialLoad = ($sinceId <= 0);
    $isTerminal = in_array((string)($run['status'] ?? ''), ['success','failed','warning','cancelled'], true);
    if ($isInitialLoad && $isTerminal && !$hasProgressOrNoChanges && isset($run['bytes_transferred'])) {
        $bytes = (int) ($run['bytes_transferred'] ?? 0);
        $summaryMsg = CloudBackupEventFormatter::render('SUMMARY_TOTAL', ['bytes_done' => $bytes]);
        $out[] = [
            'id' => $out ? ((int) end($out)['id'] + 1) : 1,
            'ts' => (string) ($run['finished_at'] ?? date('Y-m-d H:i:s')),
            'type' => 'summary',
            'level' => 'info',
            'code' => 'SUMMARY_TOTAL',
            'message_id' => 'SUMMARY_TOTAL',
            'params' => ['bytes_done' => $bytes],
            'message' => $summaryMsg,
        ];
    }
} catch (\Throwable $e) {
    // Best-effort; do not fail the entire request
}

(new JsonResponse([
    'status' => 'success',
    'events' => $out,
], 200))->send();
exit;


