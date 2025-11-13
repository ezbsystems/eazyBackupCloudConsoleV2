<?php

use WHMCS\Database\Capsule;
use PartnerHub\WhmcsBridge;

function eb_ph_client_profile_update(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $cust = Capsule::table('eb_customers')->where('id',$customerId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$cust) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    $wcId = (int)$cust->whmcs_client_id;
    $payload = [];
    foreach (['firstname','lastname','companyname','email','address1','address2','city','state','postcode','country','phonenumber'] as $k) {
        if (array_key_exists($k, $_POST)) { $payload[$k] = (string)$_POST[$k]; }
    }
    // Trace for diagnostics
    try { $keys = implode(',', array_keys($_POST ?? [])); logActivity('eazybackup: ph-client-profile-update keys='.$keys.' wcId='.$wcId); } catch (\Throwable $__) {}
    try {
        $res = WhmcsBridge::updateClient($wcId, $payload, 'API');
        try { logModuleCall('eazybackup','ph-client-profile-update',$payload,$res); } catch (\Throwable $__) {}
        if (($res['result'] ?? '') === 'success') {
            try { logActivity('eazybackup: ph-client-profile-update success wcId='.$wcId); } catch (\Throwable $__) {}
            echo json_encode(['status'=>'success']);
            return;
        }
        echo json_encode(['status'=>'error','message'=>$res['message'] ?? 'Update failed']);
        return;
    } catch (\Throwable $e) {
        try { logActivity('eazybackup: ph-client-profile-update EX='.$e->getMessage()); } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}


