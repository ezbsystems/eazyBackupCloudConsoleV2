<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupPricingPanelData;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';
require_once __DIR__ . '/../lib/Client/E3BackupClientState.php';
require_once __DIR__ . '/../lib/Client/E3BackupPricingPanelData.php';

$loggedInUserId = E3BackupAccess::requireE3BackupShellAccess('enable_agent_backup');

if (E3BackupClientState::clientHasE3AgentProduct($loggedInUserId)) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=dashboard');
    exit;
}

return [
    'isMspClient' => MspController::isMspClient($loggedInUserId),
    'pricing' => E3BackupPricingPanelData::forClient($loggedInUserId),
    'ebEnableProductChoice' => 'e3backup',
    'ebEnablePageTitle' => 'Enable workstation & server backup',
    'ebEnablePageDescription' => 'Back up files, disks, Hyper-V VMs, and more with the e3 Backup Agent. Object storage is billed at your normal rate.',
];
