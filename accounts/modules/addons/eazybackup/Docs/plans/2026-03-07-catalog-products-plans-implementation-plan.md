# Partner Hub Catalog — Products & Plans Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rebuild the Partner Hub Catalog Products and Plans pages into a complete, polished MSP billing management system with full Stripe parity.

**Architecture:** PHP backend (WHMCS addon, Capsule ORM, Stripe Connect API) serves Smarty templates with Alpine.js for reactivity. Product/Plan CRUD happens via AJAX endpoints in controller files, persisted to `eb_*` tables and synchronized with Stripe's connected account. A slide-over panel pattern (already used on Products) will be extended to Plans.

**Tech Stack:** PHP 8.2, WHMCS Capsule (Laravel ORM), Stripe PHP SDK (via CatalogService/StripeService wrappers), Smarty 3 templates, Alpine.js, Tailwind CSS, vanilla JavaScript.

**Design doc:** `Docs/plans/2026-03-07-catalog-products-plans-redesign-design.md`

---

## Codebase orientation

| Concern | File path |
|---------|-----------|
| Module router | `eazybackup.php` (routes at ~line 4100+) |
| Schema migrations | `eazybackup.php` → `eazybackup_migrate_schema()` (~line 526) |
| Products controller | `pages/partnerhub/CatalogProductsController.php` |
| Plans controller | `pages/partnerhub/CatalogPlansController.php` |
| Old plans controller | `pages/partnerhub/PlansController.php` (DEPRECATED) |
| Products list template | `templates/whitelabel/catalog-products-list.tpl` (canonical) |
| Products card template | `templates/whitelabel/catalog-products.tpl` (will be retired) |
| Plans template | `templates/whitelabel/catalog-plans.tpl` |
| Products JS | `assets/js/catalog-products.js` |
| Plans JS | `assets/js/catalog-plans.js` |
| Sidebar | `templates/whitelabel/partials/sidebar_partner_hub.tpl` |
| Stripe service | `lib/PartnerHub/StripeService.php` |
| Catalog service | `lib/PartnerHub/CatalogService.php` |
| Partner Hub docs | `Docs/PARTNER_HUB.md` |

All paths below are relative to `accounts/modules/addons/eazybackup/`.

**Important terminology rules:**
- Never use "Comet" in any frontend UI. Use "eazyBackup User" or "eazyBackup Username" instead.
- Frontend labels for overage modes: "Bill all usage" (not `bill_all`), "Cap at included" (not `cap_at_default`).

**Testing approach:** This is a PHP/WHMCS project without automated tests. Each task includes manual verification steps. Test in browser at the dev server after each change.

---

## Phase 1 — Foundation

### Task 1: Schema migrations — new columns and tables

**Files:**
- Modify: `eazybackup.php` (inside `eazybackup_migrate_schema()`, after the `eb_plan_instance_items` block ~line 1722)

**Step 1: Add new columns to `eb_catalog_prices`**

Add this block after the existing `eb_catalog_prices` table creation, inside `eazybackup_migrate_schema()`:

```php
// Tiered pricing columns on eb_catalog_prices
try {
    if ($schema->hasTable('eb_catalog_prices')) {
        if (!$schema->hasColumn('eb_catalog_prices', 'pricing_scheme')) {
            $schema->table('eb_catalog_prices', function (Blueprint $t) {
                $t->string('pricing_scheme', 20)->default('per_unit')->after('billing_type');
                $t->string('tiers_mode', 20)->nullable()->after('pricing_scheme');
                $t->text('tiers_json')->nullable()->after('tiers_mode');
            });
        }
    }
} catch (\Throwable $__) {}
```

**Step 2: Add `product_template` column to `eb_catalog_products`**

```php
try {
    if ($schema->hasTable('eb_catalog_products') && !$schema->hasColumn('eb_catalog_products', 'product_template')) {
        $schema->table('eb_catalog_products', function (Blueprint $t) {
            $t->string('product_template', 50)->nullable()->after('base_metric_code');
        });
    }
} catch (\Throwable $__) {}
```

**Step 3: Add `attributes_json` column to `eb_catalog_products`**

```php
try {
    if ($schema->hasTable('eb_catalog_products') && !$schema->hasColumn('eb_catalog_products', 'attributes_json')) {
        $schema->table('eb_catalog_products', function (Blueprint $t) {
            $t->text('attributes_json')->nullable()->after('features_json');
        });
    }
} catch (\Throwable $__) {}
```

**Step 4: Add new columns to `eb_plan_templates`**

```php
try {
    if ($schema->hasTable('eb_plan_templates')) {
        if (!$schema->hasColumn('eb_plan_templates', 'billing_interval')) {
            $schema->table('eb_plan_templates', function (Blueprint $t) {
                $t->string('billing_interval', 10)->default('month')->after('trial_days');
                $t->string('currency', 3)->default('CAD')->after('billing_interval');
                $t->string('status', 20)->default('active')->after('active');
                $t->text('metadata_json')->nullable()->after('status');
            });
        }
    }
} catch (\Throwable $__) {}
```

**Step 5: Add new columns to `eb_plan_instances`**

```php
try {
    if ($schema->hasTable('eb_plan_instances')) {
        if (!$schema->hasColumn('eb_plan_instances', 'cancelled_at')) {
            $schema->table('eb_plan_instances', function (Blueprint $t) {
                $t->dateTime('cancelled_at')->nullable()->after('status');
                $t->string('cancel_reason', 255)->nullable()->after('cancelled_at');
            });
        }
        // Also add tenant_id alias if missing (eb_plan_instances uses customer_id but plans use tenant_id)
        if (!$schema->hasColumn('eb_plan_instances', 'tenant_id')) {
            $schema->table('eb_plan_instances', function (Blueprint $t) {
                $t->bigInteger('tenant_id')->nullable()->index()->after('customer_id');
            });
        }
    }
} catch (\Throwable $__) {}
```

**Step 6: Create `eb_plan_instance_usage_map` table**

```php
if (!$schema->hasTable('eb_plan_instance_usage_map')) {
    $schema->create('eb_plan_instance_usage_map', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->bigInteger('plan_instance_item_id')->index();
        $t->string('metric_code', 50)->index();
        $t->string('stripe_subscription_item_id', 255);
        $t->dateTime('last_pushed_at')->nullable();
        $t->timestamp('created_at')->nullable()->useCurrent();
        $t->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
    });
}
```

**Step 7: Verify migration runs**

Navigate to any Partner Hub page in the browser. The page load triggers `eazybackup_migrate_schema()`. Then verify:

```sql
DESCRIBE eb_catalog_prices;
-- Should include: pricing_scheme, tiers_mode, tiers_json

DESCRIBE eb_catalog_products;
-- Should include: product_template, attributes_json

DESCRIBE eb_plan_templates;
-- Should include: billing_interval, currency, status, metadata_json

DESCRIBE eb_plan_instances;
-- Should include: cancelled_at, cancel_reason, tenant_id

SHOW TABLES LIKE 'eb_plan_instance_usage_map';
-- Should return 1 row
```

