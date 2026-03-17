<?php

declare(strict_types=1);

/**
 * Contract test: tenant detail profile editor and portal admin wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_profile_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$tenantTemplateFile = $moduleRoot . '/templates/whitelabel/tenant-detail.tpl';
$tenantsControllerFile = $moduleRoot . '/pages/partnerhub/TenantsController.php';

$targets = [
    'tenant detail template file' => [
        'path' => $tenantTemplateFile,
        'markers' => [
            'profile columns marker' => 'lg:grid-cols-2',
            'contact name input marker' => 'name="contact_name"',
            'contact phone input marker' => 'name="contact_phone"',
            'address line1 input marker' => 'name="address_line1"',
            'address line2 input marker' => 'name="address_line2"',
            'city input marker' => 'name="city"',
            'state input marker' => 'name="state"',
            'postal code input marker' => 'name="postal_code"',
            'country input marker' => 'name="country"',
            'portal admin email marker' => 'name="portal_admin_email"',
            'portal admin name marker' => 'name="portal_admin_name"',
            'portal admin status marker' => 'name="portal_admin_status"',
            'portal admin manual password marker' => 'name="portal_admin_password"',
        ],
    ],
    'tenants controller file' => [
        'path' => $tenantsControllerFile,
        'markers' => [
            'portal admin loader marker' => 'function eb_ph_tenant_primary_admin(int $tenantId): array',
            'portal admin table marker' => "Capsule::table('eb_tenant_users')",
            'contact name normalization marker' => '$contactName = trim((string)($post[\'contact_name\'] ?? \'\'));',
            'country normalization marker' => '$countryRaw = (string)($post[\'country\'] ?? \'\');',
            'portal admin email marker' => '$portalAdminEmail = strtolower(trim((string)($post[\'portal_admin_email\'] ?? \'\')));',
            'portal admin status marker' => '$portalAdminStatus = strtolower(trim((string)($post[\'portal_admin_status\'] ?? \'active\')));',
            'portal admin password mode marker' => '$portalAdminPasswordMode = trim((string)($post[\'portal_admin_password_mode\'] ?? \'keep\'));',
            'portal admin password hash marker' => 'password_hash($portalAdminPassword, PASSWORD_DEFAULT)',
            'portal admin create marker' => '->insertGetId([',
            'portal admin shell response marker' => "'portal_admin' => \$portalAdmin,",
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

echo "partnerhub-tenant-detail-profile-contract-ok\n";
exit(0);
