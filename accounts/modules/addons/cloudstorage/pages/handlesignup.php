<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Config\Setting;


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function validateTurnstile($cfToken, $secretKey)
{
    if (!$secretKey) {
        return false;
    }
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";

    // Prepare POST data
    $data = [
        'secret' => $secretKey,
        'response' => $cfToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] 
    ];

    // Using cURL for a POST request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $result = curl_exec($ch);
    if ($result === false) {
        error_log("cURL error: " . curl_error($ch));
        // Soft-fail on network errors so Cloudflare outages don't block signups
        logModuleCall(
            'eazybackup',
            'TurnstileSiteVerifySoftFailCurl',
            ['secret' => $secretKey, 'response' => $cfToken],
            ['error' => curl_error($ch)]
        );
        curl_close($ch);
        return true;
    }
    curl_close($ch);

    // Decode the JSON response from Cloudflare
    $responseData = json_decode($result, true);
    if (!is_array($responseData) || !array_key_exists('success', $responseData)) {
        // Soft-fail on malformed / unexpected responses
        logModuleCall(
            'eazybackup',
            'TurnstileSiteVerifySoftFailResponse',
            ['secret' => $secretKey, 'response' => $cfToken],
            $result
        );
        return true;
    }
    logModuleCall(
        'eazybackup',
        'TurnstileSiteVerify',
        ['secret' => $secretKey, 'response' => $cfToken],
        $responseData
    );


    return isset($responseData['success']) && $responseData['success'] === true;
}


$debugInfo = [];

