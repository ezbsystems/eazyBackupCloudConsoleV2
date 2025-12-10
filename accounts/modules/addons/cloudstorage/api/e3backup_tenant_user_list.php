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

$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

$query = Capsule::table('s3_backup_tenant_users as u')
    ->join('s3_backup_tenants as t', 'u.tenant_id', '=', 't.id')
    ->where('t.client_id', $clientId)
    ->where('t.status', '!=', 'deleted')
    ->select([
        'u.id',
        'u.tenant_id',
        'u.name',
        'u.email',
        'u.role',
        'u.status',
        'u.last_login_at',
        'u.created_at',
        't.name as tenant_name',
    ]);

if ($tenantId !== null && $tenantId > 0) {
    $query->where('u.tenant_id', $tenantId);
}

$users = $query->orderBy('t.name')->orderBy('u.name')->get();

(new JsonResponse(['status' => 'success', 'users' => $users], 200))->send();
exit;

