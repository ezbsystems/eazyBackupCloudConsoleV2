<?php

/**
 * e3backup_backup_history.php
 *
 * Returns the "Recent Backup History" grid model for the e3 Cloud Backup
 * dashboard: one entry per agent (plus a synthetic Microsoft 365 row when
 * applicable), each with a per-day worst-status strip, that day's run list,
 * a last-24h summary, and per-job sub-rows for the drill-down. Lazy-loaded
 * by the dashboard so the initial paint stays fast for MSPs / large accounts.
 *
 * GET params:
 *   days        - history window in calendar days (default 14, clamp 7..31)
 *   tenant_id   - MSP tenant public id (or 'direct') filter
 *   agent_uuid  - limit to a single agent (or __ms365__ for Microsoft 365 only)
 *
 * Returns: { status, days[], agents: [ { agent_uuid, hostname, agent_os,
 *            is_online, last24h{}, days[], jobs[] } ], agentTotal }
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/TimezoneHelper.php';
require_once __DIR__ . '/../lib/Client/E3BackupHistoryService.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupHistoryService;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\TimezoneHelper;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}
$clientId = (int) $ca->getUserID();

$days = (int) ($_GET['days'] ?? 14);
if ($days < 7) { $days = 7; }
if ($days > 31) { $days = 31; }

$agentFilter = isset($_GET['agent_uuid']) ? trim((string) $_GET['agent_uuid']) : '';
$ms365FilterMode = E3BackupHistoryService::ms365FilterMode($agentFilter);
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

try {
    $schema = Capsule::schema();
    $hasRunIdCol = $schema->hasColumn('s3_cloudbackup_runs', 'run_id');
    $hasJobIdPk = $schema->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $hasJobTenant = $schema->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
    $hasJobSourceType = $schema->hasColumn('s3_cloudbackup_jobs', 'source_type');
    $hasJobEngine = $schema->hasColumn('s3_cloudbackup_jobs', 'engine');
    $onlineThresholdSeconds = 180;
    if (function_exists('getModuleSetting')) {
        $onlineThresholdSeconds = (int) getModuleSetting('cloudbackup_agent_online_threshold_seconds', 180);
    }
    if ($onlineThresholdSeconds <= 0) { $onlineThresholdSeconds = 180; }

    $formatSize = static function (int $bytes): string {
        return HelperController::formatSizeUnitsPlain($bytes);
    };
    $epochMs = static function ($startedAt) {
        return TimezoneHelper::instantToEpochMs($startedAt);
    };

    // ── Agents for this client ──
    $agentTotalQuery = Capsule::table('s3_cloudbackup_agents')
        ->where('client_id', $clientId);
    if ($hasJobTenant && $tenantFilterRaw !== null) {
        if ($tenantFilterRaw === 'direct') {
            $agentTotalQuery->whereNull('tenant_id');
        } elseif ($tenantFilter !== null) {
            $agentTotalQuery->where('tenant_id', $tenantFilter);
        }
    }
    $agentTotal = (int) $agentTotalQuery->count();

    $agentByUuid = [];
    if ($ms365FilterMode !== E3BackupHistoryService::MS365_FILTER_ONLY) {
        $agentQuery = Capsule::table('s3_cloudbackup_agents')
            ->where('client_id', $clientId)
            ->select([
                'agent_uuid',
                'hostname',
                'agent_os',
                'last_seen_at',
                'tenant_id',
                Capsule::raw('TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) AS seconds_since_seen'),
            ]);
        if ($agentFilter !== '') {
            $agentQuery->where('agent_uuid', $agentFilter);
        }
        if ($hasJobTenant && $tenantFilterRaw !== null) {
            if ($tenantFilterRaw === 'direct') {
                $agentQuery->whereNull('tenant_id');
            } elseif ($tenantFilter !== null) {
                $agentQuery->where('tenant_id', $tenantFilter);
            }
        }
        $agentRows = $agentQuery->orderBy('hostname')->get();

        // Cap how many agents we hydrate with run history per request.
        $agentCap = 200;
        $agentRows = array_slice($agentRows->all(), 0, $agentCap);

        foreach ($agentRows as $a) {
            $uuid = (string) $a->agent_uuid;
            if ($uuid === '') { continue; }
            $secs = $a->seconds_since_seen !== null ? (int) $a->seconds_since_seen : null;
            $agentByUuid[$uuid] = E3BackupHistoryService::newAgentEntry(
                $uuid,
                (string) ($a->hostname ?? '') ?: 'Unknown host',
                (string) ($a->agent_os ?? ''),
                !empty($a->last_seen_at) && $secs !== null && $secs <= $onlineThresholdSeconds
            );
        }
    }

    // ── Runs in the window ──
    $jobRunJoin = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
    $cutoff = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $cutoff24h = strtotime('-24 hours');

    $runIdSelect = $hasRunIdCol
        ? Capsule::raw('BIN_TO_UUID(r.run_id) as run_id')
        : Capsule::raw('r.id as run_id');
    $jobIdSelect = $hasJobIdPk
        ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id')
        : Capsule::raw('j.id as job_id');

    if ($ms365FilterMode !== E3BackupHistoryService::MS365_FILTER_ONLY) {
        $runQuery = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
            ->where('j.client_id', $clientId)
            ->where('j.status', '!=', 'deleted')
            ->where('r.started_at', '>=', $cutoff)
            ->whereNotNull('j.agent_uuid');
        if ($agentFilter !== '') {
            $runQuery->where('j.agent_uuid', $agentFilter);
        }
        if ($hasJobTenant && $tenantFilterRaw !== null) {
            if ($tenantFilterRaw === 'direct') {
                $runQuery->whereNull('j.tenant_id');
            } elseif ($tenantFilter !== null) {
                $runQuery->where('j.tenant_id', $tenantFilter);
            }
        }
        $runs = $runQuery->orderBy('r.started_at', 'desc')->limit(20000)->get([
            $runIdSelect,
            $jobIdSelect,
            'r.status',
            'r.started_at',
            'r.finished_at',
            'r.bytes_processed',
            'r.bytes_transferred',
            'j.name as job_name',
            'j.agent_uuid',
        ]);

        foreach ($runs as $r) {
            $uuid = (string) $r->agent_uuid;
            if (!isset($agentByUuid[$uuid])) { continue; }
            $bytes = max((int) ($r->bytes_processed ?? 0), (int) ($r->bytes_transferred ?? 0));
            E3BackupHistoryService::applyRunToAgent(
                $agentByUuid[$uuid],
                (string) ($r->run_id ?? ''),
                (string) ($r->job_id ?? ''),
                (string) ($r->job_name ?? ''),
                (string) $r->status,
                $r->started_at ?? null,
                $bytes,
                $cutoff24h,
                $formatSize,
                $epochMs
            );
        }
    }

    // ── Microsoft 365 synthetic row ──
    $ms365Agent = null;
    if ($ms365FilterMode !== E3BackupHistoryService::MS365_FILTER_SKIP) {
        $ms365JobQuery = Capsule::table('s3_cloudbackup_jobs as j')
            ->where('j.client_id', $clientId)
            ->where('j.status', '!=', 'deleted');
        E3BackupHistoryService::applyMs365JobScope($ms365JobQuery, $hasJobSourceType, $hasJobEngine);
        if ($hasJobTenant && $tenantFilterRaw !== null) {
            if ($tenantFilterRaw === 'direct') {
                $ms365JobQuery->whereNull('j.tenant_id');
            } elseif ($tenantFilter !== null) {
                $ms365JobQuery->where('j.tenant_id', $tenantFilter);
            }
        }
        if ($ms365JobQuery->exists()) {
            $ms365Agent = E3BackupHistoryService::newMs365AgentEntry();

            $ms365RunQuery = Capsule::table('s3_cloudbackup_runs as r')
                ->join('s3_cloudbackup_jobs as j', $jobRunJoin[0], $jobRunJoin[1], $jobRunJoin[2])
                ->where('j.client_id', $clientId)
                ->where('j.status', '!=', 'deleted')
                ->where('r.started_at', '>=', $cutoff);
            E3BackupHistoryService::applyMs365JobScope($ms365RunQuery, $hasJobSourceType, $hasJobEngine);
            if ($hasJobTenant && $tenantFilterRaw !== null) {
                if ($tenantFilterRaw === 'direct') {
                    $ms365RunQuery->whereNull('j.tenant_id');
                } elseif ($tenantFilter !== null) {
                    $ms365RunQuery->where('j.tenant_id', $tenantFilter);
                }
            }
            $ms365Runs = $ms365RunQuery->orderBy('r.started_at', 'desc')->limit(20000)->get([
                $runIdSelect,
                $jobIdSelect,
                'r.status',
                'r.started_at',
                'r.finished_at',
                'r.bytes_processed',
                'r.bytes_transferred',
                'j.name as job_name',
            ]);

            foreach ($ms365Runs as $r) {
                $bytes = max((int) ($r->bytes_processed ?? 0), (int) ($r->bytes_transferred ?? 0));
                E3BackupHistoryService::applyRunToAgent(
                    $ms365Agent,
                    (string) ($r->run_id ?? ''),
                    (string) ($r->job_id ?? ''),
                    (string) ($r->job_name ?? ''),
                    (string) $r->status,
                    $r->started_at ?? null,
                    $bytes,
                    $cutoff24h,
                    $formatSize,
                    $epochMs
                );
            }
        }
    }

    $dayList = E3BackupHistoryService::buildDayList($days);

    // ── Assemble output ──
    $agentsOut = [];
    if ($ms365Agent !== null) {
        $agentsOut[] = E3BackupHistoryService::assembleAgentOutput($ms365Agent, $dayList);
    }
    foreach ($agentByUuid as $agent) {
        $agentsOut[] = E3BackupHistoryService::assembleAgentOutput($agent, $dayList);
    }

    (new JsonResponse([
        'status' => 'success',
        'days' => array_map(function ($d) {
            return ['date' => $d, 'label' => date('M j', strtotime($d))];
        }, $dayList),
        'agents' => $agentsOut,
        'agentTotal' => $agentTotal,
    ], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load backup history'], 500))->send();
}
exit;
