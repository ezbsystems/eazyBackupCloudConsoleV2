<?php

require_once dirname(__DIR__) . '/lib/Ms365BackupBootstrap.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Database\Capsule;

require_once dirname(__DIR__) . '/lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('live');

$runIdentifier = $_GET['run_uuid'] ?? ($_GET['run_id'] ?? null);
if (!$runIdentifier) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

// Verify run ownership
$run = CloudBackupController::getRun($runIdentifier, $loggedInUserId);
if (!$run) {
    header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
    exit;
}

// Get job details
$job = CloudBackupController::getJob($run['job_id'], $loggedInUserId) ?? [];

$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$isMspClient = MspController::isMspClient($loggedInUserId);
$tenantTable = MspController::getTenantTableName();
$userIdRaw = trim((string) ($_GET['user_id'] ?? ''));
$backupUsername = '';
$backupUserRouteId = '';
$backupUserInternalId = 0;

$resolveBackupUserRow = static function (int $internalId) use ($loggedInUserId, $hasPublicIdCol, $isMspClient, $tenantTable): ?object {
    if ($internalId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
        return null;
    }
    $tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
    $mspId = MspController::getMspIdForClient($loggedInUserId);
    $tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int) ($mspId ?? 0) : (int) $loggedInUserId;

    $cols = [
        'u.id',
        'u.username',
        'u.tenant_id as storage_tenant_id',
        Capsule::raw($tenantOwnerSelect),
        't.status as tenant_status',
    ];
    if ($hasPublicIdCol) {
        $cols[] = 'u.public_id';
    }

    $row = Capsule::table('s3_backup_users as u')
        ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
        ->where('u.client_id', $loggedInUserId)
        ->where('u.id', $internalId)
        ->select($cols)
        ->first();

    if (!$row) {
        return null;
    }

    if ($isMspClient && !empty($row->storage_tenant_id)) {
        $tenantClientId = (int) ($row->tenant_owner_id ?? 0);
        $tenantStatus = strtolower((string) ($row->tenant_status ?? ''));
        if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
            return null;
        }
    } elseif (!$isMspClient && !empty($row->storage_tenant_id)) {
        return null;
    }

    return $row;
};

if ($userIdRaw !== '') {
    $tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
    $mspId = MspController::getMspIdForClient($loggedInUserId);
    $tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int) ($mspId ?? 0) : (int) $loggedInUserId;

    $userLookup = Capsule::table('s3_backup_users as u')
        ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
        ->where('u.client_id', $loggedInUserId);
    if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
        $userLookup->where('u.public_id', $userIdRaw);
    } else {
        $userLookup->where('u.id', (int) $userIdRaw);
    }
    $scopeUser = $userLookup->select([
        'u.id',
        'u.username',
        'u.tenant_id as storage_tenant_id',
        Capsule::raw($tenantOwnerSelect),
        't.status as tenant_status',
    ] + ($hasPublicIdCol ? ['u.public_id'] : []))->first();

    if ($scopeUser) {
        $allowed = true;
        if ($isMspClient && !empty($scopeUser->storage_tenant_id)) {
            $tenantClientId = (int) ($scopeUser->tenant_owner_id ?? 0);
            $tenantStatus = strtolower((string) ($scopeUser->tenant_status ?? ''));
            if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
                $allowed = false;
            }
        } elseif (!$isMspClient && !empty($scopeUser->storage_tenant_id)) {
            $allowed = false;
        }
        if ($allowed) {
            $backupUsername = (string) ($scopeUser->username ?? '');
            $backupUserInternalId = (int) $scopeUser->id;
            $backupUserRouteId = $hasPublicIdCol && !empty($scopeUser->public_id)
                ? (string) $scopeUser->public_id
                : (string) $backupUserInternalId;
        }
    }
}

if ($backupUserRouteId === '') {
    $backupUserInternalId = (int) ($job['backup_user_id'] ?? 0);
    $agentUuidForUser = $job['agent_uuid'] ?? ($run['agent_uuid'] ?? null);
    if ($backupUserInternalId <= 0 && !empty($agentUuidForUser)
        && Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id')) {
        $agentBackupUserId = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agentUuidForUser)
            ->value('backup_user_id');
        $backupUserInternalId = (int) ($agentBackupUserId ?? 0);
    }
    $backupUserRow = $resolveBackupUserRow($backupUserInternalId);
    if ($backupUserRow) {
        $backupUsername = (string) ($backupUserRow->username ?? '');
        $backupUserRouteId = $hasPublicIdCol && !empty($backupUserRow->public_id)
            ? (string) $backupUserRow->public_id
            : (string) ((int) $backupUserRow->id);
    }
}

