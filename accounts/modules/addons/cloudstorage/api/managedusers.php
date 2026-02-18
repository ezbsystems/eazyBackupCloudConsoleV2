<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Database\Capsule;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Request\TenantRequest;
    use WHMCS\Module\Addon\CloudStorage\Admin\Tenant;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Session timeout.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    if (!isset($_POST['action']) || !in_array($_POST['action'], ['decryptkey', 'deletekey', 'addkey', 'addtenant', 'deletetenant'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Bad request.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (is_null($product) || is_null($product->username)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Something went wrong.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $username = $product->username;
    $parentUser = DBController::getUser($username);
    if (is_null($parentUser)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Your account has been suspended. Please contact support.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }
    $parentUserId = $parentUser->id;

    $targetUsername = trim((string)($_POST['username'] ?? ''));
    if (in_array($_POST['action'], ['decryptkey', 'addkey', 'deletekey', 'deletetenant'], true) && $targetUsername !== '') {
        $targetUser = Capsule::table('s3_users')
            ->where('username', $targetUsername)
            ->where('parent_id', $parentUserId)
            ->first(['id', 'is_system_managed', 'manage_locked']);
        if ($targetUser && (!empty($targetUser->manage_locked) || !empty($targetUser->is_system_managed))) {
            $response = new JsonResponse([
                'status' => 'fail',
                'message' => 'This user is system managed and cannot be modified.'
            ], 200);
            $response->send();
            exit();
        }
    }

    if ($_POST['action'] == 'decryptkey') {
        TenantRequest::validateDecryptKey($_POST);
        if ($_POST['type'] == 'subuser') {
            $result = Tenant::decryptSubuserKey($_POST, $parentUserId);
        } else {
            $result = Tenant::decryptTenantKey($_POST, $parentUserId);
        }
    } elseif ($_POST['action'] == 'addtenant') {
        TenantRequest::validateTenant($_POST);
        $result = Tenant::addTenant($_POST, $parentUser);
    } elseif ($_POST['action'] == 'addkey') {
        TenantRequest::validateKey($_POST);
        if ($_POST['type'] == 'subuser') {
            $result = Tenant::addTenantSubuserKey($_POST, $parentUser);
        } else {
            $result = Tenant::addTenantKey($_POST, $parentUserId);
        }
    } elseif ($_POST['action'] == 'deletekey') {
        TenantRequest::validateDeleteKey($_POST);
        if ($_POST['type'] == 'subuser') {
            $result = Tenant::deleteSubuser($_POST, $parentUserId);
        } else {
            $result = Tenant::deleteKey($_POST, $parentUserId);
        }
    } elseif ($_POST['action'] == 'deletetenant') {
        TenantRequest::validateDelete($_POST);
        $result = Tenant::deleteTenant($_POST, $parentUserId);
    } else {
        $result = [
            'status' => 'fail',
            'message' => 'Something went wrong.'
        ];
    }

    $response = new JsonResponse($result, 200);
    $response->send();
    exit();