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
            'tenant period idempotency helper marker' => 'function tenant_usage_rollup_idempotency_key(int $tenantId, string $metric, int $periodStartTs, int $periodEndTs): string',
            'canonical tenant links query marker' => "Capsule::table('eb_tenant_storage_links as tsl')",
            'storage identifier parser marker' => "preg_match('/^s3_backup_user:(\\d+)$/', \$storageIdentifier, \$matches)",
            's3 backup users lookup marker' => "Capsule::table('s3_backup_users')",
            'storage daily aggregation marker' => "Capsule::table('eb_storage_daily')",
            'usage ledger upsert marker' => "Capsule::table('eb_usage_ledger')->updateOrInsert(",
            'usage ledger pushed check marker' => "->where('idempotency_key', \$idempotencyKey)",
            'stripe metered usage push marker' => '$stripeService->createUsageRecord($subscriptionItemId, $qtyGb, $periodEndTs - 1, $stripeAccountId !== \'\' ? $stripeAccountId : null, $idempotencyKey);',
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
            'tenant id lookup marker' => "Capsule::table('eb_customers')->where('id', \$customerId)->value('tenant_id')",
            'tenant idempotency branch marker' => 'if ($tenantId > 0) {',
            'tenant idempotency key assignment marker' => '$idKey = eb_usage_tenant_period_idempotency_key($tenantId, $metric, $periodStart ?: 0, $periodEnd ?: 0);',
            'ledger tenant field marker' => "'tenant_id' => (\$tenantId > 0 ? \$tenantId : null),",
            'connected account id marker' => '$stripeAccountId = (string) ($msp->stripe_connect_id ?? \'\');',
            'usage push idempotency marker' => '$svc->createUsageRecord($itemId, $qty, time(), $stripeAccountId !== \'\' ? $stripeAccountId : null, $idKey);',
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

echo "tenant-usage-rollup-contract-ok\n";
exit(0);
