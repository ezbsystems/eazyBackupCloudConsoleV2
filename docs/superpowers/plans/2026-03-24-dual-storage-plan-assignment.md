# Dual Storage Types + Improved Plan Assignment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support two distinct storage product types (eazyBackup Cloud Storage via Comet vaults, e3 Object Storage via RGW/S3) with per-user plan assignment across all Partner Hub pages.

**Architecture:** Add `E3_STORAGE_GIB` metric code to catalog ENUMs. Refactor the binary assignment mode into a three-way mode (`comet_user` / `e3_storage`). Each assign modal gains a plan-mode-aware switcher between Comet user and S3 user pickers. The billing pipeline gets a new branch for e3 usage from `s3_historical_stats`.

**Tech Stack:** PHP 8.x, WHMCS (Laravel Capsule ORM), Smarty templates, Alpine.js, Stripe API, MySQL

**Spec:** `docs/superpowers/specs/2026-03-24-dual-storage-plan-assignment-design.md`

---

### Task 1: Schema Migration — Add E3_STORAGE_GIB to ENUMs

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php` (after line ~1801, in the migration block)

- [ ] **Step 1: Add ENUM migration to eazybackup.php**

After the `eb_catalog_products` CREATE/ALTER block (around line 1801) and the `eb_catalog_prices` block (around line 1848), add raw SQL to extend both ENUMs. Place this after both table blocks but within the same migration section:

```php
try {
    Capsule::connection()->statement(
        "ALTER TABLE `eb_catalog_products` MODIFY COLUMN `base_metric_code` "
        . "ENUM('STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB') NULL DEFAULT NULL"
    );
} catch (\Throwable $__) {}

try {
    Capsule::connection()->statement(
        "ALTER TABLE `eb_catalog_prices` MODIFY COLUMN `metric_code` "
        . "ENUM('STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB') NOT NULL DEFAULT 'GENERIC'"
    );
} catch (\Throwable $__) {}
```

Also update the CREATE TABLE blocks themselves (lines 1784 and 1815) so new installs get the full ENUM:
- Line 1784: add `'E3_STORAGE_GIB'` to the `base_metric_code` enum array
- Line 1798: add `'E3_STORAGE_GIB'` to the `eb_add_column_if_missing` enum array
- Line 1815: add `'E3_STORAGE_GIB'` to the `metric_code` enum array

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/eazybackup.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php
git commit -m "feat: add E3_STORAGE_GIB to catalog product/price metric ENUMs"
```

---

### Task 2: Refactor Assignment Mode to Three-Way

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php:415-452`
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php` (move function here)

**Important:** `eb_ph_plan_assignment_mode()` is currently defined in `CatalogPlansController.php`, but it's now needed by `UserAssignmentsController.php` and `TenantBillingController.php` too. Neither of those files includes `CatalogPlansController.php` (WHMCS loads only one controller per route). The cleanest fix: **move** the function to `TenantsController.php` (the shared helpers file that all Partner Hub controllers already `require_once`). Remove the original from `CatalogPlansController.php`.

- [ ] **Step 1: Move and update eb_ph_plan_assignment_mode in TenantsController.php**

Cut the function from `CatalogPlansController.php` (lines 415–452) and paste it into `TenantsController.php` (after the existing `eb_ph_discover_msp_comet_usernames()` function). Update the function body to the new three-way logic:

Replace the function body with:

```php
function eb_ph_plan_assignment_mode(int $planId, ?array $planComponents = null): array
{
    $metrics = [];
    if ($planComponents === null) {
        $rows = Capsule::table('eb_plan_components as pc')
            ->leftJoin('eb_catalog_prices as pr', 'pr.id', '=', 'pc.price_id')
            ->leftJoin('eb_catalog_products as p', 'p.id', '=', 'pr.product_id')
            ->where('pc.plan_id', $planId)
            ->get([
                'pc.metric_code',
                'pr.metric_code as price_metric',
                'p.base_metric_code as product_base_metric',
            ]);
        $planComponents = [];
        foreach ($rows as $row) {
            $planComponents[] = (array)$row;
        }
    }

    foreach ($planComponents as $component) {
        $metric = strtoupper(trim((string)($component['price_metric'] ?? $component['metric_code'] ?? $component['product_base_metric'] ?? '')));
        if ($metric !== '') {
            $metrics[] = $metric;
        }
    }

    $metrics = array_values(array_unique($metrics));
    $hasE3 = in_array('E3_STORAGE_GIB', $metrics, true);
    $isE3Only = $hasE3 && count($metrics) === 1;

    if ($isE3Only) {
        return [
            'mode' => 'e3_storage',
            'requires_comet_user' => false,
            'requires_s3_user' => true,
            'metrics' => $metrics,
            'primary_metric' => 'E3_STORAGE_GIB',
        ];
    }

    return [
        'mode' => 'comet_user',
        'requires_comet_user' => true,
        'requires_s3_user' => false,
        'metrics' => $metrics,
        'primary_metric' => $metrics[0] ?? 'GENERIC',
    ];
}
```

