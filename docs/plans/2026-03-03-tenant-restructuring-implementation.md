# Tenant Model Restructuring — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Consolidate `eb_customers` + `s3_backup_tenants` into a single `eb_tenants` table in the eazybackup addon, re-point all FKs, and update all controllers/services/scripts.

**Architecture:** Replace the two-table customer model with `eb_tenants` as the sole canonical tenant. All `customer_id` columns pointing to `eb_customers.id` become `tenant_id` pointing to `eb_tenants.id`. Tenants have no WHMCS client — only the MSP does. A new `eb_tenant_services` table links MSP-owned WHMCS services to tenants.

**Tech Stack:** PHP 8.x, Illuminate Capsule (Eloquent outside Laravel), Smarty templates, MySQL/MariaDB, WHMCS addon module framework.

**Design doc:** `docs/plans/2026-03-03-tenant-restructuring-design.md`

---

### Task 1: Schema — Create `eb_tenants` table

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php` (inside `eazybackup_migrate_schema()`, after the `eb_whitelabel_tenants` block ~line 1161)

**Step 1: Add `eb_tenants` creation block**

Find the `// eb_customers` comment block (line ~1326) and insert the `eb_tenants` block BEFORE it. The new table replaces `eb_customers`.

```php
// ========= Canonical Tenants (replaces eb_customers + s3_backup_tenants) =========
if (!$schema->hasTable('eb_tenants')) {
    $schema->create('eb_tenants', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('msp_id');
        $t->string('name', 255);
        $t->string('slug', 100);
        $t->string('contact_email', 255)->nullable();
        $t->string('contact_name', 255)->nullable();
        $t->string('contact_phone', 50)->nullable();
        $t->string('address_line1', 255)->nullable();
        $t->string('address_line2', 255)->nullable();
        $t->string('city', 100)->nullable();
        $t->string('state', 100)->nullable();
        $t->string('postal_code', 20)->nullable();
        $t->char('country', 2)->nullable();
        $t->string('stripe_customer_id', 255)->nullable();
        $t->string('external_ref', 191)->nullable();
        $t->enum('status', ['active','suspended','deleted'])->default('active');
        $t->text('notes')->nullable();
        $t->text('branding_json')->nullable();
        $t->timestamp('created_at')->nullable()->useCurrent();
        $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        $t->unique(['msp_id','slug'], 'uq_tenant_msp_slug');
        $t->index('msp_id', 'idx_tenant_msp_id');
        $t->index('status', 'idx_tenant_status');
        $t->index('stripe_customer_id', 'idx_tenant_stripe_customer');
    });
}
```

**Step 2: Verify schema activates**

Run: `php -r "require '/var/www/eazybackup.ca/accounts/modules/addons/eazybackup/eazybackup.php';"` (or trigger module activation from WHMCS admin)
Expected: No fatal errors. Table `eb_tenants` exists in DB.

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php
git commit -m "schema: create eb_tenants canonical tenant table"
```

---

### Task 2: Schema — Create `eb_tenant_users` and `eb_tenant_services` tables

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php` (immediately after the `eb_tenants` block from Task 1)

**Step 1: Add `eb_tenant_users` creation block**

```php
if (!$schema->hasTable('eb_tenant_users')) {
    $schema->create('eb_tenant_users', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('tenant_id');
        $t->string('email', 255);
        $t->string('password_hash', 255);
        $t->string('name', 255);
        $t->enum('role', ['admin','user'])->default('user');
        $t->enum('status', ['active','disabled'])->default('active');
        $t->string('password_reset_token', 64)->nullable();
        $t->dateTime('password_reset_expires')->nullable();
        $t->dateTime('last_login_at')->nullable();
        $t->timestamp('created_at')->nullable()->useCurrent();
        $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        $t->unique(['tenant_id','email'], 'uq_tenant_user_email');
        $t->index('tenant_id', 'idx_tenant_user_tenant');
        $t->index('email', 'idx_tenant_user_email_lookup');
    });
}
```

**Step 2: Add `eb_tenant_services` creation block**

