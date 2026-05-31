<?php
/**
 * e3 Cloud Backup - Hyper-V Restore Page
 *
 * Snapshot-scoped restore: a Hyper-V backup run can contain multiple guest VMs,
 * so this page is framed around the owning JOB. It is reached via vm_id (the VM
 * the customer clicked), which we use only to resolve the job and verify
 * ownership. The template then lists snapshots (runs) for the job and lets the
 * customer pick one or more VMs (and their disks) to restore in a single run.
 */

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use Illuminate\Database\Capsule\Manager as Capsule;

$packageId = ProductConfig::e3CloudBackupPid();
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    header('Location: index.php?m=cloudstorage&page=welcome');
    exit;
}

$vmId = isset($_GET['vm_id']) ? (int) $_GET['vm_id'] : 0;

if ($vmId <= 0) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=hyperv');
    exit;
}

$errorReturn = function (string $message) use ($vmId) {
    return [
        'error' => $message,
        'job' => null,
        'entryVm' => null,
        'vms' => [],
        'vmCount' => 0,
        'snapshotCount' => 0,
        'backupPointCount' => 0,
        'latestBackup' => null,
        'vmId' => $vmId,
    ];
};

$tablesExist = Capsule::schema()->hasTable('s3_hyperv_vms')
    && Capsule::schema()->hasTable('s3_hyperv_backup_points');
if (!$tablesExist) {
    return $errorReturn('Hyper-V backup tables not initialized');
}

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$hasBackupUserIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
$hasBackupUserPublicIdCol = Capsule::schema()->hasTable('s3_backup_users')
    && Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$vmJobJoin = $hasJobIdPk ? ['v.job_id', '=', 'j.job_id'] : ['v.job_id', '=', 'j.id'];

$jobIdSelect = $hasJobIdPk ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id') : 'v.job_id as job_id';
$entrySelect = [
    'v.id',
    'v.vm_name',
    'v.generation',
    'v.is_linux',
    'v.rct_enabled',
    'j.name as job_name',
    'j.agent_uuid',
    $jobIdSelect,
];
$entrySelect[] = $hasBackupUserIdCol ? 'j.backup_user_id' : Capsule::raw('NULL as backup_user_id');
$entrySelect[] = $hasBackupUserIdCol && $hasBackupUserPublicIdCol
    ? 'bu.public_id as backup_user_public_id'
    : Capsule::raw('NULL as backup_user_public_id');

try {
    $entryQuery = Capsule::table('s3_hyperv_vms as v')
        ->join('s3_cloudbackup_jobs as j', $vmJobJoin[0], $vmJobJoin[1], $vmJobJoin[2])
        ->where('v.id', $vmId)
        ->where('j.client_id', $loggedInUserId);
    if ($hasBackupUserIdCol && Capsule::schema()->hasTable('s3_backup_users')) {
        $entryQuery->leftJoin('s3_backup_users as bu', 'j.backup_user_id', '=', 'bu.id');
    }
    $entryVm = $entryQuery->select($entrySelect)->first();

    if (!$entryVm) {
        return $errorReturn('VM not found or access denied');
    }

    // MSP authorization check.
    $jobIdForAccess = (string) ($entryVm->job_id ?? '');
    if ($hasJobIdPk && UuidBinary::isUuid($jobIdForAccess)) {
        $accessCheck = MspController::validateJobAccess($jobIdForAccess, $loggedInUserId);
        if (!$accessCheck['valid']) {
            return $errorReturn($accessCheck['message']);
        }
    }

    // All VMs in this job (for context / counts).
    $vmsQuery = Capsule::table('s3_hyperv_vms');
    if ($hasJobIdPk) {
        $vmsQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobIdForAccess)));
    } else {
        $vmsQuery->where('job_id', $entryVm->job_id);
    }
    $jobVms = $vmsQuery->orderBy('vm_name')->get(['id', 'vm_name', 'is_linux', 'generation']);

    $vmList = [];
    $vmIds = [];
    foreach ($jobVms as $v) {
        $vmIds[] = (int) $v->id;
        $vmList[] = [
            'id' => (int) $v->id,
            'vm_name' => $v->vm_name,
            'is_linux' => (bool) $v->is_linux,
            'generation' => (int) ($v->generation ?? 2),
        ];
    }

    // Job-wide counts for the summary cards.
    $runJoinCol = $hasRunIdCol ? 'r.run_id' : 'r.id';
    $backupPointCount = 0;
    $snapshotCount = 0;
    $latestBackup = null;

    if (!empty($vmIds)) {
        $bpBase = Capsule::table('s3_hyperv_backup_points as bp')
            ->join('s3_cloudbackup_runs as r', 'bp.run_id', '=', $runJoinCol)
            ->whereIn('bp.vm_id', $vmIds)
            ->whereIn('r.status', ['success', 'warning']);

        $backupPointCount = (clone $bpBase)->count();
        $snapshotCount = (clone $bpBase)->distinct()->count('bp.run_id');

        $latestRow = Capsule::table('s3_hyperv_backup_points')
            ->whereIn('vm_id', $vmIds)
            ->orderBy('created_at', 'desc')
            ->first();
        if ($latestRow) {
            $latestBackup = [
                'backup_type' => $latestRow->backup_type,
                'created_at' => $latestRow->created_at,
                'consistency_level' => $latestRow->consistency_level ?? 'Application',
            ];
        }
    }
} catch (\Throwable $e) {
    return $errorReturn('Error loading VM data: ' . $e->getMessage());
}

$backupUserRouteId = trim((string) ($entryVm->backup_user_public_id ?? ''));
if ($backupUserRouteId === '' && !empty($entryVm->backup_user_id)) {
    $backupUserRouteId = (string) ((int) $entryVm->backup_user_id);
}

return [
    'job' => [
        'id' => $hasJobIdPk ? (string) ($entryVm->job_id ?? '') : (int) $entryVm->job_id,
        'name' => $entryVm->job_name,
        'agent_uuid' => $entryVm->agent_uuid,
        'backup_user_route_id' => $backupUserRouteId,
    ],
    'entryVm' => [
        'id' => (int) $entryVm->id,
        'vm_name' => $entryVm->vm_name,
        'generation' => (int) ($entryVm->generation ?? 2),
        'rct_enabled' => (bool) $entryVm->rct_enabled,
    ],
    'vms' => $vmList,
    'vmCount' => count($vmList),
    'snapshotCount' => $snapshotCount,
    'backupPointCount' => $backupPointCount,
    'latestBackup' => $latestBackup,
    'vmId' => $vmId,
];
