<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\ProgressLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$batchRunId = trim((string) ($_GET['batch_run_id'] ?? ''));
$jobId = trim((string) ($_GET['job_id'] ?? ''));

if ($batchRunId === '' || $jobId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'batch_run_id and job_id are required'], 400))->send();
    exit;
}

$job = Capsule::table('s3_cloudbackup_jobs')
    ->where('client_id', $clientId)
    ->where('source_type', 'ms365')
    ->whereRaw('job_id = UUID_TO_BIN(?)', [strtolower($jobId)])
    ->first();

if (!$job) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Job not found'], 404))->send();
    exit;
}

\Ms365Backup\Ms365BatchRunRepository::syncFromChildren($batchRunId);

$children = Capsule::table('ms365_backup_runs')
    ->where('whmcs_client_id', $clientId)
    ->where('e3_batch_run_id', $batchRunId)
    ->orderBy('created_at')
    ->get()
    ->map(static function ($row) use ($clientId) {
        $arr = (array) $row;
        $lines = ProgressLogger::tail((string) $arr['id'], 0);
        $arr['log_lines'] = array_slice($lines, -50);
        $arr['status_label'] = ($arr['status'] ?? '') === 'error' ? 'failed' : ($arr['status'] ?? '');

        return $arr;
    })
    ->all();

(new JsonResponse([
    'status' => 'success',
    'batch_run_id' => $batchRunId,
    'children' => $children,
], 200))->send();
