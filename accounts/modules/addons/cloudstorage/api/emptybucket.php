<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

    $ca = new ClientArea();
    $loggedInUserId = $ca->getUserID();
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'User does not exist.'], 200);
        $response->send();
        exit();
    }

    $username = $product->username;
    $user = DBController::getUser($username);
    $bucketName = $_POST['bucket_name'] ?? '';
    if ($bucketName === '') {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200);
        $response->send();
        exit();
    }

    // Validate bucket ownership (including tenants)
    $bucket = DBController::getRow('s3_buckets', [
        ['name', '=', $bucketName]
    ]);

    if (is_null($bucket)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
        $response->send();
        exit();
    }

    if ($bucket->user_id != $user->id) {
        $tenants = DBController::getTenants($user->id, 'id');
        if ($tenants->isEmpty()) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
            $response->send();
            exit();
        }
        $tenantIds = $tenants->pluck('id')->toArray();
        if (!in_array($bucket->user_id, $tenantIds)) {
            $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
            $response->send();
            exit();
        }
    }

    // Enforce 2FA delete protection if enabled for this bucket
    // Note: if the column/setting doesn't exist, this will evaluate as false
    if (!empty($bucket->two_factor_delete_enabled)) {
        $response = new JsonResponse([
            'status' => 'fail',
            'message' => 'Two-factor delete protection is enabled for this bucket. Please disable it before emptying the bucket.'
        ], 200);
        $response->send();
        exit();
    }

    // Queue empty job by adding to s3_delete_buckets (cron will process)
    DBController::insertRecord('s3_delete_buckets', [
        'user_id' => $bucket->user_id,
        'bucket_name' => $bucketName
    ]);

    $response = new JsonResponse([
        'status' => 'success',
        'message' => 'Empty job queued. We\'ve started clearing ' . $bucketName . ' in the background. You can close this window.'
    ], 200);
    $response->send();
    exit();


