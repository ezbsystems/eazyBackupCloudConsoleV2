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

// Get tenants with stats and profile info
$tenants = Capsule::table('s3_backup_tenants as t')
    ->where('t.client_id', $clientId)
    ->where('t.status', '!=', 'deleted')
    ->select([
        't.id',
        't.name',
        't.slug',
        't.status',
        't.ceph_uid',
        't.contact_email',
        't.contact_name',
        't.contact_phone',
        't.address_line1',
        't.address_line2',
        't.city',
        't.state',
        't.postal_code',
        't.country',
        't.created_at',
        't.updated_at',
        Capsule::raw('(SELECT COUNT(*) FROM s3_backup_tenant_users WHERE tenant_id = t.id AND status = "active") as user_count'),
        Capsule::raw('(SELECT COUNT(*) FROM s3_cloudbackup_agents WHERE tenant_id = t.id AND status = "active") as agent_count'),
    ])
    ->orderBy('t.name')
    ->get();

(new JsonResponse(['status' => 'success', 'tenants' => $tenants], 200))->send();
exit;

