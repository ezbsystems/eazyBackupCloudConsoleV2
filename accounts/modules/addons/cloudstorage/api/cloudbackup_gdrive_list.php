<?php

require_once __DIR__ . '/../../../../init.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\GoogleDriveService;

if (!defined("WHMCS")) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Invalid context'], 200);
    $response->send();
    exit;
}

// Basic session auth
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}

// Very simple rate limiting: 15 requests per 60s window per session
session_start();
$now = time();
if (!isset($_SESSION['gdrive_list_rl'])) {
    $_SESSION['gdrive_list_rl'] = ['start' => $now, 'count' => 0];
}
$rl = &$_SESSION['gdrive_list_rl'];
if ($now - ($rl['start'] ?? 0) > 60) {
    $rl = ['start' => $now, 'count' => 0];
}
$rl['count'] = (int)($rl['count'] ?? 0) + 1;
if ($rl['count'] > 15) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Rate limit exceeded. Please wait a moment and try again.'], 200))->send();
    exit;
}

try {
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();

    $product = DBController::getProduct($loggedInUserId, $packageId);
    if (is_null($product) || empty($product->username)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Product not found'], 200))->send();
        exit;
    }

    // Resolve the connection to use
    $connId = isset($_GET['source_connection_id']) ? (int)$_GET['source_connection_id'] : 0;
    if ($connId > 0) {
        $conn = Capsule::table('s3_cloudbackup_sources')
            ->where('id', $connId)
            ->where('client_id', $loggedInUserId)
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->first();
    } else {
        $conn = Capsule::table('s3_cloudbackup_sources')
            ->where('client_id', $loggedInUserId)
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->first();
    }
    if (!$conn) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Google Drive connection not found'], 200))->send();
        exit;
    }

    // Load module settings
    $clientId = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'cloudbackup_google_client_id')
        ->value('value');
    $clientSecret = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'cloudbackup_google_client_secret')
        ->value('value');
    if (empty($clientId) || empty($clientSecret)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Google OAuth is not configured'], 200))->send();
        exit;
    }

    // Encryption key cascade
    $encKey = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'cloudbackup_encryption_key')
        ->value('value');
    if (empty($encKey)) {
        $encKey = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'encryption_key')
            ->value('value');
    }
    if (empty($encKey)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Encryption key not configured'], 200))->send();
        exit;
    }

    // Decrypt refresh token
    $refreshToken = HelperController::decryptKey($conn->refresh_token_enc, $encKey);
    if (empty($refreshToken)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Failed to decrypt refresh token'], 200))->send();
        exit;
    }

    // Exchange for access token
    $tok = GoogleDriveService::exchangeRefreshToken($clientId, $clientSecret, $refreshToken);
    if (($tok['status'] ?? 'fail') !== 'success') {
        logModuleCall('cloudstorage', 'gdrive_list_exchange', ['client_id' => $loggedInUserId], $tok['message'] ?? 'exchange failed');
        (new JsonResponse(['status' => 'fail', 'message' => 'Google authorization failed'], 200))->send();
        exit;
    }
    $accessToken = $tok['access_token'];

    // Route modes
    $mode = $_GET['mode'] ?? 'children';
    $pageToken = $_GET['pageToken'] ?? null;
    $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 100;

    if ($mode === 'drives') {
        $out = GoogleDriveService::listSharedDrives($accessToken, $pageToken, $pageSize);
        if (($out['status'] ?? 'fail') !== 'success') {
            logModuleCall('cloudstorage', 'gdrive_list_drives', ['client_id' => $loggedInUserId], $out['message'] ?? 'list failed', [], []);
            (new JsonResponse(['status' => 'fail', 'message' => 'Failed to list shared drives'], 200))->send();
            exit;
        }
        (new JsonResponse([
            'status' => 'success',
            'items' => $out['items'] ?? [],
            'nextPageToken' => $out['nextPageToken'] ?? null,
        ], 200))->send();
        exit;
    }

    // children mode (default)
    $parentId = $_GET['parentId'] ?? 'root';
    $driveId = $_GET['driveId'] ?? null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $includeFiles = isset($_GET['includeFiles']) ? (string)$_GET['includeFiles'] : '0';
    $includeFiles = ($includeFiles !== '0' && $includeFiles !== '');
    $opts = [
        'driveId' => $driveId ?: null,
        'q' => $search !== '' ? $search : null,
        'pageToken' => $pageToken ?: null,
        'pageSize' => $pageSize,
    ];
    if ($includeFiles) {
        $out = GoogleDriveService::listChildren($accessToken, $parentId, $opts);
    } else {
        $out = GoogleDriveService::listChildFolders($accessToken, $parentId, $opts);
    }
    if (($out['status'] ?? 'fail') !== 'success') {
        logModuleCall('cloudstorage', 'gdrive_list_children', ['client_id' => $loggedInUserId, 'parentId' => $parentId, 'driveId' => $driveId, 'includeFiles' => $includeFiles ? 1 : 0], $out['message'] ?? 'list failed', [], []);
        (new JsonResponse(['status' => 'fail', 'message' => 'Failed to list items'], 200))->send();
        exit;
    }

    (new JsonResponse([
        'status' => 'success',
        'items' => $out['items'] ?? [],
        'nextPageToken' => $out['nextPageToken'] ?? null,
    ], 200))->send();
    exit;

} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'gdrive_list_exception', $_GET, $e->getMessage(), [], []);
    (new JsonResponse(['status' => 'fail', 'message' => 'Unexpected error'], 200))->send();
    exit;
}


