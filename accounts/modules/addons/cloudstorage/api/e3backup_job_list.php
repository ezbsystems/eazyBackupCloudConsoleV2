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
$tenantFilter = $_GET['tenant_id'] ?? null;
$agentFilter = $_GET['agent_id'] ?? null;

try {
    $query = Capsule::table('s3_cloudbackup_jobs as j')
        ->join('s3_cloudbackup_agents as a', 'j.agent_id', '=', 'a.id')
        ->where('j.client_id', $clientId)
        ->where('j.status', '!=', 'deleted')
        ->select([
            'j.id',
            'j.name',
            'j.source_type',
            'j.source_path',
            'j.engine',
            'j.backup_mode',
            'j.schedule_type',
            'j.status',
            'j.created_at',
            'j.updated_at',
            'a.id as agent_id',
            'a.hostname as agent_hostname',
            'a.tenant_id',
        ]);

    if ($isMsp) {
        $query->leftJoin('s3_backup_tenants as t', 'a.tenant_id', '=', 't.id')
              ->addSelect('t.name as tenant_name');

        if ($tenantFilter !== null) {
            if ($tenantFilter === 'direct') {
                $query->whereNull('a.tenant_id');
            } elseif ((int)$tenantFilter > 0) {
                $query->where('a.tenant_id', (int)$tenantFilter);
            }
        }
    }

    if ($agentFilter && (int)$agentFilter > 0) {
        $query->where('j.agent_id', (int)$agentFilter);
    }

    $jobs = $query->orderByDesc('j.created_at')->get();

    (new JsonResponse(['status' => 'success', 'jobs' => $jobs], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load jobs'], 500))->send();
}
exit;

