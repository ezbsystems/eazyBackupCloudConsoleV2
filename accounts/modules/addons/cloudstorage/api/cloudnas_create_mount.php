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

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

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

    // Verify bucket exists and belongs to client's user IDs
    $bucketExists = Capsule::table('s3_buckets')
        ->where('name', $bucket)
        ->whereIn('user_id', $userIds)
        ->where('is_active', '1')
        ->exists();

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
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Mount configuration created',
        'mount_id' => $mountId,
        'agent_uuid' => $agentUuid,
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_create_mount error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to create mount configuration'], 200))->send();
}
exit;

