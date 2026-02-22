<?php

require_once __DIR__ . '/../../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Session;

/**
 * Admin-only JSON endpoint for annual entitlement manual-assist actions.
 * Action: mark_paid_max - update max_paid_qty for a ledger row.
 */

if (!isset($_REQUEST['action']) || (string)$_REQUEST['action'] !== 'mark_paid_max') {
    $isDirect = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
    if ($isDirect) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Missing or invalid action']);
        exit;
    }
    return;
}

header('Content-Type: application/json');

function ae_ajax_json(bool $ok, array $data = [], string $msg = ''): void
{
    echo json_encode(['status' => $ok, 'data' => $data, 'message' => $msg]);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ae_ajax_json(false, [], 'POST required');
}

$token = trim((string)($_POST['token'] ?? ''));
if (!function_exists('check_token') || !check_token('plain', $token)) {
    ae_ajax_json(false, [], 'Invalid token');
}

try {
    $isAdmin = false;
    try {
        $isAdmin = (bool)(Session::get('adminid') ?? 0);
    } catch (\Throwable $e) {
        $isAdmin = false;
    }
    if (!$isAdmin) {
        ae_ajax_json(false, [], 'Admin only');
    }

    $serviceId = (int)($_POST['service_id'] ?? 0);
    $configId = (int)($_POST['config_id'] ?? 0);
    $cycleStart = trim((string)($_POST['cycle_start'] ?? ''));
    $newMaxPaidQty = (int)($_POST['new_max_paid_qty'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));

    if ($serviceId <= 0 || $configId <= 0 || $cycleStart === '' || $newMaxPaidQty < 0) {
        ae_ajax_json(false, [], 'Invalid input: service_id, config_id, cycle_start, new_max_paid_qty required');
    }

    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $cycleStart);
    if (!$date || $date->format('Y-m-d') !== $cycleStart) {
        ae_ajax_json(false, [], 'Invalid cycle_start format (expected Y-m-d)');
    }

    $addonPath = __DIR__ . '/../../modules/addons/eazybackup';
    $autoload = $addonPath . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        ae_ajax_json(false, [], 'Addon autoload not found');
    }
    require_once $autoload;

    $ledger = Capsule::table('eb_annual_entitlement_ledger')
        ->where('service_id', $serviceId)
        ->where('config_id', $configId)
        ->where('cycle_start', $cycleStart)
        ->first();

    if (!$ledger) {
        ae_ajax_json(false, [], 'No ledger row for service/config/cycle');
    }

    $currentMaxPaidQty = (int)$ledger->max_paid_qty;
    if ($newMaxPaidQty < $currentMaxPaidQty) {
        ae_ajax_json(false, [], 'new_max_paid_qty must be >= current max_paid_qty');
    }

    $usageQty = (int)$ledger->current_usage_qty;
    $configQty = (int)$ledger->current_config_qty;

    $decision = new \EazyBackup\Billing\AnnualEntitlementDecision();
    $eval = $decision->evaluate($usageQty, $configQty, $newMaxPaidQty);
    $newStatus = $eval['status'];
    $newRecommendedDelta = $eval['delta_to_charge'];

    $adminId = (int)(Session::get('adminid') ?? 0);

    Capsule::connection()->transaction(function () use (
        $serviceId,
        $configId,
        $cycleStart,
        $newMaxPaidQty,
        $newStatus,
        $newRecommendedDelta,
        $currentMaxPaidQty,
        $note,
        $adminId
    ) {
        Capsule::table('eb_annual_entitlement_ledger')
            ->where('service_id', $serviceId)
            ->where('config_id', $configId)
            ->where('cycle_start', $cycleStart)
            ->update([
                'max_paid_qty'      => $newMaxPaidQty,
                'status'            => $newStatus,
                'recommended_delta' => $newRecommendedDelta,
            ]);

        Capsule::table('eb_annual_entitlement_events')->insert([
            'service_id'      => $serviceId,
            'config_id'       => $configId,
            'cycle_start'     => $cycleStart,
            'event_type'      => 'manual_mark_paid',
            'old_max_paid_qty' => $currentMaxPaidQty,
            'new_max_paid_qty' => $newMaxPaidQty,
            'note'            => $note !== '' ? $note : null,
            'admin_id'        => $adminId > 0 ? $adminId : null,
        ]);
    });

    $updated = Capsule::table('eb_annual_entitlement_ledger')
        ->where('service_id', $serviceId)
        ->where('config_id', $configId)
        ->where('cycle_start', $cycleStart)
        ->first();

    ae_ajax_json(true, [
        'service_id'        => $serviceId,
        'config_id'         => $configId,
        'cycle_start'        => $cycleStart,
        'max_paid_qty'       => (int)$updated->max_paid_qty,
        'status'            => (string)$updated->status,
        'recommended_delta'  => $updated->recommended_delta !== null ? (int)$updated->recommended_delta : null,
        'current_usage_qty'  => (int)$updated->current_usage_qty,
        'current_config_qty' => (int)$updated->current_config_qty,
    ], 'Updated');
} catch (\Throwable $e) {
    try {
        logActivity('eazybackup: annualEntitlement_ajax error: ' . $e->getMessage());
    } catch (\Throwable $_) {
        /* ignore */
    }
    ae_ajax_json(false, [], 'Server error');
}
