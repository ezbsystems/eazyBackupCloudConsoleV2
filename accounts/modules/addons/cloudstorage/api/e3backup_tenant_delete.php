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

// Soft delete: mark as deleted, disable all users, unassign agents
Capsule::transaction(function () use ($tenantId) {
    // Mark tenant as deleted
    Capsule::table('s3_backup_tenants')
        ->where('id', $tenantId)
        ->update([
            'status' => 'deleted',
            'updated_at' => Capsule::raw('NOW()'),
        ]);
    
    // Disable all tenant users
    Capsule::table('s3_backup_tenant_users')
        ->where('tenant_id', $tenantId)
        ->update([
            'status' => 'disabled',
            'updated_at' => Capsule::raw('NOW()'),
        ]);
    
    // Unassign agents from tenant (set tenant_id to NULL)
    Capsule::table('s3_cloudbackup_agents')
        ->where('tenant_id', $tenantId)
        ->update([
            'tenant_id' => null,
            'tenant_user_id' => null,
            'updated_at' => Capsule::raw('NOW()'),
        ]);
    
    // Revoke any unused enrollment tokens scoped to this tenant
    Capsule::table('s3_agent_enrollment_tokens')
        ->where('tenant_id', $tenantId)
        ->whereNull('revoked_at')
        ->update([
            'revoked_at' => Capsule::raw('NOW()'),
        ]);
});

(new JsonResponse(['status' => 'success', 'message' => 'Tenant deleted successfully'], 200))->send();
exit;

