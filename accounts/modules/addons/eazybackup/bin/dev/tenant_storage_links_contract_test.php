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
$userResetPasswordFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_user_reset_password.php';
$userDeleteFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_user_delete.php';
$tenantCreateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_create.php';
$tenantUpdateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_update.php';
$tenantDeleteFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_delete.php';
$tenantUserCreateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_user_create.php';
$tenantUserUpdateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_user_update.php';
$tenantUserResetPasswordFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_user_reset_password.php';
$tenantUserDeleteFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_user_delete.php';
$usersTemplateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_users.tpl';
$userGetFile = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_user_get.php';
$usersPageFile = $repoRoot . '/accounts/modules/addons/cloudstorage/pages/e3backup_users.php';
$userDetailPageFile = $repoRoot . '/accounts/modules/addons/cloudstorage/pages/e3backup_user_detail.php';
$userDetailTemplateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_user_detail.tpl';
$tenantDetailPageFile = $repoRoot . '/accounts/modules/addons/cloudstorage/pages/e3backup_tenant_detail.php';
$tenantDetailTemplateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_tenant_detail.tpl';
$tenantMembersPageFile = $repoRoot . '/accounts/modules/addons/cloudstorage/pages/e3backup_tenant_members.php';
$tenantMembersTemplateFile = $repoRoot . '/accounts/modules/addons/cloudstorage/templates/e3backup_tenant_members.tpl';

