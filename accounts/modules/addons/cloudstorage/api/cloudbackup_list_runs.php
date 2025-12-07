<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

$clientId = $ca->getUserID();
$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
if ($limit <= 0 || $limit > 200) {
    $limit = 50;
}

if ($jobId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'job_id is required'], 200))->send();
    exit;
}

// Verify job ownership
$job = CloudBackupController::getJob($jobId, $clientId);
if (!$job) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Job not found or access denied'], 200))->send();
    exit;
}

$runs = Capsule::table('s3_cloudbackup_runs')
    ->where('job_id', $jobId)
    ->orderBy('id', 'desc')
    ->limit($limit)
    ->get(['id','status','started_at','finished_at','log_ref','engine','stats_json']);

$out = [];
foreach ($runs as $r) {
    // Fallback: derive log_ref from stats_json.manifest_id if missing
    $logRef = (string) ($r->log_ref ?? '');
    if ($logRef === '' && !empty($r->stats_json)) {
        $decoded = json_decode($r->stats_json, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['manifest_id']) && $decoded['manifest_id'] !== '') {
            $logRef = (string) $decoded['manifest_id'];
        }
    }

    $out[] = [
        'id' => (int) $r->id,
        'status' => (string) $r->status,
        'started_at' => (string) ($r->started_at ?? ''),
        'finished_at' => (string) ($r->finished_at ?? ''),
        'log_ref' => $logRef,
        'engine' => (string) ($r->engine ?? 'sync'),
    ];
}

(new JsonResponse(['status' => 'success', 'runs' => $out], 200))->send();
exit;


