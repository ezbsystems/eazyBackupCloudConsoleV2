# Dual Storage Types + Improved Plan Assignment

**Date:** 2026-03-24
**Status:** Approved
**Approach:** A — Extend existing assignment mode with new metric code

## Problem

The Partner Hub billing system currently treats all storage as a single type (`STORAGE_TB`), which tracks Comet vault usage. eazyBackup needs to support two distinct storage products:

1. **eazyBackup Cloud Storage** — usage from Comet cloud-type vaults (types 1000, 1003) per user, sourced from `eb_storage_daily`
2. **e3 Object Storage** — usage from RGW/S3 buckets per `s3_users` identity, sourced from `s3_historical_stats`

Additionally, storage-only plans (`STORAGE_TB`) currently skip user selection entirely, using a synthetic `storage:{tenant_id}` key. This needs to change so MSPs select a specific Comet user for eazyBackup Cloud Storage plans.

The plan assignment modals across three pages (user-assignments, catalog-plans, tenant-detail billing) need to support both Comet user and S3 user pickers, switching based on the plan's metric composition.

## Scope

- New metric code `E3_STORAGE_GIB` for e3 Object Storage products
- Three-way assignment mode logic (replaces binary `requires_comet_user`)
- S3 user discovery helper for Partner Hub
- UI picker switching across three assign modals
- Backend assignment handler updates
- Billing pipeline: new `E3_STORAGE_GIB` branch in usage job
- Hide `s3_backup_users` from Partner Hub (product not ready)

## Design

### 1. Data Model Changes

#### New metric code

Add `E3_STORAGE_GIB` to the ENUM columns:
- `eb_catalog_products.base_metric_code`
- `eb_catalog_prices.metric_code`

```sql
ALTER TABLE eb_catalog_products
  MODIFY COLUMN base_metric_code ENUM('STORAGE_TB','DEVICE','VM','M365','GENERIC','E3_STORAGE_GIB')
  DEFAULT 'GENERIC';

ALTER TABLE eb_catalog_prices
  MODIFY COLUMN metric_code ENUM('STORAGE_TB','DEVICE','VM','M365','GENERIC','E3_STORAGE_GIB')
  DEFAULT 'GENERIC';
```

The naming `E3_STORAGE_GIB` reflects the billing unit (GiB) and distinguishes from `STORAGE_TB` (which bills in GB for Comet vault storage despite its name).

#### Assignment mode (three-way)

`eb_ph_plan_assignment_mode()` return value changes from:

```php
['mode' => 'tenant_storage' | 'backup_user', 'requires_comet_user' => bool, ...]
```

To:

```php
[
    'mode' => 'comet_user' | 'e3_storage',
    'requires_comet_user' => bool,
    'requires_s3_user' => bool,
    'metrics' => string[],
    'primary_metric' => string,
]
```

Decision table:

| Metrics present | `mode` | `requires_comet_user` | `requires_s3_user` |
|---|---|---|---|
| Any non-storage metric, or `STORAGE_TB` present | `comet_user` | `true` | `false` |
| Only `E3_STORAGE_GIB` | `e3_storage` | `false` | `true` |
| No metrics resolved (empty plan) | `comet_user` | `true` | `false` |

Key change: `STORAGE_TB`-only plans now require Comet user selection (no more synthetic `storage:{tenant_id}` key).

#### Mixed-metric constraint

Plans must not contain both `STORAGE_TB` and `E3_STORAGE_GIB` components. These storage types use different usage sources and different user entity types, so combining them in a single plan is not meaningful. Enforce this at plan creation/publish time in the plan builder validation logic (`CatalogPlansController`). If a plan contains both metric types, reject with a clear error message: "A plan cannot mix eazyBackup Cloud Storage and e3 Object Storage components."

#### Backwards compatibility

Existing `eb_plan_instances` rows with `comet_user_id = 'storage:{...}'` continue to work. The current `partnerhub_usage_job.php` queries `eb_storage_daily WHERE username = comet_user_id` — for `storage:` keys this returns zero rows, producing $0 billed. Tenant-level storage billing for these legacy instances is handled by `stripe_tenant_usage_rollup.php`, which resolves usernames through `eb_tenant_storage_links` independently of the `comet_user_id` value.

No migration of existing `storage:` keys is required. As MSPs assign new plans, they will select specific Comet users, and the `storage:` pattern will naturally age out. The `stripe_tenant_usage_rollup.php` path remains unchanged and continues to handle any active legacy tenant-level subscriptions.

