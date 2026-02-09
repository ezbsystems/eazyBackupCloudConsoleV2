<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Provision\Provisioner;

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    if (isset($_GET['m']) && $_GET['m'] == 'cloudstorage') {
        $webRoot = $vars['WEB_ROOT'] ?? '';
        return <<<HTML
        <link href="{$webRoot}/modules/addons/cloudstorage/assets/css/datatables.css" rel="stylesheet" type="text/css" />
        <link href="{$webRoot}/modules/addons/cloudstorage/assets/css/responsive.css" rel="stylesheet" type="text/css" />
        <link href="{$webRoot}/modules/addons/cloudstorage/assets/css/scrollbar.css" rel="stylesheet" type="text/css" />
        
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/moment.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/popper.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/jquery.dataTables.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/custom.js"></script>
    HTML;
    }

});

add_hook('AdminServicesTabFields', 1, function($vars) {
    $clientId = (int)($vars['userid'] ?? 0);
    $packageId = (int)($vars['packageid'] ?? 0);
    $serviceId = (int)($vars['serviceid'] ?? ($vars['id'] ?? 0));
    if ($serviceId <= 0) {
        $serviceId = (int)($_REQUEST['id'] ?? ($_REQUEST['productselect'] ?? 0));
    }
    if (($clientId <= 0 || $packageId <= 0) && $serviceId > 0) {
        try {
            $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if ($svc) {
                if ($clientId <= 0 && isset($svc->userid)) {
                    $clientId = (int) $svc->userid;
                }
                if ($packageId <= 0 && isset($svc->packageid)) {
                    $packageId = (int) $svc->packageid;
                }
            }
        } catch (\Throwable $e) {}
    }
    if ($clientId <= 0 || $packageId <= 0) {
        return [];
    }

    $configuredPid = (int) Provisioner::getSetting('pid_cloud_storage', ProductConfig::$E3_PRODUCT_ID);
    if ($configuredPid <= 0) {
        $configuredPid = (int) ProductConfig::$E3_PRODUCT_ID;
    }
    if ($packageId !== $configuredPid) {
        return [];
    }

    $trialStatus = 'trial';
    $storageTier = '';
    $serviceUsername = '';
    if ($serviceId > 0) {
        try {
            $serviceUsername = (string) (Capsule::table('tblhosting')->where('id', $serviceId)->value('username') ?? '');
        } catch (\Throwable $e) {}
    }
    try {
        if (Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            $row = Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->first();
            if ($row) {
                $trialStatus = strtolower((string)($row->trial_status ?? 'trial'));
                $storageTier = strtolower((string)($row->storage_tier ?? ''));
            }
        }
    } catch (\Throwable $e) {}

    $trialSelected = ($trialStatus !== 'paid') ? 'selected' : '';
    $paidSelected = ($trialStatus === 'paid') ? 'selected' : '';
    $tierLabel = $storageTier ? ('Current tier: ' . htmlspecialchars($storageTier)) : 'Current tier: unknown';
    if ($serviceUsername !== '') {
        $tierLabel .= ' | Service Username: ' . htmlspecialchars($serviceUsername);
    }

    $html = '<select name="cloudstorage_trial_status" class="form-control select-inline">';
    $html .= '<option value="trial" ' . $trialSelected . '>Trial (No CC)</option>';
    $html .= '<option value="paid" ' . $paidSelected . '>Paid (CC on file)</option>';
    $html .= '</select>';
    $html .= '<div class="text-muted" style="margin-top:6px;">' . $tierLabel . '</div>';

    return [
        'Account Type' => $html,
    ];
});

