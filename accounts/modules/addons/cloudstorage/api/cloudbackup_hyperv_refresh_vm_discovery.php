<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function hypervRefreshRespond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function hypervRefreshUpdateVmDisks(int $vmId, array $disks): void
{
    if (!Capsule::schema()->hasTable('s3_hyperv_vm_disks')) {
        return;
    }

    $existingDisks = Capsule::table('s3_hyperv_vm_disks')
        ->where('vm_id', $vmId)
        ->pluck('id', 'disk_path')
        ->toArray();

    $seenPaths = [];

    foreach ($disks as $disk) {
        if (!is_array($disk)) {
            continue;
        }

        $diskPath = trim((string) ($disk['path'] ?? ''));
        if ($diskPath === '') {
            continue;
        }

        $seenPaths[] = $diskPath;
        $diskRecord = [
            'disk_path' => $diskPath,
            'controller_type' => (string) ($disk['controller_type'] ?? 'SCSI'),
            'controller_number' => (int) ($disk['controller_number'] ?? 0),
            'controller_location' => (int) ($disk['controller_location'] ?? 0),
            'vhd_format' => (string) ($disk['vhd_format'] ?? 'VHDX'),
            'size_bytes' => (int) ($disk['size_bytes'] ?? 0),
            'used_bytes' => (int) ($disk['used_bytes'] ?? 0),
            'rct_enabled' => !empty($disk['rct_enabled']) ? 1 : 0,
            'current_rct_id' => isset($disk['rct_id']) ? (string) $disk['rct_id'] : null,
            'updated_at' => Capsule::raw('NOW()'),
        ];

        if (isset($existingDisks[$diskPath])) {
            Capsule::table('s3_hyperv_vm_disks')
                ->where('id', $existingDisks[$diskPath])
                ->update($diskRecord);
            continue;
        }

        $diskRecord['vm_id'] = $vmId;
        $diskRecord['created_at'] = Capsule::raw('NOW()');
        Capsule::table('s3_hyperv_vm_disks')->insert($diskRecord);
    }

    if (!empty($seenPaths)) {
        Capsule::table('s3_hyperv_vm_disks')
            ->where('vm_id', $vmId)
            ->whereNotIn('disk_path', $seenPaths)
            ->delete();
    }
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    hypervRefreshRespond(['status' => 'fail', 'message' => 'Not authenticated'], 401);
}

$clientId = $ca->getUserID();
$jobId = trim((string) ($_POST['job_id'] ?? ''));
$vmsRaw = $_POST['vms_json'] ?? '';
$vms = json_decode((string) $vmsRaw, true);

if (!UuidBinary::isUuid($jobId)) {
    hypervRefreshRespond([
        'status' => 'fail',
        'code' => 'invalid_identifier_format',
        'message' => 'job_id must be a valid UUID.',
    ], 400);
}

if (!is_array($vms)) {
    hypervRefreshRespond(['status' => 'fail', 'message' => 'vms_json must be a JSON array'], 400);
}

if (!Capsule::schema()->hasTable('s3_hyperv_vms')) {
    hypervRefreshRespond(['status' => 'fail', 'message' => 'Hyper-V VM registry is not available'], 500);
}

$jobIdDbExpr = UuidBinary::toDbExpr(UuidBinary::normalize($jobId));
$job = Capsule::table('s3_cloudbackup_jobs')
    ->where('client_id', $clientId)
    ->where('engine', 'hyperv')
    ->where('status', '!=', 'deleted')
    ->whereRaw('job_id = ' . $jobIdDbExpr)
    ->first(['name']);

if (!$job) {
    hypervRefreshRespond(['status' => 'fail', 'message' => 'Job not found or access denied'], 404);
}

try {
    $created = 0;
    $updated = 0;
    $vmIds = [];

    foreach ($vms as $vmData) {
        if (!is_array($vmData)) {
            continue;
        }

        $vmGuid = trim((string) ($vmData['id'] ?? $vmData['vm_guid'] ?? $vmData['vm_id'] ?? ''));
        $vmName = trim((string) ($vmData['name'] ?? $vmData['vm_name'] ?? $vmGuid));
        if ($vmGuid === '' || $vmName === '') {
            continue;
        }

        $vmRecord = [
            'vm_name' => $vmName,
            'vm_guid' => $vmGuid,
            'generation' => (int) ($vmData['generation'] ?? 2),
            'is_linux' => !empty($vmData['is_linux']) ? 1 : 0,
            'integration_services' => array_key_exists('integration_services', $vmData)
                ? (!empty($vmData['integration_services']) ? 1 : 0)
                : 1,
            'rct_enabled' => !empty($vmData['rct_enabled']) ? 1 : 0,
            'updated_at' => Capsule::raw('NOW()'),
        ];

        $existingVm = Capsule::table('s3_hyperv_vms')
            ->whereRaw('job_id = ' . $jobIdDbExpr)
            ->where('vm_guid', $vmGuid)
            ->first(['id']);

        if ($existingVm) {
            Capsule::table('s3_hyperv_vms')
                ->where('id', $existingVm->id)
                ->update($vmRecord);
            $vmIds[] = (int) $existingVm->id;
            $updated++;
            hypervRefreshUpdateVmDisks((int) $existingVm->id, $vmData['disks'] ?? []);
            continue;
        }

        $vmRecord['job_id'] = Capsule::raw($jobIdDbExpr);
        $vmRecord['backup_enabled'] = 1;
        $vmRecord['created_at'] = Capsule::raw('NOW()');

        $newVmId = Capsule::table('s3_hyperv_vms')->insertGetId($vmRecord);
        $vmIds[] = (int) $newVmId;
        $created++;
        hypervRefreshUpdateVmDisks((int) $newVmId, $vmData['disks'] ?? []);
    }

    logModuleCall('cloudstorage', 'cloudbackup_hyperv_refresh_vm_discovery', [
        'client_id' => $clientId,
        'job_id' => $jobId,
        'job_name' => (string) ($job->name ?? ''),
        'vm_count' => count($vms),
    ], [
        'created' => $created,
        'updated' => $updated,
    ]);

    hypervRefreshRespond([
        'status' => 'success',
        'message' => 'VM discovery refreshed successfully.',
        'created' => $created,
        'updated' => $updated,
        'vm_ids' => $vmIds,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_hyperv_refresh_vm_discovery_error', [
        'client_id' => $clientId,
        'job_id' => $jobId,
    ], $e->getMessage());
    hypervRefreshRespond(['status' => 'fail', 'message' => 'Discovery refresh failed'], 500);
}
