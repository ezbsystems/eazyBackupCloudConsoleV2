<?php
/**
 * Toggle Hyper-V VM Backup Status
 * 
 * Enables or disables backup for a specific VM.
 */

require_once __DIR__ . '/../../../../init.php';

use WHMCS\ClientArea;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    $response = new JsonResponse($data, $httpCode);
    $response->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Not authenticated'], 401);
}

$loggedInUserId = $ca->getUserID();

$vmId = $_POST['vm_id'] ?? null;
$enabled = ($_POST['enabled'] ?? '0') === '1';

if (!$vmId) {
    respond(['status' => 'fail', 'message' => 'vm_id is required'], 400);
}

try {
    // Verify VM belongs to a job owned by this client
    $vm = Capsule::table('s3_hyperv_vms as v')
        ->join('s3_cloudbackup_jobs as j', 'v.job_id', '=', 'j.id')
        ->where('v.id', $vmId)
        ->where('j.client_id', $loggedInUserId)
        ->select('v.id', 'v.vm_name')
        ->first();
    
    if (!$vm) {
        respond(['status' => 'fail', 'message' => 'VM not found or unauthorized'], 403);
    }
    
    // Update backup_enabled status
    Capsule::table('s3_hyperv_vms')
        ->where('id', $vmId)
        ->update([
            'backup_enabled' => $enabled,
            'updated_at' => Capsule::raw('NOW()'),
        ]);
    
    logModuleCall('cloudstorage', 'cloudbackup_hyperv_toggle_vm', [
        'client_id' => $loggedInUserId,
        'vm_id' => $vmId,
        'vm_name' => $vm->vm_name,
        'enabled' => $enabled,
    ], 'success');
    
    respond([
        'status' => 'success',
        'vm_id' => $vmId,
        'enabled' => $enabled,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_hyperv_toggle_vm_error', [
        'client_id' => $loggedInUserId,
        'vm_id' => $vmId,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Update failed'], 500);
}

