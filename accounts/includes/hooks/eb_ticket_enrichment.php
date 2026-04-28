<?php
/**
 * eazyBackup ticket enrichment hook.
 *
 * Whenever a new support ticket is opened with the eb_job_id custom field set
 * (i.e. via the "Open Support Ticket" button on the Job Report Modal), post a
 * brief admin-only ticket reply containing context that helps support triage:
 *   - last 5 jobs for this device, with status
 *   - device online state and last seen
 *   - vault usage summary
 *   - service status / package
 *
 * Disabled by default. Enable by defining EB_TICKET_ENRICHMENT_ENABLED in a
 * settings file or env wrapper, or by setting EB_TICKET_ENRICHMENT=1.
 *
 * To enable globally without code changes, drop a one-liner into .env (loaded
 * by the addon bootstrap) or wp-equivalent: EB_TICKET_ENRICHMENT=1 .
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

if (!function_exists('eb_ticket_enrichment_enabled')) {
    function eb_ticket_enrichment_enabled(): bool
    {
        if (defined('EB_TICKET_ENRICHMENT_ENABLED') && EB_TICKET_ENRICHMENT_ENABLED) return true;
        $v = (string)getenv('EB_TICKET_ENRICHMENT');
        return $v === '1' || strtolower($v) === 'true';
    }
}

add_hook('TicketOpen', 1, function ($vars) {
    if (!eb_ticket_enrichment_enabled()) return;

    $ticketId = (int)($vars['ticketid'] ?? 0);
    $userId   = (int)($vars['userid']   ?? 0);
    if ($ticketId <= 0) return;

    try {
        // Find the eb_job_id custom field id once
        $cf = Capsule::table('tblcustomfields')
            ->where('type', 'support')
            ->where('fieldname', 'eb_job_id')
            ->select('id')->first();
        if (!$cf) return;

        $val = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', (int)$cf->id)
            ->where('relid', $ticketId)
            ->select('value')->first();
        if (!$val || !$val->value) return;
        $jobId = (string)$val->value;

        $lines = [];
        $lines[] = '== eazyBackup context (auto-generated) ==';
        $lines[] = 'Job ID: ' . $jobId;

        // Look up the comet_jobs row (if your schema has it) for username + device
        $username = ''; $deviceHash = '';
        try {
            $row = Capsule::table('comet_jobs')
                ->where('id', $jobId)
                ->orWhere('job_id', $jobId)
                ->select('username', 'device_id')
                ->first();
            if ($row) {
                $username   = (string)($row->username ?? '');
                $deviceHash = (string)($row->device_id ?? '');
            }
        } catch (\Throwable $e) {}

        if ($username !== '') {
            $lines[] = 'Backup account: ' . $username;
            // Service + package
            try {
                $svc = Capsule::table('tblhosting as h')
                    ->leftJoin('tblproducts as p', 'p.id', '=', 'h.packageid')
                    ->where('h.username', $username)
                    ->where('h.userid', $userId)
                    ->select('h.id', 'h.domainstatus', 'h.regdate', 'p.name as product')
                    ->first();
                if ($svc) {
                    $lines[] = 'Service: #' . $svc->id . ' (' . ($svc->product ?? '') . ') status=' . ($svc->domainstatus ?? '');
                }
            } catch (\Throwable $e) {}
        }

        // Last 5 jobs for this device
        try {
            $q = Capsule::table('eb_jobs_recent_24h')
                ->where('username', $username);
            if ($deviceHash !== '') $q->where('device', $deviceHash);
            $recent = $q->orderBy('ended_at', 'desc')->limit(5)->get();
            if (count($recent)) {
                $lines[] = 'Last jobs (eb_jobs_recent_24h):';
                foreach ($recent as $r) {
                    $lines[] = '  - ' . date('Y-m-d H:i', (int)$r->ended_at) . '  ' . str_pad((string)$r->status, 8) . '  ' . (string)$r->job_type;
                }
            }
        } catch (\Throwable $e) {}

        // Device online state
        if ($deviceHash !== '' && $username !== '') {
            try {
                $dev = Capsule::table('comet_devices')
                    ->where('username', $username)
                    ->where('hash', $deviceHash)
                    ->select('name', 'is_active', 'platform_os', 'updated_at')
                    ->first();
                if ($dev) {
                    $lines[] = 'Device: ' . ($dev->name ?? '(unknown)') . '  os=' . ($dev->platform_os ?? '') . '  online=' . ((int)$dev->is_active ? 'yes' : 'no') . '  last_update=' . ($dev->updated_at ?? '');
                }
            } catch (\Throwable $e) {}
        }

        // Vault usage summary for this user
        if ($username !== '') {
            try {
                $vaults = Capsule::table('comet_vaults')
                    ->where('username', $username)
                    ->select('description', 'type', 'bytes')
                    ->limit(8)->get();
                if (count($vaults)) {
                    $total = 0;
                    foreach ($vaults as $v) { $total += (int)($v->bytes ?? 0); }
                    $lines[] = 'Vaults (' . count($vaults) . '): total=' . number_format($total / 1073741824, 2) . ' GiB';
                    foreach ($vaults as $v) {
                        $lines[] = '  - ' . ($v->description ?? '(no name)') . '  type=' . ($v->type ?? '') . '  size=' . number_format(((int)$v->bytes) / 1073741824, 2) . ' GiB';
                    }
                }
            } catch (\Throwable $e) {}
        }

        $note = implode("\n", $lines);

        // Post as an admin-only ticket reply / note. WHMCS's localAPI handles either path.
        try {
            $res = localAPI('AddTicketNote', [
                'ticketid' => $ticketId,
                'message'  => $note,
            ]);
            if (!is_array($res) || ($res['result'] ?? '') !== 'success') {
                logActivity('eb_ticket_enrichment: AddTicketNote failed for ticket ' . $ticketId);
            }
        } catch (\Throwable $e) {
            try { logActivity('eb_ticket_enrichment exception: ' . $e->getMessage()); } catch (\Throwable $__) {}
        }
    } catch (\Throwable $e) {
        try { logActivity('eb_ticket_enrichment top-level exception: ' . $e->getMessage()); } catch (\Throwable $__) {}
    }
});
