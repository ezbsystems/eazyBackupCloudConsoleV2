<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (is_null($product) || is_null($product->username)) {
        $_SESSION['message'] = 'Account not exist.';
        header('location: index.php?m=cloudstorage&page=s3storage');
        exit;
    }

    // Load module configuration settings
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $_SESSION['message'] = 'Cloud Storage service error. Please contact technical support for assistance.';
        header('location: index.php?m=cloudstorage&page=buckets&status=fail');
        exit;
    }

    $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
    $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
    $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'ca-central-1';

    $message = '';
    $error = 0;
    if ($_POST['bucket_name'] == '') {
        $message .= 'Please enter the bucket name.';
        $error = 1;
    }

    // Only validate retention settings if user wants to set a default retention policy
    $setDefaultRetention = !empty($_POST['setDefaultRetention']) ? true : false;
    if (isset($_POST['enableObjectLocking']) && $setDefaultRetention && ($_POST['objectLockDays'] == '' || $_POST['objectLockDays'] < 1)) {
        $message .= 'Please enter the object lock days.';
        $error = 1;
    }

    if ($_POST['username'] == '') {
        $message .= 'Please select a Username.';
        $error = 1;
    }

    // validate bucket name
    if (!HelperController::isValidBucketName($_POST['bucket_name'])) {
        $message .= 'Bucket names can only contain lowercase letters, numbers, and hyphens, and must not start or end with a hyphen or period, or contain two consecutive periods or period-hyphen(-) or hyphen-period(-.).';
        $error = 1;
    }

    if ($error) {
        $_SESSION['message'] = $message;
        header('location: index.php?m=cloudstorage&page=buckets&status=fail');
        exit();
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $_SESSION['message'] = 'Your account has been suspended. Please contact support.';
        header('location: index.php?m=cloudstorage&page=buckets&status=fail');
        exit();
    }
    $userId = $user->id;
    if ($username != $_POST['username']) {
        $username = $_POST['username'];
        $tenant = DBController::getRow('s3_users', [
            ['username', '=', $username],
            ['parent_id', '=', $userId],
        ]);

        if (is_null($tenant)) {
            $_SESSION['message'] = 'Tenant ' . $username . ' not found. Please contact support.';
            header('location: index.php?m=cloudstorage&page=buckets&status=fail');
            exit();
        }

        $userId = $tenant->id;
        $user = $tenant;
    }

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
    $s3Connection = $bucketObject->connectS3Client($userId, $encryptionKey);
    if ($s3Connection['status'] == 'fail') {
        $_SESSION['message'] = $s3Connection['message'];
        header('location: index.php?m=cloudstorage&page=buckets');
        exit();
    }
    
    // Log the form processing start
    logModuleCall('cloudstorage', 'savebucket_form_processing', [
        'user_id' => $userId,
        'bucket_name' => $_POST['bucket_name'],
        'enable_versioning' => !empty($_POST['enableVersioning']),
        'enable_object_locking' => !empty($_POST['enableObjectLocking']),
        'set_default_retention' => !empty($_POST['setDefaultRetention']),
        'retention_mode' => $_POST['objectLockMode'] ?? 'GOVERNANCE',
        'retention_days' => $_POST['objectLockDays'] ?? 1,
        's3_endpoint' => $s3Endpoint
    ], 'Form processing started for bucket creation');
    
    $bucketName = $_POST['bucket_name'];
    $enableVersioning = !empty($_POST['enableVersioning']) ? true : false;
    $enableObjectLocking = !empty($_POST['enableObjectLocking']) ? true : false;
    $retentionMode = !empty($_POST['objectLockMode']) && in_array($_POST['objectLockMode'], ['GOVERNANCE', 'COMPLIANCE']) ? $_POST['objectLockMode'] : 'GOVERNANCE';
    $retentionDays = !empty($_POST['objectLockDays']) ? $_POST['objectLockDays'] : 1;
    $response = $bucketObject->createBucket($user, $bucketName, $enableVersioning, $enableObjectLocking, $retentionMode, $retentionDays, $setDefaultRetention);
    $_SESSION['message'] = $response['message'];
    header("location: index.php?m=cloudstorage&page=buckets&status={$response['status']}");
    exit();
