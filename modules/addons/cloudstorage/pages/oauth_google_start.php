<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

try {
    // Load addon settings
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->pluck('value', 'setting');

    $clientId = $settings['cloudbackup_google_client_id'] ?? '';
    $clientSecret = $settings['cloudbackup_google_client_secret'] ?? '';
    $scopes = trim($settings['cloudbackup_google_scopes'] ?? 'https://www.googleapis.com/auth/drive.readonly');

    if (!$clientId || !$clientSecret) {
        $_SESSION['message'] = 'Google OAuth is not configured. Please contact support.';
        header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
        exit;
    }

    // Build redirect URI (absolute) - prefer HTTPS for non-localhost; honor proxy headers
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($host === 'localhost' || preg_match('/^127\\.0\\.0\\.1(?::\\d+)?$/', $host)) {
        $scheme = ($xfp === 'https') ? 'https' : 'http';
    } else {
        // Force https for non-localhost to match registered OAuth redirect URIs
        $scheme = 'https';
        if ($xfp === 'http') {
            // Edge case: explicit http forwarded proto; still prefer https since Google disallows non-https
            $scheme = 'https';
        }
    }
    $redirectUri = $scheme . '://' . $host . '/index.php?m=cloudstorage&page=oauth_google_callback';

    // CSRF state
    $state = bin2hex(random_bytes(16));
    $_SESSION['cloudbackup_google_oauth_state'] = $state;
    if (function_exists('logModuleCall')) {
        logModuleCall('cloudstorage', 'oauth_google_start', [
            'session_id' => session_id(),
            'state' => $state,
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'xfp' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
        ], [], [], []);
    }

    // Build auth URL
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => $scopes,
        'access_type' => 'offline',
        'include_granted_scopes' => 'true',
        'prompt' => 'consent',
        'state' => $state,
    ];
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    header('Location: ' . $authUrl);
    exit;
} catch (\Exception $e) {
    $_SESSION['message'] = 'Failed to initiate Google OAuth. Please try again.';
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}


