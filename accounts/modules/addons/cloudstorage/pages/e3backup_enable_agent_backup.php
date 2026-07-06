<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';
require_once __DIR__ . '/../lib/Client/E3BackupClientState.php';

$loggedInUserId = E3BackupAccess::requireE3BackupShellAccess('enable_agent_backup');

$alreadyEntitled = E3BackupClientState::clientHasLocalAgentEntitlement($loggedInUserId);
$hasBackupUser = E3BackupAccess::defaultBackupUser($loggedInUserId) !== null;

if ($alreadyEntitled) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=agents');
    exit;
}

if (!$hasBackupUser) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users&create=1');
    exit;
}

// MS365-only and other legacy clients may already have a backup user; show the
// enable page instead of bouncing back to Users (which caused a redirect loop).
return [
    'isMspClient' => MspController::isMspClient($loggedInUserId),
    'ebHasExistingBackupUser' => true,
];