```php
if (!$schema->hasTable('eb_tenant_services')) {
    $schema->create('eb_tenant_services', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('tenant_id');
        $t->bigInteger('msp_id');
        $t->integer('hosting_id')->nullable();
        $t->bigInteger('catalog_product_id')->nullable();
        $t->enum('status', ['active','suspended','cancelled','pending'])->default('pending');
        $t->timestamp('provisioned_at')->nullable();
        $t->timestamp('created_at')->nullable()->useCurrent();
        $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        $t->unique('hosting_id', 'uq_tenant_hosting');
        $t->index('tenant_id', 'idx_ts_tenant');
        $t->index('msp_id', 'idx_ts_msp');
        $t->index('catalog_product_id', 'idx_ts_catalog');
    });
}
```

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php
git commit -m "schema: create eb_tenant_users and eb_tenant_services tables"
```

---

### Task 3: Schema — Add `whmcs_product_id` to `eb_catalog_products`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php` (in the `eb_catalog_products` else block, ~line 1531)

**Step 1: Add column migration**

Add to the existing `else` block for `eb_catalog_products` backfill:

```php
eb_add_column_if_missing('eb_catalog_products','whmcs_product_id', fn(Blueprint $t)=>$t->integer('whmcs_product_id')->nullable());
```

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php
git commit -m "schema: add whmcs_product_id to eb_catalog_products"
```

---

### Task 4: Schema — Re-point billing tables from `customer_id` to `tenant_id`

Since there is no production data, the safest approach is to modify the CREATE blocks so new installations get the correct schema. For existing dev DBs, drop the tables and let them recreate.

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php`

**Step 1: Update `eb_subscriptions` CREATE block (~line 1445)**

Change `customer_id` to `tenant_id` in the creation block:

```php
if (!$schema->hasTable('eb_subscriptions')) {
    $schema->create('eb_subscriptions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('msp_id')->index();
        $t->bigInteger('tenant_id')->index();          // was customer_id
        $t->bigInteger('plan_id')->nullable()->index();
        $t->string('stripe_subscription_id', 255)->nullable()->unique();
        $t->string('stripe_status', 32)->default('active');
        $t->bigInteger('current_price_id')->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('cancel_at')->nullable();
        $t->tinyInteger('cancel_at_period_end')->default(0);
        $t->timestamp('created_at')->nullable()->useCurrent();
        $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        $t->index(['tenant_id','stripe_status'], 'idx_sub_tenant_status');
    });
}
```

**Step 2: Update `eb_usage_ledger` CREATE block (~line 1463)**

Keep `tenant_id`, remove `customer_id`:

```php
if (!$schema->hasTable('eb_usage_ledger')) {
    $schema->create('eb_usage_ledger', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('tenant_id')->index();           // was customer_id + tenant_id
        $t->string('metric', 64);
        $t->bigInteger('qty')->default(0);
        $t->timestamp('period_start');
        $t->timestamp('period_end');
        $t->string('source', 32)->default('manual');
        $t->string('idempotency_key', 191)->nullable()->unique();
        $t->timestamp('pushed_to_stripe_at')->nullable();
        $t->timestamp('created_at')->nullable()->useCurrent();
    });
}
```

Remove the `else` block that backfills `tenant_id` column since it's now in the create block.

**Step 3: Update `eb_invoice_cache` CREATE block (~line 1482)**

Change `customer_id` to `tenant_id`.

**Step 4: Update `eb_payment_cache` CREATE block (~line 1498)**

Change `customer_id` to `tenant_id`.

**Step 5: Update `eb_service_links` CREATE block (~line 1373)**

Change `customer_id` to `tenant_id`. Update index name from `idx_service_msp_customer` to `idx_service_msp_tenant`.

**Step 6: Replace `eb_customer_comet_accounts` with `eb_tenant_comet_accounts` (~line 1360)**

Change the table name and column:

```php
if (!$schema->hasTable('eb_tenant_comet_accounts')) {
    $schema->create('eb_tenant_comet_accounts', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('tenant_id');
        $t->string('comet_user_id', 191);
        $t->unique(['tenant_id','comet_user_id'], 'uq_tenant_comet');
        $t->index('tenant_id', 'idx_tca_tenant');
        $t->index('comet_user_id', 'idx_tca_comet');
    });
}
```

**Step 7: Update Comet mirror columns (~line 1406)**

Change `customer_id` to `tenant_id`:

