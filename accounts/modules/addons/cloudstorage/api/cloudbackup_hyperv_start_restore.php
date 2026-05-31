<?php
/**
 * Hyper-V Start Restore API
 * 
 * Initiates a Hyper-V disk restore from a backup point.
 * Creates a restore run for tracking progress and queues the hyperv_restore command.
 * 
 * Flow:
 * 1. Validates backup point ownership and existence
 * 2. Creates a restore run for progress tracking
 * 3. Queues hyperv_restore command with disk manifests
 * 4. Returns restore_run_id for live progress redirect
 */

// Start output buffering to catch any stray output
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
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function respond(array $data, int $httpCode = 200): void
{
    // Clear any buffered output
    ob_end_clean();
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout.']);
}

$clientId = $ca->getUserID();
$backupPointId = isset($_POST['backup_point_id']) ? (int) $_POST['backup_point_id'] : 0;
$targetPath = isset($_POST['target_path']) ? trim((string) $_POST['target_path']) : '';
$diskFilterRaw = $_POST['disk_filter'] ?? '';
$vmsRaw = $_POST['vms'] ?? '';

if (empty($targetPath)) {
    respond(['status' => 'fail', 'message' => 'target_path is required']);
}

// Normalize input into a list of VM selections: each is a backup_point_id with
// an optional list of disk paths to restore (empty = all disks for that VM).
//
//   - Multi-VM (preferred): POST 'vms' is a JSON array of
//     { backup_point_id, disks: [diskPath, ...] }.
//   - Single-VM (legacy): POST 'backup_point_id' + 'disk_filter' (JSON array).
//
// In multi-VM mode 'target_path' is treated as a BASE directory; each VM is
// restored into its own subfolder beneath it.
$selections = [];

