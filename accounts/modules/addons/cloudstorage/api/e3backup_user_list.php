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

function userListFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

function scopeKeyFromTenant($tenantId): string
{
    return empty($tenantId) ? 'direct' : ('tenant_' . (int) $tenantId);
}

function getOnlineThresholdSeconds(): int
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

function getScopedMetrics(int $clientId, bool $isMsp, ?int $tenantFilter, bool $directOnly): array
{
    $metricsByScope = [];
    $onlineThresholdSeconds = getOnlineThresholdSeconds();

    $hasAgents = Capsule::schema()->hasTable('s3_cloudbackup_agents');
    $hasJobs = Capsule::schema()->hasTable('s3_cloudbackup_jobs');
    $hasRuns = Capsule::schema()->hasTable('s3_cloudbackup_runs');

    if ($hasAgents) {
        $agentQuery = Capsule::table('s3_cloudbackup_agents as a')
            ->where('a.client_id', $clientId)
            ->select([
                'a.tenant_id',
                Capsule::raw('COUNT(*) as agents_count'),
                Capsule::raw(
                    'SUM(CASE WHEN a.last_seen_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= ' .
                    (int) $onlineThresholdSeconds .
                    ' THEN 1 ELSE 0 END) as online_devices'
                ),
            ])
            ->groupBy('a.tenant_id');

        if ($directOnly) {
            $agentQuery->whereNull('a.tenant_id');
        } elseif ($tenantFilter !== null) {
            if ($tenantFilter > 0) {
                $agentQuery->where('a.tenant_id', $tenantFilter);
            } else {
                $agentQuery->whereNull('a.tenant_id');
            }
        } elseif (!$isMsp) {
            $agentQuery->whereNull('a.tenant_id');
        }

        $agentRows = $agentQuery->get();
        foreach ($agentRows as $row) {
            $scopeKey = scopeKeyFromTenant($row->tenant_id ?? null);
            if (!isset($metricsByScope[$scopeKey])) {
                $metricsByScope[$scopeKey] = [
                    'vaults_count' => 0,
                    'jobs_count' => 0,
                    'agents_count' => 0,
                    'online_devices' => 0,
                    'last_backup_at' => null,
                ];
            }
            $metricsByScope[$scopeKey]['agents_count'] = (int) ($row->agents_count ?? 0);
            $metricsByScope[$scopeKey]['online_devices'] = (int) ($row->online_devices ?? 0);
        }
    }

    if ($hasJobs && $hasAgents) {
        $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')
            ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_id', '=', 'a.id')
            ->where('j.client_id', $clientId)
            ->where('j.status', '!=', 'deleted')
            ->select([
                'a.tenant_id',
                Capsule::raw('COUNT(j.id) as jobs_count'),
                Capsule::raw('COUNT(DISTINCT j.dest_bucket_id) as vaults_count'),
            ])
            ->groupBy('a.tenant_id');

        if ($directOnly) {
            $jobQuery->whereNull('a.tenant_id');
        } elseif ($tenantFilter !== null) {
            if ($tenantFilter > 0) {
                $jobQuery->where('a.tenant_id', $tenantFilter);
            } else {
                $jobQuery->whereNull('a.tenant_id');
            }
        } elseif (!$isMsp) {
            $jobQuery->whereNull('a.tenant_id');
        }

        $jobRows = $jobQuery->get();
        foreach ($jobRows as $row) {
            $scopeKey = scopeKeyFromTenant($row->tenant_id ?? null);
            if (!isset($metricsByScope[$scopeKey])) {
                $metricsByScope[$scopeKey] = [
                    'vaults_count' => 0,
                    'jobs_count' => 0,
                    'agents_count' => 0,
                    'online_devices' => 0,
                    'last_backup_at' => null,
                ];
            }
            $metricsByScope[$scopeKey]['jobs_count'] = (int) ($row->jobs_count ?? 0);
            $metricsByScope[$scopeKey]['vaults_count'] = (int) ($row->vaults_count ?? 0);
        }
    }

    if ($hasRuns && $hasJobs && $hasAgents) {
        $lastBackupQuery = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
            ->leftJoin('s3_cloudbackup_agents as a', 'j.agent_id', '=', 'a.id')
            ->where('j.client_id', $clientId)
            ->whereNotNull('r.finished_at')
            ->whereIn('r.status', ['success', 'warning'])
            ->select([
                'a.tenant_id',
                Capsule::raw('MAX(r.finished_at) as last_backup_at'),
            ])
            ->groupBy('a.tenant_id');

        if ($directOnly) {
            $lastBackupQuery->whereNull('a.tenant_id');
        } elseif ($tenantFilter !== null) {
            if ($tenantFilter > 0) {
                $lastBackupQuery->where('a.tenant_id', $tenantFilter);
            } else {
                $lastBackupQuery->whereNull('a.tenant_id');
            }
        } elseif (!$isMsp) {
            $lastBackupQuery->whereNull('a.tenant_id');
        }

        $lastBackupRows = $lastBackupQuery->get();
        foreach ($lastBackupRows as $row) {
            $scopeKey = scopeKeyFromTenant($row->tenant_id ?? null);
            if (!isset($metricsByScope[$scopeKey])) {
                $metricsByScope[$scopeKey] = [
                    'vaults_count' => 0,
                    'jobs_count' => 0,
                    'agents_count' => 0,
                    'online_devices' => 0,
                    'last_backup_at' => null,
                ];
            }
            $metricsByScope[$scopeKey]['last_backup_at'] = $row->last_backup_at ?? null;
        }
    }

    return $metricsByScope;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userListFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantFilterRaw = $_GET['tenant_id'] ?? null;

$tenantFilter = null;
$directOnly = false;

if ($tenantFilterRaw !== null && $tenantFilterRaw !== '') {
    if ($tenantFilterRaw === 'direct') {
        $directOnly = true;
    } elseif ((int) $tenantFilterRaw > 0) {
        $tenantFilter = (int) $tenantFilterRaw;
    } else {
        userListFail('Invalid tenant filter', 400, ['tenant_id' => 'Invalid tenant selection']);
    }
}

if (!$isMsp) {
    if ($tenantFilter !== null || $directOnly) {
        userListFail('Tenant filtering is only available for MSP accounts', 403);
    }
    $directOnly = true;
}

if ($tenantFilter !== null) {
    $tenant = MspController::getTenant($tenantFilter, $clientId);
    if (!$tenant) {
        userListFail('Tenant not found', 404, ['tenant_id' => 'Tenant not found for this account']);
    }
}

$userQuery = Capsule::table('s3_backup_users as u')
    ->leftJoin('s3_backup_tenants as t', function ($join) use ($clientId) {
        $join->on('u.tenant_id', '=', 't.id')
            ->where('t.client_id', '=', $clientId)
            ->where('t.status', '!=', 'deleted');
    })
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
    ]);

