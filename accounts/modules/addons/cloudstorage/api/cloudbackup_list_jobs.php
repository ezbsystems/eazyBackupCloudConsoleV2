<?php
/**
 * Cloud Backup - List Jobs
 * Returns list of backup jobs for the current client
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
    $jobs = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->get([
            'id',
            'name',
            'source_type',
            'source_path',
            'engine',
            'dest_type',
            'dest_bucket',
            'dest_prefix',
            'status',
            'created_at',
            'updated_at'
        ]);

    (new JsonResponse(['status' => 'success', 'jobs' => $jobs], 200))->send();

} catch (Exception $e) {
    error_log("cloudbackup_list_jobs error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to load jobs'], 200))->send();
}
exit;

