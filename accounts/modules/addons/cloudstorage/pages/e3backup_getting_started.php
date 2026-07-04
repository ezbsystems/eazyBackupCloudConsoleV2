<?php
/**
 * e3 Cloud Backup - Getting Started route.
 *
 * Unified hub (flag on): workload-first chooser scoped to a backup user.
 * Legacy (flag off): 4-step local agent onboarding with auto-redirect when complete.
 */

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupClientState;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\OnboardingState;
use WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap;

require_once __DIR__ . '/../lib/Client/OnboardingState.php';
require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';
require_once __DIR__ . '/../lib/Client/E3BackupClientState.php';
require_once __DIR__ . '/../lib/Provision/E3BackupUserProductBootstrap.php';

$ms365Autoload = dirname(__DIR__, 2) . '/ms365backup/ms365backup_autoload.php';
if (is_file($ms365Autoload)) {
    require_once $ms365Autoload;
}

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('getting_started');
$isMspClient = MspController::isMspClient($loggedInUserId);
$unifiedEnabled = E3BackupUserProductBootstrap::isUnifiedEnabled();

$clientEmail = '';
try {
    $clientEmail = (string) Capsule::table('tblclients')->where('id', $loggedInUserId)->value('email');
} catch (\Throwable $_) {
    $clientEmail = '';
}

if (!$unifiedEnabled) {
    OnboardingState::touchVisit($loggedInUserId);
    $onboarding = OnboardingState::compute($loggedInUserId);

    if (!empty($onboarding['all_complete'])) {
        header('Location: index.php?m=cloudstorage&page=e3backup&view=dashboard');
        exit;
    }

    $defaultBackupUser = null;
    try {
        if (Capsule::schema()->hasTable('s3_backup_users')) {
            $defaultBackupUser = Capsule::table('s3_backup_users')
                ->where('client_id', $loggedInUserId)
                ->orderBy('id', 'asc')
                ->first(['id', 'username', 'email']);
        }
    } catch (\Throwable $_) {
        $defaultBackupUser = null;
    }

    return [
        'unifiedEnabled'    => false,
        'isMspClient'       => $isMspClient,
        'onboarding'        => $onboarding,
        'defaultBackupUser' => $defaultBackupUser,
        'clientEmail'       => $clientEmail,
    ];
}

OnboardingState::touchVisit($loggedInUserId);

/**
 * Map welcome product_choice to hub workload intent.
 */
$resolveIntentFromProductChoice = static function (int $clientId): string {
    try {
        if (!Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            return '';
        }
        $choice = strtolower(trim((string) Capsule::table('cloudstorage_trial_selection')
            ->where('client_id', $clientId)
            ->value('product_choice')));
        if (in_array($choice, ['e3backup', 'backup', 'e3_backup', 'e3-backup', 'cloudbackup_e3'], true)) {
            return 'local';
        }
        if (in_array($choice, ['ms365', 'm365'], true)) {
            return 'ms365';
        }
        if (in_array($choice, ['cloud2cloud', 'cloud-to-cloud'], true)) {
            return 'saas';
        }
    } catch (\Throwable $_) {
    }

    return '';
};

$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$userIdRaw = trim((string) ($_GET['user_id'] ?? ''));

$selectCols = ['u.id', 'u.username', 'u.email'];
if ($hasPublicIdCol) {
    $selectCols[] = 'u.public_id';
}
if (Capsule::schema()->hasColumn('s3_backup_users', 'encryption_mode')) {
    $selectCols[] = 'u.encryption_mode';
}
if (Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
    $selectCols[] = 'u.backup_type';
}

$backupUser = null;
if ($userIdRaw !== '') {
    $userLookup = Capsule::table('s3_backup_users as u')
        ->where('u.client_id', $loggedInUserId);
    if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
        $userLookup->where('u.public_id', $userIdRaw);
    } else {
        $userLookup->where('u.id', (int) $userIdRaw);
    }
    $backupUser = $userLookup->select($selectCols)->first();
}

if (!$backupUser) {
    $defaultBu = E3BackupAccess::defaultBackupUser($loggedInUserId);
    if ($defaultBu) {
        $fallbackLookup = Capsule::table('s3_backup_users as u')
            ->where('u.client_id', $loggedInUserId)
            ->where('u.id', (int) $defaultBu['id']);
        $backupUser = $fallbackLookup->select($selectCols)->first();
    }
}