$targets = [
    'module file' => [
        'path' => $moduleFile,
        'markers' => [
            'tenant storage links list route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-storage-links'",
            'tenant storage links write route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant-storage-links-write'",
            'tenant storage links controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantStorageLinksController.php';",
            'tenant storage links list handler marker' => 'eb_ph_tenant_storage_links_list($vars); exit;',
            'tenant storage links write handler marker' => 'eb_ph_tenant_storage_links_write($vars); exit;',
            'tenant storage links dedupe helper marker' => 'function eb_dedupe_tenant_storage_links_by_storage_identifier(): void',
            'tenant storage links dedupe invocation marker' => 'eb_dedupe_tenant_storage_links_by_storage_identifier();',
            'tenant storage links storage unique create marker' => "\$t->unique('storage_identifier', 'uq_tenant_storage_link_storage_identifier');",
            'tenant storage links storage unique require marker' => "eb_require_index('eb_tenant_storage_links', 'uq_tenant_storage_link_storage_identifier'",
        ],
    ],
    'tenant storage links controller file' => [
        'path' => $controllerFile,
        'markers' => [
            'context helper marker' => 'function eb_ph_tenant_storage_links_require_context(): array',
            'tenant resolver marker' => 'function eb_tenant_storage_links_resolve_tenant_for_client(int $clientId, int $canonicalTenantId)',
            'assignable status helper marker' => 'function eb_tenant_storage_links_is_assignable_tenant_status(string $status): bool',
            'storage tenant mapper marker' => 'function eb_tenant_storage_links_resolve_or_create_storage_tenant_id(int $clientId, int $canonicalTenantId): int',
            'storage identifier ownership helper marker' => 'function eb_tenant_storage_links_storage_identifier_belongs_to_client(int $clientId, string $storageIdentifier): bool',
            'current canonical link helper marker' => 'function eb_tenant_storage_links_get_current_link_for_identifier(int $clientId, string $storageIdentifier)',
            'infer canonical helper marker' => 'function eb_tenant_storage_links_infer_canonical_tenant_id_from_storage_tenant_id(int $clientId, ?int $storageTenantId): ?int',
            'infer canonical slug marker' => "preg_match('/^eb-canonical-(\\d+)$/', \$slug, \$matches)",
            'unsupported identifier fail-closed marker' => 'Fail closed: only s3_backup_user:<id> identifiers are supported in Task 6 path.',
            'storage identifier helper marker' => 'function eb_tenant_storage_identifier_for_user(int $userId): string',
            'upsert helper marker' => 'function eb_tenant_storage_links_upsert_for_client(int $clientId, string $storageIdentifier, ?int $canonicalTenantId): array',
            'list endpoint marker' => 'function eb_ph_tenant_storage_links_list(array $vars): void',
            'write endpoint marker' => 'function eb_ph_tenant_storage_links_write(array $vars): void',
            'canonical tenant ownership marker' => "Capsule::table('eb_whitelabel_tenants')->where('id', \$canonicalTenantId)->where('client_id', \$clientId)->first();",
            'canonical tenant assignable guard marker' => "if (!eb_tenant_storage_links_is_assignable_tenant_status((string) (\$tenant->status ?? ''))) {",
            'list assignable filter marker' => "->whereNotIn('status', ['deleted', 'removing'])",
            'storage ownership lookup marker' => "Capsule::table('s3_backup_users')->where('id', \$userId)->where('client_id', \$clientId)->exists();",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'tenant storage link canonical key upsert marker' => "->updateOrInsert(['storage_identifier' => \$storageIdentifier], [",
            'tenant storage link canonical key delete branch marker' => 'if ($canonicalTenantId === null) {',
            'tenant storage link canonical key delete marker' => "->where('storage_identifier', \$storageIdentifier)",
        ],
    ],
    'cloudstorage user create api file' => [
        'path' => $userCreateFile,
        'markers' => [
            'controller include marker' => "require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';",
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
            'canonical tenant post presence marker' => "\$canonicalTenantProvided = array_key_exists('canonical_tenant_id', \$_POST);",
            'canonical tenant post marker' => "\$canonicalTenantIdRaw = \$_POST['canonical_tenant_id'] ?? null;",
            'canonical direct precedence marker' => 'if ($isMsp) {',
            'msp legacy tenant compatibility marker' => 'if ($isMsp && $tenantId !== null && !$canonicalTenantProvided) {',
            'msp legacy tenant ownership check marker' => 'MspController::getTenant($tenantId, $clientId);',
            'canonical tenant ownership check marker' => 'eb_tenant_storage_links_resolve_tenant_for_client((int) $clientId, $canonicalTenantId);',
            'canonical to storage tenant mapping marker' => '$tenantId = eb_tenant_storage_links_resolve_or_create_storage_tenant_id((int) $clientId, $canonicalTenantId);',
            'storage identifier marker' => 'eb_tenant_storage_identifier_for_user((int) $userId);',
            'tenant storage link upsert marker' => 'eb_tenant_storage_links_upsert_for_client((int) $clientId, $storageIdentifier, $canonicalTenantId);',
        ],
    ],
    'cloudstorage user update api file' => [
        'path' => $userUpdateFile,
        'markers' => [
            'controller include marker' => "require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';",
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
            'canonical tenant post presence marker' => "\$canonicalTenantProvided = array_key_exists('canonical_tenant_id', \$_POST);",
            'canonical tenant post marker' => "\$canonicalTenantIdRaw = \$_POST['canonical_tenant_id'] ?? null;",
            'current canonical link lookup marker' => '$currentCanonicalLink = eb_tenant_storage_links_get_current_link_for_identifier((int) $clientId, $storageIdentifier);',
            'canonical managed tenant scope update guard marker' => "if (\$isMsp && !\$canonicalTenantProvided && \$currentCanonicalLink && \$tenantIdRaw !== null && \$tenantId !== \$currentTenantId) {",
            'canonical managed direct inference branch marker' => 'if ($tenantId === null) {',
            'canonical managed inferred canonical marker' => '$inferredCanonicalTenantId = eb_tenant_storage_links_infer_canonical_tenant_id_from_storage_tenant_id((int) $clientId, (int) $tenantId);',
            'canonical managed inferred promotion marker' => '$canonicalTenantId = $inferredCanonicalTenantId;',
            'canonical managed tenant scope update error marker' => 'Canonical-managed users require canonical_tenant_id for scope changes.',
            'legacy tenant scope ownership check marker' => 'MspController::getTenant($tenantId, $clientId);',
            'canonical tenant ownership check marker' => 'eb_tenant_storage_links_resolve_tenant_for_client((int) $clientId, $canonicalTenantId);',
            'canonical to storage tenant mapping marker' => '$tenantId = eb_tenant_storage_links_resolve_or_create_storage_tenant_id((int) $clientId, $canonicalTenantId);',
            'storage identifier marker' => 'eb_tenant_storage_identifier_for_user((int) $userId);',
            'tenant storage link upsert marker' => 'eb_tenant_storage_links_upsert_for_client((int) $clientId, $storageIdentifier, $canonicalTenantId);',
        ],
    ],
    'cloudstorage user reset password api file' => [
        'path' => $userResetPasswordFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage user delete api file' => [
        'path' => $userDeleteFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant create api file' => [
        'path' => $tenantCreateFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant update api file' => [
        'path' => $tenantUpdateFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant delete api file' => [
        'path' => $tenantDeleteFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant user create api file' => [
        'path' => $tenantUserCreateFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant user update api file' => [
        'path' => $tenantUserUpdateFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant user reset password api file' => [
        'path' => $tenantUserResetPasswordFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage tenant user delete api file' => [
        'path' => $tenantUserDeleteFile,
        'markers' => [
            'csrf token post marker' => "\$token = (string) (\$_POST['token'] ?? '');",
            'csrf fail-closed marker' => "if (\$token === '' || !function_exists('check_token')) {",
            'csrf check marker' => "if (!check_token('plain', \$token)) {",
            'csrf exception marker' => "} catch (\Throwable \$e) {",
        ],
    ],
    'cloudstorage users template file' => [
        'path' => $usersTemplateFile,
        'markers' => [
            'canonical tenants state marker' => 'canonicalTenants: [],',
            'load canonical tenants marker' => 'async loadCanonicalTenants() {',
            'canonical tenants endpoint marker' => "index.php?m=eazybackup&a=ph-tenant-storage-links'",
            'canonical tenants safe-empty marker' => 'this.canonicalTenants = [];',
            'assign tenant source marker' => 'return this.canonicalTenants;',
            'csrf token state marker' => "csrfToken: {/literal}{\$csrfToken|@json_encode nofilter}{literal} || '',",
            'create csrf token submit marker' => "body.set('token', this.csrfToken);",
            'create canonical tenant submit marker' => "body.set('canonical_tenant_id', this.form.tenant_id ? this.form.tenant_id : 'direct');",
        ],
        'forbidden' => [
            'legacy canonical fallback forbidden marker' => 'this.canonicalTenants = Array.isArray(this.tenants) ? this.tenants.slice() : [];',
        ],
    ],
    'cloudstorage user get api file' => [
        'path' => $userGetFile,
        'markers' => [
            'controller include marker' => "require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';",
            'canonical link lookup marker' => 'eb_tenant_storage_links_get_current_link_for_identifier((int) $clientId, $storageIdentifier);',
            'canonical managed state marker' => '$isCanonicalManaged = false;',
            'canonical managed response marker' => "'is_canonical_managed' => \$isCanonicalManaged,",
            'canonical tenant id response marker' => "'canonical_tenant_id' => \$canonicalTenantId,",
            'canonical tenant name response marker' => "'canonical_tenant_name' => \$canonicalTenantName,",
        ],
    ],
    'cloudstorage user detail page file' => [
        'path' => $userDetailPageFile,
        'markers' => [
            'controller include marker' => "require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';",
            'canonical tenants source marker' => "Capsule::table('eb_whitelabel_tenants')",
            'csrf token generate marker' => "\$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';",
            'csrf token return marker' => "'csrfToken' => \$csrfToken,",
            'canonical tenants template var marker' => "'canonicalTenants' => \$canonicalTenants,",
        ],
    ],
    'cloudstorage user detail template file' => [
        'path' => $userDetailTemplateFile,
        'markers' => [
            'canonical tenants state marker' => 'canonicalTenants: {/literal}{$canonicalTenants|@json_encode nofilter}{literal} || [],',
            'csrf token state marker' => "csrfToken: {/literal}{\$csrfToken|@json_encode nofilter}{literal} || '',",
            'canonical selection prefill marker' => "this.updateForm.tenant_id = this.user.canonical_tenant_id ? String(this.user.canonical_tenant_id) : '';",
            'canonical managed state marker' => 'is_canonical_managed: false,',
            'canonical submit predicate marker' => 'shouldSendCanonicalTenantOnUpdate() {',
            'update csrf token submit marker' => "body.set('token', this.csrfToken);",
            'reset csrf token submit marker' => "password_confirm: this.passwordForm.password_confirm\n                });\n                body.set('token', this.csrfToken);",
            'delete csrf token submit marker' => "const body = new URLSearchParams({ user_id: String(this.userId) });\n                body.set('token', this.csrfToken);",
            'canonical tenant submit marker' => "if (this.isMspClient && this.shouldSendCanonicalTenantOnUpdate()) {",
            'canonical direct submit marker' => "body.set('canonical_tenant_id', this.updateForm.tenant_id ? this.updateForm.tenant_id : 'direct');",
            'canonical tenant label marker' => 'const tenant = this.canonicalTenants.find((item) => String(item.id) === String(this.updateForm.tenant_id));',
        ],
        'forbidden' => [
            'legacy tenant id submit forbidden marker' => "body.set('tenant_id', this.updateForm.tenant_id);",
            'unguarded canonical direct submit forbidden marker' => "if (this.isMspClient) {\n                    body.set('canonical_tenant_id', this.updateForm.tenant_id ? this.updateForm.tenant_id : 'direct');",
        ],
    ],
    'cloudstorage users page file' => [
        'path' => $usersPageFile,
        'markers' => [
            'csrf token generate marker' => "\$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';",
            'csrf token return marker' => "'csrfToken' => \$csrfToken,",
        ],
    ],
    'cloudstorage tenant detail page file' => [
        'path' => $tenantDetailPageFile,
        'markers' => [
            'csrf token generate marker' => "\$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';",
            'csrf token return marker' => "'csrfToken' => \$csrfToken,",
        ],
    ],
    'cloudstorage tenant detail template file' => [
        'path' => $tenantDetailTemplateFile,
        'markers' => [
            'csrf token state marker' => "csrfToken: {/literal}{\$csrfToken|@json_encode nofilter}{literal} || '',",
            'create csrf token submit marker' => "body.set('token', this.csrfToken);",
            'tenant profile csrf token submit marker' => "country: (this.profileForm.country || '').toUpperCase()\n                });\n                body.set('token', this.csrfToken);",
            'tenant delete csrf token submit marker' => "const body = new URLSearchParams({ tenant_id: String(this.tenantId) });\n                body.set('token', this.csrfToken);",
            'tenant member csrf token submit marker' => "status: this.memberForm.status || 'active'\n                });\n                body.set('token', this.csrfToken);",
        ],
    ],
    'cloudstorage tenant members page file' => [
        'path' => $tenantMembersPageFile,
        'markers' => [
            'csrf token generate marker' => "\$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';",
            'csrf token return marker' => "'csrfToken' => \$csrfToken,",
        ],
    ],
    'cloudstorage tenant members template file' => [
        'path' => $tenantMembersTemplateFile,
        'markers' => [
            'csrf token state marker' => "csrfToken: {/literal}{\$csrfToken|@json_encode nofilter}{literal} || '',",
            'save member csrf token submit marker' => "params.set('token', this.csrfToken);",
            'reset member csrf token submit marker' => "password: this.newPassword\n                });\n                body.set('token', this.csrfToken);",
            'delete member csrf token submit marker' => "const body = new URLSearchParams({ user_id: user.id });\n                body.set('token', this.csrfToken);",
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
