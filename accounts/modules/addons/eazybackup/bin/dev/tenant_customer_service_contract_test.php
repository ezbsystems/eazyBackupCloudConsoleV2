<?php

declare(strict_types=1);

/**
 * Contract test: TenantCustomerService bridges eb_whitelabel_tenants and eb_tenants via canonical_tenant_id.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/tenant_customer_service_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$serviceFile = $moduleRoot . '/lib/PartnerHub/TenantCustomerService.php';
$publicSignupControllerFile = $moduleRoot . '/pages/whitelabel/PublicSignupController.php';

$targets = [
    'service file' => [
        'path' => $serviceFile,
        'markers' => [
            'namespace marker' => 'namespace PartnerHub;',
            'class marker' => 'class TenantCustomerService',
            'ensure method signature marker' => 'public function ensureCustomerForTenant(int $whitelabelTenantId): array',
            'get method signature marker' => 'public function getCustomerForTenant(int $whitelabelTenantId): ?array',
            'eb_whitelabel_tenants marker' => 'eb_whitelabel_tenants',
            'canonical_tenant_id marker' => 'canonical_tenant_id',
            'eb_tenants marker' => 'eb_tenants',
            'transaction marker' => 'Capsule::connection()->transaction(function () use ($whitelabelTenantId)',
            'tenant_not_found marker' => 'tenant_not_found',
            'tenant_owner_client_missing marker' => 'tenant_owner_client_missing',
            'tenant_customer_create_failed marker' => 'tenant_customer_create_failed',
        ],
    ],
    'public signup controller file' => [
        'path' => $publicSignupControllerFile,
        'markers' => [
            'service import marker (signup)' => 'use PartnerHub\\TenantCustomerService;',
            'service ensure marker (signup)' => 'ensureCustomerForTenant((int)$tenant->id)',
            'signup canonical conflict codes marker' => "['tenant_customer_owner_conflict', 'tenant_customer_conflict']",
            'signup canonical conflict hard fail marker' => 'tenant_customer_conflict_hard_fail',
            'signup canonical generic hard fail marker' => 'tenant_customer_ensure_hard_fail',
        ],
        'forbidden' => [
            'signup canonical soft-continue marker' => 'tenant_customer_ensure_failed',
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

    foreach (($target['forbidden'] ?? []) as $markerName => $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: forbidden {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "tenant-customer-service-contract-ok\n";
exit(0);
