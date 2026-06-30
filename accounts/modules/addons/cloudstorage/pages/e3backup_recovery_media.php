<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\RecoveryMediaBundleService;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('recovery_media');

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->orderBy('hostname')
    ->get(['id', 'hostname', 'tenant_id', 'status', 'last_seen_at']);

$portableToolUrl = trim((string) RecoveryMediaBundleService::getModuleSetting(
    'recovery_media_creator_download_url',
    '/client_installer/e3-recovery-media-creator.exe'
));

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
    'portableToolUrl' => $portableToolUrl,
];

