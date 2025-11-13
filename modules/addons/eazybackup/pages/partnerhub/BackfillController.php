<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_invoices_refresh(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $cust = Capsule::table('eb_customers')->where('id',$customerId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$cust) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    try {
        $svc = new StripeService();
        $scus = (string)($cust->stripe_customer_id ?? '');
        if ($scus === '') { echo json_encode(['status'=>'success','message'=>'no-stripe-customer']); return; }
        $gte = time() - 86400 * 30; // last 30 days for manual refresh
        $invs = $svc->listInvoices($scus, $gte, 100);
        foreach (($invs['data'] ?? []) as $iv) {
            $invId = (string)($iv['id'] ?? ''); if ($invId === '') continue;
            Capsule::table('eb_invoice_cache')->updateOrInsert(
                ['stripe_invoice_id' => $invId],
                [
                    'customer_id' => (int)$cust->id,
                    'amount_total' => (int)($iv['amount_total'] ?? 0),
                    'amount_tax' => (int)($iv['tax'] ?? 0),
                    'status' => (string)($iv['status'] ?? ''),
                    'hosted_invoice_url' => (string)($iv['hosted_invoice_url'] ?? ''),
                    'created' => (int)($iv['created'] ?? 0),
                    'currency' => (string)($iv['currency'] ?? 'usd'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
        $chs = $svc->listCharges($scus, $gte, 100);
        foreach (($chs['data'] ?? []) as $ch) {
            $pi = (string)($ch['payment_intent'] ?? ''); if ($pi === '') continue;
            Capsule::table('eb_payment_cache')->updateOrInsert(
                ['stripe_payment_intent_id' => $pi],
                [
                    'customer_id' => (int)$cust->id,
                    'amount' => (int)($ch['amount'] ?? 0),
                    'currency' => (string)($ch['currency'] ?? 'usd'),
                    'status' => (string)($ch['status'] ?? ''),
                    'created' => (int)($ch['created'] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

function eb_ph_payouts_refresh(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp || (string)($msp->stripe_connect_id ?? '') === '') { echo json_encode(['status'=>'error','message'=>'no-account']); return; }
    try {
        $svc = new StripeService();
        $acct = (string)$msp->stripe_connect_id;
        $gte = time() - 86400 * 30;
        $p = $svc->listPayouts($acct, ['limit'=>100, 'arrival_date[gte]'=>$gte]);
        foreach (($p['data'] ?? []) as $po) {
            $pid = (string)($po['id'] ?? ''); if ($pid === '') continue;
            Capsule::table('eb_payouts')->updateOrInsert(
                ['stripe_payout_id'=>$pid],
                [
                    'msp_id' => (int)$msp->id,
                    'amount' => (int)($po['amount'] ?? 0),
                    'currency' => (string)($po['currency'] ?? 'usd'),
                    'status' => (string)($po['status'] ?? ''),
                    'arrival_date' => (int)($po['arrival_date'] ?? 0),
                    'created' => (int)($po['created'] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

function eb_ph_disputes_refresh(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp || (string)($msp->stripe_connect_id ?? '') === '') { echo json_encode(['status'=>'error','message'=>'no-account']); return; }
    try {
        $svc = new StripeService();
        $acct = (string)$msp->stripe_connect_id;
        $gte = time() - 86400 * 30;
        $d = $svc->listDisputes($acct, ['limit'=>100, 'created[gte]'=>$gte]);
        foreach (($d['data'] ?? []) as $dp) {
            $did = (string)($dp['id'] ?? ''); if ($did === '') continue;
            Capsule::table('eb_disputes')->updateOrInsert(
                ['stripe_dispute_id'=>$did],
                [
                    'msp_id' => (int)$msp->id,
                    'amount' => (int)($dp['amount'] ?? 0),
                    'currency' => (string)($dp['currency'] ?? 'usd'),
                    'reason' => (string)($dp['reason'] ?? ''),
                    'status' => (string)($dp['status'] ?? ''),
                    'evidence_due_by' => (int)($dp['evidence_details']['due_by'] ?? 0),
                    'charge_id' => (string)($dp['charge'] ?? ''),
                    'created' => (int)($dp['created'] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
        echo json_encode(['status'=>'success']);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

