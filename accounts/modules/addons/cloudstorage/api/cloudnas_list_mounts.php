<?php
/**
 * Cloud NAS - List Mount Configurations
 * Returns all mount configurations for the current client
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

try {
    // Get all mount configurations for this client
    $mounts = Capsule::table('s3_cloudnas_mounts')
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->get([
            'id',
            'client_id',
            'agent_id',
            'bucket_name',
            'prefix',
            'drive_letter',
            'read_only',
            'persistent',
            'cache_mode',
            'status',
            'error',
            'last_mounted_at',
            'created_at',
            'updated_at'
        ]);

    // Convert boolean fields
    $mounts = $mounts->map(function ($mount) {
        $mount->read_only = (bool)$mount->read_only;
        $mount->persistent = (bool)$mount->persistent;
        return $mount;
    });

    (new JsonResponse(['status' => 'success', 'mounts' => $mounts], 200))->send();
} catch (Exception $e) {
    error_log("cloudnas_list_mounts error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to load mounts'], 200))->send();
}
exit;