$showUserSubnav = $backupUserRouteId !== '';

$isMs365Batch = Ms365BatchLiveService::isMs365BatchRun($run);
$ms365Workloads = [];
$sourceLabel = $isMs365Batch ? 'Microsoft 365' : null;
if ($isMs365Batch) {
    cloudstorage_load_ms365backup();
    $run = Ms365BatchLiveService::enrichRunForDisplay($run, (int) $loggedInUserId);
    try {
        $ms365Workloads = Ms365BatchLiveService::listWorkloadsForCustomer((string) ($run['run_id'] ?? ''), (int) $loggedInUserId);
    } catch (\Throwable $e) {
        $ms365Workloads = [];
    }
}

// Resolve agent display name (if available)
$agentName = null;
$agentUuid = $job['agent_uuid'] ?? ($run['agent_uuid'] ?? null);
if (!empty($agentUuid)) {
    try {
        $agentRow = Capsule::table('s3_cloudbackup_agents')
            ->where('agent_uuid', $agentUuid)
            ->first(['hostname']);
        if ($agentRow && !empty($agentRow->hostname)) {
            $agentName = $agentRow->hostname;
        }
    } catch (\Throwable $e) {
        // Best-effort only; leave agentName null
    }
}

// Detect if this is a restore run
$isRestore = false;
$isHypervRestore = false;
$restoreMetadata = null;

// Check run_type column if it exists
if (!empty($run['run_type'])) {
    if ($run['run_type'] === 'restore') {
        $isRestore = true;
    } elseif ($run['run_type'] === 'hyperv_restore') {
        $isRestore = true;
        $isHypervRestore = true;
    } elseif ($run['run_type'] === 'disk_restore') {
        $isRestore = true;
    }
}

// Also check stats_json for restore metadata
if (!empty($run['stats_json'])) {
    $statsJson = is_string($run['stats_json']) ? json_decode($run['stats_json'], true) : $run['stats_json'];
    if (json_last_error() === JSON_ERROR_NONE) {
        if (!empty($statsJson['type']) && $statsJson['type'] === 'restore') {
            $isRestore = true;
            $restoreMetadata = $statsJson;
        } elseif (!empty($statsJson['type']) && $statsJson['type'] === 'hyperv_restore') {
            $isRestore = true;
            $isHypervRestore = true;
            $restoreMetadata = $statsJson;
        } elseif (!empty($statsJson['type']) && $statsJson['type'] === 'ms365_restore') {
            $isRestore = true;
            $restoreMetadata = $statsJson;
        }
    }
}

// Resolve a customer-safe destination label. Never expose raw Ceph bucket ids.
//  - Restores: where the data is written back to (e.g. the Microsoft 365 account).
//  - Backups: the destination bucket *name* (or local path), never the numeric id.
$destinationHeading = 'Destination';
$destinationLabel = '';
$ms365ArchiveRestore = false;
$ms365ArchiveDownloadReady = false;
$ms365RestoreRunId = '';
$ms365BackupUserScopeId = '';

