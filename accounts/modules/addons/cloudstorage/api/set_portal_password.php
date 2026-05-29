<?php
/**
 * set_portal_password.php
 *
 * Round-2 onboarding endpoint. Collects the client-area password BEFORE the
 * customer reaches the product picker on the Welcome page, then caches the
 * plaintext in a short-lived session slot so the subsequent
 * setpassword_and_provision.php call can forward it to Comet's `servicepassword`
 * field on AcceptOrder.
 *
 * The plaintext is intentionally session-scoped (not persisted) and is unset
 * by setpassword_and_provision.php immediately after a successful provision.
 * It is never logged.
 */

require_once __DIR__ . '/../../../../init.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'method']);
        exit;
    }

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Resolve client + user ids robustly
    $userId = 0;
    try {
        if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
            $authUser = \WHMCS\Authentication\Auth::user();
            if ($authUser && isset($authUser->id)) {
                $userId = (int) $authUser->id;
            }
        }
    } catch (\Throwable $e) {}

    $clientId = 0;
    try {
        if ($userId) {
            $link = Capsule::table('tblusers_clients')
                ->where('userid', $userId)
                ->orderBy('owner', 'desc')
                ->first();
            if ($link && isset($link->clientid)) {
                $clientId = (int) $link->clientid;
            }
        }
    } catch (\Throwable $e) {}
    if ($clientId <= 0) {
        try { $clientId = (int) ($ca->getUserID() ?? 0); } catch (\Throwable $e) {}
    }
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }
    if ($clientId <= 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    $newPassword     = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['new_password_confirm'] ?? '');

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

    $adminUser = 'API';

    // 1) Update WHMCS client record
    $cliRes = localAPI('UpdateClient', [
        'clientid'  => $clientId,
        'password2' => $newPassword,
    ], $adminUser);
    if (($cliRes['result'] ?? '') !== 'success') {
        echo json_encode(['status' => 'error', 'errors' => ['general' => 'We could not update your password. Please try again.']]);
        exit;
    }

    // 2) Update WHMCS user (tblusers) — required for login under v8+
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
    if (!$userUpdated) {
        try {
            $email = null;
            if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
                $authUser = \WHMCS\Authentication\Auth::user();
                if ($authUser && !empty($authUser->email)) {
                    $email = (string) $authUser->email;
                }
            }
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
        echo json_encode(['status' => 'error', 'errors' => ['general' => 'We could not update your login password. Please contact support.']]);
        exit;
    }

    // 3) Clear must-set flag
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
    } catch (\Throwable $e) { /* non-fatal */ }

    // 4) Cache plaintext for the upcoming provision call. Session-scoped,
    // never persisted, cleared after the first successful AcceptOrder.
    $_SESSION['eb_portal_password_for_provision'] = $newPassword;

    echo json_encode(['status' => 'success']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'server']);
}
