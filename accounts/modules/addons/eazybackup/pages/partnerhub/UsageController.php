<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

require_once __DIR__ . '/TenantsController.php';

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
    $nowTs = $now->getTimestamp();
    $monthStart = new \DateTimeImmutable($now->format('Y-m-01 00:00:00'), $tz);

    $resolvedPeriodStart = ($periodStart > 0) ? $periodStart : $monthStart->getTimestamp();
    $resolvedPeriodEnd = ($periodEnd > 0) ? $periodEnd : $monthStart->modify('+1 month')->getTimestamp();

    if ($resolvedPeriodStart >= $nowTs) {
        throw new \InvalidArgumentException('period_in_future');
    }
    if ($resolvedPeriodEnd > $nowTs) {
        $resolvedPeriodEnd = $nowTs;
    }
    if ($resolvedPeriodEnd <= $resolvedPeriodStart) {
        throw new \InvalidArgumentException('invalid_period');
    }

    return [$resolvedPeriodStart, $resolvedPeriodEnd];
}

function eb_usage_pick_subscription_item_id(array $items): string
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $usageType = strtolower((string) ($item['price']['recurring']['usage_type'] ?? ''));
        if ($usageType === 'metered') {
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') {
                return $id;
            }
        }
    }

    return ''; // fail closed: no metered subscription item found
}

function eb_usage_clamp_usage_timestamp(int $periodStart, int $periodEnd, ?int $nowTs = null): int
{
    $nowTs = $nowTs ?? time();
    $upperBound = min($periodEnd - 1, $nowTs - 1);
    if ($upperBound < $periodStart) {
        return max(1, $periodStart);
    }
    return $upperBound;
}

function eb_ph_usage_push(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (!eb_ph_tenants_require_csrf_or_json_error((string)($_POST['token'] ?? ''))) { return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp || (int) ($msp->id ?? 0) <= 0) {
        echo json_encode(['status'=>'error','message'=>'msp']);
        return;
    }
    $tenantPublicId = trim((string)($_POST['tenant_id'] ?? $_POST['customer_id'] ?? ''));
    $metric = (string)($_POST['metric'] ?? '');
    $qty = (int)($_POST['qty'] ?? 0);
    $periodStart = (int)($_POST['period_start'] ?? 0);
    $periodEnd = (int)($_POST['period_end'] ?? 0);
    if ($tenantPublicId === '' || $metric === '' || $qty < 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    try {
        $tenant = eb_ph_tenants_find_owned_tenant_by_public_id((int)$msp->id, $tenantPublicId);
        if (!$tenant) {
            echo json_encode(['status'=>'error','message'=>'tenant']);
            return;
        }

        $tenantId = (int)($tenant->id ?? 0);
        if ($tenantId <= 0) {
            echo json_encode(['status'=>'error','message'=>'tenant']);
            return;
        }

        [$resolvedPeriodStart, $resolvedPeriodEnd] = eb_usage_normalize_period_bounds($periodStart, $periodEnd);
        $idKey = eb_usage_tenant_period_idempotency_key((int)$tenant->id, $metric, $resolvedPeriodStart, $resolvedPeriodEnd);

        Capsule::table('eb_usage_ledger')->updateOrInsert(
            ['idempotency_key' => $idKey],
            [
                'tenant_id' => (int)$tenant->id,
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
        $sub = Capsule::table('eb_subscriptions')->where('tenant_id',$tenantId)->where('stripe_status','active')->orderBy('created_at','desc')->first();
        if (!$sub) { echo json_encode(['status'=>'success','message'=>'recorded-only']); return; }
        $priceRow = Capsule::table('eb_plan_prices')->where('id',(int)$sub->current_price_id)->first();
        if (!$priceRow || !(int)$priceRow->is_metered) { echo json_encode(['status'=>'success','message'=>'recorded-only']); return; }
        // Retrieve subscription to get item id
        $svc = new StripeService();
        $stripeAccountId = (string) ($msp->stripe_connect_id ?? '');
        $s = $svc->retrieveSubscription((string)$sub->stripe_subscription_id, $stripeAccountId !== '' ? $stripeAccountId : null);
        $items = (array)($s['items']['data'] ?? []);
        $itemId = eb_usage_pick_subscription_item_id($items);
        if ($itemId !== '') {
            $usageTimestamp = eb_usage_clamp_usage_timestamp($resolvedPeriodStart, $resolvedPeriodEnd);
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


