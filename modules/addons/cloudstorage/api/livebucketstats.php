<?php

    require_once __DIR__ . '/../../../../init.php';

    if (!defined("WHMCS")) {
        die("This file cannot be accessed directly");
    }

    use Symfony\Component\HttpFoundation\JsonResponse;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
    use WHMCS\Module\Addon\CloudStorage\Client\DBController;
    use GuzzleHttp\Client;
    use GuzzleHttp\Promise\Utils;

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
    if (!$user) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'User not found.'], 200);
        $response->send();
        exit();
    }

    // Accept optional targeted bucket names to reduce payload/merge work
    $requestedBucketNames = [];
    if (!empty($_POST['bucket_names']) && is_array($_POST['bucket_names'])) {
        $requestedBucketNames = array_values(array_unique(array_map('strval', $_POST['bucket_names'])));
    }

    // Load module settings
    $module = DBController::getResult('tbladdonmodules', [
        ['module', '=', 'cloudstorage']
    ]);

    if (count($module) == 0) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage service error. Please contact support.'], 200);
        $response->send();
        exit();
    }

    $endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
    $adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
    $adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();

    // Build UID list: parent + tenants
    $uids = [];
    $parentUid = !empty($user->tenant_id) ? ($user->tenant_id . '$' . $user->username) : $user->username;
    $uids[] = $parentUid;
    $tenants = DBController::getTenants($user->id, ['username', 'tenant_id']);
    if ($tenants && count($tenants)) {
        foreach ($tenants as $t) {
            $tu = !empty($t->tenant_id) ? ($t->tenant_id . '$' . $t->username) : $t->username;
            $uids[] = $tu;
        }
    }
    $uids = array_values(array_unique($uids));

    // 30-second session cache keyed by UID list
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $cacheKey = 'cloudstorage_live_stats_' . md5(json_encode($uids));
    $now = time();
    if (!empty($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey]['expires']) && $_SESSION[$cacheKey]['expires'] > $now) {
        $cached = $_SESSION[$cacheKey];
        $data = $cached['data'];
        if (!empty($requestedBucketNames)) {
            // Filter to requested buckets only
            $filtered = [];
            foreach ($requestedBucketNames as $bn) {
                if (isset($data[$bn])) {
                    $filtered[$bn] = $data[$bn];
                }
            }
            $data = $filtered;
        }
        $ttl = $cached['expires'] - $now;
        $response = new JsonResponse(['status' => 'success', 'data' => $data, 'cached' => true, 'cache_ttl' => max(0, $ttl)]);
        $response->send();
        exit();
    }

    // Prepare signed requests in parallel
    $client = new Client(['http_errors' => false]);
    $date = gmdate('D, d M Y H:i:s T');
    $stringToSign = "GET\n\n\n{$date}\n/admin/bucket";
    $signature = base64_encode(hash_hmac('sha1', $stringToSign, $adminSecretKey, true));
    $authHeader = "AWS {$adminAccessKey}:{$signature}";

    $promises = [];
    foreach ($uids as $uid) {
        $promises[$uid] = $client->getAsync($endpoint . '/admin/bucket', [
            'headers' => [
                'Authorization' => $authHeader,
                'Date' => $date,
            ],
            'query' => [
                'uid' => $uid,
                'stats' => true
            ],
            'timeout' => 6.0
        ]);
    }

    $results = Utils::settle($promises)->wait();

    // Merge results across UIDs
    $merged = [];
    foreach ($results as $uid => $res) {
        if ($res['state'] !== 'fulfilled') {
            continue;
        }
        $responseObj = $res['value'];
        $statusCode = $responseObj->getStatusCode();
        if ($statusCode !== 200) {
            continue;
        }
        $json = json_decode($responseObj->getBody()->getContents(), true);
        if (!$json) {
            continue;
        }
        // Expect either array of buckets or a single object
        $buckets = is_assoc($json) && isset($json['bucket']) ? [$json] : (is_array($json) ? $json : []);
        foreach ($buckets as $bucket) {
            if (!isset($bucket['bucket'])) {
                continue;
            }
            $name = $bucket['bucket'];
            // Filter early if client asked only for a subset
            if (!empty($requestedBucketNames) && !in_array($name, $requestedBucketNames, true)) {
                continue;
            }
            $sizeBytes = 0;
            $numObjects = 0;
            if (isset($bucket['usage']['rgw.main'])) {
                $sizeBytes = (int)($bucket['usage']['rgw.main']['size'] ?? ($bucket['usage']['rgw.main']['size_actual'] ?? 0));
                $numObjects = (int)($bucket['usage']['rgw.main']['num_objects'] ?? 0);
            } else {
                $sizeBytes = (int)($bucket['size'] ?? 0);
                $numObjects = (int)($bucket['num_objects'] ?? 0);
            }
            $merged[$name] = [
                'size_bytes' => max(0, $sizeBytes),
                'num_objects' => max(0, $numObjects)
            ];
        }
    }

    // Cache for 30 seconds
    $_SESSION[$cacheKey] = [
        'data' => $merged,
        'expires' => $now + 30
    ];

    $response = new JsonResponse(['status' => 'success', 'data' => $merged, 'cached' => false, 'cache_ttl' => 30]);
    $response->send();
    exit();

    // Helper: determine associative array
    function is_assoc(array $arr) {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }


