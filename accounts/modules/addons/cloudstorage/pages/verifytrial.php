<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$debugInfo = [];

$vars = [
    'message'   => '',
    'debugInfo' => '',
];

$token = $_GET['token'] ?? '';

if ($token === '') {
    $_SESSION['message'] = 'Verification link is invalid. Please start again.';
    header('Location: index.php?m=cloudstorage&page=signup');
    exit;
}

try {
    $record = Capsule::table('cloudstorage_trial_verifications')
        ->where('token', $token)
        ->first();

    if (!$record) {
        $_SESSION['message'] = 'Verification link is invalid or has already been used.';
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    // Check expiry and consumed status
    if (!empty($record->consumed_at) || (!empty($record->expires_at) && $record->expires_at < date('Y-m-d H:i:s'))) {
        $_SESSION['message'] = 'Verification link has expired. Please start a new trial signup.';
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    $clientId = (int) $record->client_id;
    $email    = $record->email;

    $meta = [];
    if (!empty($record->meta)) {
        $decoded = json_decode($record->meta, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $username = $meta['username'] ?? null;
    if (empty($username)) {
        $username = 'e3user' . bin2hex(random_bytes(2));
    }

    $adminUser = 'API';

    // Do not provision here. Only consume the token and send the user to the Welcome page.

    // Mark verification as consumed
    Capsule::table('cloudstorage_trial_verifications')
        ->where('id', $record->id)
        ->update(['consumed_at' => date('Y-m-d H:i:s')]);

    // ----------------------------------------------------------------------------------------
    // 3) Single Sign-On (SSO) to log the user in and redirect them to the Welcome page
    // ----------------------------------------------------------------------------------------
    $ssoResult = localAPI('CreateSsoToken', [
        'client_id'         => $clientId,
        'destination'       => 'sso:custom_redirect',
        'sso_redirect_path' => 'index.php?m=cloudstorage&page=welcome',
    ], $adminUser);

    $debugInfo['ssoResult'] = $ssoResult;

    if ($ssoResult['result'] === 'success') {
        $_SESSION['message'] = "Email verified! Welcome.";
        header("Location: {$ssoResult['redirect_url']}");
        exit;
    } else {
        $_SESSION['message'] = "Account verified but auto-login failed: " . $ssoResult['message'];
        header("Location: index.php?m=cloudstorage&page=welcome");
        exit;
    }
} catch (\Exception $e) {
    $_SESSION['message'] = 'Unexpected verification error: ' . $e->getMessage();
    header('Location: index.php?m=cloudstorage&page=signup');
    exit;
}

return $vars;