$decodeJsonArray = function ($raw) {
    if ($raw === '' || $raw === null) {
        return null;
    }
    $decoded = html_entity_decode((string) $raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $parsed = json_decode($decoded, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) ? $parsed : null;
};

$vmsParsed = $decodeJsonArray($vmsRaw);
if (is_array($vmsParsed) && !empty($vmsParsed)) {
    foreach ($vmsParsed as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $bpId = isset($entry['backup_point_id']) ? (int) $entry['backup_point_id'] : 0;
        if ($bpId <= 0) {
            continue;
        }
        $disks = [];
        if (isset($entry['disks']) && is_array($entry['disks'])) {
            foreach ($entry['disks'] as $d) {
                if (is_string($d) && $d !== '') {
                    $disks[] = $d;
                }
            }
        }
        $selections[] = ['backup_point_id' => $bpId, 'disks' => $disks];
    }
} elseif ($backupPointId > 0) {
    $diskFilter = $decodeJsonArray($diskFilterRaw) ?? [];
    $selections[] = ['backup_point_id' => $backupPointId, 'disks' => $diskFilter];
}

if (empty($selections)) {
    respond(['status' => 'fail', 'message' => 'At least one VM (backup_point_id) is required']);
}

// De-duplicate by backup_point_id (last wins).
$dedup = [];
foreach ($selections as $sel) {
    $dedup[$sel['backup_point_id']] = $sel;
}
$selections = array_values($dedup);

// Check if Hyper-V tables exist
if (!Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
    respond(['status' => 'fail', 'message' => 'Hyper-V backup tables not initialized']);
}

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$vmJobJoin = $hasJobIdPk ? ['v.job_id', '=', 'j.job_id'] : ['v.job_id', '=', 'j.id'];
$bpRunJoin = $hasRunIdCol ? ['bp.run_id', '=', 'r.run_id'] : ['bp.run_id', '=', 'r.id'];

$bpSelect = [
    'bp.*',
    'v.vm_name',
    'v.vm_guid',
    'j.name as job_name',
    'j.agent_uuid',
    'j.engine',
    'r.status as run_status',
];
$bpSelect[] = $hasJobIdPk ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id') : 'j.id as job_id';
$bpSelect[] = $hasRunIdCol ? Capsule::raw('BIN_TO_UUID(bp.run_id) as run_id_uuid') : Capsule::raw('bp.run_id as run_id_uuid');

$multiVM = count($selections) > 1;

// Validate every selected backup point and assemble the per-VM payload.
$vmPayloads = [];        // entries for the agent command 'vms' array
$estimatedSize = 0;      // aggregate across VMs
$sharedJobId = null;     // all VMs must belong to the same job
$sharedJobName = null;
$sharedAgentUuid = null;
$sharedRunIdUuid = null; // backup run that produced these points (for job context)
$sharedRunIdRaw = null;
$primaryManifestId = null;
$vmNamesForLabel = [];

foreach ($selections as $sel) {
    $bpId = (int) $sel['backup_point_id'];

    $backupPoint = Capsule::table('s3_hyperv_backup_points as bp')
        ->join('s3_hyperv_vms as v', 'bp.vm_id', '=', 'v.id')
        ->join('s3_cloudbackup_jobs as j', $vmJobJoin[0], $vmJobJoin[1], $vmJobJoin[2])
        ->join('s3_cloudbackup_runs as r', $bpRunJoin[0], $bpRunJoin[1], $bpRunJoin[2])
        ->where('bp.id', $bpId)
        ->where('j.client_id', $clientId)
        ->select($bpSelect)
        ->first();

    if (!$backupPoint) {
        respond(['status' => 'fail', 'message' => "Backup point {$bpId} not found or access denied"]);
    }

    if (!in_array($backupPoint->run_status, ['success', 'warning'], true)) {
        respond(['status' => 'fail', 'message' => 'Cannot restore from backup point ' . $bpId . ' (run status: ' . $backupPoint->run_status . ')']);
    }

    // MSP tenant authorization check (job_id is UUID string when hasJobIdPk).
    $jobIdForAccess = (string) ($backupPoint->job_id ?? '');
    if ($hasJobIdPk && UuidBinary::isUuid($jobIdForAccess)) {
        $accessCheck = MspController::validateJobAccess($jobIdForAccess, $clientId);
        if (!$accessCheck['valid']) {
            respond(['status' => 'fail', 'message' => $accessCheck['message']]);
        }
    }

    // Enforce a single owning job across all selected VMs.
    if ($sharedJobId === null) {
        $sharedJobId = $backupPoint->job_id;
        $sharedJobName = $backupPoint->job_name;
        $sharedAgentUuid = $backupPoint->agent_uuid;
        $sharedRunIdUuid = $backupPoint->run_id_uuid ?? null;
        $sharedRunIdRaw = $backupPoint->run_id ?? null;
        $primaryManifestId = $backupPoint->manifest_id;
    } elseif ((string) $sharedJobId !== (string) $backupPoint->job_id) {
        respond(['status' => 'fail', 'message' => 'All selected VMs must belong to the same backup job']);
    }

    // Parse + filter this VM's disk manifests.
    $diskManifests = [];
    if (!empty($backupPoint->disk_manifests)) {
        $decoded = json_decode($backupPoint->disk_manifests, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $diskManifests = $decoded;
        }
    }
    if (empty($diskManifests)) {
        respond(['status' => 'fail', 'message' => 'Backup point for ' . $backupPoint->vm_name . ' has no disk manifests']);
    }

    $disksToRestore = $diskManifests;
    if (!empty($sel['disks'])) {
        $wanted = $sel['disks'];
        $disksToRestore = array_filter($diskManifests, function ($diskPath) use ($wanted) {
            return in_array($diskPath, $wanted, true);
        }, ARRAY_FILTER_USE_KEY);
    }
    if (empty($disksToRestore)) {
        respond(['status' => 'fail', 'message' => 'No disks selected for ' . $backupPoint->vm_name]);
    }

    // Restore chain (incremental walks back to the base full).
    if ($backupPoint->backup_type === 'Incremental') {
        $restoreChain = calculateRestoreChainForPoint((int) $backupPoint->vm_id, $bpId);
        if (empty($restoreChain)) {
            respond(['status' => 'fail', 'message' => 'Cannot restore incremental backup for ' . $backupPoint->vm_name . ': restore chain is broken. Please select a Full backup point.']);
        }
    } else {
        $restoreChain = [$backupPoint->manifest_id];
    }

    if ($backupPoint->total_size_bytes) {
        $estimatedSize += (int) $backupPoint->total_size_bytes;
    }
    $vmNamesForLabel[] = $backupPoint->vm_name;

    $vmPayloads[] = [
        'backup_point_id' => $bpId,
        'vm_name' => $backupPoint->vm_name,
        'vm_guid' => $backupPoint->vm_guid,
        // Multi-VM restores land each VM in its own subfolder; single-VM keeps
        // the base path (empty subfolder => agent restores directly to base).
        'subfolder' => $multiVM ? sanitizeHypervSubfolder($backupPoint->vm_name) : '',
        'disk_manifests' => $disksToRestore,
        'restore_chain' => $restoreChain,
        'backup_type' => $backupPoint->backup_type,
    ];
}

$totalDisks = 0;
foreach ($vmPayloads as $vp) {
    $totalDisks += count($vp['disk_manifests']);
}

// Check if commands table exists
if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Restore commands not supported on this installation']);
}