if ($isRestore) {
    $destinationHeading = 'Restore to';
    if ($isMs365Batch) {
        try {
            $destinationLabel = \Ms365Backup\Ms365BatchRunRepository::restoreTargetSummary((string) ($run['run_id'] ?? ''));
        } catch (\Throwable $e) {
            $destinationLabel = '';
        }
        if ($destinationLabel === 'Download archive') {
            $ms365ArchiveRestore = true;
            $destinationHeading = 'Delivery';
        } elseif ($destinationLabel === '') {
            $destinationLabel = 'Microsoft 365 account';
        }

        try {
            $children = \Ms365Backup\Ms365BatchRunRepository::getChildrenForRestoreBatch((string) ($run['run_id'] ?? ''));
            if ($children !== []) {
                $child = $children[0];
                $hasRestoreModeCol = Capsule::schema()->hasColumn('ms365_restore_runs', 'restore_mode');
                if ($hasRestoreModeCol && strtolower((string) ($child['restore_mode'] ?? '')) === 'archive') {
                    $ms365ArchiveRestore = true;
                    $destinationHeading = 'Delivery';
                    $destinationLabel = 'Download archive';
                    $ms365RestoreRunId = trim((string) ($child['id'] ?? ''));
                    $runStatus = strtolower((string) ($run['status'] ?? ''));
                    $childStatus = strtolower((string) ($child['status'] ?? ''));
                    $hasArchiveKeyCol = Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_object_key');
                    $archiveKey = $hasArchiveKeyCol ? trim((string) ($child['archive_object_key'] ?? '')) : '';
                    $ms365ArchiveDownloadReady = in_array($runStatus, ['success', 'partial_success'], true)
                        && $childStatus === 'success'
                        && ($archiveKey !== '' || !$hasArchiveKeyCol);
                }
            }
        } catch (\Throwable $e) {
            // Best-effort archive restore detection.
        }

        $backupUserInternalId = (int) ($job['backup_user_id'] ?? 0);
        if ($backupUserInternalId > 0 && Capsule::schema()->hasTable('s3_backup_users')) {
            $buCols = ['id'];
            if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
                $buCols[] = 'public_id';
            }
            $buRow = Capsule::table('s3_backup_users')
                ->where('id', $backupUserInternalId)
                ->first($buCols);
            if ($buRow) {
                $publicId = trim((string) ($buRow->public_id ?? ''));
                $ms365BackupUserScopeId = $publicId !== '' ? $publicId : (string) $buRow->id;
            }
        }
    } elseif (is_array($restoreMetadata) && !empty($restoreMetadata['target_path'])) {
        $destinationLabel = (string) $restoreMetadata['target_path'];
    }
} else {
    if (!empty($job['dest_local_path'])) {
        $destinationLabel = (string) $job['dest_local_path'];
    } elseif (!empty($job['dest_bucket_id'])) {
        try {
            $bucketName = Capsule::table('s3_buckets')
                ->where('id', (int) $job['dest_bucket_id'])
                ->value('name');
            if (!empty($bucketName)) {
                $destinationLabel = (string) $bucketName;
            }
        } catch (\Throwable $e) {
            $destinationLabel = '';
        }
    }
    if ($destinationLabel !== '' && !empty($job['dest_prefix'])) {
        $destinationLabel .= ' / ' . (string) $job['dest_prefix'];
    }
}

$serverTimezone = date_default_timezone_get() ?: 'UTC';
$startedAtEpochMs = null;
$finishedAtEpochMs = null;
if (!empty($run['started_at'])) {
    try {
        $dt = new \DateTime((string) $run['started_at'], new \DateTimeZone($serverTimezone));
        $startedAtEpochMs = (int) ($dt->getTimestamp() * 1000);
    } catch (\Throwable $e) {
        $startedAtEpochMs = null;
    }
}
if (!empty($run['finished_at'])) {
    try {
        $dt = new \DateTime((string) $run['finished_at'], new \DateTimeZone($serverTimezone));
        $finishedAtEpochMs = (int) ($dt->getTimestamp() * 1000);
    } catch (\Throwable $e) {
        $finishedAtEpochMs = null;
    }
}

return [
    'run' => $run,
    'job' => $job,
    'backup_username' => $backupUsername,
    'backup_user_route_id' => $backupUserRouteId,
    'show_user_subnav' => $showUserSubnav,
    'agent_name' => $agentName,
    'agent_uuid' => $agentUuid,
    'is_ms365_batch' => $isMs365Batch,
    'ms365_workloads' => $ms365Workloads,
    'source_label' => $sourceLabel,
    'is_restore' => $isRestore,
    'is_hyperv_restore' => $isHypervRestore,
    'restore_metadata' => $restoreMetadata,
    'destination_heading' => $destinationHeading,
    'destination_label' => $destinationLabel,
    'ms365_archive_restore' => $ms365ArchiveRestore,
    'ms365_archive_download_ready' => $ms365ArchiveDownloadReady,
    'ms365_restore_run_id' => $ms365RestoreRunId,
    'ms365_backup_user_scope_id' => $ms365BackupUserScopeId,
    'server_timezone' => $serverTimezone,
    'started_at_epoch_ms' => $startedAtEpochMs,
    'finished_at_epoch_ms' => $finishedAtEpochMs,
];

