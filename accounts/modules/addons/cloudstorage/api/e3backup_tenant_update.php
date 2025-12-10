<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Check MSP access
if (!MspController::isMspClient($clientId)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'MSP access required'], 403))->send();
    exit;
}

$tenantId = (int)($_POST['tenant_id'] ?? 0);

// Basic tenant info
$name = trim($_POST['name'] ?? '');
$status = $_POST['status'] ?? '';

// Profile/billing fields
$contactEmail = isset($_POST['contact_email']) ? strtolower(trim($_POST['contact_email'])) : null;
$contactName = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : null;
$contactPhone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : null;
$addressLine1 = isset($_POST['address_line1']) ? trim($_POST['address_line1']) : null;
$addressLine2 = isset($_POST['address_line2']) ? trim($_POST['address_line2']) : null;
$city = isset($_POST['city']) ? trim($_POST['city']) : null;
$state = isset($_POST['state']) ? trim($_POST['state']) : null;
$postalCode = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : null;
$country = isset($_POST['country']) ? trim($_POST['country']) : null;

if ($tenantId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid tenant ID'], 400))->send();
    exit;
}

// Verify ownership
$tenant = MspController::getTenant($tenantId, $clientId);
if (!$tenant) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found'], 404))->send();
    exit;
}

$update = ['updated_at' => Capsule::raw('NOW()')];

// Basic fields
if (!empty($name)) {
    $update['name'] = $name;
}

if (in_array($status, ['active', 'suspended'])) {
    $update['status'] = $status;
}

// Profile fields - allow empty strings to clear values
if ($contactEmail !== null) {
    if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Invalid contact email format'], 400))->send();
        exit;
    }
    $update['contact_email'] = $contactEmail ?: null;
}

if ($contactName !== null) {
    $update['contact_name'] = $contactName ?: null;
}

if ($contactPhone !== null) {
    $update['contact_phone'] = $contactPhone ?: null;
}

// Address fields
if ($addressLine1 !== null) {
    $update['address_line1'] = $addressLine1 ?: null;
}

if ($addressLine2 !== null) {
    $update['address_line2'] = $addressLine2 ?: null;
}

if ($city !== null) {
    $update['city'] = $city ?: null;
}

if ($state !== null) {
    $update['state'] = $state ?: null;
}

if ($postalCode !== null) {
    $update['postal_code'] = $postalCode ?: null;
}

if ($country !== null) {
    // Validate country code if provided (ISO 3166-1 alpha-2)
    if ($country !== '' && !preg_match('/^[A-Z]{2}$/i', $country)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Country must be a 2-letter ISO code'], 400))->send();
        exit;
    }
    $update['country'] = $country ? strtoupper($country) : null;
}

Capsule::table('s3_backup_tenants')
    ->where('id', $tenantId)
    ->update($update);

(new JsonResponse(['status' => 'success', 'message' => 'Tenant updated successfully'], 200))->send();
exit;
