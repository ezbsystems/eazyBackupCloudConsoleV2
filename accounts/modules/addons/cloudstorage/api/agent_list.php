<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Helper to fetch module setting
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

$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $clientId)
    ->select([
        'id',
        'agent_uuid',
        'client_id',
        'hostname',
        'status',
        'tenant_id',
        'last_seen_at',
        'created_at',
        'updated_at',
        Capsule::raw('TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) as seconds_since_seen'),
    ])
    ->get();

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
}

(new JsonResponse(['status' => 'success', 'agents' => $agents], 200))->send();
exit;

