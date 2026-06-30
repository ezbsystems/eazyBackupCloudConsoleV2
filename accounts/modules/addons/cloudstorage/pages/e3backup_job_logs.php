<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CanonicalHypervJobResolver;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

require_once __DIR__ . '/../lib/Client/E3BackupAccess.php';

$loggedInUserId = E3BackupAccess::requireE3BackupClientAreaAccess('job_logs');

$userIdRaw = trim((string) ($_GET['user_id'] ?? ''));
$jobIdRaw = trim((string) ($_GET['job_id'] ?? ''));
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$isMspClient = MspController::isMspClient($loggedInUserId);
$tenantTable = MspController::getTenantTableName();

$scopeUserRouteId = '';
$scopeUsername = '';
$scopeJobId = '';
$scopeJobName = '';
$showUserSubnav = false;

if ($jobIdRaw !== '') {
    $canonicalJobId = CanonicalHypervJobResolver::resolveCanonicalJobId($jobIdRaw, $loggedInUserId);
    if ($canonicalJobId !== null && $canonicalJobId !== $jobIdRaw) {
        $target = 'index.php?m=cloudstorage&page=e3backup&view=job_logs&job_id=' . rawurlencode($canonicalJobId);
        if ($userIdRaw !== '') {
            $target .= '&user_id=' . rawurlencode($userIdRaw);
        }
        header('Location: ' . $target);
        exit;
    }

    $job = CloudBackupController::getJob($jobIdRaw, $loggedInUserId);
    if (!$job) {
        header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
        exit;
    }
    $scopeJobId = (string) ($job['job_id'] ?? $jobIdRaw);
    $scopeJobName = (string) ($job['name'] ?? '');
}

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

    if (!$scopeUser) {
        header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
        exit;
    }

    if ($isMspClient && !empty($scopeUser->storage_tenant_id)) {
        $tenantClientId = (int) ($scopeUser->tenant_owner_id ?? 0);
        $tenantStatus = strtolower((string) ($scopeUser->tenant_status ?? ''));
        if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
            header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
            exit;
        }
    } elseif (!$isMspClient && !empty($scopeUser->storage_tenant_id)) {
        header('Location: index.php?m=cloudstorage&page=e3backup&view=users');
        exit;
    }

    $scopeUsername = (string) ($scopeUser->username ?? '');
    $scopeUserRouteId = $hasPublicIdCol && !empty($scopeUser->public_id)
        ? (string) $scopeUser->public_id
        : (string) ((int) $scopeUser->id);
    $showUserSubnav = true;
}

$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}
$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'csrfToken' => $csrfToken,
    'scopeUserRouteId' => $scopeUserRouteId,
    'scopeUsername' => $scopeUsername,
    'scopeJobId' => $scopeJobId,
    'scopeJobName' => $scopeJobName,
    'showUserSubnav' => $showUserSubnav,
];
