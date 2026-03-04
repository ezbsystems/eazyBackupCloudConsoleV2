<?php

use WHMCS\Database\Capsule;

function eb_ph_client_profile_update(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $tenantId = (int)($_POST['tenant_id'] ?? $_POST['customer_id'] ?? 0);
    $tenant = Capsule::table('eb_tenants')->where('id',$tenantId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$tenant) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    $up = [];
    if (array_key_exists('companyname', $_POST) || array_key_exists('firstname', $_POST) || array_key_exists('lastname', $_POST)) {
        $name = trim((string)($_POST['companyname'] ?? $tenant->name ?? ''));
        if ($name === '' && (array_key_exists('firstname', $_POST) || array_key_exists('lastname', $_POST))) {
            $name = trim((string)($_POST['firstname'] ?? '').' '.(string)($_POST['lastname'] ?? ''));
        }
        if ($name !== '') { $up['name'] = $name; }
    }
    if (array_key_exists('firstname', $_POST) || array_key_exists('lastname', $_POST)) {
        $up['contact_name'] = trim((string)($_POST['firstname'] ?? '').' '.(string)($_POST['lastname'] ?? ''));
    }
    foreach (['contact_email'=>'email','contact_phone'=>'phonenumber','address_line1'=>'address1','address_line2'=>'address2','city'=>'city','state'=>'state','postal_code'=>'postcode','country'=>'country'] as $col => $key) {
        if (array_key_exists($key, $_POST)) { $up[$col] = (string)$_POST[$key]; }
    }
    if (empty($up)) { echo json_encode(['status'=>'success']); return; }
    try {
        Capsule::table('eb_tenants')->where('id', (int)$tenant->id)->update(array_merge($up, ['updated_at' => date('Y-m-d H:i:s')]));
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        try { logActivity('eazybackup: ph-client-profile-update EX='.$e->getMessage()); } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}