try {
    $restoreRunId = null;
    $restoreRunUuid = null;
    $commandId = null;

    $hasCommandRunIdBinary = Capsule::schema()->hasTable('s3_cloudbackup_run_commands')
        && stripos((string) (Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_commands WHERE Field = 'run_id'")->Type ?? ''), 'binary') !== false;
    $hasEventsRunIdBinary = Capsule::schema()->hasTable('s3_cloudbackup_run_events')
        && stripos((string) (Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_events WHERE Field = 'run_id'")->Type ?? ''), 'binary') !== false;

    $vmCount = count($vmPayloads);
    $vmNamesLabel = implode(', ', $vmNamesForLabel);
    $backupTypeAgg = 'Full';
    foreach ($vmPayloads as $vp) {
        if ($vp['backup_type'] === 'Incremental') {
            $backupTypeAgg = 'Incremental';
            break;
        }
    }

    Capsule::connection()->transaction(function () use (
        $targetPath, $estimatedSize, $vmPayloads, $vmCount, $vmNamesLabel, $backupTypeAgg, $totalDisks,
        $sharedJobId, $sharedAgentUuid, $sharedRunIdUuid, $sharedRunIdRaw, $primaryManifestId,
        $hasJobIdPk, $hasRunIdCol, $hasCommandRunIdBinary, $hasEventsRunIdBinary,
        &$restoreRunId, &$restoreRunUuid, &$commandId
    ) {
        // Determine which columns exist for the runs table
        $hasAgentIdRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id');
        $hasEngineColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        $hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');

        $restoreRunUuidGen = ($hasRunIdCol) ? CloudBackupController::generateUuid() : null;

        // Create a single restore run for tracking progress across all VMs.
        $jobIdForRun = $hasJobIdPk && UuidBinary::isUuid((string) ($sharedJobId ?? ''))
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize((string) $sharedJobId)))
            : $sharedJobId;

        $runData = [
            'job_id' => $jobIdForRun,
            'status' => 'queued',
            'created_at' => Capsule::raw('NOW()'),
            'cancel_requested' => 0,
        ];

        if ($hasRunIdCol && $restoreRunUuidGen) {
            $runData['run_id'] = Capsule::raw(UuidBinary::toDbExpr($restoreRunUuidGen));
        }
        $hasAgentUuidRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_uuid');
        if ($hasAgentUuidRuns && !empty($sharedAgentUuid)) {
            $runData['agent_uuid'] = $sharedAgentUuid;
        }
        if ($hasEngineColumn) {
            $runData['engine'] = 'hyperv';
        }
        if ($hasRunTypeColumn) {
            $runData['run_type'] = 'hyperv_restore';
        }
        if ($hasRunUuidColumn) {
            $runData['run_uuid'] = $restoreRunUuidGen ?? CloudBackupController::generateUuid();
        }

        $restoreMetadata = [
            'type' => 'hyperv_restore',
            'vm_name' => $vmNamesLabel,
            'vm_count' => $vmCount,
            'vms' => array_map(function ($vp) {
                return [
                    'vm_name' => $vp['vm_name'],
                    'backup_point_id' => $vp['backup_point_id'],
                    'subfolder' => $vp['subfolder'],
                    'disk_count' => count($vp['disk_manifests']),
                ];
            }, $vmPayloads),
            'backup_type' => $backupTypeAgg,
            'target_path' => $targetPath,
            'disk_count' => $totalDisks,
            'estimated_size_bytes' => $estimatedSize,
        ];
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $runData['stats_json'] = json_encode($restoreMetadata);
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref')) {
            $runData['log_ref'] = $primaryManifestId;
        }

        // Insert the restore run (no insertGetId when UUID schema)
        if ($hasRunIdCol && $restoreRunUuidGen) {
            Capsule::table('s3_cloudbackup_runs')->insert($runData);
            $restoreRunId = $restoreRunUuidGen;
            $restoreRunUuid = $restoreRunUuidGen;
        } else {
            $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
            $restoreRunUuid = $runData['run_uuid'] ?? null;
        }

        // Command run_id: the backup run that produced these points, used for
        // job context (repo creds). All selected VMs share the same backup run.
        $backupRunIdUuid = (string) ($sharedRunIdUuid ?? '');
        $cmdRunIdValue = ($hasCommandRunIdBinary && UuidBinary::isUuid($backupRunIdUuid))
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($backupRunIdUuid)))
            : $sharedRunIdRaw;

        // Single command carrying all VMs. The agent restores each VM into its
        // own subfolder under target_path and completes the run once.
        $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
            'run_id' => $cmdRunIdValue,
            'type' => 'hyperv_restore',
            'payload_json' => json_encode([
                'target_path' => $targetPath,
                'backup_type' => $backupTypeAgg,
                'restore_run_id' => $restoreRunId,
                'restore_run_uuid' => $restoreRunUuid,
                'vms' => array_map(function ($vp) {
                    return [
                        'backup_point_id' => $vp['backup_point_id'],
                        'vm_name' => $vp['vm_name'],
                        'vm_guid' => $vp['vm_guid'],
                        'subfolder' => $vp['subfolder'],
                        'disk_manifests' => $vp['disk_manifests'],
                        'restore_chain' => $vp['restore_chain'],
                        'backup_type' => $vp['backup_type'],
                    ];
                }, $vmPayloads),
            ]),
            'status' => 'pending',
            'created_at' => Capsule::raw('NOW()'),
        ]);

        $eventRunIdValue = ($hasEventsRunIdBinary && $restoreRunUuid && UuidBinary::isUuid($restoreRunUuid))
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($restoreRunUuid)))
            : $restoreRunId;

        if (Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            Capsule::table('s3_cloudbackup_run_events')->insert([
                'run_id' => $eventRunIdValue,
                'ts' => Capsule::raw('NOW()'),
                'type' => 'info',
                'level' => 'info',
                'code' => 'HYPERV_RESTORE_QUEUED',
                'message_id' => 'HYPERV_RESTORE_STARTING',
                'params_json' => json_encode([
                    'vm_name' => $vmNamesLabel,
                    'vm_count' => $vmCount,
                    'backup_type' => $backupTypeAgg,
                    'target_path' => $targetPath,
                    'disk_count' => $totalDisks,
                ]),
            ]);
        }
    });

    respond([
        'status' => 'success',
        'message' => 'Hyper-V restore started',
        'restore_run_id' => $restoreRunId,
        'restore_run_uuid' => $restoreRunUuid,
        'command_id' => $commandId,
        'job_id' => $hasJobIdPk ? (string) ($sharedJobId ?? '') : (int) $sharedJobId,
        'vm_name' => $vmNamesLabel,
        'vm_count' => $vmCount,
        'disks_to_restore' => $totalDisks,
        'estimated_size_bytes' => $estimatedSize,
        'backup_type' => $backupTypeAgg,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_hyperv_start_restore', [
        'backup_point_id' => $backupPointId,
        'target_path' => $targetPath,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Failed to start Hyper-V restore: ' . $e->getMessage()]);
}

/**
 * Calculate the restore chain for an incremental backup point.
 * Returns an ordered list of manifest IDs from base full to target incremental.
 */
function calculateRestoreChainForPoint(int $vmId, int $targetBackupPointId): array
{
    $chain = [];
    $visited = [];
    $currentId = $targetBackupPointId;
    
    // Walk backwards through parent chain to find the base full backup
    while ($currentId !== null && !isset($visited[$currentId])) {
        $visited[$currentId] = true;
        
        $bp = Capsule::table('s3_hyperv_backup_points')
            ->where('id', $currentId)
            ->where('vm_id', $vmId)
            ->select('id', 'manifest_id', 'backup_type', 'parent_backup_id', 'disk_manifests')
            ->first();
        
        if (!$bp) {
            // Chain is broken - return empty
            return [];
        }
        
        // Prepend to chain (we're walking backwards)
        array_unshift($chain, [
            'backup_point_id' => (int) $bp->id,
            'manifest_id' => $bp->manifest_id,
            'backup_type' => $bp->backup_type,
            'disk_manifests' => json_decode($bp->disk_manifests ?? '{}', true),
        ]);
        
        if ($bp->backup_type === 'Full') {
            // Found the base - chain is complete
            return $chain;
        }
        
        // Move to parent
        $currentId = $bp->parent_backup_id ? (int) $bp->parent_backup_id : null;
    }
    
    // If we get here without finding a Full backup, chain is incomplete
    return [];
}

/**
 * Produce a filesystem-safe subfolder name from a VM name for multi-VM
 * restores. Mirrors the agent-side sanitizer (sanitizeSubfolderName) so the
 * server-reported target subfolders match what the agent writes.
 */
function sanitizeHypervSubfolder(string $name): string
{
    // Replace Windows-reserved characters and control chars with underscores.
    $cleaned = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/', '_', $name);
    $cleaned = trim((string) $cleaned);
    $cleaned = trim($cleaned, '.');
    return $cleaned === '' ? 'vm' : $cleaned;
}

