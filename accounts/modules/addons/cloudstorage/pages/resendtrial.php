<?php

use WHMCS\Config\Setting;
use WHMCS\Database\Capsule;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$vars = [
    'POST' => [],
    'emailSent' => true,
    'resendMessage' => '',
    'resendStatus' => 'success',
    'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $vars['resendMessage'] = 'Please submit your email to resend the verification link.';
    $vars['resendStatus'] = 'error';
    return $vars;
}

$email = trim($_POST['email'] ?? '');
$vars['POST'] = ['email' => $email];

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $vars['resendMessage'] = 'Please enter a valid email address to resend the verification link.';
    $vars['resendStatus'] = 'error';
    return $vars;
}

try {
    $record = Capsule::table('cloudstorage_trial_verifications')
        ->where('email', $email)
        ->whereNull('consumed_at')
        ->orderBy('id', 'desc')
        ->first();
} catch (\Exception $e) {
    logModuleCall('cloudstorage', 'resend_trial_lookup_error', [], $e->getMessage(), [], []);
    $vars['resendMessage'] = 'We could not look up your verification request right now. Please try again later.';
    $vars['resendStatus'] = 'error';
    return $vars;
}

if (!$record) {
    $vars['resendMessage'] = 'We could not find an active verification for that email. Please start a new trial signup.';
    $vars['resendStatus'] = 'error';
    return $vars;
}

$token = $record->token;
$expiresAt = $record->expires_at ?? '';
$isExpired = $expiresAt !== '' && strtotime($expiresAt) < time();

if ($isExpired) {
    try {
        $token = bin2hex(random_bytes(32));
        $newExpiresAt = (new \DateTime('+24 hours'))->format('Y-m-d H:i:s');
        Capsule::table('cloudstorage_trial_verifications')->insert([
            'client_id' => $record->client_id,
            'email' => $email,
            'token' => $token,
            'meta' => $record->meta ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $newExpiresAt,
        ]);
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'resend_trial_create_token_error', [], $e->getMessage(), [], []);
        $vars['resendMessage'] = 'We could not create a new verification link right now. Please try again later.';
        $vars['resendStatus'] = 'error';
        return $vars;
    }
}

try {
    $verificationTemplateId = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'trial_verification_email_template')
        ->value('value');

    if (empty($verificationTemplateId)) {
        throw new \Exception('Trial verification email template is not configured.');
    }

    $systemUrl = Setting::getValue('SystemURL');
    if (substr($systemUrl, -1) !== '/') {
        $systemUrl .= '/';
    }

    $verificationUrl = $systemUrl . 'index.php?m=cloudstorage&page=verifytrial&token=' . urlencode($token);
    $templates = function_exists('cloudstorage_get_email_templates') ? cloudstorage_get_email_templates() : [];
    $templateName = $templates[$verificationTemplateId] ?? null;

    if (empty($templateName)) {
        throw new \Exception('Selected verification email template not found.');
    }

    $sendEmailParams = [
        'messagename' => $templateName,
        'id' => (int) $record->client_id,
        'customvars' => base64_encode(serialize([
            'trial_verification_link' => $verificationUrl,
        ])),
    ];

    $adminUser = 'API';
    $emailResult = localAPI('SendEmail', $sendEmailParams, $adminUser);
    logModuleCall('cloudstorage', 'SendEmailTrialVerificationResend', $sendEmailParams, $emailResult);

    if (!isset($emailResult['result']) || $emailResult['result'] !== 'success') {
        throw new \Exception('SendEmail failed: ' . ($emailResult['message'] ?? 'Unknown error'));
    }
} catch (\Exception $e) {
    logModuleCall('cloudstorage', 'resend_trial_email_error', [], $e->getMessage(), [], []);
    $vars['resendMessage'] = 'We could not resend the verification email right now. Please try again later.';
    $vars['resendStatus'] = 'error';
    return $vars;
}

$vars['resendMessage'] = 'Verification email resent. Please check your inbox.';
$vars['resendStatus'] = 'success';

return $vars;
