<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;


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
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    // Decode the JSON response from Cloudflare
    $responseData = json_decode($result, true);
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
    'old'       => [],
    'message'   => '',
    'debugInfo' => '',
    'TURNSTILE_SITE_KEY'    => $turnstileSiteKey ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    // 0) Honeypot check
    if (!empty($_POST['hp_field'])) {
        // treat as spam—stop processing
        // optionally log or silently redirect
        header("HTTP/1.0 400 Bad Request");
        exit;
    }

    // 1) Strict name validation
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    if (!preg_match('/^[A-Za-z]+$/', $firstName)) {
        $errors['first_name'] = "First Name may only contain letters.";
    }
    if (!preg_match('/^[A-Za-z]+$/', $lastName)) {
        $errors['last_name']  = "Last Name may only contain letters.";
    }

    // Turnstile validation
    $cfToken = $_POST['cf-turnstile-response'] ?? '';
    if (!validateTurnstile($cfToken, $turnstileSecretKey ?? '')) {
        $errors['turnstile'] = 'Captcha validation failed. Please try again.';
    }

    // 2) Bail early on errors
     if (!empty($errors)) {
        $_SESSION['old'] = [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            // ... you could prefill the rest here too ...
        ];
        return [
            'errors'    => $errors,
            'POST'      => $_SESSION['old'],
            'message'   => "Please correct the indicated fields.",
            'debugInfo' => print_r($debugInfo, true),
            'TURNSTILE_SITE_KEY' => $turnstileSiteKey ?? '',
        ];
    }

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email address.";
    }
    $e164 = trim($_POST['phone_e164'] ?? '');
    if (! preg_match('/^\+[1-9]\d{6,14}$/', $e164) ) {
        $errors['phone'] = "Invalid international number.";
    }
    $username       = trim($_POST['username'] ?? '');
    if (! preg_match('/^[A-Za-z0-9]+$/', $username) ) {
        $errors['username'] = "Username may only contain letters and numbers.";
    }
    $password       = $_POST['password'] ?? '';
    $passwordVerify = $_POST['password_verify'] ?? '';
    $country        = trim($_POST['country'] ?? '');

    // Store old input for sticky form fields
    $_SESSION['old'] = [
        'email'      => $email,
        'username'   => $username,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'country'    => $country,
    ];
    $debugInfo['postData'] = $_POST;

    // 2) Validate input fields
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email address.";
    }
    if (empty($username)) {
        $errors['username'] = "Username is required.";
    }
    if (empty($firstName)) {
        $errors['first_name'] = "First name is required.";
    }
    if (empty($lastName)) {
        $errors['last_name'] = "Last name is required.";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }
    if ($password !== $passwordVerify) {
        $errors['password_verify'] = "Passwords do not match.";
    }

    // Return errors if any were found
    if (!empty($errors)) {
        $vars = [
            'errors'    => $errors,
            'POST'      => $_SESSION['old'],
            'message'   => "There were errors with your form submission.",
            'debugInfo' => print_r($debugInfo, true),
        ];
        return $vars;
    }

    // --------------------------------------------------------------------------------------------
    // 3) Check if the username already exists in the API
    // --------------------------------------------------------------------------------------------
    try {
        $module = DBController::getResult('tbladdonmodules', [
            ['module', '=', 'cloudstorage']
        ]);
        if (count($module) == 0) {
            $errors['module'] = "Cloudstorage module configuration error. Please contact support.";
            $vars = [
                'errors'    => $errors,
                'POST'      => $_SESSION['old'],
                'message'   => "Configuration error",
                'debugInfo' => print_r($debugInfo, true),
            ];
            return $vars;
        }
        $s3Endpoint         = $module->where('setting', 's3_endpoint')->pluck('value')->first();
        $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
        $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
        $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

        $result = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $username);
        if ($result['status'] == 'success') {
            $errors['username'] = "Please choose a different username.";
            $vars = [
                'errors'    => $errors,
                'POST'      => $_SESSION['old'],
                'message'   => "Please choose a different username.",
                'debugInfo' => print_r($debugInfo, true),
            ];
            return $vars;
        }
    } catch (\Exception $e) {
        $_SESSION['message'] = "Error checking Ceph username: " . $e->getMessage();
        $vars = [
            'old'       => $_SESSION['old'],
            'message'   => $_SESSION['message'],
            'debugInfo' => print_r($debugInfo, true),
        ];
        return $vars;
    }

    // --------------------------------------------------------------------------------------------
    // 4) Create the client via localAPI
    // --------------------------------------------------------------------------------------------
    $adminUser = 'API';
    $addClientData = [
        'firstname'   => $firstName,
        'lastname'    => $lastName,
        'email'       => $email,
        'address1'    => '',
        'city'        => '',
        'state'       => '',
        'postcode'    => '',
        'country'     => $country,
        'phonenumber' => $phonenumber,
        'password2'   => $password,
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
            'old'       => $_SESSION['old'],
            'message'   => $_SESSION['message'],
            // If you really need to expose debug info to a logged-in admin, you could,
            // but usually you hide this in production:
            'debugInfo' => print_r($addClientResult, true),
        ];
        return $vars;
    }

    $clientId  = $addClientResult['clientid'];
    $packageId = ProductConfig::$E3_PRODUCT_ID;


    // --------------------------------------------------------------------------------------------
    // 5) Create an order for the e3 Cloud Storage product (PID = 48) using AddOrder
    // --------------------------------------------------------------------------------------------
    $addOrderData = [
        'clientid'      => $clientId,
        'paymentmethod' => 'stripe', // Must be a valid payment method in WHMCS
        'pid'           => [$packageId],
        'billingcycle'  => ['monthly'],
        'noinvoice'     => false,
        'noemail'       => false,
        'promocode'     => '100E3SIGNUP'
    ];

    $orderResult = localAPI('AddOrder', $addOrderData, $adminUser);
    $debugInfo['addOrderResponse'] = $orderResult;

    if ($orderResult['result'] !== 'success') {
        $_SESSION['message'] = "Error adding product: " . $orderResult['message'];
        $vars = [
            'old'       => $_SESSION['old'],
            'message'   => $_SESSION['message'],
            'debugInfo' => print_r($debugInfo, true),
        ];
        return $vars;
    }

    $orderId = $orderResult['orderid'];
    $acceptResult = localAPI('AcceptOrder', [
        'orderid'   => $orderId,
        'sendemail' => true,
    ], $adminUser);

    if ($acceptResult['result'] !== 'success') {
        $_SESSION['message'] = "Failed to accept order: " . $acceptResult['message'];
        // Handle error as needed...
    }

    $hostingRow = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->where('packageid', $packageId)
        ->orderBy('id', 'desc')
        ->first();

    if (!$hostingRow) {
        $_SESSION['message'] = "Cannot find hosting record for the newly accepted order.";
    }

    Capsule::table('tblhosting')
        ->where('id', $hostingRow->id)
        ->update(['username' => $username]);

    try {
        $tenantId = HelperController::getUniqueTenantId();
        $name = $addClientData['firstname'] . ' ' . $addClientData['lastname'];
        $params = [
            'uid'  => $username,
            'name' => $name,
            'tenant' => $tenantId
        ];

        $userCreation = AdminOps::createUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

        if ($userCreation['status'] != 'success') {
            throw new \Exception('Ceph user creation failed: ' . $userCreation['message']);
        }

        if (!isset($userCreation['data']['keys'][0]['access_key'], $userCreation['data']['keys'][0]['secret_key'])) {
            throw new \Exception('Invalid Ceph output from AdminOps createUser.');
        }

        $s3UserId = DBController::saveUser([
            'name' => $name,
            'username' => $username,
            'tenant_id' => $tenantId
        ]);

        if (!$s3UserId) {
            throw new \Exception('Failed to save user in the database.');
        }
        $accessKey = HelperController::encryptKey($userCreation['data']['keys'][0]['access_key'], $encryptionKey);
        $secretKey = HelperController::encryptKey($userCreation['data']['keys'][0]['secret_key'], $encryptionKey);

        DBController::insertRecord('s3_user_access_keys', [
            'user_id'    => $s3UserId,
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
        ]);
    } catch (\Exception $e) {
        $_SESSION['message'] = "user creation error: " . $e->getMessage();
        $debugInfo['cephError'] = $e->getMessage();
        $vars = [
            'old'       => $_SESSION['old'],
            'message'   => $_SESSION['message'],
            'debugInfo' => print_r($debugInfo, true),
        ];
        return $vars;
    }

    // --------------------------------------------------------------------------------------------
    // 8) Single Sign-On (SSO) to log the user in and redirect them to the dashboard
    // --------------------------------------------------------------------------------------------
    $ssoResult = localAPI('CreateSsoToken', [
        'client_id'         => $clientId,
        'destination'       => 'sso:custom_redirect',
        'sso_redirect_path' => 'index.php?m=cloudstorage&page=dashboard',
    ], $adminUser);

    $debugInfo['ssoResult'] = $ssoResult;

    if ($ssoResult['result'] === 'success') {
        unset($_SESSION['old']);
        $_SESSION['message'] = "Account created, Ceph user provisioned! Welcome aboard, Captain.";
        header("Location: {$ssoResult['redirect_url']}");
        exit;
    } else {
        unset($_SESSION['old']);
        $_SESSION['message'] = "Account created but auto-login failed: " . $ssoResult['message'];
        header("Location: index.php?m=cloudstorage&page=dashboard");
        exit;
    }
}

// If no POST data, just return the $vars structure
return $vars;