if (!$backupUser) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

$backupUserId = (int) $backupUser->id;
$backupUserRouteId = ($hasPublicIdCol && !empty($backupUser->public_id))
    ? (string) $backupUser->public_id
    : (string) $backupUserId;

$encryptionMode = 'managed';
if (isset($backupUser->encryption_mode)) {
    $encryptionMode = strtolower(trim((string) $backupUser->encryption_mode));
    if (!in_array($encryptionMode, ['managed', 'strict'], true)) {
        $encryptionMode = 'managed';
    }
}

$intent = strtolower(trim((string) ($_GET['intent'] ?? '')));
if (!in_array($intent, ['local', 'ms365', 'saas'], true)) {
    $intent = $resolveIntentFromProductChoice($loggedInUserId);
}

$onboardingLocal = OnboardingState::compute($loggedInUserId);
$onboardingMs365 = [
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
        $onboardingMs365 = \Ms365Backup\Ms365Onboarding::computeForBackupUser($loggedInUserId, $backupUserId);
    } catch (\Throwable $_) {
    }
}

if ($intent === '') {
    if (empty($onboardingMs365['all_complete'])) {
        $intent = 'ms365';
    } elseif (empty($onboardingLocal['all_complete'])) {
        $intent = 'local';
    } else {
        $intent = 'local';
    }
}

if ($encryptionMode === 'strict') {
    $intent = 'local';
}

$availableWorkloads = $encryptionMode === 'strict'
    ? ['local']
    : ['local', 'ms365', 'saas'];

if (!in_array($intent, $availableWorkloads, true)) {
    $intent = 'local';
}

$activeWorkload = $intent;

if ($activeWorkload === 'ms365') {
    $pill = [
        'completed' => (int) ($onboardingMs365['completed_count'] ?? 0),
        'total' => (int) ($onboardingMs365['total_count'] ?? 3),
    ];
} elseif ($activeWorkload === 'local') {
    $pill = [
        'completed' => (int) ($onboardingLocal['completed_count'] ?? 0),
        'total' => (int) ($onboardingLocal['total_count'] ?? 4),
    ];
} else {
    $pill = ['completed' => 0, 'total' => 0];
}

$wizardUrl = $backupUserRouteId !== ''
    ? 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' . rawurlencode($backupUserRouteId) . '&ms365_wizard=1#jobs'
    : 'index.php?m=cloudstorage&page=e3backup&view=users';

$cloudWizardUrl = $backupUserRouteId !== ''
    ? 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' . rawurlencode($backupUserRouteId) . '&cloud_wizard=1#jobs'
    : 'index.php?m=cloudstorage&page=e3backup&view=users';

$userDetailUrl = $backupUserRouteId !== ''
    ? 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' . rawurlencode($backupUserRouteId)
    : 'index.php?m=cloudstorage&page=e3backup&view=users';

$defaultBackupUser = [
    'id' => $backupUserId,
    'username' => (string) ($backupUser->username ?? ''),
    'public_id' => ($hasPublicIdCol && !empty($backupUser->public_id))
        ? (string) $backupUser->public_id
        : '',
    'email' => (string) ($backupUser->email ?? ''),
];

return [
    'unifiedEnabled'     => true,
    'isMspClient'        => $isMspClient,
    'activeWorkload'     => $activeWorkload,
    'availableWorkloads' => $availableWorkloads,
    'onboardingLocal'    => $onboardingLocal,
    'onboardingMs365'    => $onboardingMs365,
    'onboarding'         => $activeWorkload === 'ms365' ? $onboardingMs365 : $onboardingLocal,
    'pill'               => $pill,
    'backupUserRouteId'  => $backupUserRouteId,
    'backupUser'         => $backupUser,
    'defaultBackupUser'  => $defaultBackupUser,
    'encryptionMode'     => $encryptionMode,
    'wizardUrl'          => $wizardUrl,
    'cloudWizardUrl'     => $cloudWizardUrl,
    'userDetailUrl'      => $userDetailUrl,
    'clientEmail'        => $clientEmail,
    'ebHasMs365Product'  => E3BackupClientState::clientHasMs365Product($loggedInUserId),
    'ebMs365Only'        => E3BackupAccess::clientIsMs365Only($loggedInUserId),
];
