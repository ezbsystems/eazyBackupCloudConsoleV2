<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

    if (empty($_GET['bucket']) || empty($_GET['username'])) {
        $_SESSION['message'] = "Invalid request.";
        header("location: index.php?m=cloudstorage&page=buckets&status=fail");
        exit;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        header('Location: index.php?m=cloudstorage&page=s3storage');
        exit();
    }
    $browseUser = $_GET['username'];
    $username = $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $_SESSION['message'] = "Your account has been suspended. Please contact support.";
        header("location: index.php?m=cloudstorage&page=buckets&status=fail");
        exit();
    }

    $userId = $user->id;

    if ($username != $browseUser) {
        $tenant = DBController::getRow('s3_users', [
            ['username', '=', $browseUser],
            ['parent_id', '=', $userId],
        ]);

        if (is_null($tenant)) {
            $_SESSION['message'] = "Invalid request.";
            header("location: index.php?m=cloudstorage&page=buckets&status=fail");
            exit();
        }

        $username = $browseUser;
        $userId = $tenant->id;
        $user = $tenant;
    }

    $bucketName = $_GET['bucket'];
    $prefix = isset($_GET['folder_path']) ? trim($_GET['folder_path']) : '';

    // check bucket belongs to the logged in user or tenants of the user
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName],
        ['user_id', '=', $userId],
        ['is_active', '=', '1']
    ]);

    if (is_null($bucket)) {
        $_SESSION['message'] = "Bucket not found.";
        header("location: index.php?m=cloudstorage&page=buckets&status=fail");
        exit();
    }

    $params = [
        'uid' => $username,
        'bucket' => $bucketName,
    ];
    if (!empty($user->tenant_id)) {
        $params['uid'] = $user->tenant_id . '$' . $username;
    }
    // check bucket exist on server
    $bucketInfo = AdminOps::getBucketInfo($s3Endpoint, $cephAdminAccessKey, $cephAdminSecretKey, $params);

    if ($bucketInfo['status'] == 'fail' && isset($bucketInfo['error'])) {
        if (preg_match('/"Code":"(.*?)"/', $bucketInfo['error'], $matches)) {
            if ($matches[1] == 'NoSuchBucket') {
                DBController::deleteRecord('s3_buckets', [
                    ['id', '=', $bucket->id]
                ]);
                $_SESSION['message'] = "Bucket does not exist any more.";
                header("location: index.php?m=cloudstorage&page=buckets&status=fail");
                exit;
            }
        }
    }

    // Pass error message to template and clean up session
    $errorMessage = null;
    if (isset($_SESSION['cloudstorage_error'])) {
        $errorMessage = $_SESSION['cloudstorage_error'];
        unset($_SESSION['cloudstorage_error']);
    }

    return [
        'error_message' => $errorMessage,
        'S3_ENDPOINT' => $s3Endpoint ?? ''
    ];