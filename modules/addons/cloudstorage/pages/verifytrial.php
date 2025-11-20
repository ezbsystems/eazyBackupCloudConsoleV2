<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$debugInfo = [];

$vars = [
    'message'   => '',
    'debugInfo' => '',
];

$token = $_GET['token'] ?? '';

if ($token === '') {
    $_SESSION['message'] = 'Verification link is invalid. Please start again.';
    header('Location: index.php?m=cloudstorage&page=signup');
    exit;
}

try {
    $record = Capsule::table('cloudstorage_trial_verifications')
        ->where('token', $token)
        ->first();

    if (!$record) {
        $_SESSION['message'] = 'Verification link is invalid or has already been used.';
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    // Check expiry and consumed status
    if (!empty($record->consumed_at) || (!empty($record->expires_at) && $record->expires_at < date('Y-m-d H:i:s'))) {
        $_SESSION['message'] = 'Verification link has expired. Please start a new trial signup.';
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    $clientId = (int) $record->client_id;
    $email    = $record->email;

    $meta = [];
    if (!empty($record->meta)) {
        $decoded = json_decode($record->meta, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $username = $meta['username'] ?? null;
    if (empty($username)) {
        $username = 'e3user' . bin2hex(random_bytes(2));
    }

    // Load module configuration needed for Ceph and encryption
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $_SESSION['message'] = 'Cloudstorage module configuration error. Please contact support.';
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    $s3Endpoint         = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $encryptionKey      = $module->where('setting', 'encryption_key')->pluck('value')->first();

    $adminUser = 'API';
    $packageId = ProductConfig::$E3_PRODUCT_ID;

    // ----------------------------------------------------------------------------------------
    // 1) Create an order for the e3 Cloud Storage product using AddOrder
    // ----------------------------------------------------------------------------------------
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
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    $orderId = $orderResult['orderid'];
    $acceptResult = localAPI('AcceptOrder', [
        'orderid'   => $orderId,
        'sendemail' => true,
    ], $adminUser);

    if ($acceptResult['result'] !== 'success') {
        $_SESSION['message'] = "Failed to accept order: " . $acceptResult['message'];
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    $hostingRow = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->where('packageid', $packageId)
        ->orderBy('id', 'desc')
        ->first();

    if (!$hostingRow) {
        $_SESSION['message'] = "Cannot find hosting record for the newly accepted order.";
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    Capsule::table('tblhosting')
        ->where('id', $hostingRow->id)
        ->update(['username' => $username]);

    // ----------------------------------------------------------------------------------------
    // 2) Create Ceph user and store access keys
    // ----------------------------------------------------------------------------------------
    try {
        $name = $meta['fullName'] ?? ('Client ' . $clientId);
        $tenantId = HelperController::getUniqueTenantId();

        $params = [
            'uid'    => $username,
            'name'   => $name,
            'tenant' => $tenantId,
        ];

        $userCreation = AdminOps::createUser($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

        if ($userCreation['status'] != 'success') {
            throw new \Exception('Ceph user creation failed: ' . $userCreation['message']);
        }

        if (!isset($userCreation['data']['keys'][0]['access_key'], $userCreation['data']['keys'][0]['secret_key'])) {
            throw new \Exception('Invalid Ceph output from AdminOps createUser.');
        }

        $s3UserId = DBController::saveUser([
            'name'      => $name,
            'username'  => $username,
            'tenant_id' => $tenantId,
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
        $_SESSION['message'] = "User provisioning error: " . $e->getMessage();
        $debugInfo['cephError'] = $e->getMessage();
        header('Location: index.php?m=cloudstorage&page=signup');
        exit;
    }

    // Mark verification as consumed
    Capsule::table('cloudstorage_trial_verifications')
        ->where('id', $record->id)
        ->update(['consumed_at' => date('Y-m-d H:i:s')]);

    // ----------------------------------------------------------------------------------------
    // 3) Single Sign-On (SSO) to log the user in and redirect them to the dashboard
    // ----------------------------------------------------------------------------------------
    $ssoResult = localAPI('CreateSsoToken', [
        'client_id'         => $clientId,
        'destination'       => 'sso:custom_redirect',
        'sso_redirect_path' => 'index.php?m=cloudstorage&page=dashboard',
    ], $adminUser);

    $debugInfo['ssoResult'] = $ssoResult;

    if ($ssoResult['result'] === 'success') {
        $_SESSION['message'] = "Account verified and provisioned! Welcome aboard, Captain.";
        header("Location: {$ssoResult['redirect_url']}");
        exit;
    } else {
        $_SESSION['message'] = "Account verified but auto-login failed: " . $ssoResult['message'];
        header("Location: index.php?m=cloudstorage&page=dashboard");
        exit;
    }
} catch (\Exception $e) {
    $_SESSION['message'] = 'Unexpected verification error: ' . $e->getMessage();
    header('Location: index.php?m=cloudstorage&page=signup');
    exit;
}

return $vars;


