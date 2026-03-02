<?php

declare(strict_types=1);

/**
 * Contract test: tenant-customer 1:1 service and controller wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/tenant_customer_service_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$serviceFile = $moduleRoot . '/lib/PartnerHub/TenantCustomerService.php';
$clientsControllerFile = $moduleRoot . '/pages/partnerhub/ClientsController.php';
$publicSignupControllerFile = $moduleRoot . '/pages/whitelabel/PublicSignupController.php';

$targets = [
    'service file' => [
        'path' => $serviceFile,
        'markers' => [
            'namespace marker' => 'namespace PartnerHub;',
            'class marker' => 'class TenantCustomerService',
            'ensure method signature marker' => 'public function ensureCustomerForTenant(int $tenantId): array',
            'get method signature marker' => 'public function getCustomerForTenant(int $tenantId): ?array',
            'tenant lookup marker' => "->where('tenant_id', \$tenantId)",
            'transaction marker' => 'Capsule::connection()->transaction(function () use ($tenantId)',
            'tenant ownership conflict marker' => 'tenant_customer_owner_conflict',
            'existing tenant ownership check marker' => "if ((int)(\$existingLocked->whmcs_client_id ?? 0) !== \$ownerClientId)",
        ],
    ],
    'clients controller file' => [
        'path' => $clientsControllerFile,
        'markers' => [
            'service import marker (clients)' => 'use PartnerHub\\TenantCustomerService;',
            'service ensure marker (clients)' => 'ensureCustomerForTenant($tenantId)',
            'tenant ownership validation marker (clients)' => "->where('client_id', \$clientId)",
            'authoritative msp marker (clients)' => "'msp_id' => \$authoritativeMspId",
        ],
    ],
    'public signup controller file' => [
        'path' => $publicSignupControllerFile,
        'markers' => [
            'service import marker (signup)' => 'use PartnerHub\\TenantCustomerService;',
            'service ensure marker (signup)' => 'ensureCustomerForTenant((int)$tenant->id)',
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

echo "tenant-customer-service-contract-ok\n";
exit(0);