- [ ] **Step 2: Remove the old function from CatalogPlansController.php**

Delete the `eb_ph_plan_assignment_mode` function definition (lines 415–452) from `CatalogPlansController.php`. The function is now in `TenantsController.php`, which is already `require_once`d at the top of `CatalogPlansController.php`.

- [ ] **Step 3: Verify PHP syntax for both files**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php`
Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php`
Expected: `No syntax errors detected` for both

- [ ] **Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php
git add accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php
git commit -m "feat: move assignment mode to shared helpers, refactor to three-way"
```

---

### Task 3: Add S3 User Discovery Helper

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php` (add new function after `eb_ph_discover_msp_comet_usernames`)

- [ ] **Step 1: Add eb_ph_discover_msp_s3_users function**

Add this function after the existing `eb_ph_discover_msp_comet_usernames()` function:

```php
function eb_ph_discover_msp_s3_users(int $clientId): array
{
    if ($clientId <= 0) {
        return [];
    }

    $storageProductGroupId = 11;
    try {
        $productIds = Capsule::table('tblproducts')
            ->where('gid', $storageProductGroupId)
            ->pluck('id')
            ->toArray();
    } catch (\Throwable $__) {
        return [];
    }
    if ($productIds === []) {
        return [];
    }

    try {
        $serviceUsernames = Capsule::table('tblhosting')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->where('username', '!=', '')
            ->pluck('username')
            ->unique()
            ->values()
            ->toArray();
    } catch (\Throwable $__) {
        return [];
    }
    if ($serviceUsernames === []) {
        return [];
    }

    if (!Capsule::schema()->hasTable('s3_users')) {
        return [];
    }

    try {
        $primaryUsers = Capsule::table('s3_users')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($serviceUsernames) {
                $q->whereIn('username', $serviceUsernames)
                  ->orWhereIn('ceph_uid', $serviceUsernames);
            })
            ->get(['id', 'username', 'name', 'tenant_id', 'ceph_uid'])
            ->map(fn($r) => (array)$r)
            ->toArray();
    } catch (\Throwable $__) {
        return [];
    }

    $primaryIds = array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $primaryUsers);

    $subTenantUsers = [];
    if ($primaryIds !== []) {
        try {
            $subTenantUsers = Capsule::table('s3_users')
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->whereIn('parent_id', $primaryIds)
                ->get(['id', 'username', 'name', 'tenant_id', 'ceph_uid', 'parent_id'])
                ->map(fn($r) => (array)$r)
                ->toArray();
        } catch (\Throwable $__) {}
    }

    $allUsers = array_merge($primaryUsers, $subTenantUsers);
    $result = [];
    foreach ($allUsers as $user) {
        $tenantId = $user['tenant_id'] ?? null;
        $baseUid = trim((string)($user['ceph_uid'] ?? $user['username'] ?? ''));
        $displayUid = ($tenantId && $baseUid) ? $tenantId . '$' . $baseUid : ($user['username'] ?? '');
        $name = trim((string)($user['name'] ?? ''));
        $result[] = [
            'id' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'name' => $name,
            'tenant_id' => $tenantId ? (string)$tenantId : null,
            'display_label' => $name !== '' ? $name . ' (' . $displayUid . ')' : $displayUid,
        ];
    }

    usort($result, static fn(array $a, array $b): int =>
        strcasecmp($a['display_label'], $b['display_label'])
    );

    return $result;
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantsController.php
git commit -m "feat: add S3 user discovery helper for Partner Hub"
```

---

