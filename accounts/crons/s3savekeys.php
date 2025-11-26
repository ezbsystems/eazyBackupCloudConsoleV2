<?php

require __DIR__ . '/../init.php';

use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;


$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    logModuleCall('cloudstorage', 's3savekeys', [], [
        'Please enable the cloudstorage addon module.'
    ]);
    exit;
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
$users = DBController::getResult('s3_users');
$count = 0;
foreach ($users as $user) {
    $userinfo = AdminOps::getUserInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $user->username);
    if ($userinfo['status'] != 'fail' && count($userinfo['data']['keys'])) {
        $keys = $userinfo['data']['keys'][0];
        $accessKey = HelperController::encryptKey($keys['access_key'], $encryptionKey);
        $secretKey = HelperController::encryptKey($keys['secret_key'], $encryptionKey);
        $userAccessKey = DBController::getRow('s3_user_access_keys', [
            ['user_id', '=', $user->id]
        ]);
        
        if (is_null($userAccessKey)) {
            $count++;
            DBController::insertRecord('s3_user_access_keys', [
                'access_key' => $accessKey,
                'secret_key' => $secretKey,
                'user_id' => $user->id
            ]);
        } else {
            $count++;
            DBController::updateRecord('s3_user_access_keys', [
                'access_key' => $accessKey,
                'secret_key' => $secretKey,
            ], [
                ['user_id', '=', $user->id]
            ]);
        }
    }

}

echo $count . " keys has been added or updated successfully";
