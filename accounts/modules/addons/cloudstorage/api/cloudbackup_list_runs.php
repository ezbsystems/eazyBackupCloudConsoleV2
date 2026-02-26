<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

$clientId = $ca->getUserID();
$jobId = isset($_GET['job_id']) ? trim((string) $_GET['job_id']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
if ($limit <= 0 || $limit > 200) {
    $limit = 50;
}

if ($jobId === '' || !UuidBinary::isUuid($jobId)) {
    (new JsonResponse([
        'status' => 'fail',
        'code' => 'invalid_identifier_format',
        'message' => 'job_id must be a valid UUID.',
    ], 400))->send();
    exit;
}

// Verify job ownership
$job = CloudBackupController::getJob($jobId, $clientId);
if (!$job) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Job not found or access denied'], 200))->send();
    exit;
}

// MSP tenant authorization check
$accessCheck = MspController::validateJobAccess($jobId, $clientId);
if (!$accessCheck['valid']) {
    (new JsonResponse(['status' => 'fail', 'message' => $accessCheck['message']], 200))->send();
    exit;
}

$hasRunTenantCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'tenant_id');
$hasJobTenantCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
$hasRunRepositoryCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'repository_id');
$hasJobRepositoryCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id');

$tenantExpr = 'NULL as tenant_id';
if ($hasRunTenantCol && $hasJobTenantCol) {
    $tenantExpr = 'COALESCE(r.tenant_id, j.tenant_id) as tenant_id';
} elseif ($hasRunTenantCol) {
    $tenantExpr = 'r.tenant_id as tenant_id';
} elseif ($hasJobTenantCol) {
    $tenantExpr = 'j.tenant_id as tenant_id';
}

$repositoryExpr = 'NULL as repository_id';
if ($hasRunRepositoryCol && $hasJobRepositoryCol) {
    $repositoryExpr = 'COALESCE(r.repository_id, j.repository_id) as repository_id';
} elseif ($hasRunRepositoryCol) {
    $repositoryExpr = 'r.repository_id as repository_id';
} elseif ($hasJobRepositoryCol) {
    $repositoryExpr = 'j.repository_id as repository_id';
}

$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$jobRunJoin = $hasJobIdPk
    ? ['r.job_id', '=', 'j.job_id']
    : ['r.job_id', '=', 'j.id'];
$jobIdNorm = UuidBinary::normalize($jobId);

$runs = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
    ->whereRaw('r.job_id = ' . UuidBinary::toDbExpr($jobIdNorm))
    ->orderBy($hasRunIdCol ? 'r.started_at' : 'r.id', 'desc')
    ->limit($limit)
    ->get(array_merge(
        $hasRunIdCol
            ? [Capsule::raw('BIN_TO_UUID(r.run_id) as run_id')]
            : ['r.id as run_id'],
        [
            'r.status',
            'r.started_at',
            'r.finished_at',
            'r.log_ref',
            'r.engine',
            'r.stats_json',
            Capsule::raw($tenantExpr),
            Capsule::raw($repositoryExpr),
        ]
    ));

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
        'id' => (string) ($r->run_id ?? ''),
        'status' => (string) $r->status,
        'started_at' => (string) ($r->started_at ?? ''),
        'finished_at' => (string) ($r->finished_at ?? ''),
        'log_ref' => $logRef,
        'engine' => (string) ($r->engine ?? 'sync'),
        'tenant_id' => $r->tenant_id !== null ? (int) $r->tenant_id : null,
        'repository_id' => isset($r->repository_id) ? (string) $r->repository_id : null,
    ];
}

(new JsonResponse(['status' => 'success', 'runs' => $out], 200))->send();
exit;


