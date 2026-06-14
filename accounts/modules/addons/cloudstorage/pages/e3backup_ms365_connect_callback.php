<?php

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Ms365Backup\EntraConsentService;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$stateRaw = (string) ($_GET['state'] ?? '');
$consentMode = Ms365E3Controller::peekConsentModeFromState($stateRaw);
$clientIdFromState = EntraConsentService::peekClientId($stateRaw);
$backupUserIdFromState = EntraConsentService::peekBackupUserId($stateRaw);

$error = '';
$returnPath = '';
$backupUserId = $backupUserIdFromState;
$backupUserPublicId = '';

try {
    $result = Ms365E3Controller::handleConnectCallback($_GET);
    $returnPath = trim((string) ($result['return_path'] ?? ''));
    $backupUserId = (int) ($result['backup_user_id'] ?? $backupUserIdFromState);
    $consentMode = (string) ($result['consent_mode'] ?? $consentMode);
    $clientIdFromState = (int) ($result['client_id'] ?? $clientIdFromState);
} catch (\Throwable $e) {
    $error = Ms365E3Controller::customerErrorMessage($e, 'ms365_connect_callback');
    $returnPath = Ms365E3Controller::peekReturnPathFromState($stateRaw);
}

if ($backupUserId > 0 && $clientIdFromState > 0) {
    try {
        $user = Ms365E3Controller::resolveBackupUser($clientIdFromState, (string) $backupUserId);
        $backupUserPublicId = $user['public_id'];
    } catch (\Throwable $_) {
        $backupUserPublicId = '';
    }
}

if ($consentMode === 'popup') {
    $bridgeStatus = $error !== '' ? 'error' : 'success';
    $bridgeError = $error;
    $bridgeBackupUserId = $backupUserPublicId;
    require __DIR__ . '/e3backup_ms365_connect_popup_bridge.php';
    exit;
}

$wizardReturn = '';
if ($backupUserId > 0 && $clientIdFromState > 0) {
    $wizardReturn = Ms365E3Controller::buildWizardReturnUrl(
        $clientIdFromState,
        $backupUserId,
        $error === '',
        $error,
    );
}

if ($wizardReturn !== '' && str_starts_with($wizardReturn, 'index.php')) {
    header('Location: ' . $wizardReturn);
    exit;
}

if ($returnPath !== '' && str_starts_with($returnPath, 'index.php')) {
    $sep = str_contains($returnPath, '?') ? '&' : '?';
    $suffix = $error !== '' ? 'connect_error=' . rawurlencode($error) : 'connect_ok=1&ms365_wizard=1';
    header('Location: ' . $returnPath . $sep . $suffix);
    exit;
}

header('Location: index.php?m=cloudstorage&page=e3backup&view=ms365'
    . ($error !== '' ? '&connect_error=' . rawurlencode($error) : '&connect_ok=1'));
exit;
