<?php
/**
 * Cloud NAS - Create Mount Configuration
 * Creates a new mount configuration and optionally sends mount command to agent
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// #region agent log
function cloudnasCreateMountDebugLog(string $message, array $data, string $hypothesisId): void
{
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-991471.log', json_encode([
        'sessionId' => '991471',
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => 'cloudnas_create_mount.php',
        'message' => $message,
        'data' => $data,
        'hypothesisId' => $hypothesisId,
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}
// #endregion

$cloudstorageModule = dirname(__DIR__) . '/cloudstorage.php';
if (is_file($cloudstorageModule)) {
    require_once $cloudstorageModule;
    if (function_exists('cloudstorage_ensure_cloudnas_schema')) {
        cloudstorage_ensure_cloudnas_schema('api');
    }
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = (int) $ca->getUserID();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    (new JsonResponse(['status' => 'error', 'message' => 'Invalid request'], 200))->send();
    exit;
}

// Validate required fields
$bucket = trim($input['bucket'] ?? '');
$driveLetter = strtoupper(trim($input['drive_letter'] ?? ''));
$agentUuid = trim((string) ($input['agent_uuid'] ?? ''));

if (empty($bucket)) {
    (new JsonResponse(['status' => 'error', 'message' => 'Bucket name is required'], 200))->send();
    exit;
}

if (empty($driveLetter) || !preg_match('/^[A-Z]$/', $driveLetter)) {
    (new JsonResponse(['status' => 'error', 'message' => 'Valid drive letter is required (A-Z)'], 200))->send();
    exit;
}

if ($agentUuid === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'Agent selection is required'], 200))->send();
    exit;
}

try {
    $hasMountsTable = Capsule::schema()->hasTable('s3_cloudnas_mounts');
    // #region agent log
    cloudnasCreateMountDebugLog('create_mount_start', [
        'client_id' => $clientId,
        'agent_uuid' => $agentUuid,
        'bucket' => $bucket,
        'drive_letter' => $driveLetter,
        'has_mounts_table' => $hasMountsTable,
    ], 'H1');
    // #endregion

    if (!$hasMountsTable) {
        (new JsonResponse(['status' => 'error', 'message' => 'Cloud NAS is not configured on this server. Please contact support.'], 200))->send();
        exit;
    }

    // Verify agent belongs to client
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->where('client_id', $clientId)
        ->first();

    if (!$agent) {
        (new JsonResponse(['status' => 'error', 'message' => 'Invalid agent'], 200))->send();
        exit;
    }

    // Check if drive letter is already in use for this agent
    $existing = Capsule::table('s3_cloudnas_mounts')
        ->where('client_id', $clientId)
        ->where('agent_id', (int) $agent->id)
        ->where('drive_letter', $driveLetter)
        ->first();

    if ($existing) {
        (new JsonResponse(['status' => 'error', 'message' => "Drive letter {$driveLetter}: is already in use"], 200))->send();
        exit;
    }

    // Get user's S3 user and tenant IDs to verify bucket ownership
    $packageId = (int) ProductConfig::cloudStoragePid();
    $product = DBController::getProduct($clientId, $packageId);
    if (is_null($product) || empty($product->username)) {
        $product = DBController::getActiveProduct($clientId, $packageId);
    }
    if (is_null($product) || empty($product->username)) {
        (new JsonResponse(['status' => 'error', 'message' => 'No storage account found'], 200))->send();
        exit;
    }

    $user = DBController::getUser($product->username);
    if (is_null($user)) {
        (new JsonResponse(['status' => 'error', 'message' => 'User not found'], 200))->send();
        exit;
    }

    // Get user and tenant IDs
    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id],
    ], ['id'])->pluck('id')->toArray();
    $userIds = array_merge([$user->id], $tenants);

    // Verify bucket exists and belongs to client's user IDs
    $bucketExists = Capsule::table('s3_buckets')
        ->where('name', $bucket)
        ->whereIn('user_id', $userIds)
        ->where('is_active', '1')
        ->exists();

    // #region agent log
    cloudnasCreateMountDebugLog('bucket_lookup', [
        'client_id' => $clientId,
        'package_id' => $packageId,
        'storage_username' => (string) ($product->username ?? ''),
        'user_ids' => $userIds,
        'bucket_exists' => $bucketExists,
    ], 'H2');
    // #endregion

    if (!$bucketExists) {
        (new JsonResponse(['status' => 'error', 'message' => 'Bucket not found or access denied'], 200))->send();
        exit;
    }

    // Prepare mount data
    $prefix = trim($input['prefix'] ?? '');
    $readOnly = !empty($input['read_only']);
    $persistent = isset($input['persistent']) ? !empty($input['persistent']) : true;
    $enableCache = isset($input['enable_cache']) ? !empty($input['enable_cache']) : true;
    $cacheMode = $enableCache ? ($input['cache_mode'] ?? 'full') : 'off';

    // Insert mount configuration
    $mountId = Capsule::table('s3_cloudnas_mounts')->insertGetId([
        'client_id' => $clientId,
        'agent_id' => (int) $agent->id,
        'bucket_name' => $bucket,
        'prefix' => $prefix,
        'drive_letter' => $driveLetter,
        'read_only' => $readOnly ? 1 : 0,
        'persistent' => $persistent ? 1 : 0,
        'cache_mode' => $cacheMode,
        'status' => 'unmounted',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // #region agent log
    cloudnasCreateMountDebugLog('create_mount_success', [
        'client_id' => $clientId,
        'mount_id' => $mountId,
        'agent_id' => (int) $agent->id,
    ], 'H3');
    // #endregion

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Mount configuration created',
        'mount_id' => $mountId,
        'agent_uuid' => $agentUuid,
    ], 200))->send();
} catch (\Throwable $e) {
    // #region agent log
    cloudnasCreateMountDebugLog('create_mount_exception', [
        'client_id' => $clientId,
        'error' => $e->getMessage(),
        'class' => get_class($e),
    ], 'H1');
    // #endregion
    error_log('cloudnas_create_mount error: ' . $e->getMessage());
    try {
        logModuleCall('cloudstorage', 'cloudnas_create_mount', [
            'client_id' => $clientId,
            'agent_uuid' => $agentUuid ?? '',
            'bucket' => $bucket ?? '',
        ], $e->getMessage(), [], []);
    } catch (\Throwable $_) {
    }
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to create mount configuration'], 200))->send();
}
exit;
