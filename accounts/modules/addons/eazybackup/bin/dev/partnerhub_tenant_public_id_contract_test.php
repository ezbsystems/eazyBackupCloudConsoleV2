<?php

declare(strict_types=1);

/**
 * Contract test: canonical tenant public_id schema + create-path intent markers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'module file' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'markers' => [
            'eb_tenants public_id schema marker' => "eb_add_column_if_missing('eb_tenants','public_id'",
            'eb_tenants public_id index marker' => 'idx_eb_tenants_public_id',
            'eb_tenants public_id backfill marker' => "whereNull('public_id')",
            'eb_tenants public_id backfill hard-fail marker' => 'Canonical tenant public_id backfill incomplete',
            'tenant public_id generator marker' => 'eazybackup_generate_ulid()',
            'legacy ph-clients route include marker' => "require_once __DIR__ . '/pages/partnerhub/TenantsController.php';",
            'legacy ph-clients canonical redirect marker' => 'return eb_ph_tenants_legacy_clients_redirect($vars);',
        ],
    ],
    'tenants controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/TenantsController.php',
        'markers' => [
            'tenant create public_id schema guard marker' => "hasColumn('eb_tenants', 'public_id')",
            'tenant create public_id assignment marker' => "\$insert['public_id'] = eazybackup_generate_ulid();",
            'tenant public-only resolver helper marker' => 'function eb_ph_tenants_find_owned_tenant_by_public_id(int $mspId, string $tenantPublicId): ?object',
            'tenant public-only resolver numeric reject marker' => "if (preg_match('/^\\d+$/', \$tenantPublicId)) {",
        ],
    ],
    'tenant customer service file' => [
        'path' => $moduleRoot . '/lib/PartnerHub/TenantCustomerService.php',
        'markers' => [
            'tenant customer service public_id schema guard marker' => "hasColumn('eb_tenants', 'public_id')",
            'tenant customer service public_id conditional update marker' => "->where(function (\$query) {",
            'tenant customer service public_id conditional assignment marker' => "'public_id' => \$publicId",
            'tenant customer service public_id insert assignment marker' => "\$insert['public_id'] = eazybackup_generate_ulid();",
        ],
    ],
    'tenant storage links controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/TenantStorageLinksController.php',
        'markers' => [
            'tenant storage links public_id schema guard marker' => "hasColumn('eb_tenants', 'public_id')",
            'tenant storage links public_id conditional update marker' => "->where(function (\$query) {",
            'tenant storage links public_id conditional assignment marker' => "\$publicId = eazybackup_generate_ulid();",
            'tenant storage links public_id insert assignment marker' => "\$insert['public_id'] = eazybackup_generate_ulid();",
        ],
    ],
    'tenant route resolution file' => [
        'path' => $moduleRoot . '/pages/partnerhub/TenantsController.php',
        'markers' => [
            'tenant public id route resolution marker' => "\$tenantPublicId = (string)(\$_GET['id'] ?? \$_POST['tenant_id'] ?? '');",
            'tenant public id lookup marker' => "->where('public_id', \$tenantPublicId)",
            'tenant redirect public id marker' => "function eb_ph_tenant_redirect(array \$vars, string \$tenantPublicId, string \$query = ''): void",
            'tenant tab links public id marker' => "function eb_ph_tenant_tab_links(array \$vars, string \$tenantPublicId): array",
            'tenant legacy mixed reference helper marker' => 'function eb_ph_tenants_find_owned_tenant_by_reference(int $mspId, string $tenantReference): ?object',
            'tenant canonical list redirect marker' => "\$url = eb_ph_tenants_base_link(\$vars) . '&a=ph-tenants-manage';",
        ],
    ],
    'legacy client route file' => [
        'path' => $moduleRoot . '/pages/partnerhub/ClientViewController.php',
        'markers' => [
            'legacy client route tenants helper marker' => "require_once __DIR__ . '/TenantsController.php';",
            'legacy client route mixed tenant reference marker' => "\$tenantReference = trim((string)(\$_GET['id'] ?? ''));",
            'legacy client route canonical redirect marker' => "eb_ph_tenant_redirect(\$vars, (string)\$tenant->public_id, 'legacy=ph-client');",
        ],
    ],
    'legacy subscriptions route file' => [
        'path' => $moduleRoot . '/pages/partnerhub/SubscriptionsController.php',
        'markers' => [
            'legacy subscriptions route tenants helper marker' => "require_once __DIR__ . '/TenantsController.php';",
            'legacy subscriptions route mixed tenant reference marker' => "\$tenantReference = trim((string)(\$_GET['tenant_id'] ?? \$_GET['customer_id'] ?? ''));",
            'legacy subscriptions route canonical redirect marker' => "eb_ph_tenant_redirect(\$vars, (string)\$tenant->public_id, 'legacy=ph-subscriptions');",
            'legacy subscriptions create mixed tenant reference marker' => "\$tenantReference = trim((string)(\$_POST['tenant_id'] ?? \$_POST['customer_id'] ?? ''));",
        ],
    ],
    'tenant detail template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/tenant-detail.tpl',
        'markers' => [
            'tenant detail public id breadcrumb marker' => '{$tenant.public_id|escape}',
            'tenant detail public id hidden input marker' => 'value="{$tenant.public_id|escape}"',
            'tenant detail public id route marker' => '&a=ph-tenant&id={$tenant.public_id|escape:\'url\'}',
            'tenant detail billing public id label marker' => '{$billing_tenant.public_id|escape}',
            'tenant detail canonical list route marker' => '&a=ph-tenants-manage',
        ],
    ],
    'tenants list template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/tenants.tpl',
        'markers' => [
            'tenants list public id dataset marker' => 'data-tenant-public-id="{$tenant.public_id|escape}"',
            'tenants list manage route marker' => '&a=ph-tenant&id={$tenant.public_id|escape:\'url\'}',
            'tenants list create canonical action marker' => 'action="{$modulelink}&a=ph-tenants-manage"',
        ],
    ],
    'overview template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/overview.tpl',
        'markers' => [
            'overview recent tenant public id route marker' => '&a=ph-tenant&id={$tenant.public_id|escape:\'url\'}',
            'overview canonical tenants manage route marker' => '&a=ph-tenants-manage',
        ],
    ],
    'billing controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/BillingController.php',
        'markers' => [
            'billing controller tenant public id create resolution marker' => "\$tenantPublicId = trim((string)(\$_POST['tenant_id'] ?? ''));",
            'billing controller tenant public id payment methods resolution marker' => "\$tenantPublicId = trim((string)(\$_GET['tenant_id'] ?? \$_POST['tenant_id'] ?? ''));",
            'billing controller tenant public id lookup marker' => "->where('public_id', \$tenantPublicId)",
            'billing controller browser row public id marker' => "'t.public_id as tenant_public_id'",
            'billing controller browser row tenant status marker' => "'t.status as tenant_status'",
            'billing controller deleted tenant payment selector marker' => "->where('msp_id',(int)\$msp->id)->where('status', '!=', 'deleted')->orderBy('name','asc')->get(['public_id','name','contact_email','stripe_customer_id']);",
            'billing controller deleted tenant new-payment selector marker' => "->where('msp_id',(int)\$msp->id)->where('status', '!=', 'deleted')->orderBy('name','asc')->get(['public_id','name','contact_email']);",
            'billing controller deleted tenant resolver marker' => "->where('status', '!=', 'deleted')",
            'billing controller csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'billing controller token view marker' => "'token' => function_exists('generate_token') ? generate_token('plain') : '',",
        ],
    ],
    'billing subscriptions template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/billing-subscriptions.tpl',
        'markers' => [
            'billing subscriptions tenant public id route marker' => '&a=ph-tenant&id={$row.tenant_public_id|escape:\'url\'}',
            'billing subscriptions deleted tenant guard marker' => "{if \$row.tenant_public_id|default:'' neq '' && \$row.tenant_status|default:'' neq 'deleted'}",
            'billing subscriptions deleted tenant badge marker' => 'Deleted tenant',
        ],
    ],
    'catalog plans controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/CatalogPlansController.php',
        'markers' => [
            'catalog plans tenant public id payload marker' => "\$tenantPublicId = trim((string)(\$_POST['tenant_id'] ?? ''));",
            'catalog plans tenant public id lookup marker' => "->where('public_id', \$tenantPublicId)",
            'catalog plans comet user ownership validation marker' => "->where(function (\$query) use (\$cometUserId) {",
            'catalog plans comet user ownership fields marker' => "->first(['tca.id']);",
            'catalog plans tenant public id tenant export marker' => "'t.public_id as tenant_public_id'",
            'catalog plans subscriptions explicit fields marker' => "'pi.id', 'pi.comet_user_id', 'pi.status', 'pi.created_at'",
            'catalog plans subscriptions valid tenant name marker' => "'t.name as tenant_name'",
            'catalog plans deleted tenant selector marker' => "->where('msp_id',(int)\$msp->id)->where('status', '!=', 'deleted')->orderBy('id','desc')->limit(200)->get(['public_id', 'name']);",
            'catalog plans deleted tenant comet account marker' => "->where('t.status','!=','deleted')",
            'catalog plans deleted tenant resolver marker' => "->where('status', '!=', 'deleted')",
        ],
    ],
    'billing payment modal template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/partials/billing-payment-modal.tpl',
        'markers' => [
            'billing payment modal tenant public id loop marker' => 'tenant.public_id',
            'billing payment modal tenant public id request marker' => 'this.selectedTenant.public_id',
            'billing payment modal csrf token config marker' => "token: \"{\$token|escape:'javascript'}\"",
            'billing payment modal csrf request marker' => 'payload.token = this.token;',
        ],
    ],
    'billing payment new template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/billing-payment-new.tpl',
        'markers' => [
            'billing payment new csrf hidden input marker' => '<input id="np-token" type="hidden" value="{$token|escape}" />',
            'billing payment new csrf request marker' => 'const token = document.getElementById(\'np-token\').value;',
        ],
    ],
    'subscriptions controller csrf file' => [
        'path' => $moduleRoot . '/pages/partnerhub/SubscriptionsController.php',
        'markers' => [
            'subscriptions controller csrf marker' => "eb_ph_tenants_require_csrf_or_redirect(\$vars, \$token, trim((string)(\$tenant->public_id ?? '')));",
        ],
    ],
    'profile controller csrf file' => [
        'path' => $moduleRoot . '/pages/partnerhub/ProfileController.php',
        'markers' => [
            'profile controller csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'profile controller public-only tenant resolver marker' => 'eb_ph_tenants_find_owned_tenant_by_public_id',
        ],
    ],
    'stripe controller csrf file' => [
        'path' => $moduleRoot . '/pages/partnerhub/StripeController.php',
        'markers' => [
            'stripe controller csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'stripe controller public-only tenant resolver marker' => 'eb_ph_tenants_find_owned_tenant_by_public_id',
        ],
    ],
    'services controller csrf file' => [
        'path' => $moduleRoot . '/pages/partnerhub/ServicesController.php',
        'markers' => [
            'services controller csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'services controller public-only tenant resolver marker' => 'eb_ph_tenants_find_owned_tenant_by_public_id',
        ],
    ],
    'usage controller csrf file' => [
        'path' => $moduleRoot . '/pages/partnerhub/UsageController.php',
        'markers' => [
            'usage controller csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'usage controller public-only tenant resolver marker' => 'eb_ph_tenants_find_owned_tenant_by_public_id',
        ],
    ],
    'backfill controller csrf file' => [
        'path' => $moduleRoot . '/pages/partnerhub/BackfillController.php',
        'markers' => [
            'backfill invoices csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'backfill payouts csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'backfill disputes csrf marker' => "if (!eb_ph_tenants_require_csrf_or_json_error((string)(\$_POST['token'] ?? ''))) { return; }",
            'backfill controller public-only tenant resolver marker' => 'eb_ph_tenants_find_owned_tenant_by_public_id',
        ],
    ],
    'money payouts template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/money-payouts.tpl',
        'markers' => [
            'money payouts csrf request marker' => "body: new URLSearchParams({ token: '{\$token|escape:'javascript'}' }).toString()",
        ],
    ],
    'money disputes template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/money-disputes.tpl',
        'markers' => [
            'money disputes csrf request marker' => "body: new URLSearchParams({ token: '{\$token|escape:'javascript'}' }).toString()",
        ],
    ],
    'catalog plans template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/catalog-plans.tpl',
        'markers' => [
            'catalog plans tenant option public id marker' => '<option value="{$c.public_id|escape}">',
            'catalog plans comet accounts public id marker' => '"tenant_public_id":"{$ca.tenant_public_id|escape:\'javascript\'}"',
        ],
    ],
    'catalog plans js file' => [
        'path' => $moduleRoot . '/assets/js/catalog-plans.js',
        'markers' => [
            'catalog plans js tenant public id matcher marker' => 'String(a.tenant_public_id || \'\') === tenantPublicId',
        ],
    ],
    'cloudstorage tenant list api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_tenant_list.php',
        'markers' => [
            'cloudstorage tenant list public id browser alias marker' => "Capsule::raw('t.public_id as id')",
            'cloudstorage tenant list explicit public id marker' => "'t.public_id'",
        ],
    ],
    'cloudstorage msp controller file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/lib/Client/MspController.php',
        'markers' => [
            'cloudstorage msp controller browser-safe tenants marker' => "Capsule::raw('public_id as id')",
            'cloudstorage msp controller legacy redirect resolver marker' => 'public static function resolveTenantPublicIdForClient(string $tenantReference, int $clientId): ?string',
        ],
    ],
    'cloudstorage user list api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_user_list.php',
        'markers' => [
            'cloudstorage user list public id filter resolution marker' => "MspController::getTenantByPublicId(\$tenantFilterRaw, \$clientId)",
            'cloudstorage user list public id select marker' => "Capsule::raw('t.public_id as tenant_id')",
        ],
    ],
    'cloudstorage user get api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_user_get.php',
        'markers' => [
            'cloudstorage user get public id select marker' => "Capsule::raw('t.public_id as tenant_id')",
            'cloudstorage user get canonical public id marker' => "'canonical_tenant_id' => \$canonicalTenantPublicId",
        ],
    ],
    'cloudstorage user create api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_user_create.php',
        'markers' => [
            'cloudstorage user create tenant public id resolution marker' => "MspController::getTenantByPublicId(\$tenantIdRaw, \$clientId)",
            'cloudstorage user create canonical public id resolution marker' => 'eb_tenant_storage_links_resolve_tenant_for_client_by_public_id((int) $clientId, $canonicalTenantIdRaw)',
        ],
    ],
    'cloudstorage user update api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_user_update.php',
        'markers' => [
            'cloudstorage user update tenant public id resolution marker' => "MspController::getTenantByPublicId(\$tenantIdRaw, \$clientId)",
            'cloudstorage user update canonical public id resolution marker' => 'eb_tenant_storage_links_resolve_tenant_for_client_by_public_id((int) $clientId, $canonicalTenantIdRaw)',
        ],
    ],
    'cloudstorage token create api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_token_create.php',
        'markers' => [
            'cloudstorage token create tenant public id resolution marker' => "MspController::getTenantByPublicId(\$tenantPublicId, \$clientId)",
            'cloudstorage token create csrf input marker' => "\$csrfToken = (string) (\$_POST['token'] ?? '');",
            'cloudstorage token create csrf validation marker' => "check_token('plain', \$csrfToken)",
        ],
    ],
    'cloudstorage token list api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_token_list.php',
        'markers' => [
            'cloudstorage token list public id select marker' => "Capsule::raw('tn.public_id as tenant_id')",
        ],
    ],
    'cloudstorage create job api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/cloudbackup_create_job.php',
        'markers' => [
            'cloudstorage create job tenant public id resolution marker' => "MspController::getTenantByPublicId(\$tenantIdRaw, \$loggedInUserId)",
            'cloudstorage create job csrf input marker' => "\$csrfToken = (string) (\$_POST['token'] ?? '');",
            'cloudstorage create job csrf validation marker' => "check_token('plain', \$csrfToken)",
        ],
    ],
    'cloudstorage job list api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_job_list.php',
        'markers' => [
            'cloudstorage job list deleted tenant public id null marker' => "CASE WHEN t.id IS NULL THEN NULL ELSE t.public_id END as tenant_id",
        ],
    ],
    'cloudstorage restore points list api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_restore_points_list.php',
        'markers' => [
            'cloudstorage restore points deleted tenant public id null marker' => "CASE WHEN t.id IS NULL THEN NULL ELSE t.public_id END as tenant_id",
        ],
    ],
    'cloudstorage tenant member create api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_tenant_user_create.php',
        'markers' => [
            'cloudstorage tenant member create public id resolution marker' => "MspController::getTenantByPublicId(\$tenantPublicId, \$clientId)",
        ],
    ],
    'cloudstorage tenant update api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_tenant_update.php',
        'markers' => [
            'cloudstorage tenant update public id resolution marker' => "MspController::getTenantByPublicId(\$tenantPublicId, \$clientId)",
        ],
    ],
    'cloudstorage tenant delete api file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_tenant_delete.php',
        'markers' => [
            'cloudstorage tenant delete public id resolution marker' => "MspController::getTenantByPublicId(\$tenantPublicId, \$clientId)",
        ],
    ],
    'cloudstorage tenants legacy page file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/pages/e3backup_tenants.php',
        'markers' => [
            'cloudstorage tenants legacy page public id boundary marker' => "\$tenantPublicId = MspController::resolveTenantPublicIdForClient((string) (\$_GET['tenant_id'] ?? ''), \$loggedInUserId) ?? '';",
            'cloudstorage tenants legacy page partner hub redirect marker' => "index.php?m=eazybackup&a=ph-tenant&id=' . rawurlencode(\$tenantPublicId) . '&legacy=e3-tenants'",
        ],
    ],
    'cloudstorage tenant detail legacy page file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/pages/e3backup_tenant_detail.php',
        'markers' => [
            'cloudstorage tenant detail legacy page public id boundary marker' => "\$tenantPublicId = MspController::resolveTenantPublicIdForClient((string) (\$_GET['tenant_id'] ?? ''), \$loggedInUserId) ?? '';",
            'cloudstorage tenant detail legacy page partner hub redirect marker' => "index.php?m=eazybackup&a=ph-tenant&id=' . rawurlencode(\$tenantPublicId) . '&legacy=e3-tenant-detail'",
        ],
    ],
    'cloudstorage tenant members legacy page file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/pages/e3backup_tenant_members.php',
        'markers' => [
            'cloudstorage tenant members legacy page public id boundary marker' => "\$tenantPublicId = MspController::resolveTenantPublicIdForClient((string) (\$_GET['tenant_id'] ?? ''), \$loggedInUserId) ?? '';",
            'cloudstorage tenant members legacy page partner hub redirect marker' => "index.php?m=eazybackup&a=ph-tenant-members&id=' . rawurlencode(\$tenantPublicId) . '&legacy=e3-tenant-members'",
        ],
    ],
    'cloudstorage tenants table template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_tenants_table.tpl',
        'markers' => [
            'cloudstorage tenants table public id route marker' => "@click=\"goToDetail(tenant.public_id || tenant.id)\"",
        ],
    ],
    'cloudstorage users template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_users.tpl',
        'markers' => [
            'cloudstorage users template public id filter marker' => "setTenantFilter(String(tenant.public_id || tenant.id))",
        ],
    ],
    'cloudstorage user detail template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_user_detail.tpl',
        'markers' => [
            'cloudstorage user detail public id tenant bootstrap marker' => '{$user->tenant_public_id|@json_encode nofilter}',
        ],
    ],
    'cloudstorage user detail page file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/pages/e3backup_user_detail.php',
        'markers' => [
            'cloudstorage user detail page public id select marker' => "Capsule::raw('t.public_id as tenant_public_id')",
            'cloudstorage user detail page internal tenant alias marker' => "'u.tenant_id as storage_tenant_id'",
            'cloudstorage user detail page canonical public id list marker' => "'public_id'",
            'cloudstorage user detail page canonical public id export marker' => "'id' => \$publicId",
        ],
    ],
    'cloudstorage tenant storage links controller file' => [
        'path' => dirname($moduleRoot) . '/eazybackup/pages/partnerhub/TenantStorageLinksController.php',
        'markers' => [
            'cloudstorage tenant storage links public id resolver marker' => 'function eb_tenant_storage_links_resolve_tenant_for_client_by_public_id(int $clientId, string $canonicalTenantPublicId)',
            'cloudstorage tenant storage links canonical public id ensure marker' => 'function eb_tenant_storage_links_ensure_canonical_tenant_public_id(object $canonicalTenant): string',
            'cloudstorage tenant storage links opaque slug marker' => "return 'eb-link-' . strtolower(trim(\$canonicalTenantPublicId));",
            'cloudstorage tenant storage links neutral name helper marker' => "\$name = 'Tenant ' . substr(\$canonicalTenantPublicId, 0, 8);",
            'cloudstorage tenant storage links public id infer marker' => "preg_match('/^eb-link-([0-9a-z]{26})$/', \$slug, \$matches)",
            'cloudstorage tenant storage links public id list marker' => "'id' => \$publicId",
            'cloudstorage tenant storage links write public id marker' => "'canonical_tenant_id' => \$canonicalTenantPublicId",
        ],
    ],
    'cloudstorage agents template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_agents.tpl',
        'markers' => [
            'cloudstorage agents template public id filter marker' => 'tenantFilter=\'{$tenant->public_id|escape:\'javascript\'}\'',
        ],
    ],
    'cloudstorage jobs template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_jobs.tpl',
        'markers' => [
            'cloudstorage jobs template public id filter marker' => 'tenantFilter=\'{$tenant->public_id|escape:\'javascript\'}\'',
            'cloudstorage jobs template csrf request marker' => "formData.set('token', '{/literal}{\$token|escape:'javascript'}{literal}');",
            'cloudstorage jobs template local wizard csrf request marker' => "payload.token = '{/literal}{\$token|escape:'javascript'}{literal}';",
        ],
    ],
    'cloudstorage jobs page file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/pages/e3backup_jobs.php',
        'markers' => [
            'cloudstorage jobs page csrf token marker' => "\$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';",
            'cloudstorage jobs page csrf export marker' => "'token' => \$csrfToken,",
        ],
    ],
    'cloudstorage restores template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_restores.tpl',
        'markers' => [
            'cloudstorage restores template public id filter marker' => 'selectTenant(String(tenant.public_id || tenant.id));',
        ],
    ],
    'cloudstorage tenant members template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_tenant_members.tpl',
        'markers' => [
            'cloudstorage tenant members partner hub public id marker' => "index.php?m=eazybackup&a=ph-tenant&id=' + encodeURIComponent(resolvedTenantId) + '&legacy=e3-tenant-members'",
        ],
    ],
    'cloudstorage tokens template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/e3backup_tokens.tpl',
        'markers' => [
            'cloudstorage tokens template public id option marker' => '<option value="{$tenant->public_id|escape}">{$tenant->name|escape}</option>',
            'cloudstorage tokens template csrf request marker' => "token: '{/literal}{\$token|escape:'javascript'}{literal}'",
        ],
    ],
    'cloudstorage tokens page file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/pages/e3backup_tokens.php',
        'markers' => [
            'cloudstorage tokens page csrf token marker' => "\$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';",
            'cloudstorage tokens page csrf export marker' => "'token' => \$csrfToken,",
        ],
    ],
    'cloudstorage create user modal template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/partials/e3backup_create_user_modal.tpl',
        'markers' => [
            'cloudstorage create user modal public id marker' => 'tenant.public_id',
        ],
    ],
    'cloudstorage create job wizard template file' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/templates/partials/job_create_wizard.tpl',
        'markers' => [
            'cloudstorage create job wizard public id option marker' => '<option value="{$tenant->public_id|escape}">{$tenant->name|escape}</option>',
        ],
    ],
    'partner hub docs file' => [
        'path' => $moduleRoot . '/Docs/PARTNER_HUB.md',
        'markers' => [
            'partner hub docs tenant public id client-visible marker' => '`public_id` is the only tenant identifier exposed in client-visible routes, forms, and JSON payloads.',
            'partner hub docs tenant numeric id internal-only marker' => '`eb_tenants.id` remains internal-only for joins, persistence, and server-side resolution.',
            'partner hub docs tenant canonical manage route marker' => 'Route: `index.php?m=eazybackup&a=ph-tenants-manage`',
        ],
    ],
    'msp billing release gate file' => [
        'path' => $moduleRoot . '/bin/dev/msp_billing_release_gate.php',
        'markers' => [
            'msp billing release gate public id contract command marker' => 'partnerhub_tenant_public_id_contract_test.php',
            'msp billing release gate tenant route contract command marker' => 'partnerhub_tenant_detail_tab_routes_contract_test.php',
            'msp billing release gate billing picker contract command marker' => 'partnerhub_billing_payment_new_client_picker_contract_test.php',
        ],
    ],
    'module routes canonicalization file' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'markers' => [
            'ph-tenants canonical redirect marker' => 'return eb_ph_tenants_redirect($vars);',
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

$forbiddenMarkers = [
    'legacy ph-clients missing controller include marker' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'needle' => "require_once __DIR__ . '/pages/partnerhub/ClientsController.php';",
    ],
    'billing controller legacy tenant row id marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/BillingController.php',
        'needle' => 'tenant_row_id',
    ],
    'billing subscriptions legacy numeric tenant route marker' => [
        'path' => $moduleRoot . '/templates/whitelabel/billing-subscriptions.tpl',
        'needle' => 'ph-client&id={$row.tenant_row_id}',
    ],
    'legacy client route numeric tenant lookup marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/ClientViewController.php',
        'needle' => "->where('id', \$id)",
    ],
    'legacy subscriptions route numeric tenant lookup marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/SubscriptionsController.php',
        'needle' => "->where('id', \$tenantId)",
    ],
    'profile controller mixed tenant resolver marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/ProfileController.php',
        'needle' => 'eb_ph_tenants_find_owned_tenant_by_reference',
    ],
    'stripe controller mixed tenant resolver marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/StripeController.php',
        'needle' => 'eb_ph_tenants_find_owned_tenant_by_reference',
    ],
    'services controller mixed tenant resolver marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/ServicesController.php',
        'needle' => 'eb_ph_tenants_find_owned_tenant_by_reference',
    ],
    'usage controller mixed tenant resolver marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/UsageController.php',
        'needle' => 'eb_ph_tenants_find_owned_tenant_by_reference',
    ],
    'backfill controller mixed tenant resolver marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/BackfillController.php',
        'needle' => 'eb_ph_tenants_find_owned_tenant_by_reference',
    ],
    'catalog plans subscriptions numeric leak marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/CatalogPlansController.php',
        'needle' => "'pi.*'",
    ],
    'catalog plans invalid tenant company marker' => [
        'path' => $moduleRoot . '/pages/partnerhub/CatalogPlansController.php',
        'needle' => 'tenant_company',
    ],
    'cloudstorage tenant storage links numeric id export marker' => [
        'path' => dirname($moduleRoot) . '/eazybackup/pages/partnerhub/TenantStorageLinksController.php',
        'needle' => "'id' => (int) \$row->id",
    ],
    'cloudstorage user get numeric canonical tenant marker' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_user_get.php',
        'needle' => "'canonical_tenant_id' => \$canonicalTenantId",
    ],
    'cloudstorage tenant storage links numeric slug marker' => [
        'path' => dirname($moduleRoot) . '/eazybackup/pages/partnerhub/TenantStorageLinksController.php',
        'needle' => "\$slug = 'eb-canonical-' . \$canonicalTenantId",
    ],
    'cloudstorage tenant storage links numeric fallback name marker' => [
        'path' => dirname($moduleRoot) . '/eazybackup/pages/partnerhub/TenantStorageLinksController.php',
        'needle' => "\$name = 'Canonical Tenant #' . \$canonicalTenantId",
    ],
    'cloudstorage job list raw tenant public id export marker' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_job_list.php',
        'needle' => 't.public_id as tenant_id',
    ],
    'cloudstorage restore points raw tenant public id export marker' => [
        'path' => dirname($moduleRoot) . '/cloudstorage/api/e3backup_restore_points_list.php',
        'needle' => 't.public_id as tenant_id',
    ],
];

foreach ($forbiddenMarkers as $markerName => $marker) {
    $source = @file_get_contents($marker['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read forbidden marker target for {$markerName}";
        continue;
    }
    if (strpos($source, $marker['needle']) !== false) {
        $failures[] = "FAIL: found {$markerName}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-tenant-public-id-contract-ok\n";
exit(0);
