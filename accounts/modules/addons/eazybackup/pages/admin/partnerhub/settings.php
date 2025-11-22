<?php

use WHMCS\Database\Capsule;

// Handle POST save
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_msp_fee']) && function_exists('check_token') && check_token('WHMCS.admin.default')) {
    try {
        $mspId = (int)($_POST['msp_id'] ?? 0);
        $val = trim((string)($_POST['default_fee_percent'] ?? ''));
        if ($mspId > 0) {
            $fee = $val === '' ? null : (float)$val;
            if ($fee !== null) {
                if ($fee < 0 || $fee > 100) { throw new \InvalidArgumentException('Fee must be between 0 and 100'); }
                $fee = round($fee, 2);
            }
            Capsule::table('eb_msp_accounts')->where('id',$mspId)->update([
                'default_fee_percent' => $fee,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $notice = '<div class="alert alert-success">Saved.</div>';
        }
    } catch (\Throwable $e) {
        $notice = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

// Load MSP rows
$rows = Capsule::table('eb_msp_accounts as m')
    ->leftJoin('tblclients as c','c.id','=','m.whmcs_client_id')
    ->orderBy('m.id','asc')
    ->get(['m.*','c.companyname','c.firstname','c.lastname','c.email']);

return [
    'notice' => $notice,
    'rows' => $rows,
];