$vars = [
    'POST'      => [],
    'message'   => '',
    'debugInfo' => '',
    'TURNSTILE_SITE_KEY'    => $turnstileSiteKey ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Gather basic fields
    $company    = trim($_POST['company'] ?? '');
    $fullName   = trim($_POST['fullName'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $useCase    = trim($_POST['useCase'] ?? '');
    $storageTiB = trim($_POST['storageTiB'] ?? '');
    $project    = trim($_POST['project'] ?? '');

    // Normalize storage estimate: default to 5 TiB if empty or invalid
    if ($storageTiB === '' || !is_numeric($storageTiB) || (int) $storageTiB < 1) {
        $storageTiB = 5;
    }

    // Preserve POST data for sticky form fields
    $_SESSION['POST'] = [
        'company'      => $company,
        'fullName'     => $fullName,
        'email'        => $email,
        'phone'        => $phone,
        'useCase'      => $useCase ?: 'msp',
        'storageTiB'   => $storageTiB,
        'project'      => $project,
    ];
    // 0) Honeypot check
    if (!empty($_POST['hp_field'])) {
        // treat as spam—stop processing
        // optionally log or silently redirect
        header("HTTP/1.0 400 Bad Request");
        exit;
    }

    // 1) Turnstile validation
    $cfToken = $_POST['cf-turnstile-response'] ?? '';
    if (!validateTurnstile($cfToken, $turnstileSecretKey ?? '')) {
        $errors['turnstile'] = 'Captcha validation failed. Please try again.';
    }

    // 2) Basic required field validation (Company optional)
    if ($fullName === '') {
        $errors['fullName'] = 'Full name is required.';
    }
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    }
    if ($phone === '') {
        $errors['phone'] = 'Phone is required.';
    }
    // Project optional

    // 3) Email format and phone sanity checks
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email address.";
    }

    // Phone: light validation, allow digits, spaces, +, -, ()
    if ($phone && !preg_match('/^[0-9+\-\s()]{6,}$/', $phone)) {
        $errors['phone'] = "Please enter a valid phone number.";
    }

    // If any early errors, return immediately
    if (!empty($errors)) {
        $errorKeys = implode(', ', array_keys($errors));
        $debugInfo['validationErrors'] = $errors;
        return [
            'errors'    => $errors,
            'POST'      => $_SESSION['POST'],
            'message'   => "Please correct the indicated fields: " . $errorKeys,
            'debugInfo' => print_r($debugInfo, true),
            'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
        ];
    }

    // Split full name into first and last name
    $firstName = '';
    $lastName  = '';
    if ($fullName) {
        $parts = preg_split('/\s+/', $fullName, 2);
        $firstName = $parts[0];
        $lastName  = $parts[1] ?? $parts[0];
    }

    // Normalize use case
    if (!in_array($useCase, ['msp', 'saas', 'internal'], true)) {
        $useCase = 'msp';
    }

    $debugInfo['postData'] = $_POST;

    // --------------------------------------------------------------------------------------------
    // 3) Load module configuration + prepare Ceph and verification settings
    // --------------------------------------------------------------------------------------------
    try {
        $module = DBController::getResult('tbladdonmodules', [
            ['module', '=', 'cloudstorage']
        ]);
        if (count($module) == 0) {
            $errors['module'] = "Cloudstorage module configuration error. Please contact support.";
            return [
                'errors'    => $errors,
                'POST'      => $_SESSION['POST'],
                'message'   => "Configuration error",
                'debugInfo' => print_r($debugInfo, true),
                'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
            ];
        }
        $s3Endpoint         = $module->where('setting', 's3_endpoint')->pluck('value')->first();
        $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
        $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
        $encryptionKey      = $module->where('setting', 'encryption_key')->pluck('value')->first();
        $verificationTemplateId = $module->where('setting', 'trial_verification_email_template')->pluck('value')->first();

        if (empty($verificationTemplateId)) {
            $errors['module'] = "Trial verification email template is not configured. Please contact support.";
            return [
                'errors'    => $errors,
                'POST'      => $_SESSION['POST'],
                'message'   => "Configuration error",
                'debugInfo' => print_r($debugInfo, true),
                'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
            ];
        }
    } catch (\Exception $e) {
        $_SESSION['message'] = "Error checking Ceph username: " . $e->getMessage();
        $vars = [
            'POST'      => $_SESSION['POST'],
            'message'   => $_SESSION['message'],
            'debugInfo' => print_r($debugInfo, true),
        ];
        return $vars;
    }

    // --------------------------------------------------------------------------------------------
    // 4) Create a unique storage username based on company/email
    $baseUsername = preg_replace('/[^A-Za-z0-9]/', '', strtolower(substr($company ?: $email, 0, 16)));
    if ($baseUsername === '') {
        $baseUsername = 'e3user';
    }
    $username = $baseUsername;

    try {
        // Ensure username is unique in Ceph by checking AdminOps
        $suffix = 0;
        do {
            $checkUsername = $username . ($suffix ? $suffix : '');
            $result = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $checkUsername);
            if (!isset($result['status']) || $result['status'] !== 'success') {
                $username = $checkUsername;
                break;
            }
            $suffix++;
        } while ($suffix < 50);
    } catch (\Exception $e) {
        // If the check fails for any reason, fall back to a random username
        $username = $baseUsername . bin2hex(random_bytes(2));
    }

    // 5) Create the client via localAPI
    // --------------------------------------------------------------------------------------------
    $adminUser = 'API';

    // Build admin notes from trial fields
    $notesLines = [
        'e3 Trial Signup Details:',
        'Company: ' . $company,
        'Full Name: ' . $fullName,
        'Phone: ' . $phone,
        'Use Case: ' . $useCase,
        'Estimated Storage (TiB): ' . $storageTiB,
        'How they will use e3: ' . $project,
    ];
    $clientNotes = implode("\n", $notesLines);

    // Auto-generate a strong random password for the WHMCS client
    $password = bin2hex(random_bytes(8));

    // Default country to System default country if available, otherwise CA
    $country = Setting::getValue('DefaultCountry') ?: 'CA';

    $addClientData = [
        'firstname'   => $firstName,
        'lastname'    => $lastName,
        'email'       => $email,
        'companyname' => $company,
        'address1'    => '',
        'city'        => '',
        'state'       => '',
        'postcode'    => '',
        'country'     => $country,
        'phonenumber' => $phone,
        'password2'   => $password,
        'notes'       => $clientNotes,
    ];

    $addClientResult = localAPI('AddClient', $addClientData, $adminUser);

    // Always log the API call for post-mortem
    logModuleCall(
        'cloudstorage',          
        'AddClient',            
        $addClientData,         
        $addClientResult        
    );

    if ($addClientResult['result'] !== 'success') {
        // Generic, non-leaking error for the user
        $_SESSION['message'] = "We couldn’t create your account right now. Please try again later.";
        
        $vars = [
            'POST'      => $_SESSION['POST'],
            'message'   => $_SESSION['message'],
            // If you really need to expose debug info to a logged-in admin, you could,
            // but usually you hide this in production:
            'debugInfo' => print_r($addClientResult, true),
            'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
        ];
        return $vars;
    }

    $clientId  = $addClientResult['clientid'];
    $packageId = ProductConfig::$E3_PRODUCT_ID;

    // Mark this client as requiring password setup on first login (handled by eazyBackup addon)
    try {
        $ebClientId = (int) $clientId;
        $row = Capsule::table('eb_password_onboarding')
            ->where('client_id', $ebClientId)
            ->first();

        $data = [
            'must_set'     => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'completed_at' => null,
        ];

        if ($row) {
            Capsule::table('eb_password_onboarding')
                ->where('client_id', $ebClientId)
                ->update($data);
        } else {
            $data['client_id'] = $ebClientId;
            Capsule::table('eb_password_onboarding')
                ->insert($data);
        }
    } catch (\Throwable $e) {
        // Non-fatal; password onboarding is a UX enhancement.
    }

    // --------------------------------------------------------------------------------------------
    // 6) Create verification token record
    // --------------------------------------------------------------------------------------------
    try {
        $token = bin2hex(random_bytes(32));

        $meta = json_encode([
            'username'      => $username,
            'company'       => $company,
            'fullName'      => $fullName,
            'phone'         => $phone,
            'useCase'       => $useCase,
            'storageTiB'    => $storageTiB,
            'project'       => $project,
        ]);

        $expiresAt = (new \DateTime('+48 hours'))->format('Y-m-d H:i:s');

        Capsule::table('cloudstorage_trial_verifications')->insert([
            'client_id'  => $clientId,
            'email'      => $email,
            'token'      => $token,
            'meta'       => $meta,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
        ]);
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'create_trial_verification', [], $e->getMessage(), [], []);
        $_SESSION['message'] = "We couldn’t start your trial verification right now. Please try again later.";
        return [
            'POST'      => $_SESSION['POST'],
            'message'   => $_SESSION['message'],
            'debugInfo' => print_r($debugInfo, true),
            'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
        ];
    }

    // --------------------------------------------------------------------------------------------
    // 7) Send verification email using selected template
    // --------------------------------------------------------------------------------------------
    try {
        $systemUrl = Setting::getValue('SystemURL');
        if (substr($systemUrl, -1) !== '/') {
            $systemUrl .= '/';
        }

        $verificationUrl = $systemUrl . 'index.php?m=cloudstorage&page=verifytrial&token=' . urlencode($token);

        // Map template ID -> name via helper
        $templates = function_exists('cloudstorage_get_email_templates') ? cloudstorage_get_email_templates() : [];
        $templateName = $templates[$verificationTemplateId] ?? null;

        if (empty($templateName)) {
            throw new \Exception('Selected verification email template not found.');
        }

        $sendEmailParams = [
            'messagename' => $templateName,
            'id'          => (int) $clientId,
            'customvars'  => base64_encode(serialize([
                'trial_verification_link' => $verificationUrl,
            ])),
        ];

        $emailResult = localAPI('SendEmail', $sendEmailParams, $adminUser);
        logModuleCall(
            'cloudstorage',
            'SendEmailTrialVerification',
            $sendEmailParams,
            $emailResult
        );

        if (!isset($emailResult['result']) || $emailResult['result'] !== 'success') {
            throw new \Exception('SendEmail failed: ' . ($emailResult['message'] ?? 'Unknown error'));
        }
    } catch (\Exception $e) {
        logModuleCall('cloudstorage', 'send_trial_verification_email', [], $e->getMessage(), [], []);
        $_SESSION['message'] = "We couldn’t send the verification email. Please try again later.";
        return [
            'POST'      => $_SESSION['POST'],
            'message'   => $_SESSION['message'],
            'debugInfo' => print_r($debugInfo, true),
            'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
        ];
    }

    // At this point, client exists and verification email is sent.
    // We do NOT create the order or Ceph user yet; that happens after verification.
    unset($_SESSION['POST']);

    return [
        'POST'                 => ['email' => $email],
        'emailSent'            => true,
        'TURNSTILE_SITE_KEY'   => $turnstileSiteKey ?? '',
        'message'              => 'Please check your email. We just sent you a verification link to activate your e3 Cloud Storage trial.',
    ];
}

// If no POST data, just return the $vars structure
return $vars;
