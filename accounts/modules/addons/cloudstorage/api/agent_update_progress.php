<?php
/**
 * Agent -> server: report progress of a remote agent-update job.
 *
 * Called by the Go agent while applying an update (downloading, verifying,
 * applying) and on failure. Terminal success is determined server-side once the
 * agent comes back online reporting the target version, so this endpoint does
 * not accept a "success" state.
 */

require_once __DIR__ . '/../lib/Bootstrap/agent_bootstrap.php';
require_once __DIR__ . '/../lib/Client/AgentUpdateService.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\AgentUpdateService;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function authenticateAgent(): object
{
    return \WHMCS\Module\Addon\CloudStorage\Client\AgentAuth::authenticate(
        fn(array $data, int $code) => respond($data, $code)
    );
}

$bodyRaw = file_get_contents('php://input');
$body = $bodyRaw ? json_decode($bodyRaw, true) : [];
if (!is_array($body)) {
    $body = [];
}

$agent = authenticateAgent();

$jobId = isset($body['update_job_id']) ? (int) $body['update_job_id'] : 0;
$state = isset($body['state']) ? strtolower(trim((string) $body['state'])) : '';
$detail = isset($body['detail']) ? (string) $body['detail'] : '';

if ($jobId <= 0 || $state === '') {
    respond(['status' => 'fail', 'message' => 'update_job_id and state are required'], 400);
}

$ok = AgentUpdateService::recordProgress((string) $agent->agent_uuid, $jobId, $state, $detail);
if (!$ok) {
    // Either the job is not active/owned by this agent, or the state is invalid.
    respond(['status' => 'fail', 'message' => 'Progress not recorded'], 200);
}

respond(['status' => 'success']);
