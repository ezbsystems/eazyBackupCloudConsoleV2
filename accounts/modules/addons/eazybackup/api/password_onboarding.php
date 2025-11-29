<?php

use WHMCS\Database\Capsule;

// Bootstrap WHMCS
$root = dirname(__DIR__, 4); // accounts/modules/addons/eazybackup/api -> up 4 to /
require_once $root . '/init.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId <= 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }

    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['new_password_confirm'] ?? '');

    $errors = [];
    if ($newPassword === '' || $confirmPassword === '') {
        $errors['new_password'] = 'Please enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['new_password_confirm'] = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 10) {
        $errors['new_password'] = 'Password must be at least 10 characters long.';
    }

    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'errors' => $errors]);
        exit;
    }

    // Update client password
    $adminUser = 'API';
    $result = localAPI('UpdateClient', [
        'clientid'  => $clientId,
        'password2' => $newPassword,
    ], $adminUser);

    if (!isset($result['result']) || $result['result'] !== 'success') {
        echo json_encode([
            'status' => 'error',
            'errors' => ['general' => 'We could not update your password. Please try again.']
        ]);
        exit;
    }

    // Also update the WHMCS v8+ User password (login uses tblusers)
    $userPasswordUpdated = false;
    try {
        $userId = null;
        // Prefer authenticated user (if available via session)
        if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
            $authUser = \WHMCS\Authentication\Auth::user();
            if ($authUser && isset($authUser->id)) {
                $userId = (int) $authUser->id;
            }
        }
        // Fallback to owner link for this client
        if (!$userId) {
            $ownerLink = Capsule::table('tblusers_clients')
                ->where('clientid', $clientId)
                ->where('owner', 1)
                ->first();
            if ($ownerLink && isset($ownerLink->userid)) {
                $userId = (int) $ownerLink->userid;
            }
        }
        // Fallback to any linked user if owner not found
        if (!$userId) {
            $anyLink = Capsule::table('tblusers_clients')
                ->where('clientid', $clientId)
                ->orderBy('owner', 'desc')
                ->first();
            if ($anyLink && isset($anyLink->userid)) {
                $userId = (int) $anyLink->userid;
            }
        }
        if ($userId) {
            // Try official Admin API first
            try {
                $u1 = localAPI('UpdateUser', ['user_id' => $userId, 'password' => $newPassword], $adminUser);
                if (($u1['result'] ?? '') === 'success') {
                    $userPasswordUpdated = true;
                } else {
                    // Some installations may expect 'password2'
                    $u2 = localAPI('UpdateUser', ['user_id' => $userId, 'password2' => $newPassword], $adminUser);
                    if (($u2['result'] ?? '') === 'success') {
                        $userPasswordUpdated = true;
                    }
                }
            } catch (\Throwable $__) {
                // Fall back to direct hash write below
            }
            if (!$userPasswordUpdated) {
                // Last resort: write hashed password directly to tblusers
                try {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    if ($hash && strlen($hash) >= 30) {
                        Capsule::table('tblusers')->where('id', $userId)->update(['password' => $hash]);
                        $userPasswordUpdated = true;
                    }
                } catch (\Throwable $__) { /* ignore */ }
            }
        }
    } catch (\Throwable $__) { /* ignore */ }
    if (!$userPasswordUpdated) {
        // Final fallback: resolve by email and update tblusers
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
                    $ownerLink = Capsule::table('tblusers_clients')
                        ->where('clientid', $clientId)
                        ->where('owner', 1)
                        ->first();
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
                        $userPasswordUpdated = true;
                    }
                }
            }
        } catch (\Throwable $__) { /* ignore */ }
    }
    if (!$userPasswordUpdated) {
        echo json_encode([
            'status' => 'error',
            'errors' => ['general' => 'We could not update your login password. Please contact support.']
        ]);
        exit;
    }

    // Clear onboarding flag
    try {
        if (function_exists('eazybackup_clear_must_set_password')) {
            eazybackup_clear_must_set_password($clientId);
        } else {
            Capsule::table('eb_password_onboarding')
                ->where('client_id', $clientId)
                ->update([
                    'must_set'     => 0,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
        }
    } catch (\Throwable $e) {
        // Non-fatal
    }

    echo json_encode(['status' => 'success']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unexpected server error']);
}


