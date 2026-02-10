<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

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

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $product = DBController::getProduct($loggedInUserId, $packageId);

    if (is_null($product) || empty($_REQUEST['time']) || !in_array($_REQUEST['time'], ['day', 'weekly', 'monthly'])) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Something went wrong.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }

    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $jsonData = [
            'message' => 'Cloudstorage has some issue. Please contact to site admin.',
            'status' => 'fail',
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }
    $s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
    $cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
    $s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

    $username = $product->username;
    $user = DBController::getUser($username);
    if (is_null($user)) {
        $jsonData = [
            'status' => 'fail',
            'message' => 'Your account has been suspended. Please contact support.'
        ];

        $response = new JsonResponse($jsonData, 200);
        $response->send();
        exit();
    }
    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id]
    ], [
        'id', 'username'
    ])->pluck('id', 'username')->toArray();

    $userIds = array_merge(array_values($tenants), [$user->id]);
    $usernames = array_merge([$username], array_keys($tenants));
    if (!empty($_POST['username'])) {
        if (!in_array($_POST['username'], $usernames)) {
            $jsonData = [
                'status' => 'fail',
                'message' => 'Selected user is invalid.'
            ];

            $response = new JsonResponse($jsonData, 200);
            $response->send();
            exit();
        }

        if ($_POST['username'] == $username) {
            $userIds = [$user->id];
        } else {
            $userIds = [$tenants[$_POST['username']]];
        }
    }

    $bucketObject = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
    $transferdata = $bucketObject->getUserTransferSummary($userIds, $_POST['time']);

    // Calculate totals using the same method as initial page load for consistency
    if ($_POST['time'] == 'day') {
        // For today, use the same method as dashboard.php
        $today = date('Y-m-d');
        $totalUsage = $bucketObject->getTotalUsageForBillingPeriod($userIds, $today, $today);
        $periodTotals = [
            'total_bytes_sent' => $totalUsage['total_bytes_sent'],
            'total_bytes_received' => $totalUsage['total_bytes_received']
        ];
    } else {
        // For other periods, calculate from the transfer data
        $periodTotals = [
            'total_bytes_sent' => 0,
            'total_bytes_received' => 0
        ];
        
        foreach ($transferdata as $item) {
            $periodTotals['total_bytes_sent'] += $item['total_bytes_sent'];
            $periodTotals['total_bytes_received'] += $item['total_bytes_received'];
        }
    }
    // #region agent log
    try {
        $transferCount = is_array($transferdata) ? count($transferdata) : 0;
        $transferSumReceived = 0.0;
        $transferSumSent = 0.0;
        $transferMaxReceived = 0.0;
        if (is_array($transferdata)) {
            foreach ($transferdata as $row) {
                $received = isset($row['total_bytes_received']) ? (float)$row['total_bytes_received'] : 0.0;
                $sent = isset($row['total_bytes_sent']) ? (float)$row['total_bytes_sent'] : 0.0;
                $transferSumReceived += $received;
                $transferSumSent += $sent;
                if ($received > $transferMaxReceived) {
                    $transferMaxReceived = $received;
                }
            }
        }
        file_put_contents(
            '/var/www/eazybackup.ca/.cursor/debug.log',
            json_encode([
                'id' => uniqid('log_', true),
                'timestamp' => (int)round(microtime(true) * 1000),
                'location' => 'api/updatechart.php:transfer_totals',
                'message' => 'Transfer totals for ajax refresh',
                'data' => [
                    'time' => $_POST['time'] ?? null,
                    'selectedUsername' => $_POST['username'] ?? '',
                    'userIds' => $userIds,
                    'transferCount' => $transferCount,
                    'transferSumReceived' => $transferSumReceived,
                    'transferSumSent' => $transferSumSent,
                    'transferMaxReceived' => $transferMaxReceived,
                    'periodTotalsReceived' => $periodTotals['total_bytes_received'] ?? 0,
                    'periodTotalsSent' => $periodTotals['total_bytes_sent'] ?? 0
                ],
                'runId' => 'pre-fix',
                'hypothesisId' => 'H4'
            ]) . PHP_EOL,
            FILE_APPEND
        );
    } catch (\Throwable $e) {}
    // #endregion

    $result = [
        'status' => 'success',
        'message' => 'Data retrieved successfully.',
        'data' => $transferdata,
        'totals' => [
            'ingress' => \WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($periodTotals['total_bytes_received']),
            'egress' => \WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($periodTotals['total_bytes_sent'])
        ]
    ];

    $response = new JsonResponse($result, 200);
    $response->send();
    exit();