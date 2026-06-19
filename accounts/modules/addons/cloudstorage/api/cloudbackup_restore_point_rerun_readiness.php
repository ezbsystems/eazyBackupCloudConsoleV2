<?php
/**
 * Phase 2B — re-evaluate the disk-image readiness gate against an existing
 * restore point's stored stats_json. Used to repair restore points that landed
 * in 'metadata_incomplete' due to a transient failure (e.g. lsblk hiccup) when
 * the underlying manifest is actually fine.
 *
 * Idempotent: only flips status when readiness now passes; never narrows.
 *
 * Auth: client must own the restore point (or be an MSP whose tenant covers it).
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$pointId = (int) ($_POST['restore_point_id'] ?? $_GET['restore_point_id'] ?? 0);
if ($pointId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'restore_point_id is required'], 200))->send();
    exit;
}

try {
    $rp = Capsule::table('s3_cloudbackup_restore_points')->where('id', $pointId)->first();
    if (!$rp) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Restore point not found'], 200))->send();
        exit;
    }
    if ((int) ($rp->client_id ?? 0) !== $clientId && !MspController::isMspClient($clientId)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Access denied'], 200))->send();
        exit;
    }
    if ((string) ($rp->engine ?? '') !== 'disk_image') {
        (new JsonResponse(['status' => 'skip', 'message' => 'Re-check only applies to disk_image restore points'], 200))->send();
        exit;
    }
    if ((string) ($rp->status ?? '') !== 'metadata_incomplete') {
        (new JsonResponse(['status' => 'noop', 'message' => 'Restore point already restorable'], 200))->send();
        exit;
    }

    // Find the originating run via run_uuid first, fall back to run_id.
    $run = null;
    $hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
    if (!empty($rp->run_uuid)) {
        $run = Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $rp->run_uuid)->first();
    }
    if (!$run && !empty($rp->run_id)) {
        if ($hasRunIdPk && UuidBinary::isUuid((string) $rp->run_id)) {
            $run = Capsule::table('s3_cloudbackup_runs')
                ->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize((string) $rp->run_id)))
                ->first();
        } else {
            $run = Capsule::table('s3_cloudbackup_runs')->where('id', $rp->run_id)->first();
        }
    }
    if (!$run) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Originating run not found'], 200))->send();
        exit;
    }

    $stats = [];
    if (!empty($run->stats_json)) {
        $decoded = json_decode((string) $run->stats_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $stats = $decoded;
        }
    }

    // Re-apply the same gate as recordRestorePointsForRun.
    $readiness = $stats['restore_readiness'] ?? null;
    $readinessStatus = strtolower((string) ($readiness['status'] ?? ''));
    $hasDiskLayout = isset($stats['disk_layout']) && is_array($stats['disk_layout']);
    $passes = ($readinessStatus !== 'metadata_incomplete') && $hasDiskLayout;

    if (!$passes) {
        (new JsonResponse([
            'status'  => 'noop',
            'message' => 'Readiness still fails. Backup must be re-run to repair this restore point.',
            'readiness_status' => $readinessStatus,
            'has_disk_layout' => $hasDiskLayout,
        ], 200))->send();
        exit;
    }

    // Promote: update status + refresh disk layout columns when present.
    $update = ['status' => 'success'];
    $layout = $stats['disk_layout'];
    if (isset($layout['total_bytes']))     { $update['disk_total_bytes'] = $layout['total_bytes']; }
    if (isset($layout['used_bytes']))      { $update['disk_used_bytes']  = $layout['used_bytes']; }
    if (isset($layout['boot_mode']))       { $update['disk_boot_mode']   = $layout['boot_mode']; }
    if (isset($layout['partition_style'])) { $update['disk_partition_style'] = $layout['partition_style']; }
    $update['disk_layout_json'] = json_encode($layout);

    Capsule::table('s3_cloudbackup_restore_points')->where('id', $pointId)->update($update);

    logModuleCall(
        'cloudstorage',
        'restore_point_rerun_readiness',
        ['restore_point_id' => $pointId, 'client_id' => $clientId],
        'promoted to success',
        [],
        []
    );

    (new JsonResponse([
        'status'  => 'success',
        'message' => 'Restore point promoted to success.',
        'restore_point_id' => $pointId,
        'new_status' => 'success',
    ], 200))->send();
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'restore_point_rerun_readiness_fail', ['restore_point_id' => $pointId], $e->getMessage(), [], []);
    (new JsonResponse(['status' => 'fail', 'message' => 'Internal error'], 200))->send();
}
