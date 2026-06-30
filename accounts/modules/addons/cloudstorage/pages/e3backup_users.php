<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupPricingPanelData;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('users');

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}
$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'csrfToken' => $csrfToken,
    'ebPricingPanel' => E3BackupPricingPanelData::forClient($loggedInUserId),
];

