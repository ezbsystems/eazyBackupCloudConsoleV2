<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Product not found.'], 200))->send();
    exit;
}

$accessKey = isset($_POST['access_key']) ? trim((string)$_POST['access_key']) : '';
$secretKey = isset($_POST['secret_key']) ? trim((string)$_POST['secret_key']) : '';
$region    = isset($_POST['region']) ? trim((string)$_POST['region']) : '';
$filterRegion = isset($_POST['filter_region']) ? (int)$_POST['filter_region'] : 0;

if ($accessKey === '' || $secretKey === '' || $region === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Access key, secret key, and region are required.'], 200))->send();
    exit;
}

try {
    if (!class_exists('\\Aws\\S3\\S3Client')) {
        throw new \RuntimeException('AWS SDK not available on this server');
    }

    $s3 = new \Aws\S3\S3Client([
        'version'     => 'latest',
        'region'      => $region,
        'credentials' => [
            'key'    => $accessKey,
            'secret' => $secretKey,
        ],
    ]);

    $result = $s3->listBuckets();
    $buckets = [];
    $items = isset($result['Buckets']) && is_array($result['Buckets']) ? $result['Buckets'] : [];

    $normalize = function ($loc) {
        if ($loc === null || $loc === '' || strtoupper((string)$loc) === 'US') {
            return 'us-east-1';
        }
        if (strtoupper((string)$loc) === 'EU') {
            return 'eu-west-1';
        }
        return strtolower((string)$loc);
    };

    foreach ($items as $b) {
        $name = $b['Name'] ?? null;
        if (!$name) continue;

        if ($filterRegion) {
            try {
                $lr = $s3->getBucketLocation(['Bucket' => $name]);
                $loc = $lr['LocationConstraint'] ?? null;
                $bnRegion = $normalize($loc);
                if ($bnRegion !== strtolower($region)) {
                    continue;
                }
            } catch (\Throwable $e) {
                // If location fails for a bucket, skip filtering that bucket
            }
        }

        $buckets[] = ['name' => $name];
    }

    (new JsonResponse(['status' => 'success', 'buckets' => $buckets], 200))->send();
    exit;
} catch (\Throwable $e) {
    // Sanitize logging context - do not log keys
    $ctx = ['region' => $region, 'filter_region' => $filterRegion ? 1 : 0];
    logModuleCall('cloudstorage', 'cloudbackup_list_aws_buckets', $ctx, $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to list buckets. Please verify keys and region.'], 200))->send();
    exit;
}


