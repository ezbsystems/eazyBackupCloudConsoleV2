<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('disk_image_restore');

$isMspClient = MspController::isMspClient($loggedInUserId);

$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->orderBy('hostname')
    ->get(['agent_uuid', 'hostname', 'device_name', 'tenant_id', 'status', 'last_seen_at']);

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
];
