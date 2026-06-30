<?php

/**
 * Microsoft 365 Backup - Getting Started route.
 *
 * Lands new MS365 customers here after welcome provisioning. Guides connect,
 * inventory, and first backup using the MS365 job wizard.
 */

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$ms365Autoload = dirname(__DIR__, 2) . '/ms365backup/ms365backup_autoload.php';
if (is_file($ms365Autoload)) {
    require_once $ms365Autoload;
}

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('ms365_getting_started');

$isMspClient = MspController::isMspClient($loggedInUserId);
$defaultBackupUser = E3BackupAccess::defaultBackupUser($loggedInUserId);
$backupUserId = $defaultBackupUser ? (int) $defaultBackupUser['id'] : 0;
$backupUserRouteId = '';
if ($defaultBackupUser) {
    $backupUserRouteId = ($defaultBackupUser['public_id'] ?? '') !== ''
        ? (string) $defaultBackupUser['public_id']
        : (string) $defaultBackupUser['id'];
}

$onboarding = [
    'steps' => [
        'connect' => ['complete' => false, 'label' => 'Connect Microsoft 365'],
        'inventory' => ['complete' => false, 'label' => 'Refresh tenant inventory'],
        'first_backup' => ['complete' => false, 'label' => 'Complete first backup'],
    ],
    'completed_count' => 0,
    'total_count' => 3,
    'all_complete' => false,
    'can_start_backup' => false,
];

if ($backupUserId > 0 && class_exists('\\Ms365Backup\\Ms365Onboarding')) {
    try {
        $onboarding = \Ms365Backup\Ms365Onboarding::computeForBackupUser($loggedInUserId, $backupUserId);
    } catch (\Throwable $e) {
        $onboarding = $onboarding;
    }
}

$wizardUrl = $backupUserRouteId !== ''
    ? 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' . rawurlencode($backupUserRouteId) . '&ms365_wizard=1#jobs'
    : 'index.php?m=cloudstorage&page=e3backup&view=users';

return [
    'isMspClient'        => $isMspClient,
    'onboarding'         => $onboarding,
    'defaultBackupUser'  => $defaultBackupUser,
    'backupUserRouteId'  => $backupUserRouteId,
    'wizardUrl'          => $wizardUrl,
    'ebMs365Only'        => E3BackupAccess::clientIsMs365Only($loggedInUserId),
];
