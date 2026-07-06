<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';
require_once __DIR__ . '/../lib/Client/E3BackupClientState.php';

$loggedInUserId = E3BackupAccess::requireE3BackupShellAccess('enable_agent_backup');

$alreadyEntitled = E3BackupClientState::clientHasLocalAgentEntitlement($loggedInUserId);

if ($alreadyEntitled) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

// MS365-only and other legacy clients may already have a backup user; do not
// force the create-user modal when enabling workstation backup.
if (E3BackupAccess::defaultBackupUser($loggedInUserId) !== null) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

header('Location: index.php?m=cloudstorage&page=e3backup&view=users&create=1');
exit;