**Step 8: Commit**

```bash
git add eazybackup.php
git commit -m "feat(schema): add tiered pricing, plan metadata, usage map columns and table"
```

---

### Task 2: Consolidate Products pages — redirect duplicate route

**Files:**
- Modify: `eazybackup.php` (~line 4108, the `ph-catalog-product` route)

**Step 1: Change `ph-catalog-product` to redirect**

Find this block in `eazybackup.php`:

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product') {
    require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
    return eb_ph_catalog_product_show($vars);
```

Replace with:

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product') {
    header('Location: ' . ($vars['modulelink'] ?? 'index.php?m=eazybackup') . '&a=ph-catalog-products');
    exit;
```

**Step 2: Verify redirect**

Navigate to `index.php?m=eazybackup&a=ph-catalog-product` in the browser. Should redirect to `ph-catalog-products`.

**Step 3: Commit**

```bash
git add eazybackup.php
git commit -m "feat(routing): redirect ph-catalog-product to consolidated products list"
```

---

### Task 3: Retire old Plans system — redirect route

**Files:**
- Modify: `eazybackup.php` (~line 4102, the `ph-plans` route)

**Step 1: Change `ph-plans` to redirect**

Find:

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plans') {
    require_once __DIR__ . '/pages/partnerhub/PlansController.php';
    return eb_ph_plans_index($vars);
```

Replace with:

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plans') {
    // DEPRECATED: Old Plans system — redirect to Catalog Plans
    header('Location: ' . ($vars['modulelink'] ?? 'index.php?m=eazybackup') . '&a=ph-catalog-plans');
    exit;
```

**Step 2: Add deprecation comment to PlansController.php**

At the top of `pages/partnerhub/PlansController.php`, after the `<?php` tag, add:

```php
// @deprecated — This controller is deprecated. Use CatalogPlansController.php instead.
// The ph-plans route now redirects to ph-catalog-plans.
```

**Step 3: Verify redirect**

Navigate to `index.php?m=eazybackup&a=ph-plans`. Should redirect to `ph-catalog-plans`.

**Step 4: Commit**

```bash
git add eazybackup.php pages/partnerhub/PlansController.php
git commit -m "feat(routing): deprecate old plans system, redirect to catalog plans"
```

---

### Task 4: Update PARTNER_HUB.md with new schema

**Files:**
- Modify: `Docs/PARTNER_HUB.md`

**Step 1: Add documentation for new tables and columns**

Find the database schema section in `PARTNER_HUB.md` and add documentation for:
- New columns on `eb_catalog_prices` (pricing_scheme, tiers_mode, tiers_json)
- New columns on `eb_catalog_products` (product_template, attributes_json)
- New columns on `eb_plan_templates` (billing_interval, currency, status, metadata_json)
- New columns on `eb_plan_instances` (cancelled_at, cancel_reason, tenant_id)
- `eb_plan_instance_usage_map` table
- `eb_plan_templates` table (if not already documented)
- `eb_plan_components` table (if not already documented)
- `eb_plan_instance_items` table (if not already documented)

**Step 2: Commit**

```bash
git add Docs/PARTNER_HUB.md
git commit -m "docs: document plan template, component, instance, and usage map schema"
```

---

## Phase 2 — Products Page Completion

### Task 5: Product filter tabs and type dropdown

**Files:**
- Modify: `templates/whitelabel/catalog-products-list.tpl`

**Step 1: Add Alpine.js filter state to the page container**

Update the `x-data` on the container `div` (line 9 of `catalog-products-list.tpl`) to include filter state. Change the `x-data` attribute on the outer `<div class="container ...">` to:

```html
x-data="{ q:'', statusFilter:'all', typeFilter:'all', matches(n, status, type){ if(this.statusFilter!=='all' && status!==this.statusFilter) return false; if(this.typeFilter!=='all' && type!==this.typeFilter) return false; if(!this.q) return true; try{ return String(n||'').toLowerCase().indexOf(String(this.q).toLowerCase())>=0; }catch(_){ return true; } } }"
```

**Step 2: Add filter tabs HTML**

After the counter cards `div` (after the closing `</div>` of the `grid grid-cols-1 md:grid-cols-3` block, ~line 50), insert:

```html
<div class="mb-4 flex flex-wrap items-center gap-3">
  <div class="flex items-center rounded-lg border border-slate-700 bg-slate-800/50 p-0.5">
    <template x-for="tab in [{ldelim}v:'all',l:'All'},{ldelim}v:'active',l:'Active'},{ldelim}v:'draft',l:'Draft'},{ldelim}v:'archived',l:'Archived'}]" :key="tab.v">
      <button type="button" @click="statusFilter=tab.v" :class="statusFilter===tab.v ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'" class="px-3 py-1.5 text-xs font-medium rounded-md transition" x-text="tab.l"></button>
    </template>
  </div>
  <select x-model="typeFilter" class="px-3 py-1.5 rounded-lg bg-slate-800 text-xs text-slate-300 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600">
    <option value="all">All Types</option>
    <option value="STORAGE_TB">Storage</option>
    <option value="DEVICE_COUNT">Device Count</option>
    <option value="DISK_IMAGE">Disk Image</option>
    <option value="HYPERV_VM">Hyper-V VM</option>
    <option value="PROXMOX_VM">Proxmox VM</option>
    <option value="VMWARE_VM">VMware VM</option>
    <option value="M365_USER">Microsoft 365 User</option>
    <option value="GENERIC">Generic</option>
  </select>
</div>
```

**Step 3: Update product row `x-show` directives**

For local products, update the `x-show` on each product card to use the new `matches()`:

```html
x-show="matches('{$p.name|escape}', '{if $p.stripe_product_id}active{elseif $p.active}draft{else}archived{/if}', '{$p.base_metric_code|default:'GENERIC'|escape}')"
```

For Stripe products in the table, update similarly:

```html
x-show="matches('{$sp.name|escape}', '{if $sp.active}active{else}archived{/if}', 'all')"
```

**Step 4: Verify filters**

Open the Products page, test each filter tab and type dropdown. Products should filter immediately via Alpine reactivity.

**Step 5: Commit**

```bash
git add templates/whitelabel/catalog-products-list.tpl
git commit -m "feat(products): add status tabs and product type filter dropdown"
```

---

### Task 6: Draft product delete endpoint and UI

**Files:**
- Modify: `pages/partnerhub/CatalogProductsController.php` (add new function)
- Modify: `eazybackup.php` (add route)
- Modify: `templates/whitelabel/catalog-products-list.tpl` (add delete button)
- Modify: `assets/js/catalog-products.js` (add delete handler)

**Step 1: Add delete endpoint in CatalogProductsController.php**

At the end of the file, before the closing `?>` (if any) or at end, add:

