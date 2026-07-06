<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEventFormatter;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupLogFormatter;
use WHMCS\Module\Addon\CloudStorage\Client\CustomerFacingTextSanitizer;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;
use WHMCS\Module\Addon\CloudStorage\Client\SanitizedLogFormatter;
use WHMCS\Module\Addon\CloudStorage\Client\TimezoneHelper;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

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

$loggedInUserId = (int) $ca->getUserID();

if (!E3BackupAccess::clientHasE3BackupAccess($loggedInUserId)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Product not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$runIdentifier = $_GET['run_uuid'] ?? ($_GET['run_id'] ?? null);
if (!$runIdentifier) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run ID is required.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Verify run ownership and get run details
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
$userTz = TimezoneHelper::resolveUserTimezone($loggedInUserId, $run['job_id'] ?? null);

if (Ms365BatchLiveService::isMs365BatchRun($run)) {
    try {
        $batchRunId = (string) ($run['run_id'] ?? $runIdentifier);
        $payload = Ms365BatchLiveService::aggregateStructuredLogs($batchRunId, (int) $loggedInUserId, $userTz);
        $response = new JsonResponse([
            'status' => 'success',
        ] + $payload, 200);
        $response->send();
        exit();
    } catch (\Throwable $e) {
        $response = new JsonResponse([
            'status' => 'fail',
            'message' => 'Unable to load run logs.',
        ], 200);
        $response->send();
        exit();
    }
}

if (!function_exists('ebE3FormatBytesHuman')) {
    function ebE3FormatBytesHuman($bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max((int) $bytes, 0);
        if ($bytes === 0) {
            return '0 B';
        }
        $pow = (int) floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        $value = $bytes / pow(1024, $pow);
        return round($value, $precision) . ' ' . $units[$pow];
    }
}

$isRestoreRun = false;
if (!empty($run['run_type'])) {
    $rt = (string) $run['run_type'];
    if (in_array($rt, ['restore', 'hyperv_restore', 'disk_restore'], true)) {
        $isRestoreRun = true;
    }
}
if (!$isRestoreRun && !empty($run['stats_json'])) {
    $statsJson = is_string($run['stats_json']) ? json_decode($run['stats_json'], true) : $run['stats_json'];
    if (json_last_error() === JSON_ERROR_NONE && is_array($statsJson)) {
        $stype = (string) ($statsJson['type'] ?? '');
        if (in_array($stype, ['restore', 'hyperv_restore', 'disk_restore'], true)) {
            $isRestoreRun = true;
        }
    }
}
$bytesTransferred = (int) ($run['bytes_transferred'] ?? 0);
$bytesFormatted = ebE3FormatBytesHuman($bytesTransferred);
$runSummary = [
    'bytes_transferred' => $bytesTransferred,
    'bytes_processed' => (int) ($run['bytes_processed'] ?? 0),
    'is_restore' => $isRestoreRun,
    'uploaded_formatted' => $isRestoreRun ? '—' : $bytesFormatted,
    'downloaded_formatted' => $isRestoreRun ? $bytesFormatted : '—',
];

// Client area: sanitized logs
$sanitized = SanitizedLogFormatter::sanitizeAndStructure($run['log_excerpt'] ?? null, $run['status'] ?? null, $userTz);
$formattedBackupLog = $sanitized['formatted_log'];
$formattedValidationLog = null;
$rawBackupExcerpt = trim((string) ($run['log_excerpt'] ?? ''));

// Format validation log if available
if (!empty($run['validation_log_excerpt']) && $run['validation_mode'] === 'post_run') {
	$valSan = SanitizedLogFormatter::sanitizeAndStructure($run['validation_log_excerpt'], $run['validation_status'] ?? null, $userTz);
	$formattedValidationLog = $valSan['formatted_log'];
}

// Append structured run_logs entries (client-visible)
$structuredLogs = [];
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
if ($limit <= 0) {
    $limit = 500;
}
if ($limit > 5000) {
    $limit = 5000;
}
try {
    if (\WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
        $logQuery = \WHMCS\Database\Capsule::table('s3_cloudbackup_run_logs')
            ->orderBy('created_at', 'asc')
            ->limit($limit);
        if (is_string($runIdentifier) && UuidBinary::isUuid($runIdentifier)) {
            $logQuery->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runIdentifier)));
        } else {
            $logQuery->where('run_id', $run['id'] ?? 0);
        }
        $logRows = $logQuery->get();
        foreach ($logRows as $row) {
            $details = null;
            if (!empty($row->details_json)) {
                $dec = json_decode($row->details_json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $details = $dec;
                }
            }
            $structuredLogs[] = [
                'ts' => TimezoneHelper::formatTimestamp($row->created_at, $userTz),
                'level' => (string) ($row->level ?? 'info'),
                'code' => $row->code ?? '',
                'message' => CustomerFacingTextSanitizer::scrubLogMessage((string) ($row->message ?? '')),
                'details' => $details,
            ];
        }
    }
} catch (\Throwable $e) {
    // ignore; best-effort
}

// Fallback: when excerpt/run_logs are empty, use the event stream as the user-visible run log.
if (empty($structuredLogs) && $rawBackupExcerpt === '' && \WHMCS\Database\Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
    try {
        $eventQuery = \WHMCS\Database\Capsule::table('s3_cloudbackup_run_events')
            ->select(['id', 'ts', 'type', 'level', 'code', 'message_id', 'params_json'])
            ->orderBy('id', 'asc')
            ->limit($limit);
        if (is_string($runIdentifier) && UuidBinary::isUuid($runIdentifier)) {
            $eventQuery->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runIdentifier)));
        } else {
            $eventQuery->where('run_id', $run['id'] ?? 0);
        }
        $eventRows = $eventQuery->get();
        foreach ($eventRows as $row) {
            $params = [];
            if (!empty($row->params_json)) {
                $decoded = json_decode($row->params_json, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $params = $decoded;
                }
            }
            $structuredLogs[] = [
                'ts' => TimezoneHelper::formatTimestamp($row->ts, $userTz),
                'level' => (string) ($row->level ?? 'info'),
                'code' => (string) ($row->code ?? ''),
                'message' => CloudBackupEventFormatter::render((string) ($row->message_id ?? ''), $params),
                'details' => !empty($params) ? $params : null,
            ];
        }
    } catch (\Throwable $e) {
        // ignore; best-effort
    }
}

if ($rawBackupExcerpt === '' && !empty($structuredLogs)) {
    $formattedBackupLog = implode("\n", array_map(static function ($row) {
        $ts = !empty($row['ts']) ? '[' . $row['ts'] . '] ' : '';
        $level = !empty($row['level']) ? '(' . strtoupper((string) $row['level']) . ') ' : '';
        return $ts . $level . (string) ($row['message'] ?? '');
    }, $structuredLogs));
}

$jsonData = [
    'status' => 'success',
    'backup_log' => $formattedBackupLog,
    'validation_log' => $formattedValidationLog,
    'has_validation' => !empty($run['validation_log_excerpt']) && $run['validation_mode'] === 'post_run',
    'structured_logs' => $structuredLogs,
    'run_summary' => $runSummary,
];

$response = new JsonResponse($jsonData, 200);
$response->send();
exit();
