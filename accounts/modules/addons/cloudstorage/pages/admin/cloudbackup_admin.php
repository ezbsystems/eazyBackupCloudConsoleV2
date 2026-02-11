<?php

require_once __DIR__ . '/../../lib/Admin/CloudBackupAdminController.php';

use WHMCS\Module\Addon\CloudStorage\Admin\CloudBackupAdminController;
use WHMCS\Database\Capsule;

function cloudbackup_get_setting(string $key, $default = null)
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->value('value');
        return ($val !== null && $val !== '') ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

function cloudbackup_get_timing_settings(): array
{
    $defaultWatchdog = 720;
    $defaultReclaim = 180;
    $defaultReclaimEnabled = true;

    $storedWatchdog = (int) cloudbackup_get_setting('cloudbackup_agent_watchdog_timeout_seconds', $defaultWatchdog);
    $storedReclaim = (int) cloudbackup_get_setting('cloudbackup_agent_reclaim_grace_seconds', $defaultReclaim);
    $storedReclaimEnabledRaw = cloudbackup_get_setting('cloudbackup_agent_reclaim_enabled', $defaultReclaimEnabled ? '1' : '0');
    $storedReclaimEnabled = !in_array(strtolower((string) $storedReclaimEnabledRaw), ['0', 'false', 'off', 'no'], true);

    $effectiveWatchdog = getenv('AGENT_WATCHDOG_TIMEOUT_SECONDS') !== false
        ? (int) getenv('AGENT_WATCHDOG_TIMEOUT_SECONDS')
        : $storedWatchdog;
    $effectiveReclaim = getenv('AGENT_RECLAIM_GRACE_SECONDS') !== false
        ? (int) getenv('AGENT_RECLAIM_GRACE_SECONDS')
        : $storedReclaim;
    $effectiveReclaimEnabled = getenv('AGENT_RECLAIM_ENABLED') !== false
        ? !in_array(strtolower((string) getenv('AGENT_RECLAIM_ENABLED')), ['0', 'false', 'off', 'no'], true)
        : $storedReclaimEnabled;

    return [
        'stored_watchdog' => $storedWatchdog,
        'stored_reclaim' => $storedReclaim,
        'stored_reclaim_enabled' => $storedReclaimEnabled,
        'effective_watchdog' => $effectiveWatchdog,
        'effective_reclaim' => $effectiveReclaim,
        'effective_reclaim_enabled' => $effectiveReclaimEnabled,
    ];
}

function cloudbackup_get_watchdog_status(): array
{
    $okActiveStates = ['active', 'activating'];

    $parseSystemctl = function (string $unit): array {
        $out = @shell_exec("systemctl show {$unit} --no-page --property=ActiveState,SubState,Result,UnitFileState 2>/dev/null");
        $data = [
            'ActiveState' => 'unknown',
            'SubState' => 'unknown',
            'Result' => 'unknown',
            'UnitFileState' => 'unknown',
        ];
        if (!is_string($out)) {
            return $data;
        }
        foreach (explode("\n", $out) as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k !== '' && $v !== '') {
                $data[$k] = $v;
            }
        }
        return $data;
    };

    $service = $parseSystemctl('e3-agent-watchdog.service');
    $timer = $parseSystemctl('e3-agent-watchdog.timer');

    $serviceOk = in_array($service['ActiveState'], $okActiveStates, true);
    // For oneshot services, ActiveState=inactive is expected; treat Result=success as OK
    if (!$serviceOk && $service['ActiveState'] === 'inactive' && $service['Result'] === 'success') {
        $serviceOk = true;
        $service['ActiveState'] = 'idle';
    }

    $timerOk = in_array($timer['ActiveState'], $okActiveStates, true);

    return [
        'service_status' => $service['ActiveState'],
        'service_result' => $service['Result'],
        'service_ok' => $serviceOk,
        'timer_status' => $timer['ActiveState'],
        'timer_result' => $timer['Result'],
        'timer_ok' => $timerOk,
    ];
}

function cloudbackup_get_tenants_for_filter(): array
{
    try {
        if (!Capsule::schema()->hasTable('s3_backup_tenants')) {
            return [];
        }
        $rows = Capsule::table('s3_backup_tenants')
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();
        return array_map(function ($item) {
            return (array) $item;
        }, $rows->toArray());
    } catch (\Throwable $e) {
        return [];
    }
}

