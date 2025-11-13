<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_usage_push(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $metric = (string)($_POST['metric'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    $periodStart = (int)($_POST['period_start'] ?? 0);
    $periodEnd = (int)($_POST['period_end'] ?? 0);
    if ($customerId <= 0 || $metric === '' || $qty < 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    try {
        // Record in ledger
        $idKey = sha1($customerId.'|'.$metric.'|'.$periodStart.'|'.$periodEnd);
        Capsule::table('eb_usage_ledger')->updateOrInsert(
            ['idempotency_key' => $idKey],
            [
                'customer_id' => $customerId,
                'metric' => $metric,
                'qty' => $qty,
                'period_start' => date('Y-m-d H:i:s', $periodStart ?: time()),
                'period_end' => date('Y-m-d H:i:s', $periodEnd ?: time()),
                'source' => 'manual',
                'pushed_to_stripe_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        // Find active subscription and price for the metric, then push usage
        $sub = Capsule::table('eb_subscriptions')->where('customer_id',$customerId)->where('stripe_status','active')->orderBy('created_at','desc')->first();
        if (!$sub) { echo json_encode(['status'=>'success','message'=>'recorded-only']); return; }
        $priceRow = Capsule::table('eb_plan_prices')->where('id',(int)$sub->current_price_id)->first();
        if (!$priceRow || !(int)$priceRow->is_metered) { echo json_encode(['status'=>'success','message'=>'recorded-only']); return; }
        // Retrieve subscription to get item id
        $svc = new StripeService();
        $s = $svc->retrieveSubscription((string)$sub->stripe_subscription_id);
        $items = (array)($s['items']['data'] ?? []);
        $itemId = count($items) ? (string)($items[0]['id'] ?? '') : '';
        if ($itemId !== '') {
            $svc->createUsageRecord($itemId, $qty, time());
            Capsule::table('eb_usage_ledger')->where('idempotency_key',$idKey)->update([
                'pushed_to_stripe_at' => date('Y-m-d H:i:s'),
            ]);
        }
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}


