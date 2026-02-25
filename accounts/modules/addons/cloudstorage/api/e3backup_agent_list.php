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
$tenantFilter = isset($_GET['tenant_id']) ? $_GET['tenant_id'] : null;

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
        'a.id',
        'a.agent_uuid',
        'a.client_id',
        'a.hostname',
        'a.device_id',
        'a.device_name',
        'a.install_id',
        'a.status',
        'a.agent_type',
        'a.tenant_id',
        'a.tenant_user_id',
        'a.last_seen_at',
        'a.created_at',
        'a.updated_at',
        Capsule::raw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) as seconds_since_seen'),
    ]);

if ($isMsp) {
    $query->leftJoin('s3_backup_tenants as t', 'a.tenant_id', '=', 't.id')
          ->addSelect('t.name as tenant_name');
    
    // Apply tenant filter
    if ($tenantFilter !== null) {
        if ($tenantFilter === 'direct') {
            $query->whereNull('a.tenant_id');
        } elseif ((int)$tenantFilter > 0) {
            $query->where('a.tenant_id', (int)$tenantFilter);
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
}

(new JsonResponse(['status' => 'success', 'agents' => $agents, 'online_threshold_seconds' => $onlineThresholdSeconds], 200))->send();
exit;

