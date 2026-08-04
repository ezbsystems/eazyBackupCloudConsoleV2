<?php

require_once __DIR__ . '/../../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Session;
use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

if (!isset($_REQUEST['ajax_action']) || (string) $_REQUEST['ajax_action'] !== 'save_billing_flags') {
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Missing or invalid ajax_action']);
        exit;
    }
    return;
}

header('Content-Type: application/json');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'POST required']);
    exit;
}

$token = trim((string) ($_POST['token'] ?? ''));
if (!function_exists('check_token') || !check_token('plain', $token)) {
    echo json_encode(['status' => false, 'message' => 'Invalid token']);
    exit;
}

$isAdmin = false;
try {
    $isAdmin = (bool) (Session::get('adminid') ?? 0);
} catch (\Throwable $e) {
    $isAdmin = false;
}
if (!$isAdmin) {
    echo json_encode(['status' => false, 'message' => 'Admin only']);
    exit;
}

$service_id = (int) ($_POST['service_id'] ?? 0);
$billing_exempt = isset($_POST['billing_exempt']) ? (int) $_POST['billing_exempt'] : 0;
$notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';

if ($service_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid service_id']);
    exit;
}

$billing_exempt = $billing_exempt ? 1 : 0;

$service = Capsule::table('tblhosting')->where('id', $service_id)->first();
if (!$service) {
    echo json_encode(['status' => false, 'message' => 'Service not found']);
    exit;
}

$pidCloudStorage = 48;
try {
    $configured = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'pid_cloud_storage')
        ->value('value');
    if ((int) $configured > 0) {
        $pidCloudStorage = (int) $configured;
    }
} catch (\Throwable $e) {
    // keep legacy fallback
}

if ((int) ($service->packageid ?? 0) !== $pidCloudStorage) {
    echo json_encode(['status' => false, 'message' => 'Not a Cloud Storage service']);
    exit;
}

if (!Capsule::schema()->hasTable('s3_billing_flags')) {
    echo json_encode(['status' => false, 'message' => 'Billing flags table not available']);
    exit;
}

try {
    Capsule::table('s3_billing_flags')->updateOrInsert(
        ['service_id' => $service_id],
        [
            'billing_exempt' => $billing_exempt,
            'notes' => $notes === '' ? null : $notes,
        ]
    );

    $immediate = null;
    if ($billing_exempt === 1) {
        $s3UserId = s3_billing_flags_resolve_primary_user_id((string) ($service->username ?? ''));
        if ($s3UserId <= 0) {
            // Flag is saved; amount still forced to $0 on the hosting row.
            Capsule::table('tblhosting')->where('id', $service_id)->update(['amount' => 0.00]);
            echo json_encode([
                'status' => true,
                'message' => 'Saved (no s3_users mapping; hosting amount zeroed)',
                'immediate' => ['hosting_zeroed' => true, 'prices_zeroed' => false],
            ]);
            exit;
        }

        $billing = new S3Billing();
        $immediate = $billing->applyComplimentaryBilling($service, $s3UserId);
        try {
            logModuleCall('cloudstorage', 's3_billing_flags_immediate_zero', [
                'service_id' => $service_id,
                's3_user_id' => $s3UserId,
            ], $immediate);
        } catch (\Throwable $_) {
        }
    }

    echo json_encode([
        'status' => true,
        'message' => 'Saved',
        'immediate' => $immediate,
    ]);
} catch (\Throwable $e) {
    echo json_encode(['status' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
}

/**
 * Resolve primary s3_users.id from a WHMCS service username.
 */
function s3_billing_flags_resolve_primary_user_id(string $primaryUsername): int
{
    if ($primaryUsername === '') {
        return 0;
    }

    $primaryUser = Capsule::table('s3_users')
        ->where('username', $primaryUsername)
        ->whereNull('parent_id')
        ->first();

    if (!$primaryUser) {
        $tenantRow = Capsule::table('s3_users')
            ->where('username', $primaryUsername)
            ->whereNotNull('parent_id')
            ->first();
        if ($tenantRow && isset($tenantRow->parent_id)) {
            $primaryUser = Capsule::table('s3_users')->where('id', (int) $tenantRow->parent_id)->first();
        }
    }

    return $primaryUser ? (int) $primaryUser->id : 0;
}
