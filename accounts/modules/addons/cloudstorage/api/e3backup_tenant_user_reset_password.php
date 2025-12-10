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

$userId = (int)($_POST['user_id'] ?? 0);
$password = $_POST['password'] ?? '';

if ($userId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid user ID'], 400))->send();
    exit;
}

if (strlen($password) < 8) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Password must be at least 8 characters'], 400))->send();
    exit;
}

// Verify ownership via tenant
$user = Capsule::table('s3_backup_tenant_users as u')
    ->join('s3_backup_tenants as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('t.client_id', $clientId)
    ->first(['u.id']);

if (!$user) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 404))->send();
    exit;
}

Capsule::table('s3_backup_tenant_users')
    ->where('id', $userId)
    ->update([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

(new JsonResponse(['status' => 'success', 'message' => 'Password updated successfully'], 200))->send();
exit;

