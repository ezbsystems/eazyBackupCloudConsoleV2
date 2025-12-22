<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$products = Capsule::table('tblhosting')->select('username')->where('packageid', $packageId)->where('domainstatus', 'Active')->get();
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    logModuleCall(self::$module, __FUNCTION__, $packageId, 'Please enable the cloudstorage addon module.');
    die('Please enable the cloudstorage addon module.');
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

$usersExist = [];
$newUsers = [];
$notCreatedAccount = [];
foreach ($products as $product) {
    $username = $product->username;
    $user = DBController::getUser($username);
    if (!is_null($user)) {
        array_push($usersExist, $username);
        continue;
    }

    $userinfo = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $username);
    if ($userinfo['status'] != 'success') {
        array_push($notCreatedAccount, $username);
        continue;
    }
    $accessKey = '';
    $secretKey = '';
    $keys = $userinfo['data']['keys'];
    $searchUser = $username;

    foreach ($keys as $item) {
        if ($item['user'] === $searchUser) {
            $accessKey = $item['access_key'];
            $secretKey = $item['secret_key'];
            break;
        }
    }

    if (!empty($accessKey) && !empty($secretKey)) {
        $userId = DBController::insertGetId('s3_users', [
            'name' => $userinfo['data']['display_name'],
            'username' => $username,
        ]);

        $accessKeyPlain = $accessKey;
        $hint = (strlen($accessKeyPlain) <= 8) ? $accessKeyPlain : (substr($accessKeyPlain, 0, 4) . '…' . substr($accessKeyPlain, -4));
        $accessKey = HelperController::encryptKey($accessKeyPlain ,$encryptionKey);
        $secretKey = HelperController::encryptKey($secretKey ,$encryptionKey);

        // insert record into s3_subuser_keys
        DBController::insertRecord('s3_user_access_keys', [
            'user_id' => $userId,
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'access_key_hint' => $hint,
        ]);
        $newUsers[] = $username;
    } else {
        $notCreatedAccount[] = $username;
    }
}

echo "<pre>"; print_r($newUsers); echo "</pre>";
echo "<pre>"; print_r($usersExist); echo "</pre>";
echo "<pre>"; print_r($notCreatedAccount); echo "</pre>";
die('Migration done');

