<?php

declare(strict_types=1);

/**
 * Contract test: portal services + cloud storage routes/templates/API wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/portal_services_storage_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$indexFile = $repoRoot . '/accounts/portal/index.php';
$layoutTemplateFile = $repoRoot . '/accounts/portal/templates/layout.tpl';
$servicesTemplateFile = $repoRoot . '/accounts/portal/templates/services.tpl';
$cloudStorageTemplateFile = $repoRoot . '/accounts/portal/templates/cloud_storage.tpl';
$servicesApiFile = $repoRoot . '/accounts/portal/api/services.php';

$targets = [
    'portal index route file' => [
        'path' => $indexFile,
        'markers' => [
            'services api route marker' => "'services' => __DIR__ . '/api/services.php'",
            'services page switch marker' => "case 'services':",
            'services template marker' => "\$template = 'services.tpl';",
            'cloud storage page switch marker' => "case 'cloud_storage':",
            'cloud storage template marker' => "\$template = 'cloud_storage.tpl';",
        ],
    ],
    'portal layout template file' => [
        'path' => $layoutTemplateFile,
        'markers' => [
            'services nav href marker' => 'href="index.php?page=services',
            'services nav label marker' => '>Services<',
            'cloud storage nav href marker' => 'href="index.php?page=cloud_storage',
            'cloud storage nav label marker' => '>Cloud Storage<',
        ],
    ],
    'portal services template file' => [
        'path' => $servicesTemplateFile,
        'markers' => [
            'services endpoint marker' => "fetch(apiUrl('services')",
            'cancel action marker' => "action: 'cancel_request'",
            'csrf header marker' => "'X-CSRF-Token': csrfToken",
            'cloud storage link marker' => 'index.php?page=cloud_storage',
            'services table marker' => 'id="services-table-body"',
        ],
    ],
    'portal cloud storage template file' => [
        'path' => $cloudStorageTemplateFile,
        'markers' => [
            'services data fetch marker' => "fetch(apiUrl('services')",
            'access keys label marker' => 'Access Keys',
            'buckets label marker' => 'Buckets',
            'cloud storage users table marker' => 'id="cloud-storage-users-body"',
            'access keys href marker' => 'index.php?m=cloudstorage&page=access_keys',
            'buckets href marker' => 'index.php?m=cloudstorage&page=buckets',
        ],
    ],
    'portal services api file' => [
        'path' => $servicesApiFile,
        'markers' => [
            'auth include marker' => "require_once __DIR__ . '/../auth.php';",
            'auth required marker' => '$session = portal_require_auth_json();',
            'tenant id marker' => '$tenantId = (int) ($session[\'tenant_id\'] ?? 0);',
            'tenant scoped customer lookup marker' => "->where('tenant_id', \$tenantId)",
            'subscriptions query marker' => "Capsule::table('eb_subscriptions as s')",
            'services post branch marker' => "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {",
            'csrf check marker' => '!portal_validate_csrf()',
            'cancel request action marker' => "\$action === 'cancel_request'",
            'cancel period end update marker' => "'cancel_at_period_end' => 1",
            'storage links query marker' => "Capsule::table('eb_tenant_storage_links as tsl')",
            'storage users query marker' => "Capsule::table('s3_backup_users')",
            'json success marker' => "portal_json(['status' => 'success'",
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

echo "portal-services-storage-contract-ok\n";
exit(0);
