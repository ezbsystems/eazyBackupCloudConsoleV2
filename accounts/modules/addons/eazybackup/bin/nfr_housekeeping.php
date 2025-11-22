<?php

// Nightly NFR expiry + conversion enforcement

require_once __DIR__ . '/bootstrap.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\Nfr;

date_default_timezone_set('UTC');

function out($s){ fwrite(STDOUT, '['.date('c')."] $s\n"); }

try {
    $today = date('Y-m-d');
    $rows = Capsule::table('eb_nfr')
        ->whereIn('status', ['approved','provisioned'])
        ->whereNotNull('end_date')
        ->where('end_date', '<', $today)
        ->get();

    $behavior = Nfr::conversionBehavior();
    foreach ($rows as $r) {
        $id = (int)$r->id;
        $clientId = (int)$r->client_id;
        $svcId = (int)($r->service_id ?? 0);
        if ($behavior['mode'] === 'suspend') {
            if ($svcId > 0) { @localAPI('ModuleSuspend', ['accountid' => $svcId, 'suspendreason'=>'NFR expired'], 'API'); }
            Capsule::table('eb_nfr')->where('id',$id)->update(['status'=>'expired','updated_at'=>date('Y-m-d H:i:s')]);
            out("Suspended expired NFR #$id (service $svcId)");
        } elseif ($behavior['mode'] === 'convert' && (int)($behavior['pid'] ?? 0) > 0) {
            $toPid = (int)$behavior['pid'];
            $order = @localAPI('AddOrder', [
                'clientid' => $clientId,
                'pid' => [$toPid],
                'billingcycle' => 'monthly',
            ], 'API');
            if (($order['result'] ?? '') === 'success') {
                @localAPI('AcceptOrder', ['orderid'=>$order['orderid'] ?? 0, 'autosetup'=>true, 'sendemail'=>true], 'API');
                Capsule::table('eb_nfr')->where('id',$id)->update(['status'=>'converted','updated_at'=>date('Y-m-d H:i:s')]);
                out("Converted expired NFR #$id to PID $toPid");
            } else {
                out("Failed to convert NFR #$id: " . json_encode($order));
            }
        } else {
            Capsule::table('eb_nfr')->where('id',$id)->update(['status'=>'expired','updated_at'=>date('Y-m-d H:i:s')]);
            out("Marked NFR #$id expired (no action)");
        }
    }
} catch (Throwable $e) {
    out('Error: ' . $e->getMessage());
    exit(1);
}


