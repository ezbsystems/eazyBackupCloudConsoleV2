<?php
/**
 * Cloud NAS - Update Mount Status (Agent Callback)
 * Called by the agent to update the status of a mount operation.
 *
 * Important semantics:
 * - mounted = tray-owned Explorer-session mapping was verified by the agent
 * - error   = no desktop session, no tray, mapping failure, or verification failure
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

// Read JSON input first so auth can optionally consume body fallback fields.
$inputRaw = file_get_contents('php://input');
$input = $inputRaw ? json_decode($inputRaw, true) : [];

// Authenticate agent
$agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? ($input['agent_uuid'] ?? null));
$agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? ($input['agent_token'] ?? null));

if (!$agentUuid || !$agentToken) {
    respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->first();

if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
}

if (!$input) {
    respond(['status' => 'error', 'message' => 'Invalid request']);
}

$mountId = intval($input['mount_id'] ?? 0);
$status = trim($input['status'] ?? '');
$error = trim($input['error'] ?? '');

if ($mountId <= 0 || empty($status)) {
    respond(['status' => 'error', 'message' => 'mount_id and status are required']);
}

// Validate status
$validStatuses = ['pending', 'mounted', 'unmounted', 'mounting', 'unmounting', 'error'];
if (!in_array($status, $validStatuses)) {
    respond(['status' => 'error', 'message' => 'Invalid status']);
}

try {
    // Verify mount belongs to this agent
    $mount = Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->where('agent_id', (int) ($agent->id ?? 0))
        ->first();

    if (!$mount) {
        respond(['status' => 'error', 'message' => 'Mount not found']);
    }

    // Update status
    $updateData = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($status === 'mounted') {
        $updateData['last_mounted_at'] = date('Y-m-d H:i:s');
        $updateData['error'] = null;
    } elseif ($status === 'error') {
        $updateData['error'] = $error ?: 'Unknown error';
    } elseif ($status === 'unmounted') {
        $updateData['error'] = null;
        // Revoke temp key only after the agent confirms the drive is gone.
        $tempAccessKey = '';
        $tempKeyCephUid = '';
        if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')) {
            $tempAccessKey = trim((string) ($mount->temp_access_key ?? ''));
        }
        if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')) {
            $tempKeyCephUid = trim((string) ($mount->temp_key_ceph_uid ?? ''));
        }
        if ($tempAccessKey !== '' && $tempKeyCephUid !== '') {
            try {
                $settings = Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->whereIn('setting', ['s3_endpoint', 'ceph_access_key', 'ceph_secret_key'])
                    ->pluck('value', 'setting');
                $s3Endpoint = $settings['s3_endpoint'] ?? '';
                $adminAccessKey = $settings['ceph_access_key'] ?? '';
                $adminSecretKey = $settings['ceph_secret_key'] ?? '';
                if ($adminAccessKey !== '' && $adminSecretKey !== '') {
                    AdminOps::removeKey(
                        $s3Endpoint,
                        $adminAccessKey,
                        $adminSecretKey,
                        $tempAccessKey,
                        $tempKeyCephUid,
                        null
                    );
                }
            } catch (\Throwable $e) {
                error_log('cloudnas_update_status: temp key revoke warning: ' . $e->getMessage());
            }
            $updateData['temp_access_key'] = null;
            if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')) {
                $updateData['temp_key_ceph_uid'] = null;
            }
        }
    }

    Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->update($updateData);

    // #region agent log
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-acfd10.log', json_encode([
        'sessionId' => 'acfd10',
        'hypothesisId' => 'H2',
        'location' => 'cloudnas_update_status.php',
        'message' => 'mount status updated',
        'data' => ['mountId' => $mountId, 'status' => $status, 'error' => $error],
        'timestamp' => (int) round(microtime(true) * 1000),
        'runId' => 'unmount-fix',
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    // #endregion

    if ($status === 'error' || $status === 'mounted' || $status === 'unmounted') {
        error_log(sprintf(
            'cloudnas_update_status mount=%d agent_uuid=%s status=%s detail=%s',
            $mountId,
            (string) $agentUuid,
            $status,
            $error !== '' ? $error : '-'
        ));
    }

    respond(['status' => 'success', 'message' => 'Status updated']);

} catch (Exception $e) {
    error_log("cloudnas_update_status error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => 'Failed to update status']);
}