add_hook('AdminServicesTabFieldsSave', 1, function($vars) {
    $clientId = (int)($vars['userid'] ?? 0);
    $packageId = (int)($vars['packageid'] ?? 0);
    $serviceId = (int)($vars['serviceid'] ?? ($vars['id'] ?? 0));
    if ($serviceId <= 0) {
        $serviceId = (int)($_REQUEST['id'] ?? ($_REQUEST['productselect'] ?? 0));
    }
    if (($clientId <= 0 || $packageId <= 0) && $serviceId > 0) {
        try {
            $svc = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if ($svc) {
                if ($clientId <= 0 && isset($svc->userid)) {
                    $clientId = (int) $svc->userid;
                }
                if ($packageId <= 0 && isset($svc->packageid)) {
                    $packageId = (int) $svc->packageid;
                }
            }
        } catch (\Throwable $e) {}
    }
    try {
        logModuleCall('cloudstorage', 'admin_services_tab_fields_save_entry', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'service_id' => $serviceId,
            'client_id' => $clientId,
            'package_id' => $packageId,
            'req_id' => $_REQUEST['id'] ?? null,
            'req_productselect' => $_REQUEST['productselect'] ?? null,
        ], [
            'posted_trial_status' => $_POST['cloudstorage_trial_status'] ?? null,
        ]);
    } catch (\Throwable $e) {}
    if ($clientId <= 0 || $packageId <= 0) {
        try { logModuleCall('cloudstorage', 'admin_services_tab_fields_save_skip_missing_ids', ['service_id' => $serviceId, 'client_id' => $clientId, 'package_id' => $packageId], 'missing ids'); } catch (\Throwable $e) {}
        return;
    }

    $configuredPid = (int) Provisioner::getSetting('pid_cloud_storage', ProductConfig::$E3_PRODUCT_ID);
    if ($configuredPid <= 0) {
        $configuredPid = (int) ProductConfig::$E3_PRODUCT_ID;
    }
    if ($packageId !== $configuredPid) {
        try { logModuleCall('cloudstorage', 'admin_services_tab_fields_save_skip_pid', ['service_id' => $serviceId, 'package_id' => $packageId, 'configured_pid' => $configuredPid], 'pid mismatch'); } catch (\Throwable $e) {}
        return;
    }

    $newStatus = strtolower(trim((string)($_POST['cloudstorage_trial_status'] ?? '')));
    if (!in_array($newStatus, ['trial', 'paid'], true)) {
        try { logModuleCall('cloudstorage', 'admin_services_tab_fields_save_skip_status', ['service_id' => $serviceId, 'client_id' => $clientId], ['posted_trial_status' => $newStatus]); } catch (\Throwable $e) {}
        return;
    }

    $storageTier = '';
    try {
        if (Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            $row = Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->first();
            $storageTier = $row ? strtolower((string)($row->storage_tier ?? '')) : '';

            $now = date('Y-m-d H:i:s');
            if ($row) {
                Capsule::table('cloudstorage_trial_selection')
                    ->where('client_id', $clientId)
                    ->update([
                        'trial_status' => $newStatus,
                        'updated_at'   => $now,
                    ]);
            } else {
                Capsule::table('cloudstorage_trial_selection')->insert([
                    'client_id'      => $clientId,
                    'product_choice' => 'storage',
                    'storage_tier'   => $storageTier ?: null,
                    'trial_status'   => $newStatus,
                    'meta'           => json_encode([], JSON_UNESCAPED_SLASHES),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'admin_trial_status_save_error', ['clientId' => $clientId], $e->getMessage(), [], []);
        return;
    }

    if ($newStatus === 'paid' || ($newStatus === 'trial' && $storageTier === 'trial_limited')) {
        $endpoint = (string) Provisioner::getSetting('s3_endpoint', '');
        $accessKey = (string) Provisioner::getSetting('ceph_access_key', '');
        $secretKey = (string) Provisioner::getSetting('ceph_secret_key', '');
        if ($endpoint && $accessKey && $secretKey) {
            $serviceUsername = '';
            if ($serviceId > 0) {
                try {
                    $serviceUsername = (string) (Capsule::table('tblhosting')->where('id', $serviceId)->value('username') ?? '');
                } catch (\Throwable $e) {}
            }
            try {
                if ($serviceUsername === '') {
                    $serviceUsername = (string) (Capsule::table('tblclients')->where('id', $clientId)->value('email') ?? '');
                }
            } catch (\Throwable $e) {}
            $serviceUsername = preg_replace('/[^a-z0-9._@-]+/', '', strtolower($serviceUsername));
            if ($serviceUsername === '') {
                $serviceUsername = 'e3user' . $clientId;
            }

            $quotaUid = $serviceUsername;
            $quotaTenant = null;
            try {
                $quotaUser = DBController::getUser($serviceUsername);
                if ($quotaUser && !empty($quotaUser->ceph_uid)) {
                    $quotaUid = (string) $quotaUser->ceph_uid;
                    $quotaTenant = !empty($quotaUser->tenant_id) ? (string) $quotaUser->tenant_id : null;
                }
            } catch (\Throwable $e) {}

            if ($quotaUid !== '') {
                if ($newStatus === 'paid') {
                    $quota = AdminOps::setUserQuota($endpoint, $accessKey, $secretKey, [
                        'uid' => $quotaUid,
                        'tenant' => $quotaTenant,
                        'enabled' => false,
                    ]);
                    logModuleCall('cloudstorage', 'admin_trial_status_quota_remove', ['uid' => $quotaUid, 'tenant' => $quotaTenant], $quota, [], []);
                } elseif ($storageTier === 'trial_limited') {
                    $quota = AdminOps::setUserQuota($endpoint, $accessKey, $secretKey, [
                        'uid' => $quotaUid,
                        'tenant' => $quotaTenant,
                        'enabled' => true,
                        'max_size_kb' => AdminOps::USER_TRIAL_QUOTA_KB,
                    ]);
                    logModuleCall('cloudstorage', 'admin_trial_status_quota_apply', ['uid' => $quotaUid, 'tenant' => $quotaTenant, 'max_size_kb' => AdminOps::USER_TRIAL_QUOTA_KB], $quota, [], []);
                }
            }
        }
    }
});

add_hook('EmailPreSend132', 1, function($vars) {
    $serviceId = $vars['relid'];
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();

    if (is_null($service)) {
        return;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $merge_fields = [];
    if ($vars['messagename'] == 'Cancellation Request Confirmation' && $service->packageid == $packageId) {
        $merge_fields['abortsend'] = true;
    }
    return $merge_fields;
});

add_hook('CancellationRequest132', 1, function($vars) {
    $serviceId = $vars['relid'];

    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();

    if (is_null($service)) {
        return;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;

    // Check if the specific product is cancelled
    if ($service->packageid == $packageId) {
        // Fetch client details
        $client = Capsule::table('tblclients')
            ->where('id', $service->userid)
            ->first();

        if (is_null($client)) {
            return;
        }

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        if (is_null($product)) {
            return;
        }

        // Define email merge fields
        // $mergeFields = [
        //     'client_name' => $client->firstname . ' ' . $client->lastname,
        //     'client_email' => $client->email,
        //     'service_product' => $product->name,
        //     'service_status' => $vars['status'],
        // ];

        localAPI('SendAdminEmail', [
            'messagename' => "E3 Cloud Storage Service Cancelled Email",
            // 'customvars' => base64_encode(serialize($mergeFields)),
        ]);
    }
});

/**
 * Password change/reset diagnostics
 * - Logs user/client password change events for troubleshooting cases where
 *   password-based validation (for unlocking access keys) appears to fail.
 */
add_hook('UserChangePassword', 1, function($vars) {
    // $vars commonly contains: userId, email
    $context = [
        'hook' => 'UserChangePassword',
        'userId' => $vars['userId'] ?? null,
        'email' => $vars['email'] ?? null,
    ];
    logModuleCall('cloudstorage', 'PasswordChange', $context, 'User password changed', null, []);
});

add_hook('ClientChangePassword', 1, function($vars) {
    // $vars commonly contains: userid (client ID)
    $context = [
        'hook' => 'ClientChangePassword',
        'clientId' => $vars['userid'] ?? null,
    ];
    logModuleCall('cloudstorage', 'PasswordChange', $context, 'Client password changed', null, []);
});

