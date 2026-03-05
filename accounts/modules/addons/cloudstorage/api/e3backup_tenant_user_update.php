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

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !function_exists('check_token')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'CSRF validation failed'], 400))->send();
    exit;
}
try {
    if (!check_token('plain', $token)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'CSRF validation failed'], 400))->send();
        exit;
    }
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'CSRF validation failed'], 400))->send();
    exit;
}

$clientId = $ca->getUserID();
$tenantTable = MspController::getTenantTableName();
$tenantUsersTable = MspController::getTenantUsersTableName();

// Check MSP access
if (!MspController::isMspClient($clientId)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'MSP access required'], 403))->send();
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$role = $_POST['role'] ?? '';
$status = $_POST['status'] ?? '';

if ($userId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid user ID'], 400))->send();
    exit;
}

// Verify ownership via tenant
$userQuery = Capsule::table($tenantUsersTable . ' as u')
    ->join($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('t.status', '!=', 'deleted')
    ->select(['u.*']);
MspController::scopeTenantOwnership($userQuery, 't', (int)$clientId);
$user = $userQuery->first();

if (!$user) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
    exit;
}

$update = ['updated_at' => Capsule::raw('NOW()')];

if (!empty($name)) {
    $update['name'] = $name;
}

if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Check uniqueness if email changed
    if ($email !== $user->email) {
        $existing = Capsule::table($tenantUsersTable)
            ->where('tenant_id', $user->tenant_id)
            ->where('email', $email)
            ->where('id', '!=', $userId)
            ->first();
        
        if ($existing) {
            (new JsonResponse(['status' => 'fail', 'message' => 'A user with this email already exists'], 400))->send();
            exit;
        }
    }
    $update['email'] = $email;
}

if (in_array($role, ['user', 'admin'])) {
    $update['role'] = $role;
}

if (in_array($status, ['active', 'disabled'])) {
    $update['status'] = $status;
}

Capsule::table($tenantUsersTable)
    ->where('id', $userId)
    ->update($update);

(new JsonResponse(['status' => 'success', 'message' => 'User updated successfully'], 200))->send();
exit;