### 2. S3 User Discovery

New helper function in `TenantsController.php`:

```php
function eb_ph_discover_msp_s3_users(int $clientId): array
```

Discovery path:
1. Find active WHMCS services (`tblhosting`) where `userid = $clientId` and `packageid` belongs to the Cloud Storage product group (group ID 11)
2. Collect `username` values from those services
3. Match to `s3_users` rows on `username` or `ceph_uid`
4. Include sub-tenant rows (`parent_id = primary.id`, `is_active = 1`)
5. Return array of:

```php
[
    'id' => int,           // s3_users.id (PK)
    'username' => string,  // RGW username
    'name' => string,      // display name
    'tenant_id' => ?string, // RGW tenant ID if tenanted
    'display_label' => string, // "Name (tenant$uid)" for picker
]
```

All MSP S3 users are shown regardless of which Partner Hub tenant is selected (the MSP decides which user to bill under which tenant).

### 3. UI Changes — Assign Modals

Three pages have assign modals. All gain plan-mode-aware picker switching.

#### Picker switching logic (Alpine.js)

When the selected plan changes, check `plan.assignment_mode.mode`:
- `mode === 'e3_storage'` → hide Comet user picker, show S3 user picker
- `mode === 'comet_user'` → show Comet user picker, hide S3 user picker

Both pickers use the same UX pattern: searchable dropdown with button trigger.

#### Per-page changes

**user-assignments.tpl:**
- The inline modal (recently added) currently pre-fills Comet user as read-only
- Add S3 user picker that appears when selected plan is `e3_storage` mode
- When S3 picker is active, hide Comet user field
- The "Assign Plan" button in the unassigned Comet users table remains as-is (opens modal with Comet user pre-filled)
- Add an "Assign e3 Storage Plan" button above the unassigned table (or in the page header) that opens the same modal with no Comet user pre-filled and mode forced to allow S3 user selection once an e3 plan is chosen

**catalog-plans.tpl:**
- Existing modal has Comet picker that shows/hides based on `assignPlanRequiresCometUser()`
- Change to three-state: `assignPlanRequiresCometUser()` for Comet picker, new `assignPlanRequiresS3User()` for S3 picker
- Add `s3Users` data property and `filteredS3Users()` method
- S3 user picker replaces Comet picker when mode is `e3_storage`

**tenant-detail.tpl (billing tab):**
- Same pattern as catalog-plans
- Add `s3Users` data property populated from controller
- Switch between Comet/S3 pickers based on plan mode

#### Display rendering of synthetic keys

Tables that display `comet_user_id` (assigned users list, plan instances, billing tab) must render synthetic keys as human-readable labels:

- `e3:{id}` → resolve `s3_users` row by ID and display: "S3: {username}" (or "{name}" if set). Controllers should join/resolve this when building display rows.
- `storage:{tenant_id}` → display: "Tenant-level (legacy)". This tells the MSP this is an old assignment pattern.
- Plain string → display as-is (Comet username, current behavior)

Each controller that builds rows for display (assigned users in `UserAssignmentsController`, plan instances in `TenantBillingController`) should detect the prefix and resolve the label server-side before passing to the template.

#### Controller data requirements

All three controllers (`UserAssignmentsController`, `TenantBillingController`, `CatalogPlansController`) must:

1. Call `eb_ph_discover_msp_s3_users($clientId)` and pass the result as a JSON-encoded template variable (e.g. `s3_users_json`)
2. Build plan data using `eb_ph_plan_assignment_mode()` (the shared function) rather than inline metric computation. Currently `UserAssignmentsController` and `TenantBillingController` compute `requires_comet_user` with their own inline metric logic — both must switch to calling the shared function.
3. Pass plans with the full nested `assignment_mode` object (not a flat `requires_comet_user` boolean). All three templates use the same shape: `plan.assignment_mode.mode`, `plan.assignment_mode.requires_comet_user`, `plan.assignment_mode.requires_s3_user`.

### 4. Backend Assignment Handler

`eb_ph_plan_assign()` in `CatalogPlansController.php`.

#### Input changes

- Existing fields: `plan_id`, `tenant_id`, `comet_user_id`, `token`
- New optional field: `s3_user_id` (integer, `s3_users.id` PK)

#### Validation flow

