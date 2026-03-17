# Tenant Public ID Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace client-visible canonical tenant numeric IDs with opaque ULID-based public IDs across Partner Hub and cloudstorage/e3 client-facing flows.

**Architecture:** Add a unique `public_id` column to `eb_tenants`, backfill existing rows, and generate ULIDs for all new canonical tenants. Then cut all client-visible routes, links, forms, and JSON payloads over to `public_id`, while keeping numeric `eb_tenants.id` internal-only for joins and persisted relations.

**Tech Stack:** PHP, WHMCS/Capsule schema helpers, Smarty templates, Alpine.js, PHP contract tests

---

### Task 1: Add canonical tenant public ID schema support

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php`
- Modify: `accounts/modules/addons/eazybackup/lib/PartnerHub/TenantCustomerService.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantStorageLinksController.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_canonical_schema_contract_test.php`
- Create: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

**Step 1: Write the failing test**

```php
<?php

$module = file_get_contents(__DIR__ . '/../../eazybackup.php');
$tenants = file_get_contents(__DIR__ . '/../../pages/partnerhub/TenantsController.php');

if (strpos($module, "eb_add_column_if_missing('eb_tenants','public_id'") === false) {
    echo "FAIL: missing eb_tenants public_id schema marker\n";
    exit(1);
}

if (strpos($module, 'idx_eb_tenants_public_id') === false) {
    echo "FAIL: missing eb_tenants public_id index marker\n";
    exit(1);
}