if ($directOnly) {
    $userQuery->whereNull('u.tenant_id');
} elseif ($tenantFilter !== null) {
    $userQuery->where('u.tenant_id', $tenantFilter);
} elseif ($isMsp) {
    $userQuery->where(function ($q) {
        $q->whereNull('u.tenant_id')->orWhereNotNull('t.id');
    });
}

$users = $userQuery->orderBy('u.username')->get();
$metricsByScope = getScopedMetrics($clientId, $isMsp, $tenantFilter, $directOnly);

$normalizedUsers = [];
foreach ($users as $user) {
    $scopeKey = scopeKeyFromTenant($user->tenant_id ?? null);
    $scopeMetrics = $metricsByScope[$scopeKey] ?? [
        'vaults_count' => 0,
        'jobs_count' => 0,
        'agents_count' => 0,
        'online_devices' => 0,
        'last_backup_at' => null,
    ];

    $normalizedUsers[] = [
        'id' => (int) $user->id,
        'client_id' => (int) $user->client_id,
        'tenant_id' => $user->tenant_id !== null ? (int) $user->tenant_id : null,
        'username' => (string) $user->username,
        'email' => (string) $user->email,
        'status' => (string) $user->status,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        'tenant_name' => $user->tenant_name ?? null,
        'vaults_count' => (int) $scopeMetrics['vaults_count'],
        'jobs_count' => (int) $scopeMetrics['jobs_count'],
        'agents_count' => (int) $scopeMetrics['agents_count'],
        'last_backup_at' => $scopeMetrics['last_backup_at'],
        'online_devices' => (int) $scopeMetrics['online_devices'],
        'metrics_mode' => 'derived_scope',
    ];
}

(new JsonResponse([
    'status' => 'success',
    'users' => $normalizedUsers,
    'is_msp' => $isMsp,
    'online_threshold_seconds' => getOnlineThresholdSeconds(),
], 200))->send();
exit;