### Task 4: Add Mixed-Metric Validation to Plan Builder

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php:47-132` (inside `eb_ph_plan_validate_component_rows`)

- [ ] **Step 1: Add mixed-metric check**

In `eb_ph_plan_validate_component_rows`, after the existing validation loop (after the closing `}` around line 128, before the final `return ['ok' => true, ...]`), add:

```php
    $resolvedMetrics = [];
    foreach ($byId as $row) {
        $m = strtoupper(trim((string)($row['metric_code'] ?? $row['product_base_metric'] ?? '')));
        if ($m !== '') {
            $resolvedMetrics[$m] = true;
        }
    }
    if (isset($resolvedMetrics['E3_STORAGE_GIB']) && count($resolvedMetrics) > 1) {
        return eb_ph_plan_validation_error(
            'e3 Object Storage components cannot be combined with other metric types in the same plan.',
            'mixed_e3_metrics'
        );
    }
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php
git commit -m "feat: forbid mixing E3_STORAGE_GIB with other metrics in plans"
```

---

### Task 5: Update CatalogPlansController — Assignment Handler

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php` (the `eb_ph_plan_assign` function and the `eb_ph_catalog_plans_index` function)

- [ ] **Step 1: Update eb_ph_plan_assign to handle e3_storage mode**

In `eb_ph_plan_assign()`, find the section after `$assignmentMode = eb_ph_plan_assignment_mode(...)` where `requires_comet_user` is checked. Replace the block that handles the assignment mode branching with logic that also handles `e3_storage` mode:

After `$assignmentMode = eb_ph_plan_assignment_mode((int)$plan->id);`:

```php
    if ($assignmentMode['mode'] === 'e3_storage') {
        $s3UserId = (int)($_POST['s3_user_id'] ?? 0);
        if ($s3UserId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Select an e3 storage user.']);
            return;
        }
        $mspS3Users = eb_ph_discover_msp_s3_users($clientId);
        $validS3User = null;
        foreach ($mspS3Users as $s3u) {
            if ((int)($s3u['id'] ?? 0) === $s3UserId) {
                $validS3User = $s3u;
                break;
            }
        }
        if (!$validS3User) {
            echo json_encode(['status' => 'error', 'message' => 's3_user_not_found']);
            return;
        }
        $cometUserId = 'e3:' . $s3UserId;
    } elseif ($assignmentMode['requires_comet_user']) {
        if ($cometUserId === '') {
            echo json_encode(['status' => 'error', 'message' => 'invalid']);
            return;
        }
    } else {
        $cometUserId = eb_ph_plan_storage_assignment_key($tenant);
    }
```

Remove the old `if (!$assignmentMode['requires_comet_user']) { $cometUserId = eb_ph_plan_storage_assignment_key($tenant); }` block — it's now handled inside the `elseif/else` chain above. Keep the existing Comet user ownership validation (the `$ownedCometAccount` checks) but wrap them with `if ($assignmentMode['requires_comet_user'])` so they only run for Comet mode.

- [ ] **Step 2: Update eb_ph_catalog_plans_index to pass S3 users and full assignment_mode**

In `eb_ph_catalog_plans_index()`, where `$assignPlans` is built (around lines 352–362), the `assignment_mode` is already included. Add the S3 users data to the template vars:

```php
$s3Users = eb_ph_discover_msp_s3_users($clientId);
```

Add to the return `vars` array:
```php
's3_users' => $s3Users,
's3_users_json' => json_encode($s3Users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
```

- [ ] **Step 3: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php
git commit -m "feat: handle e3_storage mode in plan assignment handler"
```

---

### Task 6: Update UserAssignmentsController — Plans Shape + S3 Users + Display Labels

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/UserAssignmentsController.php`

- [ ] **Step 1: Replace inline metric logic with eb_ph_plan_assignment_mode**

In the `eb_ph_user_assignments()` function, find the section where `$assignPlans` is built (the inline metric computation added previously). Replace the inline `requires_comet_user` computation with calls to `eb_ph_plan_assignment_mode()`:

Replace:
```php
            foreach ($planRows as $planRow) {
                $pid = (int)($planRow['id'] ?? 0);
                $metrics = array_keys($metricsByPlan[$pid] ?? []);
                $nonStorage = array_filter($metrics, static fn(string $m): bool => $m !== 'STORAGE_TB');
                $planRow['requires_comet_user'] = count($metrics) === 0 ? true : count($nonStorage) > 0;
                $assignPlans[] = $planRow;
            }
```

