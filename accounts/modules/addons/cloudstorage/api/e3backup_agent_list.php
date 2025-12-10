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

$isMsp = MspController::isMspClient($clientId);
$tenantFilter = isset($_GET['tenant_id']) ? $_GET['tenant_id'] : null;

// Build query
$query = Capsule::table('s3_cloudbackup_agents as a')
    ->where('a.client_id', $clientId)
    ->select([
        'a.id',
        'a.client_id',
        'a.hostname',
        'a.status',
        'a.agent_type',
        'a.tenant_id',
        'a.tenant_user_id',
        'a.last_seen_at',
        'a.created_at',
        'a.updated_at',
    ]);

if ($isMsp) {
    $query->leftJoin('s3_backup_tenants as t', 'a.tenant_id', '=', 't.id')
          ->addSelect('t.name as tenant_name');
    
    // Apply tenant filter
    if ($tenantFilter !== null) {
        if ($tenantFilter === 'direct') {
            $query->whereNull('a.tenant_id');
        } elseif ((int)$tenantFilter > 0) {
            $query->where('a.tenant_id', (int)$tenantFilter);
        }
    }
}

$agents = $query->orderByDesc('a.created_at')->get();

(new JsonResponse(['status' => 'success', 'agents' => $agents], 200))->send();
exit;