function cloudstorage_admin_cloudbackup($vars)
{
    ob_start();
    
    // Handle force cancel run
    if (isset($_GET['cancel_run'])) {
        $result = CloudBackupAdminController::forceCancelRun((int)$_GET['cancel_run']);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // Handle get run logs
    if (isset($_GET['get_run_logs'])) {
        $runId = (int)$_GET['get_run_logs'];
        $run = Capsule::table('s3_cloudbackup_runs')
            ->where('id', $runId)
            ->first();
        
        if ($run) {
            $structured = [];
            try {
                if (Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
                    $logRows = Capsule::table('s3_cloudbackup_run_logs')
                        ->where('run_id', $runId)
                        ->orderBy('created_at', 'asc')
                        ->limit(500)
                        ->get();
                    foreach ($logRows as $row) {
                        $details = null;
                        if (!empty($row->details_json)) {
                            $dec = json_decode($row->details_json, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $details = $dec;
                            }
                        }
                        $structured[] = [
                            'ts' => (string)$row->created_at,
                            'level' => $row->level ?? 'info',
                            'code' => $row->code ?? '',
                            'message' => $row->message ?? '',
                            'details' => $details,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'backup_log' => $run->log_excerpt ?? '',
                'validation_log' => $run->validation_log_excerpt ?? '',
                'structured_logs' => $structured,
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'fail', 'message' => 'Run not found']);
        }
        exit;
    }
    
    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $filters = [
            'client_id' => $_GET['client_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'source_type' => $_GET['source_type'] ?? null,
            'job_id' => $_GET['job_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'job_name' => $_GET['job_name'] ?? null,
        ];
        
        $runs = CloudBackupAdminController::getAllRuns($filters);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cloudbackup_runs_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Run ID', 'Job Name', 'Client', 'Status', 'Started', 'Finished', 'Bytes Transferred', 'Progress %', 'Trigger Type']);
        
        foreach ($runs as $run) {
            // Handle both array and object formats
            $runId = is_array($run) ? ($run['id'] ?? '') : ($run->id ?? '');
            $jobName = is_array($run) ? ($run['job_name'] ?? '') : ($run->job_name ?? '');
            $firstName = is_array($run) ? ($run['firstname'] ?? '') : ($run->firstname ?? '');
            $lastName = is_array($run) ? ($run['lastname'] ?? '') : ($run->lastname ?? '');
            $status = is_array($run) ? ($run['status'] ?? '') : ($run->status ?? '');
            $startedAt = is_array($run) ? ($run['started_at'] ?? '') : ($run->started_at ?? '');
            $finishedAt = is_array($run) ? ($run['finished_at'] ?? '') : ($run->finished_at ?? '');
            $bytesTransferred = is_array($run) ? ($run['bytes_transferred'] ?? 0) : ($run->bytes_transferred ?? 0);
            $progressPct = is_array($run) ? ($run['progress_pct'] ?? 0) : ($run->progress_pct ?? 0);
            $triggerType = is_array($run) ? ($run['trigger_type'] ?? '') : ($run->trigger_type ?? '');
            
            fputcsv($output, [
                $runId,
                $jobName,
                trim($firstName . ' ' . $lastName),
                $status,
                $startedAt,
                $finishedAt,
                $bytesTransferred,
                $progressPct,
                $triggerType
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    // Get module configuration
    $workerHost = $vars['cloudbackup_worker_host'] ?? 'Not configured';
    $maxConcurrentJobs = $vars['cloudbackup_global_max_concurrent_jobs'] ?? 'Not configured';
    $maxBandwidth = $vars['cloudbackup_global_max_bandwidth_kbps'] ?? 'Not configured';
    
    $timingSettings = cloudbackup_get_timing_settings();
    $watchdogSaveStatus = null;
    $watchdogSaveMessage = null;
    $watchdogStatus = cloudbackup_get_watchdog_status();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_watchdog_settings'])) {
        $watchdog = max(60, (int) ($_POST['agent_watchdog_timeout_seconds'] ?? $timingSettings['stored_watchdog']));
        $reclaim = max(30, (int) ($_POST['agent_reclaim_grace_seconds'] ?? $timingSettings['stored_reclaim']));
        if ($reclaim >= $watchdog) {
            $reclaim = max(30, $watchdog - 60);
        }
        $reclaimEnabled = isset($_POST['agent_reclaim_enabled']) ? '1' : '0';

        try {
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'cloudstorage', 'setting' => 'cloudbackup_agent_watchdog_timeout_seconds'],
                ['value' => $watchdog]
            );
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'cloudstorage', 'setting' => 'cloudbackup_agent_reclaim_grace_seconds'],
                ['value' => $reclaim]
            );
            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => 'cloudstorage', 'setting' => 'cloudbackup_agent_reclaim_enabled'],
                ['value' => $reclaimEnabled]
            );
            $watchdogSaveStatus = 'success';
            $watchdogSaveMessage = 'Watchdog/reclaim settings updated.';
            $timingSettings = cloudbackup_get_timing_settings();
        } catch (\Throwable $e) {
            $watchdogSaveStatus = 'error';
            $watchdogSaveMessage = 'Failed to save settings: ' . $e->getMessage();
        }
    }
    
    // Get filters from request
    $filters = [
        'client_id' => $_GET['client_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'source_type' => $_GET['source_type'] ?? null,
        'job_id' => $_GET['job_id'] ?? null,
        'agent_id' => $_GET['agent_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'job_name' => $_GET['job_name'] ?? null,
    ];
    
    // Get all jobs
    $jobs = CloudBackupAdminController::getAllJobs($filters);
    
    // Get all runs
    $runs = CloudBackupAdminController::getAllRuns($filters);

    $agentFilters = [
        'q' => isset($_GET['agents_q']) ? trim((string) $_GET['agents_q']) : '',
        'client_id' => $_GET['agents_client_id'] ?? null,
        'status' => $_GET['agents_status'] ?? null,
        'agent_type' => $_GET['agents_type'] ?? null,
        'tenant_id' => $_GET['agents_tenant_id'] ?? null,
        'online_status' => $_GET['agents_online'] ?? null,
    ];
    $agentSortField = (string) ($_GET['agents_sort'] ?? 'created_at');
    $agentSortDir = strtolower((string) ($_GET['agents_dir'] ?? 'desc'));
    if (!in_array($agentSortDir, ['asc', 'desc'], true)) {
        $agentSortDir = 'desc';
    }
    $agentPage = (int) ($_GET['agents_page'] ?? 1);
    if ($agentPage < 1) {
        $agentPage = 1;
    }
    $agentPerPage = (int) ($_GET['agents_per_page'] ?? 50);
    if ($agentPerPage < 1) {
        $agentPerPage = 50;
    }
    if ($agentPerPage > 200) {
        $agentPerPage = 200;
    }
    $agentOffset = ($agentPage - 1) * $agentPerPage;
    $agents = CloudBackupAdminController::getAllAgents($agentFilters, ['field' => $agentSortField, 'dir' => $agentSortDir], $agentPerPage, $agentOffset);
    $agentsTotal = CloudBackupAdminController::countAllAgents($agentFilters);
    $agentsPages = $agentPerPage > 0 ? (int) ceil($agentsTotal / $agentPerPage) : 1;
    if ($agentsPages < 1) {
        $agentsPages = 1;
    }
    $agentTenants = cloudbackup_get_tenants_for_filter();
    
    // Get all clients for filter dropdown
    $clients = Capsule::table('tblclients')
        ->select('id', 'firstname', 'lastname', 'email')
        ->orderBy('firstname', 'asc')
        ->get();
    // Convert stdClass objects to arrays for Smarty compatibility
    $clients = array_map(function($item) {
        return (array) $item;
    }, $clients->toArray());
    
    // Calculate metrics
    $totalJobs = Capsule::table('s3_cloudbackup_jobs')->where('status', '!=', 'deleted')->count();
    $activeJobs = Capsule::table('s3_cloudbackup_jobs')->where('status', 'active')->count();
    $runningRuns = Capsule::table('s3_cloudbackup_runs')
        ->whereIn('status', ['running', 'starting', 'queued'])
        ->count();
    
    // Calculate success rate for last 24 hours
    $last24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $recentRuns = Capsule::table('s3_cloudbackup_runs')
        ->where('started_at', '>=', $last24h)
        ->whereIn('status', ['success', 'failed', 'warning'])
        ->get();
    $successCount = $recentRuns->where('status', 'success')->count();
    $totalRecent = $recentRuns->count();
    $successRate = $totalRecent > 0 ? round(($successCount / $totalRecent) * 100, 1) : 0;
    
    // Prepare template variables
    $templateVars = [
        'jobs' => $jobs,
        'runs' => $runs,
        'agents' => $agents,
        'agents_filters' => $agentFilters,
        'agents_sort' => $agentSortField,
        'agents_dir' => $agentSortDir,
        'agents_page' => $agentPage,
        'agents_per_page' => $agentPerPage,
        'agents_total' => $agentsTotal,
        'agents_pages' => $agentsPages,
        'agent_tenants' => $agentTenants,
        'clients' => $clients,
        'filters' => $filters,
        'worker_host' => $workerHost,
        'max_concurrent_jobs' => $maxConcurrentJobs,
        'max_bandwidth' => $maxBandwidth,
        'watchdog_settings' => $timingSettings,
        'watchdog_save_status' => $watchdogSaveStatus,
        'watchdog_save_message' => $watchdogSaveMessage,
        'watchdog_status' => $watchdogStatus,
        'metrics' => [
            'total_jobs' => $totalJobs,
            'active_jobs' => $activeJobs,
            'running_runs' => $runningRuns,
            'success_rate' => $successRate,
        ],
    ];
    
    // Clean any output buffer
    ob_end_clean();
    
    // Load template
    $template = new \Smarty();
    $template->setTemplateDir(__DIR__ . '/../../templates/admin/');
    $template->setCompileDir($GLOBALS['templates_compiledir']);
    $template->assign($templateVars);
    echo $template->fetch('cloudbackup_admin.tpl');
}

