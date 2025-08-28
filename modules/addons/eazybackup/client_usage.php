<?php

use WHMCS\Database\Capsule;

// Start output buffering
ob_start();

require __DIR__ . '/../../../init.php';

$serviceId = isset($_GET['serviceid']) ? intval($_GET['serviceid']) : 0;
header('Content-Type: application/json');

if (!$serviceId) {
    echo json_encode(['error' => 'No service id provided']);
    exit;
}

$service = Capsule::table('tblhosting')->find($serviceId);
if (!$service) {
    echo json_encode(['error' => 'Service not found']);
    exit;
}

$username = $service->username;
if (!$username) {
    echo json_encode(['error' => 'No username found']);
    exit;
}

// Load Comet functions (use the corrected path)
require_once __DIR__ . "/../../../modules/servers/comet/functions.php";
require_once __DIR__ . "/../../../modules/servers/comet/summary_functions.php";

// Prepare parameters for the Comet API call
$params = comet_ProductParams($service->packageid);
$params['username'] = $username;

if ($params['serverhostname'] === null || $params['serverusername'] === null) {
    echo json_encode(['error' => 'Server configuration incomplete']);
    exit;
}

$user = comet_User($params);
if (is_string($user)) {
    echo json_encode(['error' => 'Error retrieving Comet user: ' . $user]);
    exit;
}

$deviceCount = comet_DeviceCount($user);
$totalStorageUsed = getUserStorage($username); // e.g. "950GB" or "1.2TB"

// Convert the formatted storage to a numeric value in GB.
$totalStorageGB = 0;
if (stripos($totalStorageUsed, 'TB') !== false) {
    $value = floatval($totalStorageUsed);
    $totalStorageGB = $value * 1000; // 1TB = 1000GB (or 1024 if needed)
} elseif (stripos($totalStorageUsed, 'GB') !== false) {
    $totalStorageGB = floatval($totalStorageUsed);
} else {
    $totalStorageGB = floatval($totalStorageUsed); // fallback
}

$protectedItemsSummary = getUserProtectedItemsSummary($username);
$totalAccountsCount = $protectedItemsSummary['totalAccountsCount'];

// Clear any stray output
ob_clean();

// Return the usage data as JSON
echo json_encode([
    'totalStorage'    => $totalStorageUsed,   // Formatted string (e.g., "950GB")
    'totalStorageGB'  => $totalStorageGB,     // Numeric value in GB (e.g., 950)
    'msAccountCount'  => $totalAccountsCount,
    'deviceCount'     => $deviceCount
]);
exit;
