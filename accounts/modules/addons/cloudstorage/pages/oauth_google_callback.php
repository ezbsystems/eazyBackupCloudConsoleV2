<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $_SESSION['message'] = 'Please log in to complete Google connection.';
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

$clientIdWhmcs = (int) $ca->getUserID();

try {
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    $expectedState = $_SESSION['cloudbackup_google_oauth_state'] ?? '';
    // DEBUG: log entry and session context
    if (function_exists('logModuleCall')) {
        logModuleCall('cloudstorage', 'oauth_google_callback_entry', [
            'code' => substr($code, 0, 16) . (strlen($code) > 16 ? '...' : ''),
            'state' => $state,
            'expectedState' => $expectedState,
            'session_id' => session_id(),
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'xfp' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
            'https' => $_SERVER['HTTPS'] ?? '',
        ], [], [], []);
    }
    unset($_SESSION['cloudbackup_google_oauth_state']);

    if (!$code || !$state || !$expectedState || !hash_equals($expectedState, $state)) {
        if (function_exists('logModuleCall')) {
            logModuleCall('cloudstorage', 'oauth_google_callback_invalid_state', [
                'code_present' => (bool)$code,
                'state' => $state,
                'expectedState' => $expectedState,
                'session_id' => session_id(),
            ], [], [], []);
        }
        $_SESSION['message'] = 'Invalid OAuth state. Please try again.';
        header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
        exit;
    }

    // Load addon settings
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->pluck('value', 'setting');

    $clientId = $settings['cloudbackup_google_client_id'] ?? '';
    $clientSecret = $settings['cloudbackup_google_client_secret'] ?? '';
    $scopes = trim($settings['cloudbackup_google_scopes'] ?? 'https://www.googleapis.com/auth/drive.readonly');
    $encryptionKey = $settings['cloudbackup_encryption_key'] ?? ($settings['encryption_key'] ?? '');

    if (!$clientId || !$clientSecret || !$encryptionKey) {
        $_SESSION['message'] = 'Google OAuth or encryption not configured.';
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
            $scheme = 'https';
        }
    }
    $redirectUri = $scheme . '://' . $host . '/index.php?m=cloudstorage&page=oauth_google_callback';

    // Exchange code for tokens
    $postFields = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ], '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $tokenResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // TEMP DEBUG: log token exchange result (enable Module Log in WHMCS to see)
    if (function_exists('logModuleCall')) {
        $dbgReq = [
            'action' => 'google_token_exchange',
            'redirect_uri' => $redirectUri,
        ];
        $dbgResp = [
            'http_code' => $httpCode,
            'curl_error' => $curlErr,
            'body' => $tokenResp,
        ];
        logModuleCall('cloudstorage', 'oauth_google_callback_token', $dbgReq, $dbgResp, [], []);
    }

    if ($httpCode < 200 || $httpCode >= 300 || !$tokenResp) {
        $_SESSION['message'] = 'Failed to obtain tokens from Google.';
        header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
        exit;
    }

    $tokenJson = json_decode($tokenResp, true);
    if (!is_array($tokenJson)) {
        $_SESSION['message'] = 'Unexpected token response.';
        header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
        exit;
    }

    $accessToken = $tokenJson['access_token'] ?? '';
    $refreshToken = $tokenJson['refresh_token'] ?? '';
    $expiresIn = (int) ($tokenJson['expires_in'] ?? 0);

    if (!$accessToken) {
        $_SESSION['message'] = 'No access token returned by Google.';
        header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
        exit;
    }

    // Fetch user info to identify the Google account
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $userResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // TEMP DEBUG: log userinfo fetch result
    if (function_exists('logModuleCall')) {
        $dbgReq = [
            'action' => 'google_userinfo',
        ];
        $dbgResp = [
            'http_code' => $httpCode,
            'body' => $userResp,
        ];
        logModuleCall('cloudstorage', 'oauth_google_callback_userinfo', $dbgReq, $dbgResp, [], []);
    }

    $email = null;
    $displayName = 'Google Drive';
    $googleId = null;

    if ($httpCode >= 200 && $httpCode < 300 && $userResp) {
        $userJson = json_decode($userResp, true);
        if (is_array($userJson)) {
            $email = $userJson['email'] ?? null;
            $displayName = $userJson['name'] ?? ($email ? ('Google Drive (' . $email . ')') : 'Google Drive');
            $googleId = $userJson['id'] ?? null;
        }
    }

    // Prepare meta
    $meta = [
        'google_id' => $googleId,
        'scopes' => $scopes,
        'received_at' => date('c'),
    ];

    // Encrypt refresh token (if provided)
    $encRefresh = $refreshToken ? HelperController::encryptKey($refreshToken, $encryptionKey) : null;

    // Upsert source connection
    $existing = Capsule::table('s3_cloudbackup_sources')
        ->where('client_id', $clientIdWhmcs)
        ->where('provider', 'google_drive')
        ->when($email, function ($q) use ($email) {
            return $q->where('account_email', $email);
        }, function ($q) use ($googleId) {
            if ($googleId) {
                return $q->where('meta', 'LIKE', '%"google_id":"' . $googleId . '"%');
            }
            return $q;
        })
        ->first();

    if ($existing) {
        $update = [
            'display_name' => $displayName,
            'account_email' => $email,
            'scopes' => $scopes,
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($encRefresh) {
            $update['refresh_token_enc'] = $encRefresh;
        }
        // Merge meta (preserve existing keys)
        $existingMeta = [];
        if (!empty($existing->meta)) {
            $tmp = json_decode($existing->meta, true);
            if (is_array($tmp)) {
                $existingMeta = $tmp;
            }
        }
        $update['meta'] = json_encode(array_merge($existingMeta, $meta));

        Capsule::table('s3_cloudbackup_sources')
            ->where('id', $existing->id)
            ->update($update);
    } else {
        if (!$encRefresh) {
            $_SESSION['message'] = 'Google did not return a refresh token. Please remove the app from your Google account permissions and try again.';
            header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
            exit;
        }
        Capsule::table('s3_cloudbackup_sources')->insert([
            'client_id' => $clientIdWhmcs,
            'provider' => 'google_drive',
            'display_name' => $displayName,
            'account_email' => $email,
            'scopes' => $scopes,
            'refresh_token_enc' => $encRefresh,
            'status' => 'active',
            'meta' => json_encode($meta),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    $_SESSION['message'] = 'Google Drive connected successfully.';
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs&open_create=1&prefill_source=google_drive');
    exit;
} catch (\Exception $e) {
    $_SESSION['message'] = 'Google OAuth failed. Please try again.';
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}


