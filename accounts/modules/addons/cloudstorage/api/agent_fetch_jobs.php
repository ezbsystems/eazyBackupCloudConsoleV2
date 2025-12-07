<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    $response = new JsonResponse($data, $httpCode);
    $response->send();
    exit;
}

function authenticateAgent(): object
{
    $agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? ($_POST['agent_id'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentId || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    // Touch last_seen_at
    Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

$agent = authenticateAgent();

$hasAgentIdJobs = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'agent_id');

$jobsQuery = Capsule::table('s3_cloudbackup_jobs')
    ->where('client_id', $agent->client_id)
    ->where('source_type', 'local_agent');
if ($hasAgentIdJobs) {
    $jobsQuery->where('agent_id', $agent->id);
}

$select = [
        'id',
        'name',
        'source_type',
        'source_display_name',
        'source_config_enc',
        'source_path',
        'local_include_glob',
        'local_exclude_glob',
        'local_bandwidth_limit_kbps',
        'dest_bucket_id',
        'dest_prefix',
        'backup_mode',
        'encryption_enabled',
        'validation_mode',
        'schedule_type',
        'schedule_time',
        'schedule_weekday',
        'retention_mode',
        'retention_value',
        'notify_override_email',
        'notify_on_success',
        'notify_on_warning',
        'notify_on_failure',
        'status',
    ];
if ($hasAgentIdJobs) {
    $select[] = 'agent_id';
}

$jobs = $jobsQuery
    ->whereNotIn('status', ['deleted'])
    ->get($select)
    ->map(function ($j) {
        $j->encryption_enabled = (bool)($j->encryption_enabled ?? 0);
        $j->notify_on_success = (bool)($j->notify_on_success ?? 0);
        $j->notify_on_warning = (bool)($j->notify_on_warning ?? 0);
        $j->notify_on_failure = (bool)($j->notify_on_failure ?? 0);
        return $j;
    });

respond(['status' => 'success', 'jobs' => $jobs]);

