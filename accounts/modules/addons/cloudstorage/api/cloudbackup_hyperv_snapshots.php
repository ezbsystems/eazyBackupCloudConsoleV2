<?php
/**
 * Hyper-V Snapshots API
 *
 * Lists restore-able snapshots for a Hyper-V job, GROUPED BY backup run.
 *
 * A single Hyper-V backup run can capture multiple guest VMs, producing one
 * s3_hyperv_backup_points row per VM. The legacy backup-points endpoint is
 * VM-scoped, which made the restore UI only able to show a single VM. This
 * endpoint resolves the owning job from a vm_id (or job_id) and returns each
 * run as one "snapshot" containing every VM (and its disks) captured in that
 * run, so the UI can let the customer restore one or more VMs at once.
 *
 * Accepts: vm_id (resolves its job) OR job_id (UUID); optional limit/offset.
 * Returns: { status, job, vms[], snapshots[] } where each snapshot has a vms[]
 * array of restorable VM backup points.
 */

ob_start();

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/UuidBinary.php';

if (!defined("WHMCS")) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'fail', 'message' => 'This file cannot be accessed directly']);
    exit;
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function respond(array $data, int $httpCode = 200): void
{
    ob_end_clean();
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        respond(['status' => 'fail', 'message' => 'Session timeout.']);
    }

    $clientId = $ca->getUserID();
    $vmId = isset($_GET['vm_id']) ? (int) $_GET['vm_id'] : 0;
    $jobIdRaw = isset($_GET['job_id']) ? trim((string) $_GET['job_id']) : '';
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    if ($limit <= 0 || $limit > 200) {
        $limit = 50;
    }
    if ($offset < 0) {
        $offset = 0;
    }

    if ($vmId <= 0 && $jobIdRaw === '') {
        respond(['status' => 'fail', 'message' => 'vm_id or job_id is required']);
    }

    if (!Capsule::schema()->hasTable('s3_hyperv_vms') || !Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
        respond(['status' => 'fail', 'message' => 'Hyper-V backup tables not initialized']);
    }

    $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
    $vmJobJoin = $hasJobIdPk ? 'j.job_id' : 'j.id';
    $runJoinCol = $hasRunIdPk ? 'r.run_id' : 'r.id';
    $jobIdSelect = $hasJobIdPk ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id') : 'j.id as job_id';

    // Resolve the owning job (and verify ownership) from vm_id or job_id.
    $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')->where('j.client_id', $clientId);
    if ($vmId > 0) {
        $jobQuery->join('s3_hyperv_vms as v', 'v.job_id', '=', $vmJobJoin)
            ->where('v.id', $vmId);
    } else {
        if ($hasJobIdPk) {
            if (!UuidBinary::isUuid($jobIdRaw)) {
                respond(['status' => 'fail', 'message' => 'job_id must be a valid UUID']);
            }
            $jobQuery->whereRaw('j.job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobIdRaw)));
        } else {
            $jobQuery->where('j.id', (int) $jobIdRaw);
        }
    }
    $job = $jobQuery
        ->select($jobIdSelect, 'j.name as job_name', 'j.agent_uuid')
        ->first();

    if (!$job) {
        respond(['status' => 'fail', 'message' => 'Job not found or access denied']);
    }

    // MSP tenant authorization check.
    $accessCheck = MspController::validateJobAccess($job->job_id, $clientId);
    if (!$accessCheck['valid']) {
        respond(['status' => 'fail', 'message' => $accessCheck['message']]);
    }

    // All VMs that belong to this job.
    $vmsQuery = Capsule::table('s3_hyperv_vms');
    if ($hasJobIdPk) {
        $vmsQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize((string) $job->job_id)));
    } else {
        $vmsQuery->where('job_id', $job->job_id);
    }
    $vms = $vmsQuery
        ->orderBy('vm_name')
        ->get(['id', 'vm_name', 'vm_guid', 'generation', 'is_linux', 'rct_enabled', 'backup_enabled']);

    $vmList = [];
    $vmIds = [];
    foreach ($vms as $vm) {
        $vmIds[] = (int) $vm->id;
        $vmList[] = [
            'id' => (int) $vm->id,
            'vm_name' => $vm->vm_name,
            'vm_guid' => $vm->vm_guid,
            'generation' => (int) ($vm->generation ?? 2),
            'is_linux' => (bool) $vm->is_linux,
            'rct_enabled' => (bool) $vm->rct_enabled,
            'backup_enabled' => (bool) $vm->backup_enabled,
        ];
    }

    if (empty($vmIds)) {
        respond([
            'status' => 'success',
            'job' => ['id' => $job->job_id, 'name' => $job->job_name, 'agent_uuid' => $job->agent_uuid],
            'vms' => [],
            'snapshots' => [],
            'total' => 0,
        ]);
    }

    // All backup points across this job's VMs, joined to their run so we can
    // group by run (snapshot) and filter to successful runs only. run_id is
    // BINARY(16) -> emit BIN_TO_UUID so the JSON payload is UTF-8 safe.
    $backupPoints = Capsule::table('s3_hyperv_backup_points as bp')
        ->join('s3_cloudbackup_runs as r', 'bp.run_id', '=', $runJoinCol)
        ->whereIn('bp.vm_id', $vmIds)
        ->whereIn('r.status', ['success', 'warning'])
        ->orderBy('bp.created_at', 'desc')
        ->select(
            'bp.id',
            Capsule::raw('BIN_TO_UUID(bp.run_id) as run_id'),
            'bp.vm_id',
            'bp.backup_type',
            'bp.manifest_id',
            'bp.parent_backup_id',
            'bp.disk_manifests',
            'bp.total_size_bytes',
            'bp.changed_size_bytes',
            'bp.duration_seconds',
            'bp.consistency_level',
            'bp.created_at',
            'bp.has_warnings',
            'bp.warning_code',
            'r.status as run_status',
            'r.started_at as run_started_at',
            'r.finished_at as run_finished_at'
        )
        ->get();

    $vmNameById = [];
    $vmGuidById = [];
    foreach ($vmList as $v) {
        $vmNameById[$v['id']] = $v['vm_name'];
        $vmGuidById[$v['id']] = $v['vm_guid'];
    }

    // Group backup points by run (snapshot).
    $snapshotsByRun = [];
    foreach ($backupPoints as $bp) {
        $diskManifests = [];
        if (!empty($bp->disk_manifests)) {
            $decoded = json_decode($bp->disk_manifests, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $diskManifests = $decoded;
            }
        }

        $restoreChainLength = 1;
        if ($bp->backup_type === 'Incremental' && $bp->parent_backup_id) {
            $restoreChainLength = count(calculateSnapshotRestoreChain((int) $bp->vm_id, (int) $bp->id));
        }

        $vmEntry = [
            'backup_point_id' => (int) $bp->id,
            'vm_id' => (int) $bp->vm_id,
            'vm_name' => $vmNameById[(int) $bp->vm_id] ?? ('VM ' . (int) $bp->vm_id),
            'vm_guid' => $vmGuidById[(int) $bp->vm_id] ?? null,
            'backup_type' => $bp->backup_type,
            'manifest_id' => $bp->manifest_id,
            'disk_manifests' => $diskManifests,
            'disk_count' => count($diskManifests),
            'total_size_bytes' => $bp->total_size_bytes ? (int) $bp->total_size_bytes : null,
            'consistency_level' => $bp->consistency_level ?? 'Application',
            'has_warnings' => (bool) $bp->has_warnings,
            'warning_code' => $bp->warning_code,
            'restore_chain_length' => $restoreChainLength,
            'is_restorable' => $restoreChainLength > 0 && count($diskManifests) > 0,
        ];

        $runKey = (string) $bp->run_id;
        if (!isset($snapshotsByRun[$runKey])) {
            $snapshotsByRun[$runKey] = [
                'run_id' => $runKey,
                'created_at' => $bp->created_at,
                'run_started_at' => $bp->run_started_at,
                'run_finished_at' => $bp->run_finished_at,
                'run_status' => $bp->run_status,
                'backup_type' => $bp->backup_type,
                'consistency_level' => $vmEntry['consistency_level'],
                'total_size_bytes' => 0,
                'vm_count' => 0,
                'disk_count' => 0,
                'vms' => [],
            ];
        }

        $snapshotsByRun[$runKey]['vms'][] = $vmEntry;
        $snapshotsByRun[$runKey]['vm_count']++;
        $snapshotsByRun[$runKey]['disk_count'] += $vmEntry['disk_count'];
        $snapshotsByRun[$runKey]['total_size_bytes'] += (int) ($vmEntry['total_size_bytes'] ?? 0);
        // A snapshot is "Incremental" only if every VM in it is incremental;
        // otherwise treat it as Full for the simpler-recovery hint.
        if ($bp->backup_type === 'Full') {
            $snapshotsByRun[$runKey]['backup_type'] = 'Full';
        }
    }

    // Preserve newest-first ordering (backupPoints were ordered desc) and paginate.
    $snapshots = array_values($snapshotsByRun);
    $total = count($snapshots);
    $snapshots = array_slice($snapshots, $offset, $limit);

    respond([
        'status' => 'success',
        'job' => [
            'id' => $job->job_id,
            'name' => $job->job_name,
            'agent_uuid' => $job->agent_uuid,
        ],
        'vms' => $vmList,
        'snapshots' => $snapshots,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Walk the parent chain of an incremental backup point back to its base full
 * backup. Returns an ordered list of manifest IDs (base full -> target), or an
 * empty array if the chain is broken.
 */
function calculateSnapshotRestoreChain(int $vmId, int $targetBackupPointId): array
{
    $chain = [];
    $visited = [];
    $currentId = $targetBackupPointId;

    while ($currentId !== null && !isset($visited[$currentId])) {
        $visited[$currentId] = true;

        $bp = Capsule::table('s3_hyperv_backup_points')
            ->where('id', $currentId)
            ->where('vm_id', $vmId)
            ->select('id', 'manifest_id', 'backup_type', 'parent_backup_id')
            ->first();

        if (!$bp) {
            return [];
        }

        array_unshift($chain, $bp->manifest_id);

        if ($bp->backup_type === 'Full') {
            return $chain;
        }

        $currentId = $bp->parent_backup_id ? (int) $bp->parent_backup_id : null;
    }

    return [];
}
