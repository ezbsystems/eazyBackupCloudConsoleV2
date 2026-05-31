<?php
/**
 * e3 Cloud Backup - Getting Started route.
 *
 * Lands new customers here right after provisioning. Renders a 4-step
 * stepper (Download / Sign in / First job / First run) and triggers the
 * driver.js tour on first visit.
 */

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\OnboardingState;

require_once __DIR__ . '/../lib/Client/OnboardingState.php';
require_once __DIR__ . '/../lib/Beta/BetaGate.php';

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = (int) $ca->getUserID();
if ($loggedInUserId <= 0) {
    header('Location: clientarea.php');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);

// Record this visit so we can tell if it's the customer's first time here.
OnboardingState::touchVisit($loggedInUserId);

$onboarding = OnboardingState::compute($loggedInUserId);

// Resolve a default backup user (provisioning created one named after the
// customer). We use it so the "Create your first backup" CTA can link to
// the right user detail page.
$defaultBackupUser = null;
try {
    if (Capsule::schema()->hasTable('s3_backup_users')) {
        $defaultBackupUser = Capsule::table('s3_backup_users')
            ->where('client_id', $loggedInUserId)
            ->orderBy('id', 'asc')
            ->first(['id', 'username', 'email']);
    }
} catch (\Throwable $e) {
    $defaultBackupUser = null;
}

// Pull the client's email so we can show it on the "Sign in" cheat sheet.
$clientEmail = '';
try {
    $clientEmail = (string) Capsule::table('tblclients')->where('id', $loggedInUserId)->value('email');
} catch (\Throwable $e) {
    $clientEmail = '';
}

// Existing-customer onboarding: unlike every other e3backup view, Getting
// Started does NOT redirect an unprovisioned client to Welcome. Instead, when
// the client has no e3 Cloud Backup service yet but is allowed to see the beta
// product, we render the page underneath the Beta + Username drawers so they
// can self-provision in place (reusing the same drawers as the trial flow).
$ebE3NeedsProvision = false;
try {
    $e3Pid = (int) ProductConfig::e3CloudBackupPid();
    $product = ($e3Pid > 0) ? DBController::getProduct($loggedInUserId, $e3Pid) : null;
    $ebE3NeedsProvision = (is_null($product) || empty($product->username));
} catch (\Throwable $e) {
    $ebE3NeedsProvision = false;
}

$ebE3BetaVisible = false;
try {
    $ebE3BetaVisible = (bool) \WHMCS\Module\Addon\CloudStorage\Beta\BetaGate::isE3BackupVisible($loggedInUserId);
} catch (\Throwable $e) {
    $ebE3BetaVisible = false;
}

$ebExistingClientOnboarding = $ebE3NeedsProvision && $ebE3BetaVisible;

return [
    'isMspClient'                 => $isMspClient,
    'onboarding'                  => $onboarding,
    'defaultBackupUser'           => $defaultBackupUser,
    'clientEmail'                 => $clientEmail,
    'ebE3NeedsProvision'          => $ebE3NeedsProvision,
    'ebE3BetaVisible'             => $ebE3BetaVisible,
    'ebExistingClientOnboarding'  => $ebExistingClientOnboarding,
];
