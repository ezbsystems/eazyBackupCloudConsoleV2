<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// Ensure client is logged in
$clientId  = (int) ($_SESSION['uid'] ?? 0);
$contactId = (int) ($_SESSION['cid'] ?? 0);

if ($clientId <= 0) {
    header('Location: clientarea.php');
    exit;
}

// Sanitize return_to – only allow internal URLs
$rawReturnTo = isset($_GET['return_to']) ? (string) $_GET['return_to'] : '';
$returnTo = 'index.php?m=eazybackup&a=dashboard';
if ($rawReturnTo !== '' && strpos($rawReturnTo, 'http') !== 0 && strpos($rawReturnTo, '//') !== 0) {
    $returnTo = $rawReturnTo;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword        = (string) ($_POST['new_password'] ?? '');
    $confirmPassword    = (string) ($_POST['new_password_confirm'] ?? '');

    // Basic strength and matching validation
    if ($newPassword === '' || $confirmPassword === '') {
        $errors['new_password'] = 'Please enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['new_password_confirm'] = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 10) {
        $errors['new_password'] = 'Password must be at least 10 characters long.';
    }

    // You can add more advanced checks here (complexity, blacklist, etc).

    if (empty($errors)) {
        try {
            $adminUser = 'API';
            $params = [
                'clientid'  => $clientId,
                'password2' => $newPassword,
            ];
            $result = localAPI('UpdateClient', $params, $adminUser);

            if (!isset($result['result']) || $result['result'] !== 'success') {
                $errors['general'] = 'We could not update your password. Please try again.';
            } else {
                // Clear the onboarding flag
                if (function_exists('eazybackup_clear_must_set_password')) {
                    eazybackup_clear_must_set_password($clientId);
                } else {
                    // Fallback direct update if helper is not available
                    try {
                        Capsule::table('eb_password_onboarding')
                            ->where('client_id', $clientId)
                            ->update([
                                'must_set'     => 0,
                                'completed_at' => date('Y-m-d H:i:s'),
                            ]);
                    } catch (\Throwable $_) {
                        // ignore
                    }
                }

                // Redirect to original destination
                header('Location: ' . $returnTo);
                exit;
            }
        } catch (\Throwable $e) {
            $errors['general'] = 'Unexpected server error while updating your password.';
        }
    }
}

return [
    'errors'    => $errors,
    'success'   => $success,
    'return_to' => $returnTo,
];


