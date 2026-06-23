<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentLiveness.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/AgentUpdateService.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\AgentUpdateService;
use WHMCS\Module\Addon\CloudStorage\Client\AgentIngestSupport;
use WHMCS\Module\Addon\CloudStorage\Client\AgentLiveness;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$hasAgentBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');
$hasBackupUserPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;
$tenantFilter = null;

if ($tenantFilterRaw !== null && $tenantFilterRaw !== '' && $tenantFilterRaw !== 'direct') {
    $tenant = MspController::getTenantByPublicId($tenantFilterRaw, $clientId);
    if ($tenant) {
        $tenantFilter = (int) $tenant->id;
    } else {
        (new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found'], 404))->send();
        exit;
    }
}

$tenantSelect = 'a.tenant_id';
if ($isMsp && $tenantTable === 'eb_tenants' && MspController::hasTenantPublicIds()) {
    $tenantSelect = Capsule::raw('t.public_id as tenant_id');
}

function getModuleSetting(string $key, $default = null)
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->value('value');
        return ($val !== null && $val !== '') ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

// Consider agent online if last_seen_at is within this window.
// Default to 180s to tolerate brief sleep/network hiccups.
$onlineThresholdSeconds = (int) getModuleSetting('cloudbackup_agent_online_threshold_seconds', 180);
if ($onlineThresholdSeconds <= 0) {
    $onlineThresholdSeconds = 180;
}

// Build query
$query = Capsule::table('s3_cloudbackup_agents as a')
    ->where('a.client_id', $clientId)
    ->select([
        'a.agent_uuid',
        'a.client_id',
        'a.hostname',
        'a.device_id',
        'a.device_name',
        'a.install_id',
        'a.status',
        'a.agent_type',
        'a.agent_version',
        'a.agent_os',
        'a.agent_arch',
        'a.agent_build',
        'a.tenant_id as storage_tenant_id',
        $tenantSelect,
        'a.tenant_user_id',
        $hasAgentBackupUserId ? 'a.backup_user_id' : Capsule::raw('NULL as backup_user_id'),
        'a.last_seen_at',
        'a.created_at',
        'a.updated_at',
        Capsule::raw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) as seconds_since_seen'),
    ]);

if ($isMsp) {
    $query->leftJoin($tenantTable . ' as t', function ($join) use ($clientId, $tenantTable) {
        $join->on('a.tenant_id', '=', 't.id');
        if ($tenantTable === 'eb_tenants') {
            $mspId = MspController::getMspIdForClient((int)$clientId);
            $join->where('t.msp_id', '=', (int)($mspId ?? 0));
        } else {
            $join->where('t.client_id', '=', (int)$clientId);
        }
    })->addSelect('t.name as tenant_name');
    
    // Apply tenant filter
    if ($tenantFilterRaw !== null) {
        if ($tenantFilterRaw === 'direct') {
            $query->whereNull('a.tenant_id');
        } elseif ($tenantFilter !== null) {
            $query->where('a.tenant_id', $tenantFilter);
        }
    }
}

$agents = $query->orderByDesc('a.created_at')->get();

$backupUserRouteById = [];
if ($hasAgentBackupUserId) {
    $backupUserIds = $agents->pluck('backup_user_id')
        ->filter(function ($value) {
            return (int) $value > 0;
        })
        ->map(function ($value) {
            return (int) $value;
        })
        ->unique()
        ->values()
        ->toArray();
    if (!empty($backupUserIds)) {
        $backupUserSelect = ['id'];
        if ($hasBackupUserPublicId) {
            $backupUserSelect[] = 'public_id';
        }
        $backupUsers = Capsule::table('s3_backup_users')
            ->whereIn('id', $backupUserIds)
            ->get($backupUserSelect);
        foreach ($backupUsers as $backupUser) {
            $routeId = $hasBackupUserPublicId
                ? trim((string) ($backupUser->public_id ?? ''))
                : '';
            if ($routeId === '') {
                $routeId = (string) ((int) ($backupUser->id ?? 0));
            }
            $backupUserRouteById[(int) $backupUser->id] = $routeId;
        }
    }
}

// Latest published version per platform, for "update available" badges.
$latestVersionByPlatform = [];
foreach (['windows', 'linux'] as $platform) {
    $rel = AgentUpdateService::latestRelease($platform);
    $latestVersionByPlatform[$platform] = $rel ? trim((string) ($rel->version_label ?? '')) : '';
}

// Most recent update job per agent in this result set (active or terminal),
// so the drawer can show live status without a second request on first paint.
$updateJobByAgent = [];
if (Capsule::schema()->hasTable('s3_agent_update_jobs')) {
    $uuids = $agents->pluck('agent_uuid')->filter()->unique()->values()->toArray();
    if (!empty($uuids)) {
        $rows = Capsule::table('s3_agent_update_jobs')
            ->whereIn('agent_uuid', $uuids)
            ->orderByDesc('id')
            ->get(['id', 'agent_uuid', 'status', 'detail', 'target_version', 'from_version', 'updated_at']);
        foreach ($rows as $row) {
            if (!isset($updateJobByAgent[$row->agent_uuid])) {
                $updateJobByAgent[$row->agent_uuid] = $row;
            }
        }
    }
}

// Add computed online/offline status.
$redisOnlineByUuid = AgentLiveness::bulkOnlineStatus(
    $agents->pluck('agent_uuid')->filter()->values()->all()
);
foreach ($agents as $a) {
    $redisOnline = $redisOnlineByUuid[(string) $a->agent_uuid] ?? null;
    if ($redisOnline === true) {
        $a->online_status = 'online';
    } else {
        $secs = isset($a->seconds_since_seen) ? (int) $a->seconds_since_seen : null;
        if (empty($a->last_seen_at)) {
            $a->online_status = 'never';
        } elseif ($secs !== null && $secs <= $onlineThresholdSeconds) {
            $a->online_status = 'online';
        } else {
            $a->online_status = 'offline';
        }
    }
    $a->online_threshold_seconds = $onlineThresholdSeconds;
    $backupUserId = (int) ($a->backup_user_id ?? 0);
    $a->backup_user_route_id = $backupUserId > 0
        ? ($backupUserRouteById[$backupUserId] ?? null)
        : null;

    // Update fields.
    $platform = AgentUpdateService::platformForAgent($a);
    $latest = $platform !== '' ? ($latestVersionByPlatform[$platform] ?? '') : '';
    $current = trim((string) ($a->agent_version ?? ''));
    $a->latest_version = $latest !== '' ? $latest : null;
    $a->update_supported = $platform !== '';
    $a->update_available = ($platform !== '' && $latest !== '' && $current !== '' && AgentIngestSupport::versionLessThan($current, $latest));
    // When the agent never reported a version yet, we cannot assert it is current.
    if ($platform !== '' && $latest !== '' && $current === '') {
        $a->update_available = true;
    }
    $job = $updateJobByAgent[$a->agent_uuid] ?? null;
    $a->update_job = $job ? [
        'id' => (int) $job->id,
        'status' => $job->status,
        'detail' => $job->detail,
        'target_version' => $job->target_version,
        'from_version' => $job->from_version,
        'updated_at' => $job->updated_at,
    ] : null;

    unset($a->storage_tenant_id, $a->backup_user_id);
}

(new JsonResponse(['status' => 'success', 'agents' => $agents, 'online_threshold_seconds' => $onlineThresholdSeconds], 200))->send();
exit;