if (strpos($tenants, "'public_id' => eazybackup_generate_ulid()") === false) {
    echo "FAIL: missing tenant create public_id assignment\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: FAIL because `eb_tenants` does not yet declare or populate `public_id`.

**Step 3: Write minimal implementation**

```php
eb_add_column_if_missing('eb_tenants','public_id', fn(Blueprint $t)=>$t->char('public_id',26)->nullable());
eb_add_index_if_missing('eb_tenants', "CREATE UNIQUE INDEX IF NOT EXISTS idx_eb_tenants_public_id ON eb_tenants (public_id)");

Capsule::table('eb_tenants')->whereNull('public_id')->update([
    'public_id' => eazybackup_generate_ulid(),
]);
```

Also update canonical tenant create paths so new rows always include a generated `public_id`.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: `partnerhub-tenant-public-id-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php \
  accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php \
  accounts/modules/addons/eazybackup/lib/PartnerHub/TenantCustomerService.php \
  accounts/modules/addons/eazybackup/pages/partnerhub/TenantStorageLinksController.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_canonical_schema_contract_test.php \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-tenant-public-id-design.md \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-tenant-public-id-implementation-plan.md
git commit -m "feat: add canonical tenant public ids"
```

### Task 2: Cut Partner Hub tenant routes and templates over to public IDs

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantMembersController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantWhiteLabelController.php`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/tenants.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/tenant-detail.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/overview.tpl`
- Modify: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

**Step 1: Write the failing test**

```php
<?php

$template = file_get_contents(__DIR__ . '/../../templates/whitelabel/tenant-detail.tpl');
$controller = file_get_contents(__DIR__ . '/../../pages/partnerhub/TenantsController.php');

if (strpos($template, '{$tenant.public_id|escape}') === false) {
    echo "FAIL: missing tenant public_id display\n";
    exit(1);
}

if (strpos($template, 'value="{$tenant.public_id|escape}"') === false) {
    echo "FAIL: missing public_id hidden form value\n";
    exit(1);
}

if (strpos($controller, '$tenantPublicId = (string)($_GET[\'id\'] ?? $_POST[\'tenant_id\'] ?? \'\');') === false) {
    echo "FAIL: missing public id route resolution\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: FAIL because Partner Hub still resolves and renders numeric tenant IDs.

**Step 3: Write minimal implementation**

```php
$tenantPublicId = (string)($_GET['id'] ?? $_POST['tenant_id'] ?? '');
$tenant = Capsule::table('eb_tenants')
    ->where('public_id', $tenantPublicId)
    ->where('msp_id', (int)$msp->id)
    ->first();
```

Update all Partner Hub tenant links, hidden fields, and visible labels to use `public_id`, and stop rendering numeric tenant IDs in templates.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: `partnerhub-tenant-public-id-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php \
  accounts/modules/addons/eazybackup/pages/partnerhub/TenantMembersController.php \
  accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php \
  accounts/modules/addons/eazybackup/pages/partnerhub/TenantWhiteLabelController.php \
  accounts/modules/addons/eazybackup/templates/whitelabel/tenants.tpl \
  accounts/modules/addons/eazybackup/templates/whitelabel/tenant-detail.tpl \
  accounts/modules/addons/eazybackup/templates/whitelabel/overview.tpl \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php
git commit -m "refactor: use tenant public ids in partner hub"
```

### Task 3: Cut billing and plan-assignment browser flows over to public IDs

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/BillingController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/billing-payment-modal.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

**Step 1: Write the failing test**

```php
<?php

$paymentTemplate = file_get_contents(__DIR__ . '/../../templates/whitelabel/billing-payment-new.tpl');
$billingController = file_get_contents(__DIR__ . '/../../pages/partnerhub/BillingController.php');

if (strpos($paymentTemplate, 'tenant.public_id') === false) {
    echo "FAIL: missing public_id tenant picker binding\n";
    exit(1);
}

if (strpos($billingController, '$_POST[\'tenant_id\']') !== false && strpos($billingController, 'public_id') === false) {
    echo "FAIL: billing controller still keyed only by numeric tenant_id\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: FAIL because billing and plan-assignment payloads still submit numeric tenant IDs from the browser.

**Step 3: Write minimal implementation**

```php
$tenantPublicId = (string)($_POST['tenant_id'] ?? $_GET['tenant_id'] ?? '');
$tenant = Capsule::table('eb_tenants')
    ->where('public_id', $tenantPublicId)
    ->where('msp_id', (int)$msp->id)
    ->first(['id', 'public_id', 'name']);
```

Update tenant JSON blobs and JS bindings so the browser sends `public_id` while the controller resolves back to internal numeric `id`.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: `partnerhub-tenant-public-id-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/BillingController.php \
  accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php \
  accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl \
  accounts/modules/addons/eazybackup/templates/whitelabel/partials/billing-payment-modal.tpl \
  accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php
git commit -m "refactor: use tenant public ids in billing flows"
```

### Task 4: Cut cloudstorage and legacy e3 client-visible tenant references over to public IDs

**Files:**
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_tenant_list.php`
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_user_list.php`
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_user_get.php`
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_user_create.php`
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_user_update.php`
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_token_create.php`
- Modify: `accounts/modules/addons/cloudstorage/api/e3backup_token_list.php`
- Modify: `accounts/modules/addons/cloudstorage/api/cloudbackup_create_job.php`
- Modify: `accounts/modules/addons/cloudstorage/pages/e3backup_tenants.php`
- Modify: `accounts/modules/addons/cloudstorage/pages/e3backup_tenant_detail.php`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_tenants_table.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_tenant_detail.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_users.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_user_detail.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_agents.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_jobs.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_restores.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_tenant_members.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_tokens.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/partials/e3backup_create_user_modal.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/partials/job_create_wizard.tpl`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

**Step 1: Write the failing test**

```php
<?php

$tenantListApi = file_get_contents(__DIR__ . '/../../../cloudstorage/api/e3backup_tenant_list.php');
$tenantTable = file_get_contents(__DIR__ . '/../../../cloudstorage/templates/e3backup_tenants_table.tpl');

if (strpos($tenantListApi, 'public_id') === false) {
    echo "FAIL: tenant list API missing public_id output\n";
    exit(1);
}

if (strpos($tenantTable, 'tenant.public_id') === false && strpos($tenantTable, 'tenant_id=') !== false) {
    echo "FAIL: tenant table still links with numeric tenant_id\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: FAIL because cloudstorage/e3 browser surfaces still emit or consume numeric tenant IDs.

**Step 3: Write minimal implementation**

```php
->get([
    't.id as internal_id',
    't.public_id',
    't.name',
])
```

Replace client-visible tenant references with `public_id` throughout cloudstorage/e3, and resolve back to numeric `id` at the API/page boundary before existing internal queries continue.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: `partnerhub-tenant-public-id-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/cloudstorage/api/e3backup_tenant_list.php \
  accounts/modules/addons/cloudstorage/api/e3backup_user_list.php \
  accounts/modules/addons/cloudstorage/api/e3backup_user_get.php \
  accounts/modules/addons/cloudstorage/api/e3backup_user_create.php \
  accounts/modules/addons/cloudstorage/api/e3backup_user_update.php \
  accounts/modules/addons/cloudstorage/api/e3backup_token_create.php \
  accounts/modules/addons/cloudstorage/api/e3backup_token_list.php \
  accounts/modules/addons/cloudstorage/api/cloudbackup_create_job.php \
  accounts/modules/addons/cloudstorage/pages/e3backup_tenants.php \
  accounts/modules/addons/cloudstorage/pages/e3backup_tenant_detail.php \
  accounts/modules/addons/cloudstorage/templates/e3backup_tenants_table.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_tenant_detail.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_users.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_user_detail.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_agents.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_jobs.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_restores.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_tenant_members.tpl \
  accounts/modules/addons/cloudstorage/templates/e3backup_tokens.tpl \
  accounts/modules/addons/cloudstorage/templates/partials/e3backup_create_user_modal.tpl \
  accounts/modules/addons/cloudstorage/templates/partials/job_create_wizard.tpl \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php
git commit -m "refactor: use tenant public ids in cloudstorage ui"
```

### Task 5: Verify end-to-end client-visible cutover

**Files:**
- Modify: `accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md`
- Modify: `accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`

**Step 1: Write the failing test**

```php
<?php

$docs = file_get_contents(__DIR__ . '/../../Docs/PARTNER_HUB.md');

if (strpos($docs, 'public_id') === false) {
    echo "FAIL: Partner Hub docs missing tenant public_id contract\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`

Expected: FAIL until docs/contracts mention the new tenant public ID behavior.

**Step 3: Write minimal implementation**

```md
- Canonical tenants now expose `public_id` for client-visible routes and UI.
- Numeric `eb_tenants.id` remains internal-only.
```

Update the release gate or contract checks if they need to guard the new public ID contract.

**Step 4: Run test to verify it passes**

Run:
- `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php`
- `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php`
- `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`

Expected:
- `partnerhub-tenant-public-id-contract-ok`
- `partnerhub-tenant-detail-tab-routes-contract-ok`
- existing billing picker contract still passes, updated for public IDs if needed

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md \
  accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php
git commit -m "docs: document tenant public id contract"
```
