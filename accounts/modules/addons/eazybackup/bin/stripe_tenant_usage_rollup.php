<?php

declare(strict_types=1);

use PartnerHub\StripeService;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../eazybackup.php';

function tenant_usage_rollup_period_bounds_utc(?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $periodStart = new DateTimeImmutable($now->format('Y-m-01 00:00:00'), new DateTimeZone('UTC'));
    $periodEnd = $periodStart->modify('+1 month');

    return [
        $periodStart->getTimestamp(),
        $periodEnd->getTimestamp(),
        $periodStart,
        $periodEnd,
    ];
}

function tenant_usage_rollup_idempotency_key(int $tenantId, string $metric, int $periodStartTs, int $periodEndTs): string
{
    return sha1(
        'tenant:' . max(0, $tenantId)
        . '|metric:' . trim($metric)
        . '|period_start:' . max(0, $periodStartTs)
        . '|period_end:' . max(0, $periodEndTs)
    );
}

function tenant_usage_rollup_parse_s3_user_id(string $storageIdentifier): ?int
{
    $matches = [];
    if (!preg_match('/^s3_backup_user:(\d+)$/', $storageIdentifier, $matches)) {
        return null;
    }

    $s3UserId = (int) ($matches[1] ?? 0);
    return $s3UserId > 0 ? $s3UserId : null;
}

function tenant_usage_rollup_pick_subscription_item_id(array $items): string
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

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        if ($id !== '') {
            return $id;
        }
    }

    return '';
}

function tenant_usage_rollup_clamp_usage_timestamp(int $periodStartTs, int $periodEndTs, ?int $nowTs = null): int
{
    $nowTs = $nowTs ?? time();
    $upperBound = min($periodEndTs - 1, $nowTs - 1);
    if ($upperBound < $periodStartTs) {
        return max(1, $periodStartTs);
    }
    return $upperBound;
}

