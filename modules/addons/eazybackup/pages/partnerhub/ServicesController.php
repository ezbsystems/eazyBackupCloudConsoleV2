<?php

use WHMCS\Database\Capsule;

function eb_ph_services_link(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $serviceId = (int)($_POST['whmcs_service_id'] ?? 0);
    $cometUser = (string)($_POST['comet_user'] ?? '');
    $cust = Capsule::table('eb_customers')->where('id',$customerId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$cust || $serviceId <= 0 || $cometUser === '') { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    try {
        Capsule::table('eb_service_links')->updateOrInsert(
            ['whmcs_service_id' => $serviceId],
            [
                'msp_id' => (int)$msp->id,
                'customer_id' => (int)$cust->id,
                'comet_user_id' => $cometUser,
            ]
        );
        // Also tag comet mirrors where available
        try {
            Capsule::table('comet_users')->where('username',$cometUser)->update([
                'msp_id' => (int)$msp->id,
                'customer_id' => (int)$cust->id,
            ]);
        } catch (\Throwable $__) { /* ignore */ }
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}


