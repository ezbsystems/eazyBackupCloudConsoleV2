<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function userGetFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

function getOnlineThresholdForUserDetail(): int
{
    try {
        $value = (int) Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'cloudbackup_agent_online_threshold_seconds')
            ->value('value');
        if ($value <= 0) {
            return 180;
        }
        return $value;
    } catch (\Throwable $e) {
        return 180;
    }
}

function applyTenantScope($query, string $column, $tenantId): void
{
    if ($tenantId === null) {
        $query->whereNull($column);
    } else {
        $query->where($column, (int) $tenantId);
    }
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userGetFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$userId = (int) ($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    userGetFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$user = Capsule::table('s3_backup_users as u')
    ->leftJoin('s3_backup_tenants as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('u.client_id', $clientId)
    ->select([
        'u.id',
        'u.client_id',
        'u.tenant_id',
        'u.username',
        'u.email',
        'u.status',
        'u.created_at',
        'u.updated_at',
        't.name as tenant_name',
        't.client_id as tenant_client_id',
        't.status as tenant_status',
    ])
    ->first();

if (!$user) {
    userGetFail('User not found.', 404);
}

if (!$isMsp && !empty($user->tenant_id)) {
    userGetFail('User not found.', 404);
}

if ($isMsp && !empty($user->tenant_id)) {
    $tenantClientId = (int) ($user->tenant_client_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== (int) $clientId || $tenantStatus === 'deleted') {
        userGetFail('User not found.', 404);
    }
}

$tenantId = $user->tenant_id !== null ? (int) $user->tenant_id : null;
$onlineThresholdSeconds = getOnlineThresholdForUserDetail();
$metrics = [
    'vaults_count' => 0,
    'jobs_count' => 0,
    'agents_count' => 0,
    'online_devices' => 0,
    'last_backup_at' => null,
];

if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
    $agentQuery = Capsule::table('s3_cloudbackup_agents as a')
        ->where('a.client_id', $clientId);
    applyTenantScope($agentQuery, 'a.tenant_id', $tenantId);
    $agentStats = $agentQuery->select([
        Capsule::raw('COUNT(*) as agents_count'),
        Capsule::raw(
            'SUM(CASE WHEN a.last_seen_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= ' .
            (int) $onlineThresholdSeconds .
            ' THEN 1 ELSE 0 END) as online_devices'
        ),
    ])->first();

    if ($agentStats) {
        $metrics['agents_count'] = (int) ($agentStats->agents_count ?? 0);
        $metrics['online_devices'] = (int) ($agentStats->online_devices ?? 0);
    }
}

if (Capsule::schema()->hasTable('s3_cloudbackup_jobs') && Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
    $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_id', '=', 'a.id')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted');
    applyTenantScope($jobQuery, 'a.tenant_id', $tenantId);
    $jobStats = $jobQuery->select([
        Capsule::raw('COUNT(j.id) as jobs_count'),
        Capsule::raw('COUNT(DISTINCT j.dest_bucket_id) as vaults_count'),
    ])->first();

    if ($jobStats) {
        $metrics['jobs_count'] = (int) ($jobStats->jobs_count ?? 0);
        $metrics['vaults_count'] = (int) ($jobStats->vaults_count ?? 0);
    }
}

if (
    Capsule::schema()->hasTable('s3_cloudbackup_runs') &&
    Capsule::schema()->hasTable('s3_cloudbackup_jobs') &&
    Capsule::schema()->hasTable('s3_cloudbackup_agents')
) {
    $lastBackupQuery = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
        ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_id', '=', 'a.id')
        ->where('j.client_id', $clientId)
        ->whereNotNull('r.finished_at')
        ->whereIn('r.status', ['success', 'warning']);
    applyTenantScope($lastBackupQuery, 'a.tenant_id', $tenantId);
    $lastBackup = $lastBackupQuery->max('r.finished_at');
    if (!empty($lastBackup)) {
        $metrics['last_backup_at'] = $lastBackup;
    }
}

(new JsonResponse([
    'status' => 'success',
    'user' => [
        'id' => (int) $user->id,
        'client_id' => (int) $user->client_id,
        'tenant_id' => $tenantId,
        'username' => (string) $user->username,
        'email' => (string) $user->email,
        'status' => (string) $user->status,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'tenant_name' => $user->tenant_name ?? null,
        'vaults_count' => $metrics['vaults_count'],
        'jobs_count' => $metrics['jobs_count'],
        'agents_count' => $metrics['agents_count'],
        'online_devices' => $metrics['online_devices'],
        'last_backup_at' => $metrics['last_backup_at'],
        'metrics_mode' => 'derived_scope',
    ],
], 200))->send();
exit;

