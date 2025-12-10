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

if ($userId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid user ID'], 400))->send();
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

// Unassign agents from this user
Capsule::table('s3_cloudbackup_agents')
    ->where('tenant_user_id', $userId)
    ->update([
        'tenant_user_id' => null,
        'updated_at' => Capsule::raw('NOW()'),
    ]);

// Delete the user
Capsule::table('s3_backup_tenant_users')->where('id', $userId)->delete();

(new JsonResponse(['status' => 'success', 'message' => 'User deleted successfully'], 200))->send();
exit;

