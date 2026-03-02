<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_usage_tenant_period_idempotency_key(int $tenantId, string $metric, int $periodStart, int $periodEnd): string
{
    return sha1(
        'tenant:' . max(0, $tenantId)
        . '|metric:' . trim($metric)
        . '|period_start:' . max(0, $periodStart)
        . '|period_end:' . max(0, $periodEnd)
    );
}

function eb_usage_normalize_period_bounds(int $periodStart, int $periodEnd): array
{
    $tz = new \DateTimeZone('UTC');
    $now = new \DateTimeImmutable('now', $tz);
    $monthStart = new \DateTimeImmutable($now->format('Y-m-01 00:00:00'), $tz);

    $resolvedPeriodStart = ($periodStart > 0) ? $periodStart : $monthStart->getTimestamp();
    $resolvedPeriodEnd = ($periodEnd > 0) ? $periodEnd : $monthStart->modify('+1 month')->getTimestamp();

    if ($resolvedPeriodEnd <= $resolvedPeriodStart) {
        throw new \InvalidArgumentException('invalid_period');
    }

    return [$resolvedPeriodStart, $resolvedPeriodEnd];
}

function eb_usage_record_timestamp_for_period(int $periodStart, int $periodEnd): int
{
    if ($periodEnd > $periodStart && $periodEnd > 1) {
        return $periodEnd - 1;
    }
    return max(1, $periodStart);
}

function eb_ph_usage_push(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp || (int) ($msp->id ?? 0) <= 0) {
        echo json_encode(['status'=>'error','message'=>'msp']);
        return;
    }
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $metric = (string)($_POST['metric'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    $periodStart = (int)($_POST['period_start'] ?? 0);
    $periodEnd = (int)($_POST['period_end'] ?? 0);
    if ($customerId <= 0 || $metric === '' || $qty < 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    try {
        [$resolvedPeriodStart, $resolvedPeriodEnd] = eb_usage_normalize_period_bounds($periodStart, $periodEnd);

        $ownedCustomer = Capsule::table('eb_customers')
            ->where('id', $customerId)
            ->where('msp_id', (int) $msp->id)
            ->first(['id', 'tenant_id']);
        if (!$ownedCustomer) {
            echo json_encode(['status'=>'error','message'=>'customer']);
            return;
        }

        $tenantId = (int) ($ownedCustomer->tenant_id ?? 0);

        // Record in ledger
        if ($tenantId > 0) {
            $idKey = eb_usage_tenant_period_idempotency_key($tenantId, $metric, $resolvedPeriodStart, $resolvedPeriodEnd);
        } else {
            $idKey = sha1(
                'customer:' . $customerId
                . '|metric:' . $metric
                . '|period_start:' . $resolvedPeriodStart
                . '|period_end:' . $resolvedPeriodEnd
            );
        }

        Capsule::table('eb_usage_ledger')->updateOrInsert(
            ['idempotency_key' => $idKey],
            [
                'tenant_id' => ($tenantId > 0 ? $tenantId : null),
                'customer_id' => $customerId,
                'metric' => $metric,
                'qty' => $qty,
                'period_start' => date('Y-m-d H:i:s', $resolvedPeriodStart),
                'period_end' => date('Y-m-d H:i:s', $resolvedPeriodEnd),
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
        $stripeAccountId = (string) ($msp->stripe_connect_id ?? '');
        $s = $svc->retrieveSubscription((string)$sub->stripe_subscription_id, $stripeAccountId !== '' ? $stripeAccountId : null);
        $items = (array)($s['items']['data'] ?? []);
        $itemId = count($items) ? (string)($items[0]['id'] ?? '') : '';
        if ($itemId !== '') {
            $usageTimestamp = eb_usage_record_timestamp_for_period($resolvedPeriodStart, $resolvedPeriodEnd);
            $svc->createUsageRecord($itemId, $qty, $usageTimestamp, $stripeAccountId !== '' ? $stripeAccountId : null, $idKey);
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


