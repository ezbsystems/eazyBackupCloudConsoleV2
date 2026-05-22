<?php

/**
 * Pending signup approval count helpers (MSP-scoped).
 *
 * Used by the Partner Hub sidebar bell (hooks.php) and the White-Label tenants
 * slide-over (BuildController -> branding-list.tpl) to surface pending signup
 * approval counts without forcing the user to navigate to the queue page.
 */

use WHMCS\Database\Capsule;

if (!function_exists('eb_ph_pending_signups_summary_for_client')) {
    /**
     * Return pending-signup-approval counts for a given MSP (WHMCS client),
     * scoped to tenants owned by that client.
     *
     * @return array{total:int, by_tenant_tid:array<string,int>}
     */
    function eb_ph_pending_signups_summary_for_client(int $clientId): array
    {
        $out = ['total' => 0, 'by_tenant_tid' => []];
        if ($clientId <= 0) {
            return $out;
        }

        try {
            $rows = Capsule::table('eb_whitelabel_signup_events as e')
                ->join('eb_whitelabel_tenants as t', 't.id', '=', 'e.tenant_id')
                ->where('t.client_id', $clientId)
                ->where('e.status', 'pending_approval')
                ->groupBy('t.public_id')
                ->get([
                    't.public_id as public_id',
                    Capsule::raw('COUNT(*) as cnt'),
                ]);
        } catch (\Throwable $__) {
            return $out;
        }

        $total = 0;
        $byTid = [];
        foreach ($rows as $row) {
            $tid = (string)($row->public_id ?? '');
            $cnt = (int)($row->cnt ?? 0);
            if ($cnt <= 0) { continue; }
            $total += $cnt;
            if ($tid !== '') {
                $byTid[$tid] = $cnt;
            }
        }

        $out['total'] = $total;
        $out['by_tenant_tid'] = $byTid;
        return $out;
    }
}
