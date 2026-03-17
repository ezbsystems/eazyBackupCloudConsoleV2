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

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
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
        'a.tenant_id as storage_tenant_id',
        $tenantSelect,
        'a.tenant_user_id',
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

// Add computed online/offline status.
foreach ($agents as $a) {
    $secs = isset($a->seconds_since_seen) ? (int) $a->seconds_since_seen : null;
    if (empty($a->last_seen_at)) {
        $a->online_status = 'never';
    } elseif ($secs !== null && $secs <= $onlineThresholdSeconds) {
        $a->online_status = 'online';
    } else {
        $a->online_status = 'offline';
    }
    $a->online_threshold_seconds = $onlineThresholdSeconds;
    unset($a->storage_tenant_id);
}

(new JsonResponse(['status' => 'success', 'agents' => $agents, 'online_threshold_seconds' => $onlineThresholdSeconds], 200))->send();
exit;

