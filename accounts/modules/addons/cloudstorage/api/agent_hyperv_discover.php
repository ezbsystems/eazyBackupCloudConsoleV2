<?php
/**
 * Hyper-V VM Discovery Endpoint
 * 
 * Receives VM discovery data from the agent and updates the s3_hyperv_vms table.
 * Called by the agent when it discovers VMs on the Hyper-V host.
 */

require_once __DIR__ . '/../../../../init.php';

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

function authenticateAgent(): object
{
    $agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? ($_POST['agent_id'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentId || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$agent = authenticateAgent();
$body = getBodyJson();

$jobId = $body['job_id'] ?? null;
$vms = $body['vms'] ?? [];

if (!$jobId) {
    respond(['status' => 'fail', 'message' => 'job_id is required'], 400);
}

// Verify job belongs to agent's client
$job = Capsule::table('s3_cloudbackup_jobs')
    ->where('id', $jobId)
    ->where('client_id', $agent->client_id)
    ->first();

if (!$job) {
    respond(['status' => 'fail', 'message' => 'Job not found or unauthorized'], 403);
}

try {
    $created = 0;
    $updated = 0;
    $vmIds = [];
    
    foreach ($vms as $vmData) {
        $vmGuid = $vmData['id'] ?? null;
        $vmName = $vmData['name'] ?? '';
        
        if (!$vmGuid || !$vmName) {
            continue;
        }
        
        // Check if VM already exists for this job
        $existingVm = Capsule::table('s3_hyperv_vms')
            ->where('job_id', $jobId)
            ->where('vm_guid', $vmGuid)
            ->first();
        
        $vmRecord = [
            'vm_name' => $vmName,
            'vm_guid' => $vmGuid,
            'generation' => $vmData['generation'] ?? 2,
            'is_linux' => (bool) ($vmData['is_linux'] ?? false),
            'integration_services' => (bool) ($vmData['integration_services'] ?? true),
            'rct_enabled' => (bool) ($vmData['rct_enabled'] ?? false),
            'updated_at' => Capsule::raw('NOW()'),
        ];
        
        if ($existingVm) {
            // Update existing VM
            Capsule::table('s3_hyperv_vms')
                ->where('id', $existingVm->id)
                ->update($vmRecord);
            $vmIds[] = $existingVm->id;
            $updated++;
            
            // Update disk records
            updateVmDisks($existingVm->id, $vmData['disks'] ?? []);
        } else {
            // Create new VM
            $vmRecord['job_id'] = $jobId;
            $vmRecord['backup_enabled'] = true; // Enable by default
            $vmRecord['created_at'] = Capsule::raw('NOW()');
            
            $newVmId = Capsule::table('s3_hyperv_vms')->insertGetId($vmRecord);
            $vmIds[] = $newVmId;
            $created++;
            
            // Create disk records
            updateVmDisks($newVmId, $vmData['disks'] ?? []);
        }
    }
    
    logModuleCall('cloudstorage', 'agent_hyperv_discover', [
        'agent_id' => $agent->id,
        'job_id' => $jobId,
        'vm_count' => count($vms),
    ], [
        'created' => $created,
        'updated' => $updated,
    ]);
    
    respond([
        'status' => 'success',
        'created' => $created,
        'updated' => $updated,
        'vm_ids' => $vmIds,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_hyperv_discover_error', [
        'agent_id' => $agent->id,
        'job_id' => $jobId,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Discovery failed'], 500);
}

/**
 * Update disk records for a VM
 */
function updateVmDisks(int $vmId, array $disks): void
{
    // Get existing disk paths
    $existingDisks = Capsule::table('s3_hyperv_vm_disks')
        ->where('vm_id', $vmId)
        ->pluck('id', 'disk_path')
        ->toArray();
    
    $seenPaths = [];
    
    foreach ($disks as $disk) {
        $diskPath = $disk['path'] ?? '';
        if (!$diskPath) {
            continue;
        }
        
        $seenPaths[] = $diskPath;
        
        $diskRecord = [
            'disk_path' => $diskPath,
            'controller_type' => $disk['controller_type'] ?? 'SCSI',
            'controller_number' => $disk['controller_number'] ?? 0,
            'controller_location' => $disk['controller_location'] ?? 0,
            'vhd_format' => $disk['vhd_format'] ?? 'VHDX',
            'size_bytes' => $disk['size_bytes'] ?? 0,
            'used_bytes' => $disk['used_bytes'] ?? 0,
            'rct_enabled' => (bool) ($disk['rct_enabled'] ?? false),
            'current_rct_id' => $disk['rct_id'] ?? null,
            'updated_at' => Capsule::raw('NOW()'),
        ];
        
        if (isset($existingDisks[$diskPath])) {
            // Update existing disk
            Capsule::table('s3_hyperv_vm_disks')
                ->where('id', $existingDisks[$diskPath])
                ->update($diskRecord);
        } else {
            // Create new disk
            $diskRecord['vm_id'] = $vmId;
            $diskRecord['created_at'] = Capsule::raw('NOW()');
            Capsule::table('s3_hyperv_vm_disks')->insert($diskRecord);
        }
    }
    
    // Remove disks that no longer exist
    if (!empty($seenPaths)) {
        Capsule::table('s3_hyperv_vm_disks')
            ->where('vm_id', $vmId)
            ->whereNotIn('disk_path', $seenPaths)
            ->delete();
    }
}

