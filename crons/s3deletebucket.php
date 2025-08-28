<?php


require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

$buckets = DBController::getResult('s3_delete_buckets', [
    ['attempt_count', '=', '0']
]);

if (count($buckets)) {
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $response = [
            'message' => 'Cloudstorage has some issue. Please contact to site admin.',
            'status' => 'fail',
        ];
        logModuleCall('cloudstorage', 'deletebucket', [], $response);
        return;
    }
    $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
    $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey);

    foreach ($buckets as $bucket) {
        $userId = $bucket->user_id;
        // get the user
        $user = Capsule::table('s3_users')->where('id', $userId)->first();
        if (is_null($user)) {
            logModuleCall('cloudstorage', 'deletebucket', $userId, 'User not found in db.');
            continue;
        }
        $s3Connection = $bucketObject->connectS3Client($userId, $encryptionKey);
        if ($s3Connection['status'] == 'fail') {
            logModuleCall('cloudstorage', 'deletebucket', 'S3 connection failed for user id' . $userId, $s3Connection['message']);
            continue;
        }
        $bucketName = $bucket->bucket_name;
        $params = [
            'bucket' => $bucketName,
        ];

        if (!empty($user->tenant_id)) {
            $params['bucket'] = $user->tenant_id . '/' . $bucketName;
        }

        // check bucket exist on server
        $bucketInfo = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);
        $dbclear = 0;
        if ($bucketInfo['status'] != 'success' && isset($bucketInfo['error'])) {
            if (preg_match('/"Code":"(.*?)"/', $bucketInfo['error'], $matches)) {
                if ($matches[1] == 'NoSuchBucket') {
                    $dbclear = 1;
                }
            }
        }

        if (!$dbclear) {
            $response = $bucketObject->deleteBucket($bucket->user_id, $bucket->bucket_name);
            if ($response['status'] == 'fail') {
                logModuleCall('cloudstorage', 'deletebucket', $response['status'], $response['message']);
                DBController::updateRecord('s3_delete_buckets', [
                    'attempt_count' => $bucket->attempt_count + 1
                ], [
                    ['id', '=', $bucket->id]
                ]);
                continue;
            }
        }

        DBController::deleteRecord('s3_delete_buckets', [
            ['id', '=', $bucket->id]
        ]);

        DBController::deleteRecord('s3_buckets', [
            ['name', '=', $bucketName],
            ['user_id', '=', $userId]
        ]);
    }
}
