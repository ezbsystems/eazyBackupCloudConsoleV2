<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';

function eb_ph_client_view(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-tenants-manage'); exit; }
    $tenantReference = trim((string)($_GET['id'] ?? ''));
    $tenant = eb_ph_tenants_find_owned_tenant_by_reference((int)$msp->id, $tenantReference);
    if (!$tenant || trim((string)($tenant->public_id ?? '')) === '') { header('Location: index.php?m=eazybackup&a=ph-tenants-manage'); exit; }

    eb_ph_tenant_redirect($vars, (string)$tenant->public_id, 'legacy=ph-client');
}


