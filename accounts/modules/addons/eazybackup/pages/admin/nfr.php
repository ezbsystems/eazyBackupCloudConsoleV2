<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\Nfr;

// Handle actions
$__hasPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($__hasPost) {
    try {
        $dbg = $_POST;
        if (isset($dbg['token'])) { $dbg['token'] = '***redacted***'; }
        $dbgMeta = [
            'has_token' => isset($_POST['token']) ? 1 : 0,
            'action_hint' => (string)($_POST['nfr_action'] ?? ''),
            'id_hint' => (int)($_POST['id'] ?? 0),
        ];
        logModuleCall('eazybackup','nfr_admin_post',$dbg,$dbgMeta);
    } catch (\Throwable $___) {}
}
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('check_token') && check_token('WHMCS.admin.default')) {
    $action = (string)($_POST['nfr_action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        try { logModuleCall('eazybackup','nfr_admin_action',['action'=>$action,'id'=>$id],[]); } catch (\Throwable $___) {}
        $row = Capsule::table('eb_nfr')->where('id', $id)->first();
        if ($row) {
            if ($action === 'approve') {
                $pid = (int)($_POST['product_id'] ?? 0);
                if ($pid <= 0) { throw new \RuntimeException('Product is required'); }
                $dur = (int)($_POST['duration_days'] ?? Nfr::defaultDurationDays());
                $quota = (int)($_POST['approved_quota_gib'] ?? Nfr::defaultQuotaGiB());
                $cap = ($_POST['device_cap'] === '' ? null : max(0, (int)$_POST['device_cap']));
                $start = date('Y-m-d');
                $end   = date('Y-m-d', strtotime('+' . max(1,$dur) . ' days'));
                $upd = [
                    'status' => 'approved',
                    'product_id' => $pid,
                    'approved_quota_gib' => $quota,
                    'device_cap' => $cap,
                    'duration_days' => $dur,
                    'start_date' => $start,
                    'end_date' => $end,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                try { logModuleCall('eazybackup','nfr_admin_approve_begin',['id'=>$id],$upd); } catch (\Throwable $___) {}
                Capsule::table('eb_nfr')->where('id', $id)->update($upd);
                try { logModuleCall('eazybackup','nfr_admin_approve_done',['id'=>$id],['ok'=>1]); } catch (\Throwable $___) {}
                // Notify client
                @localAPI('SendEmail', [
                    'customtype' => 'general',
                    'customsubject' => 'Your NFR application was approved',
                    'custommessage' => "Approved. Product PID: {$pid}.\nStart: {$start}\nEnd: {$end}",
                    'to' => (string)$row->work_email,
                ]);
                // Auto-provision immediately on approval
                try {
                    $paymentMethod = (string)(Capsule::table('tblclients')->where('id', (int)$row->client_id)->value('defaultgateway') ?? '');
                    if ($paymentMethod === '') { $paymentMethod = 'stripe'; }
                    $addOrderPayload = [
                        'clientid' => (int)$row->client_id,
                        'pid' => [$pid],
                        'billingcycle' => 'monthly',
                        'paymentmethod' => $paymentMethod,
                        'noinvoice' => true,
                        'noemail' => true,
                    ];
                    try { logModuleCall('eazybackup','nfr_approve_addorder',$addOrderPayload,[]); } catch (\Throwable $___) {}
                    $order = @localAPI('AddOrder', $addOrderPayload, 'API');
                    try { logModuleCall('eazybackup','nfr_approve_addorder_resp',$addOrderPayload,$order); } catch (\Throwable $___) {}
                    if (($order['result'] ?? '') !== 'success') { throw new \RuntimeException('Order failed: ' . (string)($order['message'] ?? 'unknown')); }
                    $acceptPayload = [
                        'orderid' => $order['orderid'] ?? 0,
                        'autosetup' => true,
                        'sendemail' => true,
                    ];
                    $reqUser = (string)($row->requested_username ?? '');
                    if ($reqUser !== '') { $acceptPayload['serviceusername'] = $reqUser; }
                    $reqPass = (string)($row->requested_password ?? '');
                    if ($reqPass !== '') { $acceptPayload['servicepassword'] = $reqPass; }
                    try { logModuleCall('eazybackup','nfr_approve_acceptorder',$acceptPayload,[]); } catch (\Throwable $___) {}
                    $accept = @localAPI('AcceptOrder', $acceptPayload, 'API');
                    try { logModuleCall('eazybackup','nfr_approve_acceptorder_resp',$acceptPayload,$accept); } catch (\Throwable $___) {}
                    if (($accept['result'] ?? '') !== 'success') { throw new \RuntimeException('Accept failed: ' . (string)($accept['message'] ?? 'unknown')); }
                    $service = Capsule::table('tblhosting')->where('orderid', (int)$order['orderid'])->first();
                    try { logModuleCall('eazybackup','nfr_approve_service',['orderid'=>$order['orderid']??0], $service ? (array)$service : ['service'=>null]); } catch (\Throwable $___) {}
                    Capsule::table('eb_nfr')->where('id', $id)->update([
                        'status' => 'provisioned',
                        'service_id' => $service ? (int)$service->id : null,
                        'service_username' => $service ? (string)$service->username : null,
                        'requested_password' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    // Apply quotas: device cap + storage quota (GiB → bytes) across all vaults
                    try {
                        if ($service) {
                            $params = comet_ServiceParams((int)$service->id);
                            $params['username'] = (string)$service->username;
                            $server = comet_Server($params);
                            $ph = $server->AdminGetUserProfileAndHash($params['username']);
                            $prof = $ph->Profile;
                            $devCapSet = (int)$cap;
                            if ($devCapSet < 0) { $devCapSet = 0; }
                            $prof->MaximumDevices = $devCapSet;
                            $quotaGiBSet = (int)$quota;
                            if (isset($prof->Destinations) && is_array((array)$prof->Destinations)) {
                                foreach ($prof->Destinations as $did => $dest) {
                                    if (!is_object($dest)) { continue; }
                                    if ($quotaGiBSet > 0) {
                                        $dest->StorageLimitEnabled = true;
                                        $dest->StorageLimitBytes = (int)($quotaGiBSet * 1024 * 1024 * 1024);
                                    } else {
                                        $dest->StorageLimitEnabled = false;
                                        $dest->StorageLimitBytes = 0;
                                    }
                                }
                            }
                            try { logModuleCall('eazybackup','nfr_approve_set_quotas_start',['id'=>$id],['devCap'=>$devCapSet,'quotaGiB'=>$quotaGiBSet]); } catch (\Throwable $___) {}
                            $set = $server->AdminSetUserProfileHash($params['username'], $prof, $ph->ProfileHash);
                            try { logModuleCall('eazybackup','nfr_approve_set_quotas_resp',[], $set ? (array)$set : ['resp'=>null]); } catch (\Throwable $___) {}
                        }
                    } catch (\Throwable $qex) {
                        try { logModuleCall('eazybackup','nfr_approve_set_quotas_error',['id'=>$id],['error'=>$qex->getMessage()]); } catch (\Throwable $___) {}
                    }
                } catch (\Throwable $ex) {
                    try { logModuleCall('eazybackup','nfr_approve_autoprovision_error',['id'=>$id],['error'=>$ex->getMessage()]); } catch (\Throwable $___) {}
                    // Leave as approved if provisioning failed; admin can retry with Provision button
                }
                // Optional ticket
                if (Nfr::autoCreateTicket()) {
                    @localAPI('OpenTicket', [
                        'clientid' => (int)$row->client_id,
                        'deptid' => 1,
                        'subject' => 'NFR Approval',
                        'message' => 'Your NFR has been approved. This ticket tracks any onboarding questions.',
                        'priority'=> 'Low',
                    ], 'API');
                }
                $notice = '<div class="alert alert-success">Approved application #' . (int)$id . '</div>';
            } elseif ($action === 'reject') {
                Capsule::table('eb_nfr')->where('id', $id)->update(['status' => 'rejected', 'updated_at'=>date('Y-m-d H:i:s')]);
                @localAPI('SendEmail', [
                    'customtype' => 'general',
                    'customsubject' => 'NFR application status',
                    'custommessage' => 'We are unable to approve your NFR application at this time.',
                    'to' => (string)$row->work_email,
                ]);
                $notice = '<div class="alert alert-success">Rejected application #' . (int)$id . '</div>';
            } elseif ($action === 'update_active') {
                // Update end_date, approved_quota_gib, device_cap and apply to Comet
                $newEnd = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';
                $newQuota = isset($_POST['approved_quota_gib']) && $_POST['approved_quota_gib'] !== '' ? max(0, (int)$_POST['approved_quota_gib']) : null;
                $newCap = isset($_POST['device_cap']) && $_POST['device_cap'] !== '' ? max(0, (int)$_POST['device_cap']) : null;
                $upd = [ 'updated_at' => date('Y-m-d H:i:s') ];
                if ($newEnd === '') { $upd['end_date'] = null; } else { $upd['end_date'] = $newEnd; }
                if ($newQuota !== null) { $upd['approved_quota_gib'] = $newQuota; }
                if ($newCap !== null) { $upd['device_cap'] = $newCap; }
                Capsule::table('eb_nfr')->where('id', $id)->update($upd);
                try { logModuleCall('eazybackup','nfr_update_active',['id'=>$id],$upd); } catch (\Throwable $___) {}
                // Apply quota updates to Comet if service exists
                try {
                    if ((int)($row->service_id ?? 0) > 0) {
                        $service = Capsule::table('tblhosting')->where('id', (int)$row->service_id)->first();
                        if ($service) {
                            $params = comet_ServiceParams((int)$service->id);
                            $params['username'] = (string)$service->username;
                            $server = comet_Server($params);
                            $ph = $server->AdminGetUserProfileAndHash($params['username']);
                            $prof = $ph->Profile;
                            if ($newCap !== null) { $prof->MaximumDevices = (int)$newCap; }
                            if ($newQuota !== null && isset($prof->Destinations) && is_array((array)$prof->Destinations)) {
                                foreach ($prof->Destinations as $did => $dest) {
                                    if (!is_object($dest)) { continue; }
                                    if ($newQuota > 0) {
                                        $dest->StorageLimitEnabled = true;
                                        $dest->StorageLimitBytes = (int)($newQuota * 1024 * 1024 * 1024);
                                    } else {
                                        $dest->StorageLimitEnabled = false;
                                        $dest->StorageLimitBytes = 0;
                                    }
                                }
                            }
                            $resp = $server->AdminSetUserProfileHash($params['username'], $prof, $ph->ProfileHash);
                            try { logModuleCall('eazybackup','nfr_update_active_apply',['id'=>$id], $resp ? (array)$resp : ['resp'=>null]); } catch (\Throwable $___) {}
                        }
                    }
                } catch (\Throwable $ex) {
                    try { logModuleCall('eazybackup','nfr_update_active_error',['id'=>$id], ['error'=>$ex->getMessage()]); } catch (\Throwable $___) {}
                }
                $notice = '<div class="alert alert-success">Updated #' . (int)$id . '</div>';
            } elseif ($action === 'provision') {
                $pid = (int)($row->product_id ?? 0);
                if ($pid <= 0) { throw new \RuntimeException('Product not set. Approve first.'); }
                // Resolve payment method; fallback to 'stripe' or system default
                $paymentMethod = (string)(Capsule::table('tblclients')->where('id', (int)$row->client_id)->value('defaultgateway') ?? '');
                if ($paymentMethod === '') { $paymentMethod = 'stripe'; }
                $addOrderPayload = [
                    'clientid' => (int)$row->client_id,
                    'pid' => [$pid],
                    'billingcycle' => 'monthly',
                    'paymentmethod' => $paymentMethod,
                    'noinvoice' => true,
                    'noemail' => true,
                ];
                try { logModuleCall('eazybackup','nfr_provision_addorder',$addOrderPayload,[]); } catch (\Throwable $___) {}
                $order = @localAPI('AddOrder', $addOrderPayload, 'API');
                try { logModuleCall('eazybackup','nfr_provision_addorder_resp',$addOrderPayload,$order); } catch (\Throwable $___) {}
                if (($order['result'] ?? '') !== 'success') { throw new \RuntimeException('Order failed'); }
                $acceptPayload = [
                    'orderid' => $order['orderid'] ?? 0,
                    'autosetup' => true,
                    'sendemail' => true,
                ];
                $reqUser = (string)($row->requested_username ?? '');
                if ($reqUser !== '') { $acceptPayload['serviceusername'] = $reqUser; }
                $reqPass = (string)($row->requested_password ?? '');
                if ($reqPass !== '') { $acceptPayload['servicepassword'] = $reqPass; }
                try { logModuleCall('eazybackup','nfr_provision_acceptorder',$acceptPayload,[]); } catch (\Throwable $___) {}
                $accept = @localAPI('AcceptOrder', $acceptPayload, 'API');
                try { logModuleCall('eazybackup','nfr_provision_acceptorder_resp',$acceptPayload,$accept); } catch (\Throwable $___) {}
                if (($accept['result'] ?? '') !== 'success') { throw new \RuntimeException('Accept failed'); }
                $service = Capsule::table('tblhosting')->where('orderid', (int)$order['orderid'])->first();
                try { logModuleCall('eazybackup','nfr_provision_service',['orderid'=>$order['orderid']??0], $service ? (array)$service : ['service'=>null]); } catch (\Throwable $___) {}
                Capsule::table('eb_nfr')->where('id', $id)->update([
                    'status' => 'provisioned',
                    'service_id' => $service ? (int)$service->id : null,
                    'service_username' => $service ? (string)$service->username : null,
                    'requested_password' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                // Attempt to apply device and storage quotas immediately
                try {
                    if ($service) {
                        $params = comet_ServiceParams((int)$service->id);
                        $params['username'] = (string)$service->username;
                        $server = comet_Server($params);
                        $ph = $server->AdminGetUserProfileAndHash($params['username']);
                        $prof = $ph->Profile;
                        // Device cap
                        $devCap = (int)($row->device_cap ?? 0);
                        if ($devCap < 0) { $devCap = 0; }
                        $prof->MaximumDevices = $devCap;
                        // Vault quota from approved_quota_gib (0 => unlimited)
                        $quotaGiB = (int)($row->approved_quota_gib ?? 0);
                        if (isset($prof->Destinations) && is_array((array)$prof->Destinations)) {
                            foreach ($prof->Destinations as $did => $dest) {
                                if (!is_object($dest)) { continue; }
                                if ($quotaGiB > 0) {
                                    $dest->StorageLimitEnabled = true;
                                    $dest->StorageLimitBytes = (int)($quotaGiB * 1024 * 1024 * 1024);
                                } else {
                                    $dest->StorageLimitEnabled = false;
                                    $dest->StorageLimitBytes = 0;
                                }
                            }
                        }
                        try { logModuleCall('eazybackup','nfr_provision_set_quotas_start',['id'=>$id], ['devCap'=>$devCap,'quotaGiB'=>$quotaGiB]); } catch (\Throwable $___) {}
                        $set = $server->AdminSetUserProfileHash($params['username'], $prof, $ph->ProfileHash);
                        try { logModuleCall('eazybackup','nfr_provision_set_quotas_resp',[], $set ? (array)$set : ['resp'=>null]); } catch (\Throwable $___) {}
                    }
                } catch (\Throwable $ex) {
                    try { logModuleCall('eazybackup','nfr_provision_set_quotas_error',['id'=>$id],['error'=>$ex->getMessage()]); } catch (\Throwable $___) {}
                }
                $notice = '<div class="alert alert-success">Provisioned service for #' . (int)$id . '</div>';
            } elseif ($action === 'suspend') {
                if ((int)$row->service_id > 0) { @localAPI('ModuleSuspend', ['accountid' => (int)$row->service_id, 'suspendreason'=>'NFR expired/suspended'], 'API'); }
                Capsule::table('eb_nfr')->where('id', $id)->update(['status' => 'suspended', 'updated_at'=>date('Y-m-d H:i:s')]);
                $notice = '<div class="alert alert-success">Suspended #' . (int)$id . '</div>';
            } elseif ($action === 'resume') {
                if ((int)$row->service_id > 0) { @localAPI('ModuleUnsuspend', ['accountid' => (int)$row->service_id], 'API'); }
                Capsule::table('eb_nfr')->where('id', $id)->update(['status' => 'approved', 'updated_at'=>date('Y-m-d H:i:s')]);
                $notice = '<div class="alert alert-success">Resumed #' . (int)$id . '</div>';
            } elseif ($action === 'convert') {
                $behavior = Nfr::conversionBehavior();
                $toPid = (int)($_POST['convert_pid'] ?? ($behavior['pid'] ?? 0));
                if ($toPid <= 0) { throw new \RuntimeException('Conversion PID required'); }
                $order = @localAPI('AddOrder', [
                    'clientid' => (int)$row->client_id,
                    'pid' => [$toPid],
                    'billingcycle' => 'monthly',
                    'noinvoice' => false,
                ]);
                if (($order['result'] ?? '') !== 'success') { throw new \RuntimeException('Order failed'); }
                @localAPI('AcceptOrder', [ 'orderid' => $order['orderid'] ?? 0, 'autosetup' => true, 'sendemail' => true ], 'API');
                Capsule::table('eb_nfr')->where('id', $id)->update(['status' => 'converted', 'updated_at'=>date('Y-m-d H:i:s')]);
                $notice = '<div class="alert alert-success">Converted #' . (int)$id . ' to PID ' . (int)$toPid . '</div>';
            } elseif ($action === 'expire_now') {
                Capsule::table('eb_nfr')->where('id', $id)->update(['status' => 'expired', 'end_date'=>date('Y-m-d'), 'updated_at'=>date('Y-m-d H:i:s')]);
                $notice = '<div class="alert alert-success">Expired #' . (int)$id . '</div>';
            }
        }
    } catch (\Throwable $e) {
        $notice = '<div class="alert alert-danger">Action failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'applications';
if (!in_array($tab, ['applications','active','all'], true)) { $tab = 'applications'; }

$products = [];
foreach (Nfr::productIds() as $pid) {
    $name = Capsule::table('tblproducts')->where('id', $pid)->value('name');
    if ($name) { $products[] = ['id' => (int)$pid, 'name' => (string)$name]; }
}

$rows = [];
try {
    $q = Capsule::table('eb_nfr')->orderBy('created_at','desc');
    if ($tab === 'applications') { $q->where('status','pending'); }
    elseif ($tab === 'active') {
        // Treat suspended as active; only remove from Active when explicitly expired
        $q->whereIn('status',['approved','provisioned','suspended']);
    }
    $rows = $q->get();
} catch (\Throwable $_) {}

return [
    'notice' => $notice,
    'tab' => $tab,
    'products' => $products,
    'rows' => $rows,
];


