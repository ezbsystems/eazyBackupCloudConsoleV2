<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub tenant routing + canonical tenant CRUD wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenants_route_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$moduleFile = $moduleRoot . '/eazybackup.php';
$controllerFile = $moduleRoot . '/pages/partnerhub/TenantsController.php';
$listTemplateFile = $moduleRoot . '/templates/whitelabel/tenants.tpl';
$detailTemplateFile = $moduleRoot . '/templates/whitelabel/tenant-detail.tpl';
$cloudstorageTenantsFile = $repoRoot . '/accounts/modules/addons/cloudstorage/pages/e3backup_tenants.php';

$targets = [
    'module routing file' => [
        'path' => $moduleFile,
        'markers' => [
            'tenant list route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenants'",
            'tenant detail route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenant'",
            'tenant management entry route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-tenants-manage'",
            'tenants controller include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantsController.php';",
            'tenant list handler marker' => 'return eb_ph_tenants_redirect($vars);',
            'tenant detail handler marker' => 'return eb_ph_tenant_detail($vars);',
            'tenant management entry handler marker' => 'return eb_ph_tenants_management_entry($vars);',
        ],
    ],
    'tenants controller file' => [
        'path' => $controllerFile,
        'markers' => [
            'context helper marker' => 'function eb_ph_tenants_require_context(array $vars): array',
            'reseller gate marker' => "Capsule::table('tbladdonmodules')",
            'msp lookup marker' => "Capsule::table('eb_msp_accounts')->where('whmcs_client_id', \$clientId)->first();",
            'csrf helper marker' => 'function eb_ph_tenants_require_csrf_or_redirect(array $vars, string $token, ?string $tenantPublicId = null): void',
            'csrf validation marker' => "check_token('plain', \$token)",
            'csrf fail-closed marker' => 'error=csrf',
            'management entry function marker' => 'function eb_ph_tenants_management_entry(array $vars)',
            'list function marker' => 'function eb_ph_tenants_index(array $vars)',
            'list create post marker' => "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['eb_create_tenant']))",
            'list create canonical insert marker' => "Capsule::table('eb_tenants')->insertGetId(\$insert);",
            'list canonical owner marker' => "'msp_id' => (int)\$msp->id,",
            'list idempotency marker' => 'function eb_ph_tenants_generate_idempotency_key(): string',
            'list canonical fetch marker' => "\$rowsCol = Capsule::table('eb_tenants')->where('msp_id', (int)\$msp->id)",
            'list template marker' => "'templatefile' => 'whitelabel/tenants'",
            'detail function marker' => 'function eb_ph_tenant_detail(array $vars)',
            'detail canonical fetch marker' => "->where('public_id', \$tenantPublicId)",
            'detail save marker' => "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['eb_save_tenant']))",
            'detail update marker' => "Capsule::table('eb_tenants')->where('id', \$tenantId)->where('msp_id', (int)\$msp->id)->update(\$update);",
            'detail delete marker' => "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['eb_delete_tenant']))",
            'detail delete canonical ref guard marker' => "eb_ph_tenants_delete_blockers(\$tenantId);",
            'detail delete canonical ref error marker' => 'error=tenant_in_use',
            'detail delete canonical marker' => "Capsule::table('eb_tenants')->where('id', \$tenantId)->where('msp_id', (int)\$msp->id)->update([",
            'detail template marker' => "'templatefile' => 'whitelabel/tenant-detail'",
        ],
    ],
    'tenants list template file' => [
        'path' => $listTemplateFile,
        'markers' => [
            'tenants heading marker' => 'Customer Tenants',
            'create tenant hidden marker' => 'name="eb_create_tenant"',
            'create tenant csrf hidden marker' => 'name="token"',
            'manage tenant action marker' => '&a=ph-tenant&id=',
        ],
    ],
    'tenant detail template file' => [
        'path' => $detailTemplateFile,
        'markers' => [
            'save tenant hidden marker' => 'name="eb_save_tenant"',
            'delete tenant hidden marker' => 'name="eb_delete_tenant"',
            'tenant detail csrf hidden marker' => 'name="token"',
            'back to list marker' => '&a=ph-tenants-manage',
        ],
    ],
    'cloudstorage tenants page file' => [
        'path' => $cloudstorageTenantsFile,
        'markers' => [
            'auth check marker' => '$ca->isLoggedIn()',
            'msp check marker' => 'MspController::isMspClient($loggedInUserId)',
            'partner hub redirect marker' => "\$targetUrl = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenants';",
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
    $failures[] = "FAIL: unable to read tenants controller source for ordering checks";
} else {
    if (substr_count($controllerSource, 'eb_ph_tenants_require_csrf_or_redirect(') < 4) {
        $failures[] = 'FAIL: missing create/update/delete csrf enforcement calls';
    }

    $guardPos = strpos($controllerSource, "eb_ph_tenants_delete_blockers(\$tenantId);");
    $deletePos = strpos($controllerSource, "Capsule::table('eb_tenants')->where('id', \$tenantId)->where('msp_id', (int)\$msp->id)->update([");
    if ($guardPos === false || $deletePos === false) {
        $failures[] = 'FAIL: missing delete guard ordering markers';
    } elseif ($guardPos > $deletePos) {
        $failures[] = 'FAIL: tenant reference guard must run before delete';
    }
}

$detailTemplateSource = @file_get_contents($detailTemplateFile);
if ($detailTemplateSource === false) {
    $failures[] = "FAIL: unable to read tenant detail template source for csrf count";
} elseif (substr_count($detailTemplateSource, 'name="token"') < 2) {
    $failures[] = 'FAIL: tenant detail template missing csrf fields on save/delete forms';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-tenants-route-contract-ok\n";
exit(0);
