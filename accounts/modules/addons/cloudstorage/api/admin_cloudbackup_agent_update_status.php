<?php
/**
 * Admin: poll the status of an agent's most recent remote-update job for the
 * Cloud Backup Administration -> Agents page.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

session_start();
if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 200))->send();
    exit;
}

$agentUuid = trim((string) ($_GET['agent_uuid'] ?? ($_POST['agent_uuid'] ?? '')));
if ($agentUuid === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'agent_uuid is required'], 200))->send();
    exit;
}

$agent = Capsule::table('s3_cloudbackup_agents')->where('agent_uuid', $agentUuid)->first();
if (!$agent) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Agent not found'], 200))->send();
    exit;
}

$job = null;
if (Capsule::schema()->hasTable('s3_agent_update_jobs')) {
    $job = Capsule::table('s3_agent_update_jobs')
        ->where('agent_uuid', $agentUuid)
        ->orderByDesc('id')
        ->first(['id', 'status', 'detail', 'from_version', 'target_version', 'created_at', 'updated_at', 'finished_at']);
}

(new JsonResponse([
    'status' => 'success',
    'agent_version' => $agent->agent_version ?? null,
    'update_job' => $job ? [
        'id' => (int) $job->id,
        'status' => $job->status,
        'detail' => $job->detail,
        'from_version' => $job->from_version,
        'target_version' => $job->target_version,
        'created_at' => $job->created_at,
        'updated_at' => $job->updated_at,
        'finished_at' => $job->finished_at,
    ] : null,
], 200))->send();
exit;
