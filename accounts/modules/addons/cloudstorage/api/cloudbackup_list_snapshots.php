<?php
/**
 * Cloud Backup - List Snapshots for a Job
 * Returns list of Kopia snapshots (completed backup runs with manifest IDs)
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/UuidBinary.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$jobIdRaw = isset($_GET['job_id']) ? trim((string) $_GET['job_id']) : '';
if ($jobIdRaw === '' || !UuidBinary::isUuid($jobIdRaw)) {
    (new JsonResponse([
        'status' => 'error',
        'code' => 'invalid_identifier_format',
        'message' => 'job_id must be a valid UUID.',
    ], 400))->send();
    exit;
}
$jobIdNorm = UuidBinary::normalize($jobIdRaw);

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');

try {
    // Verify job belongs to client (UUID schema: job_id binary; legacy: id)
    $jobQuery = Capsule::table('s3_cloudbackup_jobs')->where('client_id', $clientId);
    if ($hasJobIdPk) {
        $jobQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr($jobIdNorm));
    } else {
        (new JsonResponse(['status' => 'error', 'message' => 'Job schema not supported'], 200))->send();
        exit;
    }
    $job = $jobQuery->first();

    if (!$job) {
        (new JsonResponse(['status' => 'error', 'message' => 'Job not found'], 200))->send();
        exit;
    }

    $hasManifestCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'manifest_id');
    $hasLogRefCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref');

    $runSelect = [
        'started_at as created_at',
        'stats_json',
    ];
    if ($hasManifestCol) {
        $runSelect[] = 'manifest_id';
    } elseif ($hasLogRefCol) {
        $runSelect[] = 'log_ref';
    }
    if ($hasRunIdCol) {
        $runSelect[] = Capsule::raw('BIN_TO_UUID(run_id) as run_id');
    } else {
        $runSelect[] = 'id as run_id';
    }

    $runsQuery = Capsule::table('s3_cloudbackup_runs')
        ->where('status', 'success')
        ->orderBy('started_at', 'desc')
        ->limit(100);
    if ($hasManifestCol) {
        $runsQuery->whereNotNull('manifest_id')->where('manifest_id', '!=', '');
    } elseif ($hasLogRefCol) {
        $runsQuery->whereNotNull('log_ref')->where('log_ref', '!=', '');
    }

    if ($hasJobIdPk) {
        $runsQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr($jobIdNorm));
    } else {
        (new JsonResponse(['status' => 'error', 'message' => 'Job schema not supported'], 200))->send();
        exit;
    }

    $runs = $runsQuery->get($runSelect);

    if (!$hasManifestCol && !$hasLogRefCol) {
        $runs = $runs->filter(static function ($run) {
            $stats = json_decode($run->stats_json ?? '', true) ?: [];
            return !empty($stats['manifest_id']);
        })->values();
    }

    // Transform runs to snapshot format
    $snapshots = $runs->map(function ($run) use ($job) {
        $stats = json_decode($run->stats_json, true) ?: [];
        $manifestId = '';
        if (!empty($run->manifest_id)) {
            $manifestId = (string) $run->manifest_id;
        } elseif (!empty($run->log_ref)) {
            $manifestId = (string) $run->log_ref;
        } elseif (!empty($stats['manifest_id'])) {
            $manifestId = (string) $stats['manifest_id'];
        }

        return [
            'manifest_id' => $manifestId,
            'created_at' => $run->created_at,
            'run_id' => (string) ($run->run_id ?? ''),
            'size_bytes' => $stats['bytes_total'] ?? $stats['total_bytes'] ?? 0,
            'size_human' => formatBytes($stats['bytes_total'] ?? $stats['total_bytes'] ?? 0),
            'file_count' => $stats['files_total'] ?? $stats['total_files'] ?? 0,
            'dir_count' => $stats['dirs_total'] ?? $stats['total_dirs'] ?? 0,
            'source_path' => $job->source_path ?? ''
        ];
    });

    (new JsonResponse(['status' => 'success', 'snapshots' => $snapshots], 200))->send();

} catch (Exception $e) {
    error_log("cloudbackup_list_snapshots error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to load snapshots'], 200))->send();
}
exit;

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
