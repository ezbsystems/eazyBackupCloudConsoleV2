<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';
require_once __DIR__ . '/../lib/Client/E3BackupClientState.php';

$loggedInUserId = E3BackupAccess::requireE3BackupShellAccess('enable_agent_backup');

$alreadyEntitled = E3BackupClientState::clientHasLocalAgentEntitlement($loggedInUserId);

// #region agent log
@file_put_contents('/var/www/eazybackup.ca/.cursor/debug-991471.log', json_encode([
    'sessionId' => '991471',
    'timestamp' => (int) round(microtime(true) * 1000),
    'location' => 'e3backup_enable_agent_backup.php',
    'message' => 'enable_agent_backup_redirect',
    'data' => [
        'client_id' => $loggedInUserId,
        'already_entitled' => $alreadyEntitled,
        'redirect' => $alreadyEntitled ? 'users' : 'users&create=1',
    ],
    'hypothesisId' => 'H2',
], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
// #endregion

if ($alreadyEntitled) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

header('Location: index.php?m=cloudstorage&page=e3backup&view=users&create=1');
exit;