```php
foreach (['comet_users','comet_devices','comet_items','comet_jobs'] as $mirror) {
    if ($schema->hasTable($mirror)) {
        eb_add_column_if_missing($mirror, 'msp_id', fn(Blueprint $t)=>$t->bigInteger('msp_id')->nullable()->index());
        eb_add_column_if_missing($mirror, 'tenant_id', fn(Blueprint $t)=>$t->bigInteger('tenant_id')->nullable()->index());
    }
}
```

**Step 8: Remove `eb_customers` and `eb_customer_user_links` CREATE blocks**

Delete (or comment out) the entire `eb_customers` creation block (~lines 1326-1345) and the `eb_customer_user_links` block (~lines 1347-1358), plus the `eb_require_index` call for `uq_eb_customers_tenant_id`.

**Step 9: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php
git commit -m "schema: re-point billing tables from customer_id to tenant_id, drop eb_customers"
```

---

### Task 5: Schema — Guard cloudstorage `s3_backup_tenants` creation

**Files:**
- Modify: `accounts/modules/addons/cloudstorage/cloudstorage.php` (~line 1452)

**Step 1: Wrap `s3_backup_tenants` creation with guard**

Wrap the existing `s3_backup_tenants` creation block and its column additions with:

```php
if (!Capsule::schema()->hasTable('eb_tenants')) {
    // Legacy s3_backup_tenants — only created if eazybackup module hasn't created eb_tenants
    if (!Capsule::schema()->hasTable('s3_backup_tenants')) {
        // ... existing creation code ...
    }
    // ... existing column additions ...
}
```

**Step 2: Wrap `s3_backup_tenant_users` creation with same guard**

```php
if (!Capsule::schema()->hasTable('eb_tenant_users')) {
    if (!Capsule::schema()->hasTable('s3_backup_tenant_users')) {
        // ... existing creation code ...
    }
}
```

**Step 3: Commit**

```bash
git add accounts/modules/addons/cloudstorage/cloudstorage.php
git commit -m "schema: guard s3_backup_tenants creation when eb_tenants exists"
```

---

### Task 6: Update `TenantsController.php` — switch to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php`

**Step 1: Replace all `s3_backup_tenants` references with `eb_tenants`**

Global find-and-replace within this file:
- `'s3_backup_tenants'` → `'eb_tenants'`