With:
```php
            foreach ($planRows as $planRow) {
                $planRow['assignment_mode'] = eb_ph_plan_assignment_mode((int)($planRow['id'] ?? 0));
                $assignPlans[] = $planRow;
            }
```

Remove the `$metricsByPlan` computation block (no longer needed — `eb_ph_plan_assignment_mode` queries components internally).

- [ ] **Step 2: Add S3 users data to template vars**

Add before the return statement:

```php
    $s3Users = eb_ph_discover_msp_s3_users($clientId);
```

Add to the return `vars` array:
```php
            's3_users' => $s3Users,
            's3_users_json' => json_encode($s3Users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
```

- [ ] **Step 3: Add display label resolution for assigned rows**

In the loop that builds `$assignedRows` display data, resolve synthetic `comet_user_id` values. After the `tenant_url` and `plans_url` assignments in the `$assignedRows` loop:

```php
    foreach ($assignedRows as &$row) {
        $cuid = (string)($row['comet_user_id'] ?? '');
        if (str_starts_with($cuid, 'e3:')) {
            $e3Id = (int)substr($cuid, 3);
            $row['comet_user_display'] = $cuid;
            foreach ($s3Users as $s3u) {
                if ((int)($s3u['id'] ?? 0) === $e3Id) {
                    $row['comet_user_display'] = 'S3: ' . ($s3u['display_label'] ?? $cuid);
                    break;
                }
            }
        } elseif (str_starts_with($cuid, 'storage:')) {
            $row['comet_user_display'] = 'Tenant-level (legacy)';
        } else {
            $row['comet_user_display'] = $cuid;
        }
        $row['tenant_url'] = $baseLink . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = $baseLink . '&a=ph-catalog-plans';
    }
    unset($row);
```

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/UserAssignmentsController.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/UserAssignmentsController.php
git commit -m "feat: use shared assignment_mode, add S3 users and display labels"
```

---

### Task 7: Update TenantBillingController — Plans Shape + S3 Users

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php:57-101`

- [ ] **Step 1: Replace inline metric logic with eb_ph_plan_assignment_mode**

Replace the entire block at lines 57–101 (the `$planRows` fetch, `$planComponentRows`, `$metricsByPlan`, and the foreach that computes `requires_comet_user`) with:

```php
        if (Capsule::schema()->hasTable('eb_plan_templates')) {
            $planRows = Capsule::table('eb_plan_templates')
                ->where('msp_id', $mspId)
                ->where('status', 'active')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'description', 'billing_interval', 'currency'])
                ->map(fn($r) => (array)$r)
                ->toArray();

            foreach ($planRows as $planRow) {
                $planRow['assignment_mode'] = eb_ph_plan_assignment_mode((int)($planRow['id'] ?? 0));
                $assignablePlans[] = $planRow;
            }
        }
```

- [ ] **Step 2: Add S3 users data**

After the `$tenantCometUsers` block, add:

```php
        $s3Users = eb_ph_discover_msp_s3_users((int)($msp->whmcs_client_id ?? 0));
```

Add to the template vars returned via `eb_ph_tenant_shell_response`:

```php
        'billing_s3_users' => $s3Users,
```

- [ ] **Step 3: Add display label resolution for plan instances**

In `eb_ph_tenant_billing()`, find the section where `$planInstances` is built (the query joining `eb_plan_instances` with `eb_plan_templates`). After the query results are collected into an array, add a loop to resolve display labels:

```php
        foreach ($planInstances as &$pi) {
            $cuid = (string)($pi['comet_user_id'] ?? '');
            if (str_starts_with($cuid, 'e3:')) {
                $e3Id = (int)substr($cuid, 3);
                $pi['comet_user_display'] = $cuid;
                foreach ($s3Users as $s3u) {
                    if ((int)($s3u['id'] ?? 0) === $e3Id) {
                        $pi['comet_user_display'] = 'S3: ' . ($s3u['display_label'] ?? $cuid);
                        break;
                    }
                }
            } elseif (str_starts_with($cuid, 'storage:')) {
                $pi['comet_user_display'] = 'Tenant-level (legacy)';
            } else {
                $pi['comet_user_display'] = $cuid;
            }
        }
        unset($pi);
```

