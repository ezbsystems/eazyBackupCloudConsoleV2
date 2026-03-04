<?php

use WHMCS\Database\Capsule;

function eb_ph_services_link(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $tenantId = (int)($_POST['tenant_id'] ?? $_POST['customer_id'] ?? 0);
    $serviceId = (int)($_POST['whmcs_service_id'] ?? 0);
    $cometUser = (string)($_POST['comet_user'] ?? '');
    $tenant = Capsule::table('eb_tenants')->where('id',$tenantId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$tenant || $serviceId <= 0 || $cometUser === '') { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    try {
        Capsule::table('eb_service_links')->updateOrInsert(
            ['whmcs_service_id' => $serviceId],
            [
                'msp_id' => (int)$msp->id,
                'tenant_id' => (int)$tenant->id,
                'comet_user_id' => $cometUser,
            ]
        );
        try {
            Capsule::table('eb_tenant_comet_accounts')->updateOrInsert(
                ['comet_user_id' => $cometUser],
                ['tenant_id' => (int)$tenant->id]
            );
        } catch (\Throwable $__) { /* ignore */ }
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}


