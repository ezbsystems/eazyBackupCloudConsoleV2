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
$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$status = $_POST['status'] ?? 'active';

// Validate required fields
if ($tenantId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Tenant is required'], 400))->send();
    exit;
}

// Verify tenant ownership
$tenant = MspController::getTenant($tenantId, $clientId);
if (!$tenant) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found'], 404))->send();
    exit;
}

if (empty($name)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Name is required'], 400))->send();
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Valid email is required'], 400))->send();
    exit;
}

if (strlen($password) < 8) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Password must be at least 8 characters'], 400))->send();
    exit;
}

// Check email uniqueness within tenant
$existing = Capsule::table('s3_backup_tenant_users')
    ->where('tenant_id', $tenantId)
    ->where('email', $email)
    ->first();

if ($existing) {
    (new JsonResponse(['status' => 'fail', 'message' => 'A user with this email already exists in this tenant'], 400))->send();
    exit;
}

$userId = Capsule::table('s3_backup_tenant_users')->insertGetId([
    'tenant_id' => $tenantId,
    'name' => $name,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => in_array($role, ['user', 'admin']) ? $role : 'user',
    'status' => in_array($status, ['active', 'disabled']) ? $status : 'active',
    'created_at' => Capsule::raw('NOW()'),
    'updated_at' => Capsule::raw('NOW()'),
]);

(new JsonResponse([
    'status' => 'success', 
    'user_id' => $userId,
    'message' => 'User created successfully'
], 200))->send();
exit;

