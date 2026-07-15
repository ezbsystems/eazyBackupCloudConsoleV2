<?php

/**
 * e3backup_run_list.php
 *
 * Server-side, filtered + paginated run-level list across ALL of the
 * client's e3 Cloud Backup jobs. Powers the Job Logs page (one row per run).
 *
 * GET params:
 *   range_hours  - 24 | 48 | 60 | 72   (default 24)
 *   statuses[]   - filter to these run statuses
 *   workload[]   - ms365 | local_agent | cloud_to_cloud
 *   agent_uuid   - filter to one agent
 *   tenant_id    - MSP tenant public id (or 'direct')
 *   user_id      - backup user (public id or int id) scope
 *   job_id       - filter to one job (UUID or legacy int id)
 *   q            - free-text search (job name / agent hostname / user / source)
 *   page         - 1-based page (default 1)
 *   pageSize     - 10 | 25 | 50 | 100  (default 25)
 *   sortBy       - started | finished | status | job | agent | source  (default started)
 *   sortDir      - asc | desc  (default desc)
 *
 * Returns: { status, total, page, pageSize, rows[], facets: { statusCounts } }
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/E3BackupRunListService.php';
require_once __DIR__ . '/../lib/Client/E3BackupUserScope.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupRunListService;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}
$clientId = (int) $ca->getUserID();

$rangeHours = (int) ($_GET['range_hours'] ?? 24);
if (!in_array($rangeHours, [24, 48, 60, 72], true)) {
    $rangeHours = 24;
}
$statuses = isset($_GET['statuses']) && is_array($_GET['statuses']) ? array_map('strval', $_GET['statuses']) : [];
$validStatuses = ['success', 'warning', 'partial_success', 'failed', 'cancelled', 'running', 'starting', 'queued'];
$statuses = array_values(array_intersect($statuses, $validStatuses));

$workloads = isset($_GET['workload']) && is_array($_GET['workload']) ? array_map('strval', $_GET['workload']) : [];
if (empty($workloads) && isset($_GET['workloads']) && is_array($_GET['workloads'])) {
    $workloads = array_map('strval', $_GET['workloads']);
}
$workloads = array_values(array_intersect($workloads, E3BackupRunListService::VALID_WORKLOADS));

$agentFilter = isset($_GET['agent_uuid']) ? trim((string) $_GET['agent_uuid']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = (int) ($_GET['pageSize'] ?? 25);
if (!in_array($pageSize, [10, 25, 50, 100], true)) {
    $pageSize = 25;
}
$sortBy = strtolower((string) ($_GET['sortBy'] ?? 'started'));
$sortDir = strtolower((string) ($_GET['sortDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;
$tenantFilter = null;
if ($isMsp && $tenantFilterRaw !== null && $tenantFilterRaw !== '' && $tenantFilterRaw !== 'direct') {
    $tenant = MspController::getTenantByPublicId($tenantFilterRaw, $clientId);
    if ($tenant) {
        $tenantFilter = (int) $tenant->id;
    }
}

$userScopeIdRaw = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$jobFilterRaw = isset($_GET['job_id']) ? trim((string) $_GET['job_id']) : '';
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$userScopeId = 0;
$scopeStorageTenantId = null;
$scopeUserActive = false;

if ($userScopeIdRaw !== '' && $userScopeIdRaw !== '0') {
    $tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
    $scopeUserQuery = Capsule::table('s3_backup_users as u')
        ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
        ->where('u.client_id', $clientId);
    E3BackupUserScope::applyNotDeletedScope($scopeUserQuery, 'u');
    if ($hasPublicIdCol && !ctype_digit($userScopeIdRaw)) {
        $scopeUserQuery->where('u.public_id', $userScopeIdRaw);
    } else {
        $scopeUserQuery->where('u.id', (int) $userScopeIdRaw);
    }
    $scopeUser = $scopeUserQuery->select([
            'u.id',
            'u.tenant_id as storage_tenant_id',
            Capsule::raw($tenantOwnerSelect),
            't.status as tenant_status',
        ])
        ->first();

    if (!$scopeUser) {
        (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
        exit;
    }

    if (!$isMsp && !empty($scopeUser->storage_tenant_id)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
        exit;
    }

    if ($isMsp && !empty($scopeUser->storage_tenant_id)) {
        $tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int) (MspController::getMspIdForClient($clientId) ?? 0) : (int) $clientId;
        $tenantClientId = (int) ($scopeUser->tenant_owner_id ?? 0);
        $tenantStatus = strtolower((string) ($scopeUser->tenant_status ?? ''));
        if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
            (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
            exit;
        }
    }

    $userScopeId = (int) $scopeUser->id;
    $scopeStorageTenantId = $scopeUser->storage_tenant_id !== null ? (int) $scopeUser->storage_tenant_id : null;
    $scopeUserActive = true;
    $tenantFilterRaw = null;
    $tenantFilter = null;
}

try {
    $result = E3BackupRunListService::listRuns($clientId, [
        'range_hours' => $rangeHours,
        'statuses' => $statuses,
        'workloads' => $workloads,
        'agent_uuid' => $agentFilter,
        'q' => $q,
        'page' => $page,
        'pageSize' => $pageSize,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'tenant_id' => $tenantFilterRaw,
        'tenant_filter_id' => $tenantFilter,
        'scope_user_active' => $scopeUserActive,
        'user_scope_id' => $userScopeId,
        'scope_storage_tenant_id' => $scopeStorageTenantId,
        'job_id' => $jobFilterRaw,
    ]);

    (new JsonResponse([
        'status' => 'success',
        'total' => $result['total'],
        'page' => $page,
        'pageSize' => $pageSize,
        'rangeHours' => $rangeHours,
        'rows' => $result['rows'],
        'facets' => $result['facets'],
    ], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load runs'], 500))->send();
}
exit;