```php
function eb_ph_catalog_product_delete_draft(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'id']); return; }
    $prod = Capsule::table('eb_catalog_products')->where('id',$id)->first();
    if (!$prod || (int)$prod->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    if (!empty($prod->stripe_product_id)) { echo json_encode(['status'=>'error','message'=>'published_cannot_delete']); return; }
    try {
        Capsule::table('eb_catalog_prices')->where('product_id',$id)->delete();
        Capsule::table('eb_catalog_products')->where('id',$id)->delete();
        echo json_encode(['status'=>'success']);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>'delete_fail']);
    }
}
```

**Step 2: Add route in eazybackup.php**

After the `ph-catalog-product-delete-stripe` route block, add:

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-product-delete-draft') {
    require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
    eb_ph_catalog_product_delete_draft($vars); exit;
```

**Step 3: Add delete button to draft product rows in template**

In `catalog-products-list.tpl`, for each local product card, add a kebab menu next to the "Edit" button with "Delete draft" option (only visible when `!$p.stripe_product_id`).

**Step 4: Add delete handler in catalog-products.js**

Add to `window.ebStripeActions`:

```javascript
async deleteDraft(id){
  if (!confirm('Delete this draft product and all its prices? This cannot be undone.')) return;
  try {
    const token = (document.getElementById('eb-token')||{}).value || '';
    const body = new URLSearchParams({ token, id: String(id) });
    const res = await fetch(`${modulelink}&a=ph-catalog-product-delete-draft`, { method:'POST', credentials:'include', headers:{ 'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json' }, body });
    const out = await res.json();
    if (out && out.status==='success'){ setTimeout(()=>location.reload(),500); }
    else { alert('Delete failed'+(out && out.message ? ': '+out.message : '')); }
  } catch(e){ console.error(e); alert('Network error'); }
}
```

**Step 5: Verify**

Create a draft product, then delete it from the list. Verify it disappears. Try deleting a published product — should show error.

**Step 6: Commit**

```bash
git add pages/partnerhub/CatalogProductsController.php eazybackup.php templates/whitelabel/catalog-products-list.tpl assets/js/catalog-products.js
git commit -m "feat(products): add delete draft product endpoint and UI"
```

---

### Task 7: Explicit Publish flow — Save Draft vs Publish buttons

**Files:**
- Modify: `templates/whitelabel/catalog-products-list.tpl` (slide-over footer)
- Modify: `assets/js/catalog-products.js` (productPanelFactory save method)

**Step 1: Update slide-over footer buttons**

In `catalog-products-list.tpl`, find the slide-over footer (the `border-t border-slate-800 px-6 py-5` div near line 282). Replace the single Save button with:

```html
<template x-if="mode==='create' || mode==='edit'">
  <div class="flex items-center gap-3">
    <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="save('draft')" :disabled="isSaving">Save Draft</button>
    <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="save('publish')" :disabled="isSaving">Publish to Stripe</button>
  </div>
</template>
<template x-if="mode==='editStripe'">
  <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="save()" :disabled="isSaving">Save</button>
</template>
```

**Step 2: Update save method in productPanelFactory**

In `catalog-products.js`, update the `save()` method in `productPanelFactory` to accept an optional `mode` parameter. When `mode === 'publish'`, set the JSON body mode to `'publish'` instead of `'draft'`.

The existing `save()` method already sends `mode:'draft'` for local saves. Add `mode` parameter:

```javascript
async save(mode){
  // ... existing validation ...
  if (this.mode==='editStripe') {
    // Stripe save logic (unchanged)
  }
  // Local create/edit
  const body = { mode: mode || 'draft', /* ... rest unchanged ... */ };
  // ...
}
```

**Step 3: Add Publish action to draft rows on the list**

In the product card kebab menu for draft products, add a "Publish to Stripe" button that calls `window.ebProductPanel.openEdit(id)` and then triggers publish.

**Step 4: Verify**

Create a product, save as draft. Edit it again, click "Publish to Stripe". Verify the product appears on Stripe.

**Step 5: Commit**

```bash
git add templates/whitelabel/catalog-products-list.tpl assets/js/catalog-products.js
git commit -m "feat(products): explicit Save Draft and Publish to Stripe buttons"
```

---

### Task 8: Product type contextual help

**Files:**
- Modify: `templates/whitelabel/catalog-products-list.tpl` (slide-over panel)
- Modify: `assets/js/catalog-products.js` (productPanelFactory)

**Step 1: Add description method to productPanelFactory**

In `catalog-products.js`, add this method to `productPanelFactory`:

```javascript
metricDescription(v){
  switch(String(v||'')){
    case 'STORAGE_TB': return 'Metered billing based on the customer\'s storage consumption. Priced per GiB or TiB.';
    case 'DEVICE_COUNT': return 'Per-unit billing for each backup endpoint (workstation or server) registered in the customer\'s account.';
    case 'DISK_IMAGE': return 'Per-unit billing for each machine protected with disk image backups.';
    case 'HYPERV_VM': return 'Per-unit billing for each Microsoft Hyper-V virtual machine being backed up.';
    case 'PROXMOX_VM': return 'Per-unit billing for each Proxmox virtual machine being backed up.';
    case 'VMWARE_VM': return 'Per-unit billing for each VMware virtual machine being backed up.';
    case 'M365_USER': return 'Per-unit billing for each Microsoft 365 user account protected.';
    case 'GENERIC': return 'Flexible billing for any service you provide — IT support, antivirus, consulting, or any recurring/one-time charge.';
    default: return '';
  }
}
```

**Step 2: Add description display in template**

In the product type button grid area of the slide-over in `catalog-products-list.tpl`, after the `flex flex-wrap gap-2` div, add:

```html
<template x-if="baseMetric">
  <p class="mt-3 text-xs text-slate-400 bg-slate-900/50 rounded-lg px-3 py-2" x-text="metricDescription(baseMetric)"></p>
</template>
```

**Step 3: Verify**

Open the product slide-over. Click each product type button. Verify description updates reactively.

**Step 4: Commit**

```bash
git add templates/whitelabel/catalog-products-list.tpl assets/js/catalog-products.js
git commit -m "feat(products): add contextual help descriptions for each product type"
```

---

### Task 9: Product templates/presets

**Files:**
- Modify: `templates/whitelabel/catalog-products-list.tpl`
- Modify: `assets/js/catalog-products.js`

**Step 1: Add preset cards in the slide-over**

In `catalog-products-list.tpl`, inside the slide-over `flex-1 overflow-y-auto` area, before the product name input, add a "Start from template" section (visible only in create mode):

```html
<template x-if="mode==='create'">
  <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
    <h4 class="text-sm font-medium text-slate-100 mb-3">Start from template</h4>
    <div class="grid grid-cols-2 gap-2">
      <button type="button" @click="applyPreset('eazybackup_cloud_backup')" :class="preset==='eazybackup_cloud_backup' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
        <div class="text-sm font-medium text-slate-100">eazyBackup Cloud Backup</div>
        <div class="text-xs text-slate-400 mt-1">Storage (metered, GiB, monthly)</div>
      </button>
      <button type="button" @click="applyPreset('e3_object_storage')" :class="preset==='e3_object_storage' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
        <div class="text-sm font-medium text-slate-100">e3 Object Storage</div>
        <div class="text-xs text-slate-400 mt-1">Storage (metered, GiB, monthly, 1 TiB min)</div>
      </button>
      <button type="button" @click="applyPreset('workstation_seat')" :class="preset==='workstation_seat' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
        <div class="text-sm font-medium text-slate-100">Workstation Backup Seat</div>
        <div class="text-xs text-slate-400 mt-1">Device count (per-unit, monthly)</div>
      </button>
      <button type="button" @click="applyPreset('custom_service')" :class="preset==='custom_service' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
        <div class="text-sm font-medium text-slate-100">Custom Service</div>
        <div class="text-xs text-slate-400 mt-1">Generic (per-unit, monthly)</div>
      </button>
    </div>
    <template x-if="preset">
      <div class="mt-2 flex items-center gap-2">
        <span class="text-xs text-sky-400">Using template: <span x-text="preset"></span></span>
        <button type="button" @click="clearPreset()" class="text-xs text-slate-500 hover:text-white underline">Clear</button>
      </div>
    </template>
  </div>
