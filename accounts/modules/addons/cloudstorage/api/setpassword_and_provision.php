<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Provision/Provisioner.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Provision\Provisioner;

header('Content-Type: application/json');

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }
    // Resolve current user id and client id robustly
    $userId = 0;
    try {
        if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
            $authUser = \WHMCS\Authentication\Auth::user();
            if ($authUser && isset($authUser->id)) {
                $userId = (int) $authUser->id;
            }
        }
    } catch (\Throwable $e) {}
    // Map to client id via link table (prefer owner)
    $clientId = 0;
    try {
        if ($userId) {
            $link = Capsule::table('tblusers_clients')->where('userid', $userId)->orderBy('owner', 'desc')->first();
            if ($link && isset($link->clientid)) {
                $clientId = (int) $link->clientid;
            }
        }
    } catch (\Throwable $e) {}
    // Fallbacks
    if ($clientId <= 0) {
        try { $clientId = (int) ($ca->getUserID() ?? 0); } catch (\Throwable $e) {}
    }
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }
    if ($clientId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'method']);
        exit;
    }

    $choice = strtolower(trim((string)($_POST['product_choice'] ?? '')));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['new_password_confirm'] ?? '');
    $username = (string)($_POST['username'] ?? '');

    $valid = ['backup','cloudbackup','storage','cloudstorage','ms365','m365','cloud2cloud','cloud-to-cloud'];
    if ($choice === '' || !in_array($choice, $valid, true)) {
        echo json_encode(['status' => 'error', 'errors' => ['general' => 'Invalid product selection.']]);
        exit;
    }
    // Normalize
    if (in_array($choice, ['backup','cloudbackup'], true)) $choice = 'backup';
    if (in_array($choice, ['storage','cloudstorage'], true)) $choice = 'storage';
    if (in_array($choice, ['ms365','m365'], true)) $choice = 'ms365';
    if (in_array($choice, ['cloud2cloud','cloud-to-cloud'], true)) $choice = 'cloud2cloud';

    // Password and username validation
    $errors = [];
    if ($newPassword === '' || $confirmPassword === '') {
        $errors['new_password'] = 'Please enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['new_password_confirm'] = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters long.';
    }
    // Username required for backup/ms365
    if ($choice === 'backup' || $choice === 'ms365') {
        if ($username === '') {
            $errors['username'] = 'Please enter a username.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{6,}$/', $username)) {
            $errors['username'] = 'Backup username must be at least 6 characters and may contain only a-z, A-Z, 0-9, _, ., -';
        }
    }
    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }

    $adminUser = 'API';
    // 1) Update client password (legacy)
    $cliRes = localAPI('UpdateClient', [
        'clientid'  => $clientId,
        'password2' => $newPassword,
    ], $adminUser);
    if (($cliRes['result'] ?? '') !== 'success') {
        echo json_encode(['status' => 'error', 'errors' => ['general' => 'Unable to update account password.']] );
        exit;
    }

    // 2) Update WHMCS user password (tblusers)
    if (!$userId) {
        try {
            $ownerLink = Capsule::table('tblusers_clients')->where('clientid', $clientId)->where('owner', 1)->first();
            if ($ownerLink && isset($ownerLink->userid)) {
                $userId = (int) $ownerLink->userid;
            } else {
                $anyLink = Capsule::table('tblusers_clients')->where('clientid', $clientId)->orderBy('owner', 'desc')->first();
                if ($anyLink && isset($anyLink->userid)) {
                    $userId = (int) $anyLink->userid;
                }
            }
        } catch (\Throwable $e) {}
    }

    $userUpdated = false;
    if ($userId) {
        try {
            $u1 = localAPI('UpdateUser', ['user_id' => $userId, 'password' => $newPassword], $adminUser);
            if (($u1['result'] ?? '') === 'success') {
                $userUpdated = true;
            } else {
                $u2 = localAPI('UpdateUser', ['user_id' => $userId, 'password2' => $newPassword], $adminUser);
                if (($u2['result'] ?? '') === 'success') {
                    $userUpdated = true;
                }
            }
        } catch (\Throwable $e) {}
        if (!$userUpdated) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($hash && strlen($hash) >= 30) {
                    Capsule::table('tblusers')->where('id', $userId)->update(['password' => $hash]);
                    $userUpdated = true;
                }
            } catch (\Throwable $e) {}
        }
    }
    // Final fallback: resolve by email and write hash directly to tblusers
    if (!$userUpdated) {
        try {
            $email = null;
            // Prefer authenticated user email
            if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
                $authUser = \WHMCS\Authentication\Auth::user();
                if ($authUser && !empty($authUser->email)) {
                    $email = (string) $authUser->email;
                }
            }
            // Fallback to owner user email
            if (empty($email)) {
                try {
                    $ownerLink = Capsule::table('tblusers_clients')->where('clientid', $clientId)->where('owner', 1)->first();
                    if ($ownerLink && isset($ownerLink->userid)) {
                        $ownerUser = Capsule::table('tblusers')->where('id', (int)$ownerLink->userid)->first();
                        if ($ownerUser && !empty($ownerUser->email)) {
                            $email = (string) $ownerUser->email;
                        }
                    }
                } catch (\Throwable $__) { /* ignore */ }
            }
            // Fallback to client email
            if (empty($email)) {
                try {
                    $email = (string) (Capsule::table('tblclients')->where('id', $clientId)->value('email') ?? '');
                } catch (\Throwable $__) { $email = ''; }
            }
            if (!empty($email)) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($hash && strlen($hash) >= 30) {
                    $updated = Capsule::table('tblusers')->where('email', $email)->update(['password' => $hash]);
                    if ($updated > 0) {
                        $userUpdated = true;
                    }
                }
            }
        } catch (\Throwable $__) { /* ignore */ }
    }
    if (!$userUpdated) {
        echo json_encode(['status' => 'error', 'errors' => ['general' => 'Unable to update login password.']] );
        exit;
    }

    // 3) Provision based on selection (username is provided for backup/ms365)

    $redirectUrl = '';
    switch ($choice) {
        case 'backup':
            $redirectUrl = Provisioner::provisionCloudBackup($clientId, $username, $newPassword);
            break;
        case 'ms365':
            $redirectUrl = Provisioner::provisionMs365($clientId, $username, $newPassword);
            break;
        case 'storage':
            $redirectUrl = Provisioner::provisionCloudStorage($clientId);
            break;
        case 'cloud2cloud':
            $redirectUrl = Provisioner::provisionCloudToCloud($clientId);
            break;
        default:
            echo json_encode(['status' => 'error', 'errors' => ['general' => 'Unknown product selection.']]);
            exit;
    }

    // If updating the password invalidated the current session, ensure continuity via SSO
    try {
        // Build WHMCS-expected SSO params using custom redirect path
        // Convert any absolute URL into a relative WHMCS path for sso_redirect_path
        $destPath = $redirectUrl;
        if (preg_match('~^https?://~i', $destPath)) {
            $u = parse_url($destPath);
            $path = isset($u['path']) ? $u['path'] : '/';
            $query = isset($u['query']) ? ('?' . $u['query']) : '';
            $destPath = ltrim($path . $query, '/');
        } else {
            $destPath = ltrim($destPath, '/');
        }

        $ssoParams = [
            'destination'       => 'sso:custom_redirect',
            'sso_redirect_path' => $destPath,
        ];
        if (!empty($userId)) {
            $ssoParams['user_id'] = $userId;
        } else {
            $ssoParams['client_id'] = $clientId;
        }
        $sso = localAPI('CreateSsoToken', $ssoParams, $adminUser);
        if (($sso['result'] ?? '') === 'success' && !empty($sso['redirect_url'])) {
            $redirectUrl = $sso['redirect_url'];
        }
    } catch (\Throwable $e) {}

    echo json_encode(['status' => 'success', 'redirectUrl' => $redirectUrl]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'server']);
}


