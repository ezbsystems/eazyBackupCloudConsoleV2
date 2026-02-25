<?php
/**
 * Cloud NAS - Mount Drive
 * Sends a mount command to the agent for a configured mount
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$mountId = intval($input['mount_id'] ?? 0);

if ($mountId <= 0) {
    (new JsonResponse(['status' => 'error', 'message' => 'Mount ID is required'], 200))->send();
    exit;
}

try {
    // Get mount configuration
    $mount = Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->where('client_id', $clientId)
        ->first();

    if (!$mount) {
        (new JsonResponse(['status' => 'error', 'message' => 'Mount configuration not found'], 200))->send();
        exit;
    }

    if ($mount->status === 'mounted') {
        (new JsonResponse(['status' => 'error', 'message' => 'Drive is already mounted'], 200))->send();
        exit;
    }
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', (int) $mount->agent_id)
        ->where('client_id', $clientId)
        ->first(['agent_uuid']);
    if (!$agent || trim((string) ($agent->agent_uuid ?? '')) === '') {
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found for mount'], 200))->send();
        exit;
    }
    $agentUuid = trim((string) $agent->agent_uuid);

    // Get user's S3 user account
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($clientId, $packageId);
    if (is_null($product) || is_null($product->username)) {
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
        ['parent_id', '=', $user->id]
    ], ['id'])->pluck('id')->toArray();
    $userIds = array_merge([$user->id], $tenants);

    // Get bucket - find which user owns it
    $bucket = Capsule::table('s3_buckets')
        ->where('name', $mount->bucket_name)
        ->whereIn('user_id', $userIds)
        ->where('is_active', '1')
        ->first();

    if (!$bucket) {
        (new JsonResponse(['status' => 'error', 'message' => 'Bucket not found'], 200))->send();
        exit;
    }

    // Get access keys for the bucket owner
    $accessKeys = Capsule::table('s3_user_access_keys')
        ->where('user_id', $bucket->user_id)
        ->first();

    if (!$accessKeys) {
        (new JsonResponse(['status' => 'error', 'message' => 'No access keys found for bucket owner'], 200))->send();
        exit;
    }

    // Get encryption key from module settings
    $encryptionKey = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'encryption_key')
        ->value('value');

    // Decrypt the keys using HelperController
    $accessKey = HelperController::decryptKey($accessKeys->access_key, $encryptionKey);
    $secretKey = HelperController::decryptKey($accessKeys->secret_key, $encryptionKey);

    if (!$accessKey || !$secretKey) {
        (new JsonResponse(['status' => 'error', 'message' => 'Failed to decrypt access credentials'], 200))->send();
        exit;
    }

    // Get S3 endpoint from settings
    $s3Endpoint = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 's3_endpoint')
        ->value('value') ?: 'https://s3.eazybackup.ca';

    // Queue mount command for agent
    $commandPayload = [
        'run_id' => null, // No associated run (NULL to avoid FK constraint)
        'type' => 'nas_mount',
        'payload_json' => json_encode([
            'mount_id' => $mountId,
            'bucket' => $mount->bucket_name,
            'prefix' => $mount->prefix,
            'drive_letter' => $mount->drive_letter,
            'read_only' => (bool)$mount->read_only,
            'cache_mode' => $mount->cache_mode,
            'endpoint' => $s3Endpoint,
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'region' => 'us-east-1'
        ]),
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()')
    ];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
        $commandPayload['agent_uuid'] = $agentUuid;
    } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
        $commandPayload['agent_id'] = (int) $mount->agent_id;
    }
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($commandPayload);

    // Update mount status to mounting
    Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->update([
            'status' => 'mounting',
            'error' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Mount command queued',
        'command_id' => $commandId
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_mount error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to send mount command'], 200))->send();
}
exit;

