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
    $mounts = Capsule::table('s3_cloudnas_mounts as m')
        ->leftJoin('s3_cloudbackup_agents as a', 'm.agent_id', '=', 'a.id')
        ->where('m.client_id', $clientId)
        ->orderBy('m.created_at', 'desc')
        ->get([
            'm.id',
            'm.client_id',
            'a.agent_uuid',
            'm.bucket_name',
            'm.prefix',
            'm.drive_letter',
            'm.read_only',
            'm.persistent',
            'm.cache_mode',
            'm.status',
            'm.error',
            'm.last_mounted_at',
            'm.created_at',
            'm.updated_at'
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

