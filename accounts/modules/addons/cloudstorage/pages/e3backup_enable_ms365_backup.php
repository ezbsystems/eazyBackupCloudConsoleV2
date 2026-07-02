<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupPricingPanelData;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';
require_once __DIR__ . '/../lib/Client/E3BackupClientState.php';
require_once __DIR__ . '/../lib/Client/E3BackupPricingPanelData.php';

$loggedInUserId = E3BackupAccess::requireE3BackupShellAccess('enable_ms365_backup');

if (E3BackupClientState::clientHasMs365Product($loggedInUserId)) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=dashboard');
    exit;
}

return [
    'isMspClient' => MspController::isMspClient($loggedInUserId),
    'pricing' => E3BackupPricingPanelData::forClient($loggedInUserId),
    'ebEnableProductChoice' => 'ms365',
    'ebEnablePageTitle' => 'Add Microsoft 365 Backup',
    'ebEnablePageDescription' => 'Back up Exchange, OneDrive, SharePoint, and Teams. No local backup agent is required.',
];