try {
    [$periodStartTs, $periodEndTs, $periodStart, $periodEnd] = tenant_usage_rollup_period_bounds_utc();
    $metric = 'STORAGE_TB';
    $stripeService = new StripeService();

    $linkRows = Capsule::table('eb_tenant_storage_links as tsl')
        ->join('eb_whitelabel_tenants as wt', 'wt.id', '=', 'tsl.tenant_id')
        ->where('tsl.link_status', 'active')
        ->whereNotIn('wt.status', ['deleted', 'removing'])
        ->get([
            'tsl.tenant_id',
            'wt.client_id',
            'tsl.storage_identifier',
        ]);

    $tenantToUserIds = [];
    $tenantToClientId = [];
    foreach ($linkRows as $row) {
        $tenantId = (int) ($row->tenant_id ?? 0);
        if ($tenantId <= 0) {
            continue;
        }

        $s3UserId = tenant_usage_rollup_parse_s3_user_id((string) ($row->storage_identifier ?? ''));
        if ($s3UserId === null) {
            continue;
        }

        $tenantToClientId[$tenantId] = (int) ($row->client_id ?? 0);
        if (!isset($tenantToUserIds[$tenantId])) {
            $tenantToUserIds[$tenantId] = [];
        }
        $tenantToUserIds[$tenantId][$s3UserId] = true;
    }

    foreach ($tenantToUserIds as $tenantId => $userIdMap) {
        $tenantId = (int) $tenantId;
        try {
            if ($tenantId <= 0) {
                continue;
            }

            $clientId = (int) ($tenantToClientId[$tenantId] ?? 0);
            if ($clientId <= 0) {
                continue;
            }

            $s3UserIds = array_map('intval', array_keys($userIdMap));
            if ($s3UserIds === []) {
                continue;
            }

            $s3Users = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->whereIn('id', $s3UserIds)
                ->get(['username']);

            $usernames = [];
            foreach ($s3Users as $s3User) {
                $username = trim((string) ($s3User->username ?? ''));
                if ($username === '') {
                    continue;
                }
                $usernames[$username] = true;
            }
            $usernames = array_keys($usernames);
            if ($usernames === []) {
                continue;
            }

            $sumBytes = (int) Capsule::table('eb_storage_daily')
                ->where('client_id', $clientId)
                ->whereIn('username', $usernames)
                ->where('d', '>=', $periodStart->format('Y-m-d'))
                ->where('d', '<', $periodEnd->format('Y-m-d'))
                ->sum('bytes_total');
            $qtyGb = (int) floor(max(0, $sumBytes) / (1024 * 1024 * 1024));

            $customer = Capsule::table('eb_customers')
                ->where('tenant_id', $tenantId)
                ->first(['id', 'msp_id']);
            if (!$customer) {
                continue;
            }

            $customerId = (int) ($customer->id ?? 0);
            $mspId = (int) ($customer->msp_id ?? 0);
            if ($customerId <= 0 || $mspId <= 0) {
                continue;
            }

            $sub = Capsule::table('eb_subscriptions')
                ->where('customer_id', $customerId)
                ->where('stripe_status', 'active')
                ->orderBy('created_at', 'desc')
                ->first();
            if (!$sub) {
                continue;
            }

            $priceRow = Capsule::table('eb_plan_prices')->where('id', (int) $sub->current_price_id)->first();
            if (!$priceRow || !(int) $priceRow->is_metered) {
                continue;
            }

            $idempotencyKey = tenant_usage_rollup_idempotency_key($tenantId, $metric, $periodStartTs, $periodEndTs);

            $existingLedger = Capsule::table('eb_usage_ledger')
                ->where('idempotency_key', $idempotencyKey)
                ->first(['pushed_to_stripe_at']);
            if ($existingLedger && !empty($existingLedger->pushed_to_stripe_at)) {
                continue;
            }

            Capsule::table('eb_usage_ledger')->updateOrInsert(
                ['idempotency_key' => $idempotencyKey],
                [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'metric' => $metric,
                    'qty' => $qtyGb,
                    'period_start' => $periodStart->format('Y-m-d H:i:s'),
                    'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                    'source' => 'tenant_rollup',
                    'pushed_to_stripe_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $msp = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first(['stripe_connect_id']);
            $stripeAccountId = (string) ($msp->stripe_connect_id ?? '');
            $subscription = $stripeService->retrieveSubscription((string) $sub->stripe_subscription_id, $stripeAccountId !== '' ? $stripeAccountId : null);
            $items = (array) ($subscription['items']['data'] ?? []);
            $subscriptionItemId = tenant_usage_rollup_pick_subscription_item_id($items);
            if ($subscriptionItemId === '') {
                continue;
            }

            $usageTimestamp = tenant_usage_rollup_clamp_usage_timestamp($periodStartTs, $periodEndTs);
            $stripeService->createUsageRecord($subscriptionItemId, $qtyGb, $usageTimestamp, $stripeAccountId !== '' ? $stripeAccountId : null, $idempotencyKey);

            Capsule::table('eb_usage_ledger')
                ->where('idempotency_key', $idempotencyKey)
                ->update([
                    'pushed_to_stripe_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $tenantError) {
            try {
                if (function_exists('logActivity')) {
                    @logActivity('eazybackup: stripe_tenant_usage_rollup tenant ' . $tenantId . ' failed: ' . $tenantError->getMessage());
                }
            } catch (\Throwable $__) {
            }
            continue;
        }
    }

    fwrite(STDOUT, "[tenant-rollup] completed\n");
    exit(0);
} catch (\Throwable $e) {
    try {
        if (function_exists('logActivity')) {
            @logActivity('eazybackup: stripe_tenant_usage_rollup failed: ' . $e->getMessage());
        }
    } catch (\Throwable $__) {
    }

    fwrite(STDERR, "[tenant-rollup] error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
