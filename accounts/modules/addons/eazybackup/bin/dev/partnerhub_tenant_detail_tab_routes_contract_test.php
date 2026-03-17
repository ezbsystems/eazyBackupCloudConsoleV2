<?php

declare(strict_types=1);

/**
 * Contract test: tenant detail tab routes are wired in the addon router.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$moduleFile = $moduleRoot . '/eazybackup.php';
$tenantTemplateFile = $moduleRoot . '/templates/whitelabel/tenant-detail.tpl';
$membersControllerFile = $moduleRoot . '/pages/partnerhub/TenantMembersController.php';
$billingControllerFile = $moduleRoot . '/pages/partnerhub/TenantBillingController.php';
$whitelabelControllerFile = $moduleRoot . '/pages/partnerhub/TenantWhiteLabelController.php';
$tenantsControllerFile = $moduleRoot . '/pages/partnerhub/TenantsController.php';

$targets = [
    'module routing file' => [
        'path' => $moduleFile,
        'markers' => [
            'members route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-members'",
            'members controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantMembersController.php';",
            'members handler marker' => 'return eb_ph_tenant_members($vars);',
            'storage users route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-storage-users'",
            'storage users controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantsController.php';",
            'storage users handler marker' => 'return eb_ph_tenant_storage_users($vars);',
            'billing route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-billing'",
            'billing controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantBillingController.php';",
            'billing handler marker' => 'return eb_ph_tenant_billing($vars);',
            'white label route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-whitelabel'",
            'white label controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantWhiteLabelController.php';",
            'white label handler marker' => 'return eb_ph_tenant_whitelabel($vars);',
            'white label enable route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-whitelabel-enable'",
            'white label enable handler marker' => 'eb_ph_tenant_whitelabel_enable($vars); exit;',
        ],
    ],
    'tenant detail template file' => [
        'path' => $tenantTemplateFile,
        'markers' => [
            'members tab link marker' => '{$tab_links.members|default',
            'storage users tab link marker' => '{$tab_links.storage_users|default',
            'billing tab link marker' => '{$tab_links.billing|default',
            'white label tab link marker' => '{$tab_links.white_label|default',
            'tenant detail public id tab route marker' => '&a=ph-tenant&id={$tenant.public_id|escape:\'url\'}',
        ],
    ],
    'members controller file' => [
        'path' => $membersControllerFile,
        'markers' => [
            'members action marker' => 'function eb_ph_tenant_members(array $vars)',
            'members shell response marker' => "return eb_ph_tenant_shell_response(\$vars, (array)\$msp, (array)\$tenant, 'members'",
        ],
    ],
    'tenants controller file' => [
        'path' => $tenantsControllerFile,
        'markers' => [
            'tenant tab links public id signature marker' => 'function eb_ph_tenant_tab_links(array $vars, string $tenantPublicId): array',
            'storage users action marker' => 'function eb_ph_tenant_storage_users(array $vars)',
            'storage users shell response marker' => "return eb_ph_tenant_shell_response(\$vars, (array)\$msp, (array)\$tenant, 'storage_users'",
        ],
    ],
    'billing controller file' => [
        'path' => $billingControllerFile,
        'markers' => [
            'billing action marker' => 'function eb_ph_tenant_billing(array $vars)',
            'billing shell response marker' => "return eb_ph_tenant_shell_response(\$vars, (array)\$msp, (array)\$tenant, 'billing'",
        ],
    ],
    'white label controller file' => [
        'path' => $whitelabelControllerFile,
        'markers' => [
            'white label action marker' => 'function eb_ph_tenant_whitelabel(array $vars)',
            'white label enable action marker' => 'function eb_ph_tenant_whitelabel_enable(array $vars): void',
            'white label shell response marker' => "return eb_ph_tenant_shell_response(\$vars, (array)\$msp, (array)\$tenant, 'white_label'",
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $source = @file_get_contents($target['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName}";
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

echo "partnerhub-tenant-detail-tab-routes-contract-ok\n";
exit(0);