Also update the insert in the POST handler to use `msp_id` instead of `client_id`:
- The current create inserts into `s3_backup_tenants` with `client_id` (the MSP's WHMCS client ID). Change this to insert `msp_id` (the `eb_msp_accounts.id`).

Verify the controller fetches `$msp` and uses `$msp->id` for the `msp_id` field. The slug generation and other fields should remain the same.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php
git commit -m "controller: switch TenantsController to eb_tenants"
```

---

### Task 7: Update `TenantMembersController.php` — switch to `eb_tenant_users`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantMembersController.php`

**Step 1: Replace table references**

- `'s3_backup_tenant_users'` → `'eb_tenant_users'`
- `'s3_backup_tenants'` → `'eb_tenants'` (for ownership check)

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantMembersController.php
git commit -m "controller: switch TenantMembersController to eb_tenant_users"
```

---

### Task 8: Update `TenantBillingController.php` — switch to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php`

**Step 1: Replace table and column references**

- `'eb_customers'` → `'eb_tenants'`
- `'customer_id'` → `'tenant_id'` (in billing table queries)
- Remove joins to `eb_customers` — query `eb_tenants` directly by `tenant_id`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php
git commit -m "controller: switch TenantBillingController to eb_tenants"
```

---

### Task 9: Update `TenantStorageLinksController.php`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantStorageLinksController.php`

**Step 1: Replace table references**

- `'s3_backup_tenants'` → `'eb_tenants'`
- Ensure ownership checks use `msp_id` = `$msp->id` instead of `client_id` = `$clientId`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantStorageLinksController.php
git commit -m "controller: switch TenantStorageLinksController to eb_tenants"
```

---

### Task 10: Update `ClientsController.php` — refactor for `eb_tenants`

This is the largest controller change. The current flow creates a WHMCS client and an `eb_customers` row. The new flow creates an `eb_tenants` row directly — no WHMCS client.

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/ClientsController.php`

**Step 1: Refactor `eb_ph_clients_index` POST handler**

Replace the `eb_create_client` POST branch. Instead of calling `WhmcsBridge::addClient()` and inserting into `eb_customers`:

1. Insert directly into `eb_tenants` with `msp_id`, `name`, `slug`, `contact_email`, `contact_name`, address fields, `status='active'`.
2. Remove all `TenantCustomerService` calls.
3. Remove WHMCS client creation (`WhmcsBridge::addClient`).
4. Optionally link a Comet user via `eb_tenant_comet_accounts` instead of `eb_customer_comet_accounts`.
5. Redirect to `ph-tenant&id=<new_tenant_id>` instead of `ph-client&id=<ecid>`.

**Step 2: Refactor the GET/list query**

Change from:
```php
Capsule::table('eb_customers as c')
    ->leftJoin('tblclients as wc','wc.id','=','c.whmcs_client_id')
    ->where('c.msp_id', (int)($msp->id ?? 0))
```

To:
```php
Capsule::table('eb_tenants as t')
    ->where('t.msp_id', (int)($msp->id ?? 0))
```

The join to `tblclients` is no longer needed since contact info lives directly on `eb_tenants`.

**Step 3: Update template reference**

The `templatefile` currently returns `whitelabel/clients`. This should remain as-is or be updated depending on whether a separate clients template is still desired. The `tenants.tpl` page already lists tenants, so this controller may eventually merge with `TenantsController`. For now, update the query and keep the template.

**Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/ClientsController.php
git commit -m "controller: refactor ClientsController to use eb_tenants, remove WHMCS client creation"
```

---

### Task 11: Update `ClientViewController.php` — switch to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/ClientViewController.php`

**Step 1: Replace `eb_customers` with `eb_tenants`**

- `Capsule::table('eb_customers')` → `Capsule::table('eb_tenants')`
- `->where('msp_id',(int)$msp->id)` stays the same.
- Remove `->where('whmcs_client_id',...)` references.
- Change `customer_id` to `tenant_id` in billing queries:
  - `eb_invoice_cache` → `where('tenant_id', $tenant->id)`
  - `eb_subscriptions` → `where('tenant_id', $tenant->id)`
  - `eb_payment_cache` → `where('tenant_id', $tenant->id)`
- Remove `tblhosting` query via `$cust->whmcs_client_id` — instead query `eb_tenant_services` joined to `tblhosting`.
- Remove `comet_users` query via `$cust->whmcs_client_id` — instead query `eb_tenant_comet_accounts`.

**Step 2: Update variable names in template vars**

Change `$cust` / `customer` to `$tenant` / `tenant` in the returned vars array.

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/ClientViewController.php
git commit -m "controller: switch ClientViewController to eb_tenants"
```

---

### Task 12: Update `SubscriptionsController.php` — switch to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/SubscriptionsController.php`

**Step 1: Replace all `eb_customers` references with `eb_tenants`**

- `Capsule::table('eb_customers')` → `Capsule::table('eb_tenants')`
- `->where('id',$customerId)` → `->where('id',$tenantId)`
- Rename `$customerId` → `$tenantId`, `$cust` → `$tenant`.
- In `eb_ph_stripe_subscribe`: `$svc->ensureStripeCustomerFor((int)$cust->id, ...)` → needs to be updated to work with `eb_tenants` (see Task 15 for StripeService changes).

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/SubscriptionsController.php
git commit -m "controller: switch SubscriptionsController to eb_tenants"
```

---

### Task 13: Update `BillingController.php` — switch to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/BillingController.php`

**Step 1: Replace all `eb_customers` references**

- `'eb_customers as c'` → `'eb_tenants as t'`
- `s.customer_id` → `s.tenant_id` in joins
- `c.name` → `t.name`, `c.id` → `t.id`
- Rename `customer_name` → `tenant_name`, `customer_row_id` → `tenant_row_id` in select aliases.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/BillingController.php
git commit -m "controller: switch BillingController to eb_tenants"
```

---

### Task 14: Update remaining controllers — `ServicesController`, `ProfileController`, `BackfillController`, `CatalogPlansController`, `UsageController`

These controllers all have a similar pattern: fetch `eb_customers` row by `id` + `msp_id`, then operate on it.

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/ServicesController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/ProfileController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/BackfillController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/UsageController.php`

**Step 1: In each file, replace:**

- `'eb_customers'` → `'eb_tenants'`
- `$customerId` / `customer_id` → `$tenantId` / `tenant_id`
- `$cust` → `$tenant`
- `$cust->whmcs_client_id` references → remove or replace with `eb_tenant_services` joins

**ServicesController specifics:**
- `eb_customer_comet_accounts` → `eb_tenant_comet_accounts`
- `customer_id` in `eb_service_links` → `tenant_id`
- `comet_users` `customer_id` update → `tenant_id`

**ProfileController specifics:**
- Currently updates WHMCS client profile via `$cust->whmcs_client_id`. Since tenants no longer have WHMCS clients, this should update `eb_tenants` contact fields directly instead of calling WHMCS LocalAPI `UpdateClient`.

**BackfillController specifics:**
- Calls `StripeService` methods with customer ID. Update to pass tenant ID (aligned with Task 15).

**CatalogPlansController specifics:**
- Lists `eb_customers` for MSP → change to list `eb_tenants`.

**UsageController specifics:**
- Looks up `eb_customers.tenant_id` to find tenant. Now just query `eb_tenants` directly.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/ServicesController.php \
      accounts/modules/addons/eazybackup/pages/partnerhub/ProfileController.php \
      accounts/modules/addons/eazybackup/pages/partnerhub/BackfillController.php \
      accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php \
      accounts/modules/addons/eazybackup/pages/partnerhub/UsageController.php
git commit -m "controller: switch remaining Partner Hub controllers to eb_tenants"
```

---

### Task 15: Update `StripeService.php` — switch `ensureStripeCustomerFor` to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php`

**Step 1: Update `ensureStripeCustomerFor`**

The method currently:
1. Fetches `eb_customers` by ID
2. Gets WHMCS client profile for name/email
3. Creates Stripe customer
4. Writes `stripe_customer_id` back to `eb_customers`

Change to:
1. Fetch `eb_tenants` by ID
2. Use `contact_name` and `contact_email` from `eb_tenants` directly (no WHMCS lookup)
3. Create Stripe customer
4. Write `stripe_customer_id` back to `eb_tenants`

```php
public function ensureStripeCustomerFor(int $tenantId, ?string $stripeAccount = null): string
{
    $tenant = Capsule::table('eb_tenants')->where('id', $tenantId)->first();
    if (!$tenant) { throw new \RuntimeException('Tenant not found'); }
    $scus = (string)($tenant->stripe_customer_id ?? '');
    if ($scus !== '') { return $scus; }
    $name = trim((string)($tenant->name ?? ''));
    $email = trim((string)($tenant->contact_email ?? ''));
    $created = $this->request('POST', '/v1/customers', [
        'name' => $name,
        'email' => $email,
    ], null, $stripeAccount);
    $scus = (string)($created['id'] ?? '');
    if ($scus !== '') {
        Capsule::table('eb_tenants')->where('id', $tenantId)->update([
            'stripe_customer_id' => $scus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
    return $scus;
}
```

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php
git commit -m "service: switch StripeService to eb_tenants"
```

---

### Task 16: Update `StripeWebhookController.php` — switch to `eb_tenants`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/StripeWebhookController.php`

**Step 1: Replace customer lookups**

Three locations look up `eb_customers` by `stripe_customer_id`. Change all to:

```php
$custId = Capsule::table('eb_tenants')->where('stripe_customer_id', $stripeCustomer)->value('id');
```

Also change `customer_id` to `tenant_id` in the `updateOrInsert` calls for `eb_invoice_cache` and `eb_payment_cache`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/StripeWebhookController.php
git commit -m "controller: switch StripeWebhookController to eb_tenants"
```

---

### Task 17: Rewrite `TenantCustomerService.php`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/lib/PartnerHub/TenantCustomerService.php`

**Step 1: Simplify the service**

The entire `ensureCustomerForTenant` method that bridged `eb_whitelabel_tenants` → `eb_customers` is no longer needed in its current form. The service should now simply look up the canonical `eb_tenants` row linked via `eb_whitelabel_tenants.canonical_tenant_id`.

```php
class TenantCustomerService
{
    public function getTenantForWhitelabel(int $whitelabelTenantId): ?array
    {
        if ($whitelabelTenantId <= 0) { return null; }
        try {
            $wl = Capsule::table('eb_whitelabel_tenants')
                ->where('id', $whitelabelTenantId)
                ->first(['canonical_tenant_id']);
            if (!$wl || !$wl->canonical_tenant_id) { return null; }
            $row = Capsule::table('eb_tenants')
                ->where('id', (int)$wl->canonical_tenant_id)
                ->first();
            return $row ? (array)$row : null;
        } catch (\Throwable $__) { return null; }
    }

    public function ensureMspAccountForClient(int $clientId): int
    {
        // Keep this method — still needed for MSP account lazy creation
        $existing = Capsule::table('eb_msp_accounts')
            ->where('whmcs_client_id', $clientId)
            ->first();
        if ($existing) { return (int)$existing->id; }
        $name = $this->resolveClientDisplayName($clientId);
        $now = date('Y-m-d H:i:s');
        try {
            return (int)Capsule::table('eb_msp_accounts')->insertGetId([
                'whmcs_client_id' => $clientId,
                'name' => $name,
                'status' => 'active',
                'billing_mode' => 'stripe_connect',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $__) {
            $raced = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
            if ($raced) { return (int)$raced->id; }
            throw $__;
        }
    }

    private function resolveClientDisplayName(int $clientId): string
    {
        // Keep existing implementation unchanged
    }
}
```

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/lib/PartnerHub/TenantCustomerService.php
git commit -m "service: simplify TenantCustomerService for eb_tenants model"
```

---

### Task 18: Update bin scripts — `stripe_tenant_usage_rollup.php` and `stripe_backfill_caches.php`

**Files:**
- Modify: `accounts/modules/addons/eazybackup/bin/stripe_tenant_usage_rollup.php`
- Modify: `accounts/modules/addons/eazybackup/bin/stripe_backfill_caches.php`

**Step 1: `stripe_tenant_usage_rollup.php`**

Replace:
```php
$customer = Capsule::table('eb_customers')
    ->where('tenant_id', $tenantId)
    ->first(['id', 'msp_id']);
```
With:
```php
$tenant = Capsule::table('eb_tenants')
    ->where('id', $tenantId)
    ->first(['id', 'msp_id']);
```

Then update all subsequent `$customer->` references to `$tenant->`.

**Step 2: `stripe_backfill_caches.php`**

Replace:
```php
$rows = Capsule::table('eb_customers')->whereNotNull('stripe_customer_id')->get(['id','stripe_customer_id']);
```
With:
```php
$rows = Capsule::table('eb_tenants')->whereNotNull('stripe_customer_id')->get(['id','stripe_customer_id']);
```

Update `customer_id` references in cache inserts to `tenant_id`.

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/bin/stripe_tenant_usage_rollup.php \
      accounts/modules/addons/eazybackup/bin/stripe_backfill_caches.php
git commit -m "scripts: switch bin scripts to eb_tenants"
```

---

### Task 19: Update templates and navigation

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/tenants.tpl`
- Check: `accounts/modules/addons/eazybackup/templates/whitelabel/client-view.tpl` (if exists)
- Check: `accounts/modules/addons/eazybackup/templates/whitelabel/clients.tpl` (if exists)
- Check: `accounts/templates/eazyBackup/includes/nav_partner_hub.tpl`

**Step 1: Update `tenants.tpl`**

- Change "Back to Clients" link to Partner Hub dashboard or remove it.
- Update any `$customer` Smarty variables to `$tenant` if the controller has changed the variable name.

**Step 2: Update client-view template**

If `client-view.tpl` exists, update `{$customer}` variables to `{$tenant}`. Remove references to WHMCS client profile fields. Update links from `ph-client` to `ph-tenant`.

**Step 3: Update navigation**

In `nav_partner_hub.tpl`, update "Clients" link to "Tenants" if appropriate, or remove the separate Clients nav entry.

**Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/ accounts/templates/eazyBackup/includes/
git commit -m "templates: update Partner Hub templates for eb_tenants model"
```

---

### Task 20: Update cloudstorage API files to use `eb_tenants` (if needed)

**Files (check each for `s3_backup_tenants` usage):**
- `accounts/modules/addons/cloudstorage/api/e3backup_tenant_create.php`
- `accounts/modules/addons/cloudstorage/api/e3backup_tenant_delete.php`
- `accounts/modules/addons/cloudstorage/api/e3backup_tenant_list.php`
- `accounts/modules/addons/cloudstorage/api/e3backup_tenant_user_*.php`
- `accounts/modules/addons/cloudstorage/lib/Client/CloudBackupBootstrapService.php`
- `accounts/modules/addons/cloudstorage/lib/Client/MspController.php`

**Step 1: In each API file, add a table resolution helper at the top:**

```php
$tenantTable = Capsule::schema()->hasTable('eb_tenants') ? 'eb_tenants' : 's3_backup_tenants';
```

Then use `$tenantTable` instead of hardcoded `'s3_backup_tenants'`. This maintains backward compatibility while preferring the new table.

Similarly for tenant users:
```php
$tenantUsersTable = Capsule::schema()->hasTable('eb_tenant_users') ? 'eb_tenant_users' : 's3_backup_tenant_users';
```

**Step 2: Note on `client_id` vs `msp_id`**

The cloudstorage APIs currently use `client_id` (the MSP's WHMCS client ID) on `s3_backup_tenants`. The new `eb_tenants` uses `msp_id` (FK to `eb_msp_accounts.id`). The API files will need to resolve `client_id` → `msp_id` via `eb_msp_accounts.whmcs_client_id`.

**Step 3: Commit**

```bash
git add accounts/modules/addons/cloudstorage/
git commit -m "cloudstorage: add eb_tenants compatibility layer to API files"
```

---

### Task 21: Update dev/test scripts and documentation

**Files:**
- Modify: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_canonical_schema_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenants_route_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md`
- Modify: `accounts/modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_MSP_ARCHITECTURE.md`
- Modify: `accounts/modules/addons/eazybackup/lib/eazyBackup-Partner-Hub.md`

**Step 1: Update contract tests**

Replace `eb_customers` and `s3_backup_tenants` references with `eb_tenants`. Update assertions to check the new table name and column names.

**Step 2: Update documentation**

Add a section to `PARTNER_HUB.md` documenting:
- The `eb_tenants` table replaces both `eb_customers` and `s3_backup_tenants`.
- Tenants have no WHMCS client — only the MSP does.
- The `eb_tenant_services` table links WHMCS services to tenants.
- Portal auth uses `eb_tenant_users` (standalone, not WHMCS login).

**Step 3: Archive the migration script**

`bin/dev/migrate_tenant_v2_canonical.php` is no longer relevant (it migrated between the old tables). Add a comment at the top noting it's obsolete.

**Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/bin/dev/ \
      accounts/modules/addons/eazybackup/Docs/ \
      accounts/modules/addons/cloudstorage/docs/ \
      accounts/modules/addons/eazybackup/lib/eazyBackup-Partner-Hub.md
git commit -m "docs: update documentation and tests for eb_tenants restructuring"
```

---

### Task 22: Drop old tables from dev database and verify

**Step 1: Drop old tables (dev only)**

```sql
DROP TABLE IF EXISTS eb_customers;
DROP TABLE IF EXISTS eb_customer_user_links;
DROP TABLE IF EXISTS eb_customer_comet_accounts;
DROP TABLE IF EXISTS s3_backup_tenants;
DROP TABLE IF EXISTS s3_backup_tenant_users;
DROP TABLE IF EXISTS eb_subscriptions;
DROP TABLE IF EXISTS eb_usage_ledger;
DROP TABLE IF EXISTS eb_invoice_cache;
DROP TABLE IF EXISTS eb_payment_cache;
DROP TABLE IF EXISTS eb_service_links;
```

**Step 2: Trigger schema migration**

Activate the eazybackup addon module (or call `eazybackup_migrate_schema()`) to recreate all tables with the new schema.

**Step 3: Verify**

```sql
SHOW TABLES LIKE 'eb_tenants';
SHOW TABLES LIKE 'eb_tenant_users';
SHOW TABLES LIKE 'eb_tenant_services';
DESCRIBE eb_tenants;
DESCRIBE eb_subscriptions;   -- should have tenant_id, not customer_id
DESCRIBE eb_usage_ledger;    -- should have tenant_id only
SHOW TABLES LIKE 'eb_customers';  -- should not exist
```

**Step 4: Commit any final fixes**

```bash
git add -A
git commit -m "verify: tenant restructuring schema and controllers complete"
```