</template>
```

**Step 2: Add preset methods to productPanelFactory**

In `catalog-products.js`, add to `productPanelFactory`:

```javascript
preset: null,
applyPreset(key){
  this.preset = key;
  const presets = {
    eazybackup_cloud_backup: { name:'eazyBackup Cloud Backup', metric:'STORAGE_TB', items:[{ label:'Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'GiB', amount:0, interval:'month', active:true }] },
    e3_object_storage: { name:'e3 Object Storage', metric:'STORAGE_TB', items:[{ label:'Object Storage', billingType:'metered', metric:'STORAGE_TB', unitLabel:'GiB', amount:0, interval:'month', active:true }] },
    workstation_seat: { name:'Workstation Backup Seat', metric:'DEVICE_COUNT', items:[{ label:'Workstation Seat', billingType:'per_unit', metric:'DEVICE_COUNT', unitLabel:'device', amount:0, interval:'month', active:true }] },
    custom_service: { name:'Custom Service', metric:'GENERIC', items:[{ label:'Service', billingType:'per_unit', metric:'GENERIC', unitLabel:'unit', amount:0, interval:'month', active:true }] },
  };
  const p = presets[key]; if(!p) return;
  this.product.name = p.name;
  this.baseMetric = p.metric;
  this.items = JSON.parse(JSON.stringify(p.items));
  this.lastSeededMetric = p.metric;
},
clearPreset(){ this.preset=null; },
```

**Step 3: Verify**

Open slide-over in create mode. Click each preset. Verify fields populate. Click "Clear" to reset.

**Step 4: Commit**

```bash
git add templates/whitelabel/catalog-products-list.tpl assets/js/catalog-products.js
git commit -m "feat(products): add product template presets in slide-over"
```

---

### Task 10: Tiered pricing — backend support

**Files:**
- Modify: `pages/partnerhub/CatalogProductsController.php` (`eb_ph_catalog_product_save`)

**Step 1: Handle tiered pricing in the save endpoint**

In `eb_ph_catalog_product_save`, where price rows are persisted (both insert and update blocks), add handling for the new fields. After the existing `$billingType` / `$kind` logic, add:

```php
$pricingScheme = (string)($it['pricingScheme'] ?? 'per_unit');
$tiersMode = null;
$tiersJson = null;
if ($pricingScheme === 'tiered') {
    $tiersMode = (string)($it['tiersMode'] ?? 'graduated');
    if (!in_array($tiersMode, ['graduated', 'volume'], true)) { $tiersMode = 'graduated'; }
    $tiers = (array)($it['tiers'] ?? []);
    if (!empty($tiers)) { $tiersJson = json_encode($tiers); }
    $kind = 'recurring'; // tiered pricing requires recurring in Stripe
}
```

Include these fields in the INSERT and UPDATE arrays:

```php
'pricing_scheme' => $pricingScheme,
'tiers_mode' => $tiersMode,
'tiers_json' => $tiersJson,
```

When publishing to Stripe with tiered pricing, use `billing_scheme: tiered` and include `tiers[]`:

```php
if ($pricingScheme === 'tiered' && $tiersJson !== null) {
    $tiers = json_decode($tiersJson, true);
    $params['billing_scheme'] = 'tiered';
    $params['tiers_mode'] = $tiersMode;
    unset($params['unit_amount']);
    foreach ($tiers as $ti => $tier) {
        $params["tiers[$ti][up_to]"] = $tier['up_to'] === null ? 'inf' : (int)$tier['up_to'];
        $params["tiers[$ti][unit_amount]"] = (int)($tier['unit_amount'] ?? 0);
        if (isset($tier['flat_amount'])) {
            $params["tiers[$ti][flat_amount]"] = (int)($tier['flat_amount'] ?? 0);
        }
    }
}
```

**Step 2: Verify**

Test by sending a tiered price via the product save endpoint. Check that tiers_json is stored correctly in the database.

**Step 3: Commit**

```bash
git add pages/partnerhub/CatalogProductsController.php
git commit -m "feat(products): backend support for tiered pricing (graduated + volume)"
```

---

### Task 11: Tiered pricing — frontend UI

**Files:**
- Modify: `templates/whitelabel/catalog-products-list.tpl` (slide-over pricing section)
- Modify: `assets/js/catalog-products.js` (productPanelFactory)

**Step 1: Add pricing model selector per price row**

In the slide-over pricing section of `catalog-products-list.tpl`, inside each price row template, add a pricing model selector after the billing type selector:

```html
<label class="block">
  <span class="text-sm text-slate-400">Pricing model</span>
  <select x-model="it.pricingScheme" @change="onPricingSchemeChange(i)" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
    <option value="per_unit">Flat rate</option>
    <option value="tiered_graduated">Graduated tiers</option>
    <option value="tiered_volume">Volume tiers</option>
  </select>
</label>
```

**Step 2: Add tier builder table**

Below the pricing model selector, add a conditional tier builder:

```html
<template x-if="it.pricingScheme && it.pricingScheme.startsWith('tiered')">
  <div class="mt-3 rounded-lg border border-slate-700 bg-slate-950/40 p-3">
    <div class="text-xs text-slate-400 mb-2" x-text="it.pricingScheme==='tiered_graduated' ? 'Each tier is billed independently (graduated)' : 'All units use the tier that matches total quantity (volume)'"></div>
    <table class="w-full text-xs">
      <thead><tr class="text-slate-400"><th class="px-2 py-1 text-left">First unit</th><th class="px-2 py-1 text-left">Last unit</th><th class="px-2 py-1 text-left">Per unit ($)</th><th class="px-2 py-1 text-left">Flat fee ($)</th><th class="px-2 py-1"></th></tr></thead>
      <tbody>
        <template x-for="(tier, ti) in (it.tiers || [])" :key="'tier-'+i+'-'+ti">
          <tr>
            <td class="px-2 py-1" x-text="ti===0 ? 1 : ((it.tiers[ti-1]?.up_to||0)+1)"></td>
            <td class="px-2 py-1"><input x-model.number="tier.up_to" type="number" min="1" :placeholder="ti===it.tiers.length-1?'∞':''" class="w-20 px-2 py-1 rounded bg-slate-800 text-slate-100 outline-none" /></td>
            <td class="px-2 py-1"><input x-model.number="tier.unit_amount_display" type="number" step="0.01" min="0" class="w-20 px-2 py-1 rounded bg-slate-800 text-slate-100 outline-none" /></td>
            <td class="px-2 py-1"><input x-model.number="tier.flat_amount_display" type="number" step="0.01" min="0" class="w-20 px-2 py-1 rounded bg-slate-800 text-slate-100 outline-none" /></td>
            <td class="px-2 py-1"><button type="button" @click="removeTier(i, ti)" class="text-rose-400 hover:text-rose-300">✕</button></td>
          </tr>
        </template>
      </tbody>
    </table>
    <button type="button" @click="addTier(i)" class="mt-2 text-xs text-sky-400 hover:text-sky-300">+ Add tier</button>
  </div>
</template>
```

**Step 3: Add tier methods to productPanelFactory**

```javascript
onPricingSchemeChange(i){
  var it = this.items[i]; if(!it) return;
  if (it.pricingScheme && it.pricingScheme.startsWith('tiered')) {
    if (!it.tiers || it.tiers.length === 0) {
      it.tiers = [
        { up_to: 1024, unit_amount: 0, unit_amount_display: 0, flat_amount: 0, flat_amount_display: 0 },
        { up_to: null, unit_amount: 0, unit_amount_display: 0, flat_amount: 0, flat_amount_display: 0 },
      ];
    }
  }
},
addTier(i){
  var it = this.items[i]; if(!it || !it.tiers) return;
  it.tiers.push({ up_to: null, unit_amount: 0, unit_amount_display: 0, flat_amount: 0, flat_amount_display: 0 });
},
removeTier(i, ti){
  var it = this.items[i]; if(!it || !it.tiers) return;
  if (it.tiers.length <= 2) return;
  it.tiers.splice(ti, 1);
},
```

**Step 4: Verify**

Open product slide-over. Select "Graduated tiers" for a price. Verify tier builder appears. Add/remove tiers. Save as draft and verify `tiers_json` is stored.

**Step 5: Commit**

```bash
git add templates/whitelabel/catalog-products-list.tpl assets/js/catalog-products.js
git commit -m "feat(products): tiered pricing UI (graduated + volume tier builder)"
```

---

### Task 12: Multi-currency per price

**Files:**
- Modify: `templates/whitelabel/catalog-products-list.tpl`
- Modify: `assets/js/catalog-products.js`
- Modify: `pages/partnerhub/CatalogProductsController.php`

**Step 1: Add currency selector per price row in template**

In the slide-over price row grid, add a currency `<select>` alongside the amount input:

```html
<label class="block">
  <span class="text-sm text-slate-400">Currency</span>
  <select x-model="it.currency" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
    <option value="CAD">CAD</option>
    <option value="USD">USD</option>
    <option value="EUR">EUR</option>
    <option value="GBP">GBP</option>
    <option value="AUD">AUD</option>
    <option value="NZD">NZD</option>
  </select>
</label>
```

**Step 2: Default currency from MSP**

In `productPanelFactory`, when creating new items, use `this.currency` (already comes from MSP default). Update the `addEmptyItem()` to include `currency: this.currency`.

**Step 3: Backend — use per-price currency**

In `eb_ph_catalog_product_save`, update the price save logic to read `currency` from each item instead of the global MSP currency:

```php
$itemCurrency = strtoupper((string)($it['currency'] ?? $mspCurrency));
```

Use `$itemCurrency` instead of `$mspCurrency` in the insert/update and Stripe publish calls.

**Step 4: Verify**

Create a product with two prices in different currencies. Verify both save correctly.

**Step 5: Commit**

```bash
git add templates/whitelabel/catalog-products-list.tpl assets/js/catalog-products.js pages/partnerhub/CatalogProductsController.php
git commit -m "feat(products): per-price currency selector with full multi-currency support"
```

---

### Task 13: Subscription safety warning for prices

**Files:**
- Modify: `pages/partnerhub/CatalogProductsController.php` (add endpoint)
- Modify: `eazybackup.php` (add route)
- Modify: `assets/js/catalog-products.js` (query on load)

**Step 1: Add subscription count endpoint**

```php
function eb_ph_catalog_price_sub_count(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $priceId = (int)($_GET['price_id'] ?? 0);
    if ($priceId <= 0) { echo json_encode(['status'=>'error','message'=>'id']); return; }
    try {
        $count = Capsule::table('eb_plan_instance_items as pii')
            ->join('eb_plan_instances as pi', 'pi.id', '=', 'pii.plan_instance_id')
            ->join('eb_plan_components as pc', 'pc.id', '=', 'pii.plan_component_id')
            ->where('pc.price_id', $priceId)
            ->where('pi.msp_id', (int)$msp->id)
            ->whereIn('pi.status', ['active','trialing'])
            ->count();
        echo json_encode(['status'=>'success','count'=>$count]);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'success','count'=>0]);
    }
}
```

**Step 2: Add route**

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-catalog-price-sub-count') {
    require_once __DIR__ . '/pages/partnerhub/CatalogProductsController.php';
    eb_ph_catalog_price_sub_count($vars); exit;
```

**Step 3: In the slide-over, show amber badge when deactivating a price with active subs**

In `productPanelFactory.removeItem(i)`, before removing, check the sub count and show confirmation if > 0.

**Step 4: Commit**

```bash
git add pages/partnerhub/CatalogProductsController.php eazybackup.php assets/js/catalog-products.js
git commit -m "feat(products): subscription safety warning when deactivating prices"
```

---

## Phase 3 — Plans Page Rebuild

### Task 14: Plans controller — expand with full CRUD

**Files:**
- Modify: `pages/partnerhub/CatalogPlansController.php`
- Modify: `eazybackup.php` (add routes)

**Step 1: Add plan template update endpoint**

```php
function eb_ph_plan_template_update(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (!is_array($json) && isset($_POST['payload'])) {
        $json = json_decode((string)$_POST['payload'], true);
    }
    if (!is_array($json)) { echo json_encode(['status'=>'error','message'=>'bad_json']); return; }

    $planId = (int)($json['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id', $planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    $name = trim((string)($json['name'] ?? $plan->name));
    $description = trim((string)($json['description'] ?? ''));
    $trialDays = (int)($json['trial_days'] ?? $plan->trial_days);
    $billingInterval = (string)($json['billing_interval'] ?? $plan->billing_interval ?? 'month');
    $currency = strtoupper((string)($json['currency'] ?? $plan->currency ?? 'CAD'));
    $status = (string)($json['status'] ?? $plan->status ?? 'active');

    if (!in_array($billingInterval, ['month','year'], true)) { $billingInterval = 'month'; }
    if (!in_array($status, ['active','archived','draft'], true)) { $status = 'active'; }

    Capsule::table('eb_plan_templates')->where('id', $planId)->update([
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'trial_days' => max(0, $trialDays),
        'billing_interval' => $billingInterval,
        'currency' => $currency,
        'status' => $status,
        'version' => (int)$plan->version + 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Diff components
    $components = (array)($json['components'] ?? []);
    if (!empty($components)) {
        $existingIds = Capsule::table('eb_plan_components')->where('plan_id', $planId)->pluck('id')->all();
        $incomingIds = [];
        foreach ($components as $c) {
            $cId = (int)($c['id'] ?? 0);
            if ($cId > 0 && in_array($cId, $existingIds)) {
                Capsule::table('eb_plan_components')->where('id', $cId)->update([
                    'price_id' => (int)($c['price_id'] ?? 0),
                    'metric_code' => (string)($c['metric_code'] ?? 'GENERIC'),
                    'default_qty' => (int)($c['default_qty'] ?? 0),
                    'overage_mode' => in_array((string)($c['overage_mode'] ?? ''), ['bill_all','cap_at_default']) ? (string)$c['overage_mode'] : 'bill_all',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $incomingIds[] = $cId;
            } else {
                $newCId = Capsule::table('eb_plan_components')->insertGetId([
                    'plan_id' => $planId,
                    'price_id' => (int)($c['price_id'] ?? 0),
                    'metric_code' => (string)($c['metric_code'] ?? 'GENERIC'),
                    'default_qty' => (int)($c['default_qty'] ?? 0),
                    'overage_mode' => in_array((string)($c['overage_mode'] ?? ''), ['bill_all','cap_at_default']) ? (string)$c['overage_mode'] : 'bill_all',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $incomingIds[] = $newCId;
            }
        }
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            Capsule::table('eb_plan_components')->whereIn('id', $toDelete)->delete();
        }
    }

    echo json_encode(['status'=>'success','version'=>(int)$plan->version + 1]);
}
```

**Step 2: Add plan template duplicate endpoint**

```php
function eb_ph_plan_template_duplicate(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $planId = (int)($_POST['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id', $planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    $newId = Capsule::table('eb_plan_templates')->insertGetId([
        'msp_id' => (int)$msp->id,
        'name' => 'Copy of ' . $plan->name,
        'description' => $plan->description,
        'trial_days' => (int)$plan->trial_days,
        'billing_interval' => (string)($plan->billing_interval ?? 'month'),
        'currency' => (string)($plan->currency ?? 'CAD'),
        'status' => 'draft',
        'version' => 1,
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $components = Capsule::table('eb_plan_components')->where('plan_id', $planId)->get();
    foreach ($components as $c) {
        Capsule::table('eb_plan_components')->insert([
            'plan_id' => $newId,
            'price_id' => (int)$c->price_id,
            'metric_code' => (string)$c->metric_code,
            'default_qty' => (int)$c->default_qty,
            'overage_mode' => (string)$c->overage_mode,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    echo json_encode(['status'=>'success','id'=>$newId]);
}
```

**Step 3: Add plan status toggle endpoint**

```php
function eb_ph_plan_template_toggle(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $planId = (int)($_POST['plan_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if (!in_array($status, ['active','archived','draft'], true)) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    $plan = Capsule::table('eb_plan_templates')->where('id', $planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    Capsule::table('eb_plan_templates')->where('id', $planId)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    echo json_encode(['status'=>'success']);
}
```

**Step 4: Add plan delete endpoint**

```php
function eb_ph_plan_template_delete(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $planId = (int)($_POST['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id', $planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $activeSubs = Capsule::table('eb_plan_instances')
        ->where('plan_id', $planId)
        ->whereIn('status', ['active','trialing'])
        ->count();
    if ($activeSubs > 0) { echo json_encode(['status'=>'error','message'=>'has_active_subs']); return; }
    Capsule::table('eb_plan_components')->where('plan_id', $planId)->delete();
    Capsule::table('eb_plan_templates')->where('id', $planId)->delete();
    echo json_encode(['status'=>'success']);
}
```

**Step 5: Add plan-get endpoint for slide-over loading**

```php
function eb_ph_plan_template_get(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $planId = (int)($_GET['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id', $planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    $components = Capsule::table('eb_plan_components as pc')
        ->join('eb_catalog_prices as pr', 'pr.id', '=', 'pc.price_id')
        ->join('eb_catalog_products as p', 'p.id', '=', 'pr.product_id')
        ->where('pc.plan_id', $planId)
        ->get(['pc.*', 'pr.name as price_name', 'pr.unit_amount as price_unit_amount', 'pr.currency as price_currency', 'pr.kind as price_kind', 'pr.unit_label as price_unit_label', 'pr.interval as price_interval', 'p.name as product_name', 'p.base_metric_code as product_type']);

    $subCount = Capsule::table('eb_plan_instances')
        ->where('plan_id', $planId)
        ->whereIn('status', ['active','trialing'])
        ->count();

    echo json_encode([
        'status' => 'success',
        'plan' => (array)$plan,
        'components' => array_map(function($c){ return (array)$c; }, iterator_to_array($components)),
        'active_subscriptions' => $subCount,
    ]);
}
```

**Step 6: Add component remove endpoint**

```php
function eb_ph_plan_component_remove(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $componentId = (int)($_POST['component_id'] ?? 0);
    $component = Capsule::table('eb_plan_components as pc')
        ->join('eb_plan_templates as pt', 'pt.id', '=', 'pc.plan_id')
        ->where('pc.id', $componentId)
        ->first(['pc.id', 'pt.msp_id']);
    if (!$component || (int)$component->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    Capsule::table('eb_plan_components')->where('id', $componentId)->delete();
    echo json_encode(['status'=>'success']);
}
```

**Step 7: Register all new routes in eazybackup.php**

After the `ph-plan-assign` route, add:

```php
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-template-update') {
    require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
    eb_ph_plan_template_update($vars); exit;
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-template-duplicate') {
    require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
    eb_ph_plan_template_duplicate($vars); exit;
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-template-toggle') {
    require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
    eb_ph_plan_template_toggle($vars); exit;
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-template-delete') {
    require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
    eb_ph_plan_template_delete($vars); exit;
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-template-get') {
    require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
    eb_ph_plan_template_get($vars); exit;
} else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-plan-component-remove') {
    require_once __DIR__ . '/pages/partnerhub/CatalogPlansController.php';
    eb_ph_plan_component_remove($vars); exit;
```

**Step 8: Commit**

```bash
git add pages/partnerhub/CatalogPlansController.php eazybackup.php
git commit -m "feat(plans): full CRUD endpoints — update, duplicate, toggle, delete, get, component remove"
```

---

### Task 15: Plans controller — expand index query for new columns

**Files:**
- Modify: `pages/partnerhub/CatalogPlansController.php` (`eb_ph_catalog_plans_index`)

**Step 1: Update index query**

Update the `eb_ph_catalog_plans_index` function to:
- Join `eb_plan_instances` to get active subscription counts per plan.
- Include new columns (`billing_interval`, `currency`, `status`) in the Smarty output.
- Fetch `eb_tenant_comet_accounts` for the assignment picker (join with `comet_users` for usernames).

Add after `$tenants` query:

```php
$subCounts = [];
try {
    $subs = Capsule::table('eb_plan_instances')
        ->where('msp_id', (int)$msp->id)
        ->whereIn('status', ['active','trialing'])
        ->selectRaw('plan_id, COUNT(*) as cnt')
        ->groupBy('plan_id')
        ->get();
    foreach ($subs as $s) { $subCounts[(int)$s->plan_id] = (int)$s->cnt; }
} catch (\Throwable $__) {}

$cometAccounts = [];
try {
    $ca = Capsule::table('eb_tenant_comet_accounts as tca')
        ->join('eb_tenants as t', 't.id', '=', 'tca.tenant_id')
        ->where('t.msp_id', (int)$msp->id)
        ->get(['tca.tenant_id', 'tca.comet_username', 'tca.comet_user_id', 't.name as tenant_name']);
    foreach ($ca as $r) { $cometAccounts[] = (array)$r; }
} catch (\Throwable $__) {}
```

Add `subCounts` and `cometAccounts` to the return vars.

**Step 2: Commit**

```bash
git add pages/partnerhub/CatalogPlansController.php
git commit -m "feat(plans): enrich index with subscription counts and eazyBackup user picker data"
```

---

### Task 16: Plans template — complete rewrite with slide-over

**Files:**
- Modify: `templates/whitelabel/catalog-plans.tpl` (complete rewrite)
- Modify: `assets/js/catalog-plans.js` (complete rewrite)

This is the largest single task. The template should follow the same patterns as `catalog-products-list.tpl`:
- Token hidden input
- Alpine.js `x-data` for search/filters
- Filter tabs (All / Active / Draft / Archived)
- Counter cards
- Table with columns: Name, Components, Currency, Interval, Active Subs, Status, Created, Actions
- Kebab menu per row: Edit, Duplicate, Archive/Activate, Delete
- Two header buttons: "New Plan" and "eazyBackup Quick Plan"
- Slide-over panel for plan builder
- Slide-over sections: Header (name, description, interval, currency, trial, status), Components (add/remove/reorder), Pricing Preview, Footer (Cancel, Save Draft, Save & Activate)
- Improved "Assign to Customer" secondary slide-over or section
- Overage mode human-readable labels

**Implementation approach:** Build the template in sections. The JS file should use Alpine.js component factory pattern (like `productPanelFactory`), named `planPanelFactory`.

The slide-over should include:
- Plan metadata form (name, description, billing_interval, currency, trial toggle + days, status)
- Components list with inline quantity editing, overage mode dropdown with human-readable labels, remove button
- "Add Component" searchable dropdown (prices grouped by product)
- Live pricing preview (sum of components × quantities × amounts)
- Footer with Cancel / Save Draft / Save & Activate

For the Assign modal:
- Tenant picker dropdown (from `eb_tenants`)
- eazyBackup User picker (from `eb_tenant_comet_accounts`, label as "eazyBackup User")
- Application fee input
- Pricing summary preview
- "Create Subscription" button

This task is large enough that it should be split into sub-steps during implementation. Refer to the design document Phase 3 for full UI specifications.

**Commit after template:**

```bash
git add templates/whitelabel/catalog-plans.tpl
git commit -m "feat(plans): rebuild plans page with table, filters, counter cards"
```

**Commit after JS:**

```bash
git add assets/js/catalog-plans.js
git commit -m "feat(plans): plan builder slide-over with component CRUD and pricing preview"
```

---

### Task 17: Subscription management on Plans page

**Files:**
- Modify: `pages/partnerhub/CatalogPlansController.php` (add subscription list/cancel/update endpoints)
- Modify: `eazybackup.php` (add routes)
- Modify: `templates/whitelabel/catalog-plans.tpl` (subscriptions section)
- Modify: `assets/js/catalog-plans.js` (subscription management)

**Step 1: Add subscription list endpoint**

```php
function eb_ph_plan_subscriptions_list(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;

    $query = Capsule::table('eb_plan_instances as pi')
        ->join('eb_plan_templates as pt', 'pt.id', '=', 'pi.plan_id')
        ->leftJoin('eb_tenants as t', 't.id', '=', Capsule::raw('COALESCE(pi.tenant_id, pi.customer_id)'))
        ->where('pi.msp_id', (int)$msp->id);
    if ($planId) { $query->where('pi.plan_id', $planId); }
    $subs = $query->orderBy('pi.created_at', 'desc')
        ->get(['pi.*', 'pt.name as plan_name', 't.name as tenant_name', 't.email as tenant_email']);

    $out = [];
    foreach ($subs as $s) { $out[] = (array)$s; }
    echo json_encode(['status'=>'success','subscriptions'=>$out]);
}
```

**Step 2: Add subscription cancel endpoint**

```php
function eb_ph_plan_subscription_cancel(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $instanceId = (int)($_POST['instance_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $instance = Capsule::table('eb_plan_instances')->where('id', $instanceId)->first();
    if (!$instance || (int)$instance->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    try {
        $svc = new StripeService();
        $svc->cancelSubscription((string)$instance->stripe_subscription_id, (string)$instance->stripe_account_id);
        Capsule::table('eb_plan_instances')->where('id', $instanceId)->update([
            'status' => 'canceled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => $reason !== '' ? $reason : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['status'=>'success']);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}
```

**Step 3: Register routes and commit**

```bash
git add pages/partnerhub/CatalogPlansController.php eazybackup.php templates/whitelabel/catalog-plans.tpl assets/js/catalog-plans.js
git commit -m "feat(plans): subscription management — list, cancel, quantity update on plans page"
```

---

## Phase 4 — Quick Plan Wizard & Custom Services

### Task 18: Quick Plan wizard — template and JS

**Files:**
- Modify: `templates/whitelabel/catalog-plans.tpl` (add wizard modal)
- Create: `assets/js/catalog-plans-wizard.js`

Build the 4-step wizard modal as described in the design document Phase 4:
- Step 1: Choose plan type (Cloud Backup / e3 Object Storage / Custom Service)
- Step 2: Configure resources (checklist for Cloud Backup, storage-only for e3, redirect to standard builder for Custom)
- Step 3: Set pricing per resource
- Step 4: Review & Create

The wizard should auto-create products using the hybrid approach (check for existing matches, create if needed, show summary).

**JS structure:** Create an Alpine.js component `planWizardFactory` with:
- `step` (1-4)
- `planType` ('cloud_backup' | 'e3_storage' | 'custom')
- `resources` array (each with enabled, metric, label, pricing fields)
- Methods for navigation, validation, pricing calculation, and submission

**Commit:**

```bash
git add templates/whitelabel/catalog-plans.tpl assets/js/catalog-plans-wizard.js
git commit -m "feat(plans): eazyBackup Quick Plan wizard — 4-step guided plan creation"
```

---

### Task 19: Custom Service path — terminology swap

**Files:**
- Modify: `assets/js/catalog-plans-wizard.js`
- Modify: `assets/js/catalog-products.js`

When `baseMetric === 'GENERIC'` or the user selects Custom Service in the wizard:
- Swap all backup-centric terminology to generic service language
- Name placeholder: "e.g., Managed IT Support, Antivirus License, On-Call Support"
- Price label placeholder: "e.g., Monthly Retainer, Per-Seat License"
- Unit label placeholder: "e.g., seat, hour, license, user"

Implement via conditional text in Alpine.js `x-text` or `x-bind:placeholder`.

**Commit:**

```bash
git add assets/js/catalog-plans-wizard.js assets/js/catalog-products.js
git commit -m "feat(products): custom service terminology swap for Generic product type"
```

---

## Phase 5 — Integration & Polish

### Task 20: Metered usage integration

**Files:**
- Modify: `pages/partnerhub/CatalogPlansController.php` (`eb_ph_plan_assign`)

**Step 1: After creating subscription and plan instance items, insert usage map rows**

In `eb_ph_plan_assign`, after the `eb_plan_instance_items` insert loop, add:

```php
// Create usage map for metered components
try {
    if ($schema->hasTable('eb_plan_instance_usage_map')) {
        $instanceItems = Capsule::table('eb_plan_instance_items')
            ->where('plan_instance_id', $instanceId)
            ->get();
        foreach ($instanceItems as $ii) {
            $comp = Capsule::table('eb_plan_components')->where('id', (int)$ii->plan_component_id)->first();
            $price = $comp ? Capsule::table('eb_catalog_prices')->where('id', (int)$comp->price_id)->first() : null;
            if ($price && (string)$price->kind === 'metered') {
                Capsule::table('eb_plan_instance_usage_map')->insert([
                    'plan_instance_item_id' => (int)$ii->id,
                    'metric_code' => (string)$ii->metric_code,
                    'stripe_subscription_item_id' => (string)$ii->stripe_subscription_item_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
} catch (\Throwable $__) {}
```

**Commit:**

```bash
git add pages/partnerhub/CatalogPlansController.php
git commit -m "feat(plans): populate usage map on plan assignment for metered components"
```

---

### Task 21: Real-time price validation

**Files:**
- Modify: `assets/js/catalog-products.js`
- Modify: `assets/js/catalog-plans.js`

Add client-side validation:
- `unit_amount >= 1` cent (0.01 in display currency)
- `unit_amount <= 99999999` cents
- Tier rules: contiguous, at least 2, last unbounded
- Inline error messages below inputs
- Disable Save/Publish when errors exist

**Commit:**

```bash
git add assets/js/catalog-products.js assets/js/catalog-plans.js
git commit -m "feat(validation): real-time price and tier validation with inline errors"
```

---

### Task 22: Plan import/export

**Files:**
- Modify: `pages/partnerhub/CatalogPlansController.php` (export/import endpoints)
- Modify: `eazybackup.php` (routes)
- Modify: `templates/whitelabel/catalog-plans.tpl` (buttons)
- Modify: `assets/js/catalog-plans.js` (import handler)

**Export:** JSON file containing plan template fields + components array.
**Import:** File picker, validate JSON structure, match/create products.

**Commit:**

```bash
git add pages/partnerhub/CatalogPlansController.php eazybackup.php templates/whitelabel/catalog-plans.tpl assets/js/catalog-plans.js
git commit -m "feat(plans): JSON import/export for plan templates"
```

---

### Task 23: Pricing table preview

**Files:**
- Modify: `templates/whitelabel/catalog-plans.tpl` (preview modal)
- Modify: `assets/js/catalog-plans.js` (preview rendering)

Add a "Preview" button in the plan builder that opens a modal with a customer-facing pricing card mockup. Read-only visual showing plan name, price, feature bullets, usage-based items, trial badge.

**Commit:**

```bash
git add templates/whitelabel/catalog-plans.tpl assets/js/catalog-plans.js
git commit -m "feat(plans): pricing table preview modal for customer-facing view"
```

---

### Task 24: Documentation updates

**Files:**
- Modify: `Docs/PARTNER_HUB.md`

Comprehensive update:
- Complete schema documentation for all tables
- New routes documentation
- Catalog section rewrite (consolidated products page, filter tabs, tiered pricing, multi-currency, presets)
- Plans section rewrite (plan builder, wizard, subscription management)
- Metered usage integration documentation
- Wizard flow documentation

**Commit:**

```bash
git add Docs/PARTNER_HUB.md
git commit -m "docs: comprehensive PARTNER_HUB.md update for catalog redesign"
```

---

### Task 25: Old system cleanup

**Files:**
- Modify: `eazybackup.php` (verify redirect, audit routes)
- Modify: `pages/partnerhub/PlansController.php` (deprecation comment — done in Task 3)

Verify:
1. `ph-plans` redirect works
2. `ph-catalog-product` redirect works
3. No stale route references in `eazybackup.php`
4. Old `plans.tpl` has deprecation comment
5. Sidebar only shows `ph-catalog-plans` (not `ph-plans`)

**Commit:**

```bash
git add eazybackup.php
git commit -m "chore: verify old system cleanup and route redirects"
```

---

## Execution Order Summary

| # | Task | Phase | Dependencies |
|---|------|-------|-------------|
| 1 | Schema migrations | 1 | None |
| 2 | Consolidate Products redirect | 1 | None |
| 3 | Retire old Plans redirect | 1 | None |
| 4 | PARTNER_HUB.md schema docs | 1 | Task 1 |
| 5 | Product filter tabs | 2 | Task 2 |
| 6 | Draft product delete | 2 | Task 2 |
| 7 | Publish flow buttons | 2 | Task 2 |
| 8 | Product type help text | 2 | Task 2 |
| 9 | Product presets | 2 | Task 2 |
| 10 | Tiered pricing backend | 2 | Task 1 |
| 11 | Tiered pricing frontend | 2 | Task 10 |
| 12 | Multi-currency | 2 | Task 2 |
| 13 | Subscription safety warning | 2 | Task 1 |
| 14 | Plans controller CRUD | 3 | Task 1, 3 |
| 15 | Plans index enrichment | 3 | Task 14 |
| 16 | Plans template rewrite | 3 | Task 14, 15 |
| 17 | Subscription management | 3 | Task 14, 16 |
| 18 | Quick Plan wizard | 4 | Task 14, 16 |
| 19 | Custom service path | 4 | Task 18 |
| 20 | Metered usage integration | 5 | Task 14 |
| 21 | Price validation | 5 | Task 11, 16 |
| 22 | Plan import/export | 5 | Task 14 |
| 23 | Pricing table preview | 5 | Task 16 |
| 24 | Documentation | 5 | All |
| 25 | Old system cleanup | 5 | Task 2, 3 |