Place this after `$s3Users` is computed (Step 2) and before the template vars are returned.

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/TenantBillingController.php
git commit -m "feat: use shared assignment_mode, add S3 users to tenant billing"
```

---

### Task 8: Update user-assignments.tpl — S3 Picker + e3 Button

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/user-assignments.tpl`

- [ ] **Step 1: Add S3 users data and mode-aware logic to Alpine component**

In the `x-data` object on the unassigned section, add:
- `s3Users` property initialized from `{$s3_users|default:array()|@json_encode|escape:'html'}`
- `selectedS3UserId` state property (empty string default)
- `s3DropOpen`, `s3Search` state properties
- `filteredS3Users()` method (filter by `display_label` on search string)
- `selectS3User(id)` method
- `selectedS3UserLabel()` method
- Update `requiresCometUser()` → check `plan.assignment_mode.requires_comet_user`
- Add `requiresS3User()` → check `plan.assignment_mode.requires_s3_user`
- Update `submit()` to send `s3_user_id` instead of `comet_user_id` when mode is `e3_storage`

- [ ] **Step 2: Add picker switching to the modal body**

After the "Backup User" field:
- Wrap the existing Comet user field with `x-show="requiresCometUser()"`
- Add new S3 user picker field wrapped with `x-show="requiresS3User()"` — same searchable dropdown pattern as the tenant/plan pickers

- [ ] **Step 3: Add "Assign e3 Storage Plan" button**

Above the unassigned users table, add a button:
```html
<button type="button" class="eb-btn eb-btn-secondary eb-btn-sm"
  @click="openModal('', '')">Assign e3 Storage Plan</button>
```

When `openModal` is called with empty `cometUserId`, the Comet user field stays empty and the modal allows the MSP to pick an e3 plan + S3 user.

- [ ] **Step 4: Update assigned users table to use comet_user_display**

Change `{$row.comet_user_id|default:'-'|escape}` to `{$row.comet_user_display|default:$row.comet_user_id|default:'-'|escape}` in the assigned rows table.

- [ ] **Step 5: Verify page renders**

Load the User Assignments page in a browser. Verify:
- Unassigned Comet users still show "Assign Plan" button that opens modal with user pre-filled
- "Assign e3 Storage Plan" button opens modal with empty user, allowing plan/S3 user selection
- Selecting an e3 plan shows the S3 user picker, selecting a backup plan shows the Comet user (read-only)
- Assigned users table shows resolved display labels

- [ ] **Step 6: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/user-assignments.tpl
git commit -m "feat: add S3 user picker and e3 assign button to user-assignments"
```

---

### Task 9: Update catalog-plans.tpl + catalog-plans.js — Three-State Pickers

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl` (add S3 users JSON script tag)
- Modify: `accounts/modules/addons/eazybackup/assets/js/catalog-plans.js` (add S3 picker state + methods)

- [ ] **Step 1: Add S3 users JSON to template**

In `catalog-plans.tpl`, after the existing `<script type="application/json" id="eb-comet-accounts-json">` tag (around line 1000), add:

```html
<script type="application/json" id="eb-s3-users-json">{$s3_users_json|default:'[]' nofilter}</script>
```

- [ ] **Step 2: Update planPageFactory in catalog-plans.js**

In the `planPageFactory` function, after parsing `cometAccounts`:
```javascript
var s3Users = parseJsonScript('eb-s3-users-json');
```

Add state properties:
```javascript
assignS3UserOpen: false,
assignS3UserSearch: '',
selectedS3UserId: '',
```

Add methods:
```javascript
assignPlanRequiresS3User() {
    var meta = this.assignPlanMeta();
    return !!(meta && meta.assignment_mode && meta.assignment_mode.requires_s3_user);
},
filteredS3Users() {
    var query = String(this.assignS3UserSearch || '').trim().toLowerCase();
    if (!query) return s3Users;
    return s3Users.filter(function(u) {
        return String(u.display_label || '').toLowerCase().includes(query)
            || String(u.username || '').toLowerCase().includes(query);
    });
},
selectAssignS3User(id) {
    this.selectedS3UserId = String(id || '');
    this.assignS3UserOpen = false;
    this.assignS3UserSearch = '';
},
assignS3UserLabel() {
    if (!this.selectedS3UserId) return '';
    var u = s3Users.find(function(u) { return String(u.id || '') === String(this.selectedS3UserId); }.bind(this));
    return u ? (u.display_label || u.username || String(u.id)) : this.selectedS3UserId;
},
toggleAssignS3UserDropdown() {
    this.assignS3UserOpen = !this.assignS3UserOpen;
    if (this.assignS3UserOpen) {
        this.assignTenantOpen = false;
        this.assignUserOpen = false;
    }
},
```

