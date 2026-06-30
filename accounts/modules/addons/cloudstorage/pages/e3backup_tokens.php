<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('tokens');

$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

$isMspClient = MspController::isMspClient($loggedInUserId);

// Get tenants for dropdown (MSP only)
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'token' => $csrfToken,
];

