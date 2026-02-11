<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/CloudBackupEventFormatter.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEventFormatter;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 200))->send();
    exit;
}

$agentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
if ($agentId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'agent_id is required'], 200))->send();
    exit;
}
if ($limit < 1) {
    $limit = 100;
}
if ($limit > 250) {
    $limit = 250;
}

$codes = [
    'STORAGE_DNS_FAILED',
    'STORAGE_TCP_REFUSED',
    'STORAGE_TCP_TIMEOUT',
    'STORAGE_TLS_FAILED',
    'STORAGE_HTTP_BLOCKED',
    'STORAGE_ENDPOINT_UNREACHABLE',
];

try {
    $rows = Capsule::table('s3_cloudbackup_run_events as e')
        ->join('s3_cloudbackup_runs as r', 'e.run_id', '=', 'r.id')
        ->leftJoin('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
        ->where('r.agent_id', $agentId)
        ->where(function ($q) use ($codes) {
            $q->whereIn('e.code', $codes)
                ->orWhere('e.code', 'LIKE', 'STORAGE_%')
                ->orWhere('e.level', 'warn')
                ->orWhere('e.level', 'error');
        })
        ->orderBy('e.id', 'desc')
        ->limit($limit)
        ->get([
            'e.id',
            'e.ts',
            'e.type',
            'e.level',
            'e.code',
            'e.message_id',
            'e.params_json',
            'e.run_id',
            'r.status as run_status',
            'j.name as job_name',
        ]);

    $events = [];
    foreach ($rows as $row) {
        $params = [];
        if (!empty($row->params_json)) {
            $dec = json_decode($row->params_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                $params = $dec;
            }
        }
        $safeParams = CloudBackupEventFormatter::sanitizeParamsForOutput($params);
        $events[] = [
            'id' => (int) $row->id,
            'ts' => (string) $row->ts,
            'type' => (string) $row->type,
            'level' => (string) $row->level,
            'code' => (string) $row->code,
            'message_id' => (string) $row->message_id,
            'message' => CloudBackupEventFormatter::render((string) $row->message_id, $safeParams),
            'params' => $safeParams,
            'run_id' => (int) $row->run_id,
            'run_status' => (string) $row->run_status,
            'job_name' => (string) ($row->job_name ?? ''),
        ];
    }

    (new JsonResponse(['status' => 'success', 'events' => $events], 200))->send();
    exit;
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to fetch diagnostics'], 200))->send();
    exit;
}