Update `assignPlanRequiresCometUser()` to use the new `assignment_mode` shape:
```javascript
assignPlanRequiresCometUser() {
    var meta = this.assignPlanMeta();
    if (!meta || !meta.assignment_mode) return true;
    return !!meta.assignment_mode.requires_comet_user;
},
```

Update `submitAssign()` to send `s3_user_id` when `assignPlanRequiresS3User()`:
```javascript
if (this.assignPlanRequiresS3User()) {
    if (!this.selectedS3UserId) {
        safeToast('Select an e3 storage user.', 'warning');
        return;
    }
    body.set('s3_user_id', String(this.selectedS3UserId));
}
```

Update `openAssign()` to reset `selectedS3UserId`.

- [ ] **Step 3: Add S3 user picker markup to catalog-plans.tpl**

In the assign modal markup, after the existing eazyBackup user picker section (around lines 797–860), add a new S3 user picker section:
```html
<div class="relative min-w-0" x-show="assignPlanRequiresS3User()">
    <!-- Same dropdown pattern as existing pickers -->
</div>
```

Hide the existing Comet user picker when S3 mode is active:
```html
x-show="assignPlanRequiresCometUser()"
```

- [ ] **Step 4: Verify PHP syntax and page load**

Run: `php -l accounts/modules/addons/eazybackup/assets/js/catalog-plans.js` won't work (JS), but verify no Smarty errors by loading the page.

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl
git add accounts/modules/addons/eazybackup/assets/js/catalog-plans.js
git commit -m "feat: add S3 user picker to catalog-plans assign modal"
```

---

### Task 10: Update tenant-detail.tpl — S3 Picker + Hide Storage Users Tab

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/tenant-detail.tpl` (billing tab assign modal + Storage Users tab)

- [ ] **Step 1: Add S3 user picker to billing tab assign modal**

In the billing tab's `x-data` object (around line 423):
- Add `s3Users: {$billing_s3_users|default:array()|@json_encode|escape:'html'}`
- Add `selectedS3UserId: ''`, `assignS3UserOpen: false`, `assignS3UserSearch: ''`
- Add `requiresS3User()`, `filteredS3Users()`, `selectS3User()`, `s3UserLabel()` methods

Update `requiresCometUser()` to read from `plan.assignment_mode.requires_comet_user` instead of `plan.requires_comet_user`.

Update `submitAssignPlan()` to send `s3_user_id` when `requiresS3User()`.

- [ ] **Step 2: Add S3 picker markup**

Same pattern as Task 9 Step 3 — add S3 picker section, wrap existing Comet picker with `x-show="requiresCometUser()"`.

- [ ] **Step 3: Hide Storage Users tab**

Find the "Storage Users" tab link in the tab navigation. Comment it out or wrap with a conditional that evaluates to false:

```smarty
{* Storage Users tab hidden — s3_backup_users product not ready *}
{* <button ...>Storage Users</button> *}
```

Also hide the Storage Users tab content panel if it exists.

- [ ] **Step 4: Update plan instance display labels**

In the billing tab's active subscriptions table, replace raw `comet_user_id` display with the resolved `comet_user_display` variable from the controller.

- [ ] **Step 5: Verify page renders**

Load a tenant's Billing tab. Verify:
- "Assign Plan" modal shows Comet picker for backup/storage plans, S3 picker for e3 plans
- Storage Users tab is not visible
- Active subscription list shows resolved display labels

