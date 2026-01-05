<?php

    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Database\Capsule;

    if (empty($_GET['bucket']) || empty($_GET['username'])) {
        $_SESSION['message'] = "Invalid request.";
        header("location: index.php?m=cloudstorage&page=buckets&status=fail");
        exit;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit();
    }

    // Resolve client ID (WHMCS v8 user->client mapping)
    $loggedInUserId = (int) $ca->getUserID();
    $clientId = 0;
    try {
        $link = Capsule::table('tblusers_clients')->where('userid', $loggedInUserId)->orderBy('owner', 'desc')->first();
        if ($link && isset($link->clientid)) {
            $clientId = (int) $link->clientid;
        }
    } catch (\Throwable $e) {}
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }
    if ($clientId <= 0) {
        $clientId = $loggedInUserId; // legacy fallback
    }

    $product = DBController::getProduct($clientId, $packageId);
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

    // Option B / data-plane guard: browsing requires a key for the selected storage user.
    try {
        $k = Capsule::table('s3_user_access_keys')->where('user_id', (int)$userId)->first();
        if (!$k) {
            $_SESSION['message'] = 'Create an access key before browsing buckets.';
            if ($browseUser === $product->username) {
                header('Location: index.php?m=cloudstorage&page=access_keys');
            } else {
                header('Location: index.php?m=cloudstorage&page=users');
            }
            exit();
        }
    } catch (\Throwable $e) {
        // If key check fails, proceed; API endpoints may still enforce key presence.
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