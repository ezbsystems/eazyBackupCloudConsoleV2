<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use function PartnerHub\computeBillableMeteredUsage;
use function PartnerHub\resolveActivePlanInstanceMeteredItem;

require_once __DIR__ . '/TenantsController.php';
require_once __DIR__ . '/../../lib/PartnerHub/MeteredUsage.php';

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

/**
 * Pure-function backend for `eb_ph_usage_push`. No HTTP, no $_SESSION, no $_POST.
 *
 * Records a usage data point in `eb_usage_ledger` (idempotent via the
 * `idempotency_key` UNIQUE column) and, if the tenant has an active plan
 * instance with a metered item for that metric, pushes the billable quantity
 * to Stripe and stamps `pushed_to_stripe_at` on the ledger row.
 *
 * Returns ['status' => 'success'|'error', 'message' => string|null].
 *
 * The optional `$stripeService` lets tests pass a `TestableStripeService` to
 * capture the Stripe call without going over the network. Production callers
 * pass null and a real `StripeService` is constructed internally.
 *
 * @return array{status: string, message: ?string, billable_qty?: int, idempotency_key?: string}
 */
function eb_ph_usage_push_for_tenant(
    int $tenantId,
    int $mspId,
    string $stripeAccountId,
    string $metric,
    int $rawQty,
    int $periodStart,
    int $periodEnd,
    ?\PartnerHub\StripeService $stripeService = null
): array {
    $metric = trim($metric);
    if ($tenantId <= 0 || $metric === '' || $rawQty < 0) {
        return ['status' => 'error', 'message' => 'invalid'];
    }

    try {
        [$resolvedStart, $resolvedEnd] = eb_usage_normalize_period_bounds($periodStart, $periodEnd);
    } catch (\InvalidArgumentException $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }

    $idKey = eb_usage_tenant_period_idempotency_key($tenantId, $metric, $resolvedStart, $resolvedEnd);

    Capsule::table('eb_usage_ledger')->updateOrInsert(
        ['idempotency_key' => $idKey],
        [
            'tenant_id' => $tenantId,
            'metric' => $metric,
            'qty' => $rawQty,
            'period_start' => date('Y-m-d H:i:s', $resolvedStart),
            'period_end' => date('Y-m-d H:i:s', $resolvedEnd),
            'source' => 'manual',
            'pushed_to_stripe_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    );

    $meteredItem = resolveActivePlanInstanceMeteredItem($tenantId, $metric);
    if (!$meteredItem) {
        return ['status' => 'success', 'message' => 'recorded-only', 'idempotency_key' => $idKey];
    }

    $billableQty = computeBillableMeteredUsage(
        $rawQty,
        (int) ($meteredItem['default_qty'] ?? 0),
        (string) ($meteredItem['overage_mode'] ?? 'bill_all')
    );

    $svc = $stripeService ?? new StripeService();
    $usageTimestamp = eb_usage_clamp_usage_timestamp($resolvedStart, $resolvedEnd);
    try {
        $svc->createUsageRecord(
            (string) $meteredItem['stripe_subscription_item_id'],
            $billableQty,
            $usageTimestamp,
            $stripeAccountId !== '' ? $stripeAccountId : null,
            $idKey
        );
    } catch (\Throwable $e) {
        return ['status' => 'error', 'message' => $e->getMessage(), 'idempotency_key' => $idKey];
    }

    Capsule::table('eb_usage_ledger')->where('idempotency_key', $idKey)->update([
        'qty' => $billableQty,
        'pushed_to_stripe_at' => date('Y-m-d H:i:s'),
    ]);

    return ['status' => 'success', 'billable_qty' => $billableQty, 'idempotency_key' => $idKey];
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

    $tenant = eb_ph_tenants_find_owned_tenant_by_public_id((int)$msp->id, $tenantPublicId);
    if (!$tenant) {
        echo json_encode(['status'=>'error','message'=>'tenant']);
        return;
    }

    $result = eb_ph_usage_push_for_tenant(
        (int) ($tenant->id ?? 0),
        (int) $msp->id,
        (string) ($msp->stripe_connect_id ?? ''),
        $metric,
        $qty,
        $periodStart,
        $periodEnd
    );
    // Strip internal-only metadata from the wire response (preserves prior shape).
    if (isset($result['idempotency_key'])) { unset($result['idempotency_key']); }
    if (isset($result['billable_qty'])) { unset($result['billable_qty']); }
    echo json_encode($result);
}


