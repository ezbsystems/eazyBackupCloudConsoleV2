<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\SanitizedLogFormatter;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEmailService;
use WHMCS\Database\Capsule;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Session timeout.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Product not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$runIdentifier = $_GET['run_uuid'] ?? $_GET['run_id'] ?? null;
if (!$runIdentifier) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run ID is required.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
$run = CloudBackupController::getRun($runIdentifier, $loggedInUserId);
if (!$run) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run not found or access denied.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Parse log excerpt into lines for live display
$logLines = [];
if (!empty($run['log_excerpt'])) {
    // Try to parse as JSON array first
    $logData = json_decode($run['log_excerpt'], true);
    
    // Handle case where log is stored as JSON array of JSON strings (double-encoded)
    if (is_array($logData) && !empty($logData) && is_string($logData[0])) {
        foreach ($logData as $line) {
            if (is_string($line)) {
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    $logLines[] = $decoded;
                }
            } elseif (is_array($line)) {
                $logLines[] = $line;
            }
        }
    } elseif (is_array($logData)) {
        $logLines = $logData;
    } else {
        // If not a JSON array, try parsing line by line
        $lines = explode("\n", trim($run['log_excerpt']));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $logLines[] = $decoded;
            }
        }
    }
}

// Build formatted log excerpt (server-side formatting) and a simple hash for caching
$formattedLog = null;
$structuredEntries = [];
$logHash = null;
if (!empty($run['log_excerpt'])) {
    $logHash = md5($run['log_excerpt']);
    // Light caching: avoid formatting if client already has same hash (client passes ?log_hash=...)
    $clientHash = isset($_GET['log_hash']) ? (string)$_GET['log_hash'] : null;
    if ($clientHash !== $logHash) {
        $san = SanitizedLogFormatter::sanitizeAndStructure($run['log_excerpt'], $run['status'] ?? null);
        $formattedLog = $san['formatted_log'];
        $structuredEntries = $san['entries'];
    }
}

// If run has reached a terminal state, do not emit entries repeatedly to the client (avoid duplicate lines)
$runStatus = (string)($run['status'] ?? '');
$isTerminalState = in_array($runStatus, ['success','warning','failed','cancelled'], true);
if ($isTerminalState) {
    $structuredEntries = [];
}

$jsonData = [
    'status' => 'success',
    'run' => [
        'status' => $run['status'],
        'error_summary' => $run['error_summary'] ?? '',
        'worker_host' => $run['worker_host'] ?? '',
        'progress_pct' => $run['progress_pct'],
        'bytes_total' => $run['bytes_total'],
        'bytes_transferred' => $run['bytes_transferred'],
        'objects_total' => $run['objects_total'],
        'objects_transferred' => $run['objects_transferred'],
        'speed_bytes_per_sec' => $run['speed_bytes_per_sec'],
        'eta_seconds' => $run['eta_seconds'],
        'current_item' => $run['current_item'],
        'started_at' => $run['started_at'],
        'finished_at' => $run['finished_at'],
        'log_excerpt' => $run['log_excerpt'] ?? '',
        'log_lines' => $logLines, // Raw lines for fallback
        'formatted_log_excerpt' => $formattedLog, // Formatted full excerpt (null if unchanged by hash)
		'entries' => $structuredEntries, // Structured sanitized entries
        'log_excerpt_hash' => $logHash,
    ]
];

// Opportunistic immediate email notification on terminal states (no cron required)
try {
    $isTerminal = in_array((string)($run['status'] ?? ''), ['success','warning','failed','cancelled'], true);
    $hasFinishedAt = !empty($run['finished_at']);
    if ($isTerminal && $hasFinishedAt) {
        // Check if already notified
            $alreadyNotified = Capsule::table('s3_cloudbackup_runs')
                ->where('id', (int)($run['id'] ?? 0))
            ->value('notified_at');
        if (!$alreadyNotified) {
            // Load email template setting
            $templateSetting = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'cloudbackup_email_template')
                ->value('value');
            if (!empty($templateSetting)) {
                // Load joined run + job for merge vars
                $row = Capsule::table('s3_cloudbackup_runs')
                    ->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id')
                    ->where('s3_cloudbackup_runs.id', (int)$runId)
                    ->select(
                        's3_cloudbackup_runs.id as run_id',
                        's3_cloudbackup_runs.status',
                        's3_cloudbackup_runs.started_at',
                        's3_cloudbackup_runs.finished_at',
                        's3_cloudbackup_runs.progress_pct',
                        's3_cloudbackup_runs.bytes_total',
                        's3_cloudbackup_runs.bytes_transferred',
                        's3_cloudbackup_runs.objects_total',
                        's3_cloudbackup_runs.objects_transferred',
                        's3_cloudbackup_runs.error_summary',
                        's3_cloudbackup_jobs.id as job_id',
                        's3_cloudbackup_jobs.name',
                        's3_cloudbackup_jobs.client_id',
                        's3_cloudbackup_jobs.source_display_name',
                        's3_cloudbackup_jobs.source_type',
                        's3_cloudbackup_jobs.dest_bucket_id',
                        's3_cloudbackup_jobs.dest_prefix',
                        's3_cloudbackup_jobs.notify_on_success',
                        's3_cloudbackup_jobs.notify_on_warning',
                        's3_cloudbackup_jobs.notify_on_failure',
                        's3_cloudbackup_jobs.notify_override_email'
                    )
                    ->first();
                if ($row) {
                    $client = DBController::getClient($row->client_id);
                    if ($client) {
                        $runArr = [
                            'id' => $row->run_id,
                            'status' => $row->status,
                            'started_at' => $row->started_at,
                            'finished_at' => $row->finished_at,
                            'progress_pct' => $row->progress_pct,
                            'bytes_total' => $row->bytes_total,
                            'bytes_transferred' => $row->bytes_transferred,
                            'objects_total' => $row->objects_total,
                            'objects_transferred' => $row->objects_transferred,
                            'error_summary' => $row->error_summary,
                        ];
                        $jobArr = [
                            'id' => $row->job_id,
                            'name' => $row->name,
                            'client_id' => $row->client_id,
                            'source_display_name' => $row->source_display_name,
                            'source_type' => $row->source_type,
                            'dest_bucket_id' => $row->dest_bucket_id,
                            'dest_prefix' => $row->dest_prefix,
                            'notify_on_success' => $row->notify_on_success,
                            'notify_on_warning' => $row->notify_on_warning,
                            'notify_on_failure' => $row->notify_on_failure,
                            'notify_override_email' => $row->notify_override_email,
                        ];
                        $res = CloudBackupEmailService::sendRunNotification($runArr, $jobArr, $client, $templateSetting);
                        // Mark notified for success or skipped (to avoid repeated attempts via polling)
                        if (in_array(($res['status'] ?? ''), ['success','skipped'], true)) {
                            Capsule::table('s3_cloudbackup_runs')
                                ->where('id', (int)($run['id'] ?? 0))
                                ->update(['notified_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Do not impact progress response
    logModuleCall('cloudstorage', 'progress_notify_error', ['run_id' => $run['id'] ?? $runIdentifier], $e->getMessage());
}

$response = new JsonResponse($jsonData, 200);
$response->send();
exit();

