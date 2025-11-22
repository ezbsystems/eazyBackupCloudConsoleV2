<?php
// File: modules/addons/eazybackup/get_service_username.php

use WHMCS\Database\Capsule;

require __DIR__ . '/../../../init.php';

$serviceId = isset($_GET['serviceid']) ? intval($_GET['serviceid']) : 0;

header('Content-Type: application/json');

if (!$serviceId) {
    echo json_encode(['error' => 'Invalid service ID']);
    exit;
}

// Retrieve the service row from tblhosting
$service = Capsule::table('tblhosting')
    ->where('id', $serviceId)
    ->first();

if (!$service) {
    echo json_encode(['error' => 'Service not found']);
    exit;
}

// Return the comet backup account username
echo json_encode(['username' => $service->username]);
exit;
