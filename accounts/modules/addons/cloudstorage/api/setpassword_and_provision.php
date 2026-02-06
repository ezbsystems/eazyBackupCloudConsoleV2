<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Provision/Provisioner.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Provision\Provisioner;

header('Content-Type: application/json');

function cloudstorage_client_has_stripe_card(int $clientId): bool
{
    if ($clientId <= 0) {
        return false;
    }

    $hasCard = false;
    try {
        if (class_exists('\\WHMCS\\Payment\\PayMethod\\PayMethod')) {
            $pmQuery = \WHMCS\Payment\PayMethod\PayMethod::where('userid', $clientId)
                ->whereNull('deleted_at')
                ->whereIn('payment_type', ['CreditCard', 'RemoteCreditCard']);
            $payMethods = $pmQuery->get();
            foreach ($payMethods as $pm) {
                $hasCard = true;
                break;
            }
        }
    } catch (\Throwable $e) {
        $hasCard = false;
    }

    if (!$hasCard) {
        try {
            if (Capsule::schema()->hasTable('tblpaymethods')) {
                $q = Capsule::table('tblpaymethods')
                    ->where('userid', $clientId)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_type', ['CreditCard', 'RemoteCreditCard']);
                $hasCard = $q->exists();
            }
        } catch (\Throwable $e) {
            $hasCard = false;
        }
    }

    if (!$hasCard) {
        try {
            $resp = localAPI('GetPayMethods', ['clientid' => $clientId]);
            if (($resp['result'] ?? '') === 'success' && !empty($resp['paymethods']) && is_array($resp['paymethods'])) {
                foreach ($resp['paymethods'] as $pm) {
                    $ptype = strtolower((string) ($pm['payment_type'] ?? ''));
                    if ($ptype === 'creditcard' || $ptype === 'remotecreditcard') {
                        $hasCard = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $hasCard = false;
        }
    }

    return $hasCard;
}

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
    // Storage tier for Cloud Storage product: 'trial_limited' (free, 1TiB cap) or 'trial_unlimited' (CC provided)
    $storageTier = strtolower(trim((string)($_POST['storage_tier'] ?? '')));

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

    // Validate and normalize storage tier for Cloud Storage
    if ($choice === 'storage') {
        if (!in_array($storageTier, ['trial_limited', 'trial_unlimited'], true)) {
            // Default to trial_limited if not specified
            $storageTier = 'trial_limited';
        }
        try {
            logModuleCall('cloudstorage', 'setpassword_storage_tier_input', [
                'client_id' => $clientId,
                'choice' => $choice,
            ], [
                'storage_tier' => $storageTier,
            ]);
        } catch (\Throwable $e) {}
    } else {
        // Storage tier only applies to Cloud Storage product
        $storageTier = '';
    }

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

    // Persist storage_tier and trial_status before provisioning for Cloud Storage
    if ($choice === 'storage' && $storageTier !== '') {
        $hasCard = true;
        if ($storageTier === 'trial_unlimited') {
            $hasCard = cloudstorage_client_has_stripe_card($clientId);
        }
        $trialStatus = ($storageTier === 'trial_unlimited' && $hasCard) ? 'paid' : 'trial';
        try {
            if (Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
                try {
                    $schema = Capsule::schema();
                    $added = [];
                    if (!$schema->hasColumn('cloudstorage_trial_selection', 'storage_tier')) {
                        $schema->table('cloudstorage_trial_selection', function ($table) {
                            $table->string('storage_tier', 32)->nullable()->after('product_choice');
                        });
                        $added[] = 'storage_tier';
                    }
                    if (!$schema->hasColumn('cloudstorage_trial_selection', 'trial_status')) {
                        $schema->table('cloudstorage_trial_selection', function ($table) {
                            $table->string('trial_status', 16)->default('trial')->after('storage_tier');
                        });
                        $added[] = 'trial_status';
                    }
                    if (!empty($added)) {
                        try {
                            logModuleCall('cloudstorage', 'setpassword_trial_selection_columns_added', [
                                'client_id' => $clientId,
                            ], [
                                'added' => $added,
                            ]);
                        } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {
                    try {
                        logModuleCall('cloudstorage', 'setpassword_trial_selection_columns_add_error', [
                            'client_id' => $clientId,
                        ], $e->getMessage());
                    } catch (\Throwable $e) {}
                }
                $now = date('Y-m-d H:i:s');
                $exists = Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->first();
                $action = $exists ? 'update' : 'insert';
                if ($exists) {
                    Capsule::table('cloudstorage_trial_selection')
                        ->where('client_id', $clientId)
                        ->update([
                            'storage_tier' => $storageTier,
                            'trial_status' => $trialStatus,
                            'updated_at'   => $now,
                        ]);
                } else {
                    Capsule::table('cloudstorage_trial_selection')->insert([
                        'client_id'      => $clientId,
                        'product_choice' => $choice,
                        'storage_tier'   => $storageTier,
                        'trial_status'   => $trialStatus,
                        'meta'           => json_encode([], JSON_UNESCAPED_SLASHES),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                }
                try {
                    logModuleCall('cloudstorage', 'setpassword_save_storage_tier', [
                        'client_id' => $clientId,
                        'action' => $action,
                    ], [
                        'storage_tier' => $storageTier,
                        'trial_status' => $trialStatus,
                        'has_card' => $hasCard ? 1 : 0,
                    ]);
                } catch (\Throwable $e) {}
            } else {
                try {
                    logModuleCall('cloudstorage', 'setpassword_storage_tier_table_missing', [
                        'client_id' => $clientId,
                    ], 'cloudstorage_trial_selection missing');
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            // Non-fatal - log for debugging
            logModuleCall('cloudstorage', 'setpassword_save_storage_tier_error', ['client_id' => $clientId], $e->getMessage(), [], []);
        }

        if ($storageTier === 'trial_unlimited' && !$hasCard) {
            try {
                logModuleCall('cloudstorage', 'setpassword_storage_requires_card', [
                    'client_id' => $clientId,
                ], [
                    'storage_tier' => $storageTier,
                    'trial_status' => $trialStatus,
                ]);
            } catch (\Throwable $e) {}
            echo json_encode([
                'status' => 'error',
                'requires_payment_method' => true,
                'message' => 'Please add a payment method to continue.',
            ]);
            exit;
        }
    }

    // 3) Provision based on selection (username is provided for backup/ms365)
    $redirectUrl = '';
    try {
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
    } catch (\Throwable $e) {
        // Map common backend messages to user-friendly, field-specific errors
        $msg = strtolower((string) $e->getMessage());
        if (strpos($msg, 'username') !== false && (strpos($msg, 'already') !== false || strpos($msg, "can\'t be used") !== false || strpos($msg, 'taken') !== false || strpos($msg, 'can’t be used') !== false)) {
            echo json_encode(['status' => 'error', 'errors' => ['username' => 'That username is already taken. Please choose another.']]);
            exit;
        }
        echo json_encode(['status' => 'error', 'errors' => ['general' => 'Provisioning failed. Please try again.']]);
        exit;
    }

    // Clear any onboarding flag so the dashboard doesn't prompt to set password again
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