- [ ] **Step 6: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/tenant-detail.tpl
git commit -m "feat: add S3 picker to tenant billing, hide Storage Users tab"
```

---

### Task 11: Update Billing Pipeline — E3_STORAGE_GIB Branch

**Files:**
- Modify: `accounts/modules/addons/eazybackup/bin/partnerhub_usage_job.php:32-74`

- [ ] **Step 1: Add storage: prefix skip and E3_STORAGE_GIB branch**

In `partnerhub_usage_job.php`, modify the `STORAGE_TB` block (lines 32–74):

After `if (isset($map['STORAGE_TB'])) {`, add at the top:
```php
            if (str_starts_with((string)$pi->comet_user_id, 'storage:')) {
                // Legacy tenant-level instance — handled by stripe_tenant_usage_rollup.php
            } else {
```

Close the `else` brace before the existing `catch`. This wraps the existing `eb_storage_daily` query so it only runs for real Comet usernames.

After the entire `STORAGE_TB` block, add the `E3_STORAGE_GIB` block:

```php
        if (isset($map['E3_STORAGE_GIB'])) {
            try {
                $cuid = (string)$pi->comet_user_id;
                if (!str_starts_with($cuid, 'e3:')) {
                    continue;
                }
                $s3UserId = (int)substr($cuid, 3);
                if ($s3UserId <= 0) {
                    continue;
                }

                $maxBytes = 0;
                if (Capsule::schema()->hasTable('s3_historical_stats')) {
                    $maxBytes = (int)Capsule::table('s3_historical_stats')
                        ->where('user_id', $s3UserId)
                        ->where('date', '>=', $periodStart->format('Y-m-d'))
                        ->where('date', '<', $periodEnd->format('Y-m-d'))
                        ->max('total_storage');
                }

                $gib = (int)floor(max(0, $maxBytes) / (1024 * 1024 * 1024));
                $tenantId = (int)($pi->tenant_id ?? $pi->customer_id ?? 0);
                $meteredItem = resolveActivePlanInstanceMeteredItem($tenantId, 'E3_STORAGE_GIB');
                if (!$meteredItem) {
                    continue;
                }
                if ((int)($meteredItem['plan_instance_id'] ?? 0) !== (int)$pi->id) {
                    continue;
                }
                $resolvedItemId = (string)$meteredItem['stripe_subscription_item_id'];
                $billableGib = computeBillableMeteredUsage(
                    $gib,
                    (int)($meteredItem['default_qty'] ?? 0),
                    (string)($meteredItem['overage_mode'] ?? 'bill_all')
                );
                $idemp = sha1('pi:' . $pi->id . '|item:' . $resolvedItemId . '|metric:E3_STORAGE_GIB|' . $periodStart->format('Y-m-d') . '|' . $periodEnd->format('Y-m-d'));
                $exists = Capsule::table('eb_usage_ledger')->where('idempotency_key', $idemp)->first();
                if (!$exists && $gib >= 0) {
                    Capsule::table('eb_usage_ledger')->insert([
                        'tenant_id' => $tenantId,
                        'metric' => 'E3_STORAGE_GIB',
                        'qty' => $billableGib,
                        'period_start' => $periodStart->format('Y-m-d H:i:s'),
                        'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                        'source' => 'cron',
                        'idempotency_key' => $idemp,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    try {
                        $cSvc->createUsageRecordConnected($resolvedItemId, $billableGib, time(), $acct);
                        Capsule::table('eb_usage_ledger')->where('idempotency_key', $idemp)->update(['pushed_to_stripe_at' => date('Y-m-d H:i:s')]);
                    } catch (\Throwable $e) {
                        try {
                            if (function_exists('logActivity')) {
                                @logActivity('eazybackup: e3 usage push failed for item ' . $resolvedItemId . ' — ' . $e->getMessage());
                            }
                        } catch (\Throwable $__) {}
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/bin/partnerhub_usage_job.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/bin/partnerhub_usage_job.php
git commit -m "feat: add E3_STORAGE_GIB billing branch, skip legacy storage: keys"
```

---

### Task 12: Hide s3_backup_users Sidebar Link

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl:55-60`

- [ ] **Step 1: Comment out the Storage Users (e3) sidebar link**

At lines 55–60, wrap the button in a Smarty comment:

```smarty
{* Storage Users (e3) hidden — s3_backup_users product not ready
            <button type="button" @click="window.location.href='{$WEB_ROOT}/index.php?m=cloudstorage&amp;page=e3backup_users'" class="eb-sidebar-link w-full cursor-pointer text-left {if $ebPhSidebarPage eq 'storage-users'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Storage Users (e3)' : ''">
                <span class="eb-sidebar-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>
                </span>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Storage Users (e3)</span>
            </button>
*}
```

- [ ] **Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl
git commit -m "chore: hide s3_backup_users sidebar link (product not ready)"
```
