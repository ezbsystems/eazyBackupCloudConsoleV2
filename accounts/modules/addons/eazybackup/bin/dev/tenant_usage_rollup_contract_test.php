<?php

declare(strict_types=1);

/**
 * Contract test: canonical tenant usage rollup + metered Stripe push wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/tenant_usage_rollup_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);

$rollupScriptFile = $moduleRoot . '/bin/stripe_tenant_usage_rollup.php';
$stripeServiceFile = $moduleRoot . '/lib/PartnerHub/StripeService.php';
$usageControllerFile = $moduleRoot . '/pages/partnerhub/UsageController.php';

$targets = [
    'tenant usage rollup script file' => [
        'path' => $rollupScriptFile,
        'markers' => [
            'period bounds helper marker' => 'function tenant_usage_rollup_period_bounds_utc(?DateTimeImmutable $now = null): array',
            'closed period current month anchor marker' => '$currentMonthStart = new DateTimeImmutable($now->format(\'Y-m-01 00:00:00\'), new DateTimeZone(\'UTC\'));',
            'closed period start previous month marker' => '$periodStart = $currentMonthStart->modify(\'-1 month\');',
            'closed period end current month marker' => '$periodEnd = $currentMonthStart;',
            'tenant period idempotency helper marker' => 'function tenant_usage_rollup_idempotency_key(int $tenantId, string $metric, int $periodStartTs, int $periodEndTs): string',
            'rollup usage timestamp clamp helper marker' => 'function tenant_usage_rollup_clamp_usage_timestamp(int $periodStartTs, int $periodEndTs, ?int $nowTs = null): int',
            'rollup usage timestamp clamp upper marker' => '$upperBound = min($periodEndTs - 1, $nowTs - 1);',
            'rollup usage timestamp clamp lower marker' => 'if ($upperBound < $periodStartTs) {',
            'metered item picker helper marker' => 'function tenant_usage_rollup_pick_subscription_item_id(array $items): string',
            'metered usage type preference marker' => "if (\$usageType === 'metered') {",
            'rollup metered fail-closed marker' => 'return \'\'; // fail closed: no metered subscription item found',
            'canonical tenant links query marker' => "Capsule::table('eb_tenant_storage_links as tsl')",
            'storage identifier parser marker' => "preg_match('/^s3_backup_user:(\\d+)$/', \$storageIdentifier, \$matches)",
            's3 backup users lookup marker' => "Capsule::table('s3_backup_users')",
            'storage daily aggregation marker' => "Capsule::table('eb_storage_daily')",
            'per tenant isolation catch marker' => 'catch (\Throwable $tenantError) {',
            'per tenant isolation log marker' => "logActivity('eazybackup: stripe_tenant_usage_rollup tenant ' . \$tenantId . ' failed: ' . \$tenantError->getMessage());",
            'usage ledger upsert marker' => "Capsule::table('eb_usage_ledger')->updateOrInsert(",
            'usage ledger pushed check marker' => "->where('idempotency_key', \$idempotencyKey)",
            'stripe metered usage timestamp marker' => '$usageTimestamp = tenant_usage_rollup_clamp_usage_timestamp($periodStartTs, $periodEndTs);',
            'stripe metered usage push marker' => '$stripeService->createUsageRecord($subscriptionItemId, $qtyGb, $usageTimestamp, $stripeAccountId !== \'\' ? $stripeAccountId : null, $idempotencyKey);',
        ],
    ],
    'stripe service file' => [
        'path' => $stripeServiceFile,
        'markers' => [
            'request extra headers signature marker' => 'private function request(string $method, string $path, array $params = [], ?string $apiKey = null, ?string $stripeAccount = null, ?array $extraHeaders = null): array',
            'request extra headers append marker' => 'if (is_array($extraHeaders)) {',
            'usage record idempotent signature marker' => 'public function createUsageRecord(string $subscriptionItemId, int $quantity, int $timestamp, ?string $stripeAccount = null, ?string $idempotencyKey = null): array',
            'usage idempotency header marker' => '$headers = $idempotencyKey ? [\'Idempotency-Key: \'.$idempotencyKey] : null;',
            'usage request with connected account marker' => '], null, $stripeAccount, $headers);',
        ],
    ],
    'usage controller file' => [
        'path' => $usageControllerFile,
        'markers' => [
            'tenant idempotency helper marker' => 'function eb_usage_tenant_period_idempotency_key(int $tenantId, string $metric, int $periodStart, int $periodEnd): string',
            'period normalization helper marker' => 'function eb_usage_normalize_period_bounds(int $periodStart, int $periodEnd): array',
            'future period guard marker' => "throw new \\InvalidArgumentException('period_in_future');",
            'period end clamp marker' => 'if ($resolvedPeriodEnd > $nowTs) {',
            'manual metered item picker helper marker' => 'function eb_usage_pick_subscription_item_id(array $items): string',
            'manual metered usage type preference marker' => "if (\$usageType === 'metered') {",
            'manual metered fail-closed marker' => 'return \'\'; // fail closed: no metered subscription item found',
            'deterministic usage timestamp helper marker' => 'function eb_usage_clamp_usage_timestamp(int $periodStart, int $periodEnd, ?int $nowTs = null): int',
            'manual usage timestamp clamp upper marker' => '$upperBound = min($periodEnd - 1, $nowTs - 1);',
            'manual usage timestamp clamp lower marker' => 'if ($upperBound < $periodStart) {',
            'msp required guard marker' => 'if (!$msp || (int) ($msp->id ?? 0) <= 0) {',
            'customer ownership query marker' => "->where('msp_id', (int) \$msp->id)",
            'customer ownership fail closed marker' => "echo json_encode(['status'=>'error','message'=>'customer']);",
            'tenant idempotency branch marker' => 'if ($tenantId > 0) {',
            'tenant idempotency key assignment marker' => '$idKey = eb_usage_tenant_period_idempotency_key($tenantId, $metric, $resolvedPeriodStart, $resolvedPeriodEnd);',
            'ledger tenant field marker' => "'tenant_id' => (\$tenantId > 0 ? \$tenantId : null),",
            'connected account id marker' => '$stripeAccountId = (string) ($msp->stripe_connect_id ?? \'\');',
            'manual usage item picker marker' => '$itemId = eb_usage_pick_subscription_item_id($items);',
            'deterministic usage timestamp marker' => '$usageTimestamp = eb_usage_clamp_usage_timestamp($resolvedPeriodStart, $resolvedPeriodEnd);',
            'usage push idempotency marker' => '$svc->createUsageRecord($itemId, $qty, $usageTimestamp, $stripeAccountId !== \'\' ? $stripeAccountId : null, $idKey);',
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $path = $target['path'];
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName} at {$path}";
        continue;
    }

    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: missing {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

require_once $usageControllerFile;

// Behavioral assertions for helper functions to avoid marker-only regressions.
try {
    eb_usage_normalize_period_bounds(time() + 3600, time() + 7200);
    $failures[] = 'FAIL: future period should throw period_in_future';
} catch (\InvalidArgumentException $e) {
    if ($e->getMessage() !== 'period_in_future') {
        $failures[] = 'FAIL: future period threw unexpected error';
    }
} catch (\Throwable $e) {
    $failures[] = 'FAIL: future period threw non-InvalidArgumentException';
}

$pickedMetered = eb_usage_pick_subscription_item_id([
    ['id' => 'si_fixed', 'price' => ['recurring' => ['usage_type' => 'licensed']]],
    ['id' => 'si_metered', 'price' => ['recurring' => ['usage_type' => 'metered']]],
]);
if ($pickedMetered !== 'si_metered') {
    $failures[] = 'FAIL: metered picker should prefer metered item id';
}

$pickedNoMetered = eb_usage_pick_subscription_item_id([
    ['id' => 'si_fixed', 'price' => ['recurring' => ['usage_type' => 'licensed']]],
]);
if ($pickedNoMetered !== '') {
    $failures[] = 'FAIL: metered picker should fail closed when no metered item exists';
}

$clamped = eb_usage_clamp_usage_timestamp(100, 200, 150);
if ($clamped !== 149) {
    $failures[] = 'FAIL: usage timestamp clamp should use now-1 inside period';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "tenant-usage-rollup-contract-ok\n";
exit(0);
