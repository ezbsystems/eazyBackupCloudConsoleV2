<?php

require_once __DIR__ . '/../../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Session;

if (!isset($_REQUEST['ajax_action']) || (string)$_REQUEST['ajax_action'] !== 'save_billing_flags') {
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

$token = trim((string)($_POST['token'] ?? ''));
if (!function_exists('check_token') || !check_token('plain', $token)) {
    echo json_encode(['status' => false, 'message' => 'Invalid token']);
    exit;
}

$isAdmin = false;
try {
    $isAdmin = (bool)(Session::get('adminid') ?? 0);
} catch (\Throwable $e) {
    $isAdmin = false;
}
if (!$isAdmin) {
    echo json_encode(['status' => false, 'message' => 'Admin only']);
    exit;
}

$service_id = (int)($_POST['service_id'] ?? 0);
$storage_exempt = isset($_POST['storage_exempt']) ? (int)$_POST['storage_exempt'] : 0;
$devices_exempt = isset($_POST['devices_exempt']) ? (int)$_POST['devices_exempt'] : 0;
$notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

if ($service_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid service_id']);
    exit;
}

$storage_exempt = $storage_exempt ? 1 : 0;
$devices_exempt = $devices_exempt ? 1 : 0;

$service = Capsule::table('tblhosting')->where('id', $service_id)->first();
if (!$service) {
    echo json_encode(['status' => false, 'message' => 'Service not found']);
    exit;
}

if (!Capsule::schema()->hasTable('eb_billing_flags')) {
    echo json_encode(['status' => false, 'message' => 'Billing flags table not available']);
    exit;
}

try {
    Capsule::table('eb_billing_flags')->updateOrInsert(
        ['service_id' => $service_id],
        [
            'storage_exempt' => $storage_exempt,
            'devices_exempt' => $devices_exempt,
            'notes'          => $notes === '' ? null : $notes,
        ]
    );
    echo json_encode(['status' => true, 'message' => 'Saved']);
} catch (\Throwable $e) {
    echo json_encode(['status' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
}
