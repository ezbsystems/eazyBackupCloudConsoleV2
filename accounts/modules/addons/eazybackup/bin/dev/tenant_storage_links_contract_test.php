<?php

declare(strict_types=1);

/**
 * Contract test: canonical tenant storage-link endpoints + cloudstorage wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/tenant_storage_links_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$moduleFile = $moduleRoot . '/eazybackup.php';
$controllerFile = $moduleRoot . '/pages/partnerhub/TenantStorageLinksController.php';
$userCreateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_user_create.php';
$userUpdateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_user_update.php';
$usersTemplateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_users.tpl';

$targets = [
    'module routing file' => [
        'path' => $moduleFile,
        'markers' => [
            'tenant storage links list route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-storage-links'",
            'tenant storage links write route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-storage-links-write'",
            'tenant storage links controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantStorageLinksController.php';",
            'tenant storage links list handler marker' => 'eb_ph_tenant_storage_links_list($vars); exit;',
            'tenant storage links write handler marker' => 'eb_ph_tenant_storage_links_write($vars); exit;',
        ],
    ],
    'tenant storage links controller file' => [
        'path' => $controllerFile,
        'markers' => [
            'context helper marker' => 'function eb_ph_tenant_storage_links_require_context(): array',
            'tenant resolver marker' => 'function eb_tenant_storage_links_resolve_tenant_for_client(int $clientId, int $canonicalTenantId)',
            'storage identifier helper marker' => 'function eb_tenant_storage_identifier_for_user(int $userId): string',
            'upsert helper marker' => 'function eb_tenant_storage_links_upsert_for_client(int $clientId, string $storageIdentifier, ?int $canonicalTenantId): array',
            'list endpoint marker' => 'function eb_ph_tenant_storage_links_list(array $vars): void',
            'write endpoint marker' => 'function eb_ph_tenant_storage_links_write(array $vars): void',
            'canonical tenant ownership marker' => "Capsule::table('eb_whitelabel_tenants')->where('id', \$canonicalTenantId)->where('client_id', \$clientId)->first();",
            'tenant storage link table write marker' => "Capsule::table('eb_tenant_storage_links')->insert([",
            'tenant storage link table delete marker' => "Capsule::table('eb_tenant_storage_links as l')",
        ],
    ],
    'cloudstorage user create api file' => [
        'path' => $userCreateFile,
        'markers' => [
            'controller include marker' => "require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';",
            'canonical tenant post marker' => "\$canonicalTenantIdRaw = \$_POST['canonical_tenant_id'] ?? null;",
            'canonical tenant ownership check marker' => 'eb_tenant_storage_links_resolve_tenant_for_client((int) $clientId, $canonicalTenantId);',
            'storage identifier marker' => 'eb_tenant_storage_identifier_for_user((int) $userId);',
            'tenant storage link upsert marker' => 'eb_tenant_storage_links_upsert_for_client((int) $clientId, $storageIdentifier, $canonicalTenantId);',
        ],
    ],
    'cloudstorage user update api file' => [
        'path' => $userUpdateFile,
        'markers' => [
            'controller include marker' => "require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';",
            'canonical tenant post presence marker' => "\$canonicalTenantProvided = array_key_exists('canonical_tenant_id', \$_POST);",
            'canonical tenant post marker' => "\$canonicalTenantIdRaw = \$_POST['canonical_tenant_id'] ?? null;",
            'canonical tenant ownership check marker' => 'eb_tenant_storage_links_resolve_tenant_for_client((int) $clientId, $canonicalTenantId);',
            'storage identifier marker' => 'eb_tenant_storage_identifier_for_user((int) $userId);',
            'tenant storage link upsert marker' => 'eb_tenant_storage_links_upsert_for_client((int) $clientId, $storageIdentifier, $canonicalTenantId);',
        ],
    ],
    'cloudstorage users template file' => [
        'path' => $usersTemplateFile,
        'markers' => [
            'canonical tenants state marker' => 'canonicalTenants: [],',
            'load canonical tenants marker' => 'async loadCanonicalTenants() {',
            'canonical tenants endpoint marker' => "index.php?m=eazybackup&a=ph-tenant-storage-links'",
            'assign tenant source marker' => 'return this.canonicalTenants;',
            'create canonical tenant submit marker' => "body.set('canonical_tenant_id', this.form.tenant_id);",
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

$controllerSource = @file_get_contents($controllerFile);
if ($controllerSource === false) {
    $failures[] = 'FAIL: unable to read tenant storage links controller source';
} else {
    if (substr_count($controllerSource, 'eb_tenant_storage_links_upsert_for_client(') < 2) {
        $failures[] = 'FAIL: upsert helper must be used by endpoint and API callers';
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "tenant-storage-links-contract-ok\n";
exit(0);
