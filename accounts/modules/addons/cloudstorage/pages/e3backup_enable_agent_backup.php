<?php

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupShellAccess('enable_agent_backup');

header('Location: index.php?m=cloudstorage&page=e3backup&view=users&create=1');
exit;