```
1. Resolve MSP, tenant, plan (unchanged)
2. Get assignment_mode for plan
3. Switch on mode:
   ├── comet_user:
   │   ├── Require comet_user_id non-empty
   │   ├── Validate ownership (existing logic)
   │   └── Store comet_user_id on eb_plan_instances.comet_user_id
   │
   └── e3_storage:
       ├── Require s3_user_id non-empty (integer > 0)
       ├── Validate: s3_user exists, is_active = 1, discoverable
       │   via eb_ph_discover_msp_s3_users($clientId)
       ├── Duplicate check: no active eb_plan_instances with same
       │   plan_id + tenant_id + comet_user_id where comet_user_id = 'e3:{s3_user_id}'
       └── Store: comet_user_id = 'e3:{s3_user_id}' on eb_plan_instances

4. Create Stripe subscription + local rows (unchanged)
```

The `comet_user_id` column on `eb_plan_instances` (VARCHAR) stores `e3:{s3_users.id}` as a synthetic key for e3 plans, avoiding schema changes while maintaining uniqueness.

### 5. Billing Pipeline

#### `partnerhub_usage_job.php`

New branch for `E3_STORAGE_GIB` metered items:

```
For each active eb_plan_instances row:
  For each metered subscription item:
    ├── STORAGE_TB:
    │   ├── comet_user_id starts with 'storage:' → legacy tenant-level
    │   │   aggregation (backwards compat)
    │   └── Otherwise → read eb_storage_daily for that username
    │       (per-user Comet vault billing)
    │
    └── E3_STORAGE_GIB:
        ├── Parse s3_user_id from comet_user_id ('e3:{id}' → integer)
        ├── Query s3_historical_stats:
        │     WHERE user_id = {id}
        │     AND date >= periodStart AND date < periodEnd
        ├── Take MAX(total_storage) across the period
        │   (daily snapshots → peak usage billing)
        ├── Convert: gib = floor(maxBytes / (1024^3))
        └── Apply computeBillableMeteredUsage(gib, default_qty, overage_mode)
            then push to Stripe
```

**Why MAX:** `s3_historical_stats.total_storage` is a point-in-time daily snapshot, not a cumulative delta. Peak usage billing (maximum across the period) is the standard for object storage.

#### `stripe_tenant_usage_rollup.php`

No e3 branch needed. E3 plans have per-instance (per-S3-user) billing handled entirely by `partnerhub_usage_job.php`.

#### Usage ledger

`eb_usage_ledger` entries for e3 use metric `E3_STORAGE_GIB` and store GiB quantities.

### 6. Cleanup — Hide s3_backup_users

The `s3_backup_users` product is not ready for the Partner Hub.

**Hide:**
- The "Storage Users" tab on the tenant-detail page (rendered by `eb_ph_tenant_storage_users()`)
- Any corresponding sidebar link to the cloudstorage module's e3 backup users page

**Method:** Conditionally hide via visibility check (no data deletion, no schema changes). Re-enable when the product is ready.

**Keep visible:**
- The new S3 user picker (from `s3_users`) in assign modals
- Cloud storage product management in the cloudstorage module itself

## Files Affected

| File | Change |
|---|---|
| `eazybackup.php` | ALTER TABLE for metric ENUMs (migration block) |
| `pages/partnerhub/TenantsController.php` | New `eb_ph_discover_msp_s3_users()` helper |
| `pages/partnerhub/CatalogPlansController.php` | Update `eb_ph_plan_assignment_mode()`, update `eb_ph_plan_assign()` validation, add mixed-metric constraint to plan builder validation |
| `pages/partnerhub/UserAssignmentsController.php` | Replace inline metric logic with `eb_ph_plan_assignment_mode()`, pass S3 users + nested `assignment_mode` to template, resolve `e3:`/`storage:` display labels for assigned rows |
| `pages/partnerhub/TenantBillingController.php` | Replace inline metric logic with `eb_ph_plan_assignment_mode()`, pass S3 users + nested `assignment_mode` to template, resolve synthetic key display labels for plan instances |
| `templates/whitelabel/user-assignments.tpl` | Add S3 user picker to inline modal, add "Assign e3 Storage Plan" button |
| `templates/whitelabel/catalog-plans.tpl` | Add S3 user picker, three-state mode switching |
| `assets/js/catalog-plans.js` | S3 picker methods, mode-aware logic |
| `templates/whitelabel/tenant-detail.tpl` | Add S3 user picker to billing tab modal, hide Storage Users tab |
| `bin/partnerhub_usage_job.php` | New E3_STORAGE_GIB branch, STORAGE_TB per-user change |
| `templates/whitelabel/partials/partner_hub_shell.tpl` | Hide s3_backup_users sidebar link (if present) |
