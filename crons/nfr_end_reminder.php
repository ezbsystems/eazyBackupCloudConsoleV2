<?php

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;

date_default_timezone_set(@date_default_timezone_get());

try {
    $settings = [];
    foreach (Capsule::table('tbladdonmodules')->where('module','eazybackup')->get() as $row) {
        $settings[$row->setting] = $row->value;
    }
    $adminTo = (string)($settings['nfr_admin_email'] ?? $settings['nfr_admin_notify_email'] ?? '');
    $tplSel = (string)($settings['nfr_end_admin_email_template'] ?? '');
    try { logModuleCall('eazybackup','nfr_end_reminder_boot', [], ['to'=>$adminTo !== '' ? $adminTo : '(empty)', 'tplSel'=>$tplSel]); } catch (\Throwable $___) {}

    if ($adminTo === '') {
        // Nothing to do without recipient
        try { logModuleCall('eazybackup','nfr_end_reminder_skip', [], ['reason'=>'admin recipient empty']); } catch (\Throwable $___) {}
        return;
    }

    $today = date('Y-m-d');
    // Eligible statuses: approved, provisioned, suspended (not expired)
    $rows = Capsule::table('eb_nfr')
        ->whereIn('status', ['approved','provisioned','suspended'])
        ->whereNotNull('end_date')
        ->where('end_date', '<=', $today)
        ->whereNull('end_reminder_sent_at')
        ->get();
    try {
        $meta = ['count' => is_iterable($rows) ? count($rows) : 0];
        $ids = [];
        foreach ($rows as $r) { $ids[] = ['id'=>(int)$r->id,'client_id'=>(int)$r->client_id,'status'=>(string)$r->status,'end_date'=>(string)$r->end_date]; }
        logModuleCall('eazybackup','nfr_end_reminder_candidates', $meta, $ids);
        // Diagnostic: show rows that match status/date regardless of marker
        $all = Capsule::table('eb_nfr')
            ->whereIn('status', ['approved','provisioned','suspended'])
            ->whereNotNull('end_date')
            ->where('end_date','<=',$today)
            ->get();
        $allIds = [];
        foreach ($all as $r) { $allIds[] = ['id'=>(int)$r->id,'marker'=>($r->end_reminder_sent_at ? 'sent' : 'null')]; }
        logModuleCall('eazybackup','nfr_end_reminder_all_match', ['count'=>is_iterable($all)?count($all):0], $allIds);
    } catch (\Throwable $___) {}

    foreach ($rows as $r) {
        $subject = 'NFR end date reached: ' . ((string)($r->company_name ?? '')) . ' (Client ' . (int)$r->client_id . ')';
        $body = "An NFR grant has reached its end date.\n\n"
              . 'ID: ' . (int)$r->id . "\n"
              . 'Client ID: ' . (int)$r->client_id . "\n"
              . 'Company: ' . ((string)$r->company_name) . "\n"
              . 'Username: ' . ((string)($r->service_username ?? $r->requested_username ?? '')) . "\n"
              . 'Status: ' . ((string)$r->status) . "\n"
              . 'Start: ' . ((string)($r->start_date ?? '')) . "\n"
              . 'End: ' . ((string)($r->end_date ?? '')) . "\n"
              . 'Approved Quota (GiB): ' . ($r->approved_quota_gib !== null ? (int)$r->approved_quota_gib : 0) . "\n"
              . 'Device Cap: ' . ($r->device_cap !== null ? (int)$r->device_cap : 0) . "\n\n"
              . 'Admin: View in Addons → eazyBackup → NFR (Active NFRs).';

        $payload = [ 'to' => $adminTo ];
        if ($tplSel !== '') {
            if (strpos($tplSel, ':') !== false) {
                // Format "type:id" → fetch template subject/body and send as custom admin email
                list($tType, $tId) = explode(':', $tplSel, 2);
                $tType = trim((string)$tType);
                $tId = (string)trim((string)$tId);
                try {
                    $res = localAPI('GetEmailTemplates', ['type' => $tType]);
                    if (isset($res['emailtemplates']['emailtemplate']) && is_array($res['emailtemplates']['emailtemplate'])) {
                        foreach ($res['emailtemplates']['emailtemplate'] as $tpl) {
                            if ((string)($tpl['id'] ?? '') === $tId) {
                                $subject = (string)($tpl['subject'] ?? $subject);
                                $body    = (string)($tpl['message'] ?? $body);
                                break;
                            }
                        }
                    }
                } catch (\Throwable $__) {}
                $payload['customsubject'] = $subject;
                $payload['custommessage'] = $body;
            } else {
                // Back-compat: a plain admin template name
                $payload['messagename'] = $tplSel;
            }
        } else {
            $payload['customsubject'] = $subject;
            $payload['custommessage'] = $body;
        }
        try { logModuleCall('eazybackup','nfr_end_reminder_sending', ['id'=>(int)$r->id], ['to'=>$adminTo,'tplSel'=>$tplSel,'subject'=>$subject,'bodyLen'=>strlen($body)]); } catch (\Throwable $___) {}

        // Prefer SendEmail (general) so it appears in Email Log; fallback to SendAdminEmail
        $resp = localAPI('SendEmail', [
            'customtype'    => 'general',
            'customsubject' => $subject,
            'custommessage' => $body,
            'to'            => $adminTo,
        ]);
        try { logModuleCall('eazybackup','nfr_end_reminder_send_email', ['id'=>(int)$r->id], $resp); } catch (\Throwable $___) {}
        $ok = (is_array($resp) && (($resp['result'] ?? '') === 'success'));
        if (!$ok) {
            $resp = localAPI('SendAdminEmail', $payload);
            try { logModuleCall('eazybackup','nfr_end_reminder_send_admin', ['id'=>(int)$r->id], $resp); } catch (\Throwable $___) {}
            $ok = (is_array($resp) && (($resp['result'] ?? '') === 'success' || ($resp['Status'] ?? 0) < 400));
        }
        if ($ok) {
            Capsule::table('eb_nfr')->where('id', (int)$r->id)->update([
                'end_reminder_sent_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            try { logModuleCall('eazybackup','nfr_end_reminder_marked', ['id'=>(int)$r->id], ['marked'=>true]); } catch (\Throwable $___) {}
        }
    }
    try { logModuleCall('eazybackup','nfr_end_reminder_done', [], ['processed'=>true]); } catch (\Throwable $___) {}
} catch (\Throwable $e) {
    try { logModuleCall('eazybackup','nfr_end_reminder_error', [], ['error'=>$e->getMessage()]); } catch (\Throwable $___) {}
}


