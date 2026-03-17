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

// Get tenants with stats and profile info. Use eb_tenants when present (eazybackup canonical).
$tenants = [];
if (Capsule::schema()->hasTable('eb_tenants')) {
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if ($msp) {
        $mspId = (int)$msp->id;
        $userCountSub = Capsule::schema()->hasTable('eb_tenant_users')
            ? '(SELECT COUNT(*) FROM eb_tenant_users WHERE tenant_id = t.id AND status = "active")'
            : '0';
        $tenantSelect = [
            Capsule::raw('t.public_id as id'),
            't.public_id',
            't.name',
            't.slug',
            't.status',
            Capsule::raw('NULL as ceph_uid'),
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
            Capsule::raw($userCountSub . ' as user_count'),
            Capsule::raw('(SELECT COUNT(*) FROM s3_cloudbackup_agents WHERE tenant_id = t.id AND status = "active") as agent_count'),
        ];

        if (!MspController::hasTenantPublicIds()) {
            $tenantSelect[0] = 't.id';
            unset($tenantSelect[1]);
            $tenantSelect = array_values($tenantSelect);
        }

        $tenants = Capsule::table('eb_tenants as t')
            ->where('t.msp_id', $mspId)
            ->where('t.status', '!=', 'deleted')
            ->select($tenantSelect)
            ->orderBy('t.name')
            ->get();
    }
} else {
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
}

(new JsonResponse(['status' => 'success', 'tenants' => $tenants], 200))->send();
exit;

