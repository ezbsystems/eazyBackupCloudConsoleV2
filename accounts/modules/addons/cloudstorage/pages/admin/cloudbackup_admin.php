<?php

require_once __DIR__ . '/../../lib/Admin/CloudBackupAdminController.php';

use WHMCS\Module\Addon\CloudStorage\Admin\CloudBackupAdminController;
use WHMCS\Database\Capsule;

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
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'backup_log' => $run->log_excerpt ?? '',
                'validation_log' => $run->validation_log_excerpt ?? ''
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
    
    // Get filters from request
    $filters = [
        'client_id' => $_GET['client_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'source_type' => $_GET['source_type'] ?? null,
        'job_id' => $_GET['job_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'job_name' => $_GET['job_name'] ?? null,
    ];
    
    // Get all jobs
    $jobs = CloudBackupAdminController::getAllJobs($filters);
    
    // Get all runs
    $runs = CloudBackupAdminController::getAllRuns($filters);
    
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
        'clients' => $clients,
        'filters' => $filters,
        'worker_host' => $workerHost,
        'max_concurrent_jobs' => $maxConcurrentJobs,
        'max_bandwidth' => $maxBandwidth,
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

