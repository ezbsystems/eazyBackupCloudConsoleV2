# Protected Objects Billing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bill MS365 backup as **Protected Objects** (users + shared/room/equipment mailboxes + guests + SharePoint site members), with strict Azure-ID dedupe, while keeping internal metric key `protected_users` and updating all customer-facing copy.

**Architecture:** Expand `ProtectedUserResolver` in place (single `$protected[$azureId]` set). Add SharePoint site member discovery/caching in `DiscoveryService` + `InventoryService` mirroring Teams/Groups. Relax `isBillableMember` exclusions. Update display names only for UI/docs/invoice labels.

**Tech Stack:** PHP 8.2, WHMCS, Microsoft Graph (`Ms365Backup\GraphClient` pagination), existing `ms365_protected_user_resolver_test.php` harness, Smarty/Alpine client templates.

**Spec:** `accounts/modules/addons/ms365backup/Docs/specs/2026-07-21-protected-objects-billing-design.md`

## Global Constraints

- Internal metric key remains `protected_users`; admin setting key remains `protected_user_price_cad`.
- Customer-facing language is **Protected Objects** / **per object / month**.
- Same Azure object ID must never increment the count more than once per backup-user measurement (personal ∩ team ∩ group ∩ site).
- Guests bill like Member users (personal selection or membership).
- Shared / room / equipment mailboxes (`TYPE_MAILBOX`) are billable when selected or present as identities.
- No grandfathering — next meter run uses the new rules.
- Do **not** rename `ProtectedUserResolver` class.
- Do **not** git commit unless the user explicitly asks in the implementing session (skip Commit steps until then).
- Workspace root for commands: `/var/www/eazybackup.ca/accounts`.
- Follow existing patterns; minimal diffs; update `Docs/PROGRESS.md` at session end.

## File map

| File | Responsibility |
|------|----------------|
| `lib/Ms365Backup/DiscoveryService.php` | `listSiteMembers($siteId)` — Graph site permissions → user rows |
| `lib/Ms365Backup/InventoryService.php` | Enrich `sharepoint_site` with `meta.member_azure_ids` (+ merge group-connected members) |
| `lib/Ms365Backup/ProtectedUserResolver.php` | Billable rules + site membership source + dedupe |
| `tests/ms365_protected_user_resolver_test.php` | Inclusion + SP + cross-source dedupe tests |
| `lib/Ms365Backup/Ms365BillingConfig.php` | Friendly name → Protected Objects |
| `lib/Ms365Backup/Ms365ProductBootstrap.php` | Config option display name |
| `cloudstorage/.../E3BackupUserProductBootstrap.php` | Unified product option display name |
| `ms365backup.php` | Admin setting FriendlyName/Description |
| Client/admin templates + `jobs.js` | “Protected Objects” copy |
| Billing docs + `AZURE_SETUP.md` + `PROGRESS.md` + plan file | Documentation |

---

### Task 1: Failing tests for Protected Objects rules

**Files:**
- Modify: `accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php`
- Test: same file

**Interfaces:**
- Consumes: `ProtectedUserResolver::resolve`, `Ms365UsageMeter::measureSelection`, `TenantResource::build`, `BackupScope::*`
- Produces: failing assertions that drive Tasks 2–3

- [ ] **Step 1: Update the guest/shared exclusion assertions to expect inclusion**

Replace the block that currently asserts guests and shared mailboxes are excluded (around the `$inventoryGuests` case) with:

```php
$resultGuests = ProtectedUserResolver::resolve(
    $inventoryGuests,
    ['team:grp-g', 'mailbox:shared-1'],
    $scopeGuests,
);
assert_true(in_array($guestId, $resultGuests['protected_azure_ids'], true), 'guest user counted as protected object via team membership');
assert_true(in_array('shared-1', $resultGuests['protected_azure_ids'], true), 'shared mailbox counted when personally selected');
assert_eq(3, count($resultGuests['protected_azure_ids']), 'team member user-1 + guest + shared mailbox = 3');
```

- [ ] **Step 2: Replace deferred SharePoint assertion with member-cache billing**

Replace the site-only deferred test with:

```php
$inventorySite = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'site-1', 'Standalone Site', null, [
            'id' => 'site:site-1',
            'meta' => [
                'member_azure_ids' => ['sp-user-1', 'sp-user-2', 'guest-sp'],
                'member_count' => 3,
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'guest-sp', 'SP Guest', null, [
            'id' => 'user:guest-sp',
            'email' => 'guest_sp#EXT#@example.com',
            'meta' => ['user_type' => 'Guest'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'sp-user-1', 'SP User 1', null, [
            'id' => 'user:sp-user-1',
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'sp-user-2', 'SP User 2', null, [
            'id' => 'user:sp-user-2',
            'meta' => ['user_type' => 'Member'],
        ]),
    ],
];
$scopeSite = [
    'site:site-1' => [BackupScope::FILES => true],
];
$resultSite = ProtectedUserResolver::resolve($inventorySite, ['site:site-1'], $scopeSite);
assert_eq(3, count($resultSite['protected_azure_ids']), 'sharepoint site members become protected objects');
assert_true(in_array('guest-sp', $resultSite['protected_azure_ids'], true), 'sharepoint guest member counted');
```

- [ ] **Step 3: Add cross-source dedupe + personally selected guest tests**

Append before the `Ms365UsageMeter::measureSelection` assertion:

```php
// Cross-source dedupe: personal + team + site
$alice = 'alice-1';
$inventoryCross = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, $alice, 'Alice', null, [
            'id' => 'user:' . $alice,
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_TEAM, 'grp-cross', 'Cross Team', null, [
            'id' => 'team:grp-cross',
            'meta' => [
                'group_id' => 'grp-cross',
                'member_azure_ids' => [$alice, 'bob-1'],
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_SHAREPOINT_SITE, 'site-cross', 'Cross Site', null, [
            'id' => 'site:site-cross',
            'meta' => [
                'member_azure_ids' => [$alice, 'carol-1'],
            ],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'bob-1', 'Bob', null, [
            'id' => 'user:bob-1',
            'meta' => ['user_type' => 'Member'],
        ]),
        TenantResource::build(TenantResource::TYPE_USER, 'carol-1', 'Carol', null, [
            'id' => 'user:carol-1',
            'meta' => ['user_type' => 'Member'],
        ]),
    ],
];
$scopeCross = [
    'user:' . $alice => [BackupScope::MAIL => true],
    'team:grp-cross' => [BackupScope::TEAMS_MESSAGES => true],
    'site:site-cross' => [BackupScope::FILES => true],
];
$resultCross = ProtectedUserResolver::resolve(
    $inventoryCross,
    ['user:' . $alice, 'team:grp-cross', 'site:site-cross'],
    $scopeCross,
);
assert_eq(3, count($resultCross['protected_azure_ids']), 'alice+bob+carol deduped across personal/team/site');

// Personally selected guest
$inventoryGuestPersonal = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_USER, 'guest-solo', 'Solo Guest', null, [
            'id' => 'user:guest-solo',
            'email' => 'solo#EXT#@example.com',
            'meta' => ['user_type' => 'Guest'],
        ]),
    ],
];
$resultGuestPersonal = ProtectedUserResolver::resolve(
    $inventoryGuestPersonal,
    ['user:guest-solo'],
    ['user:guest-solo' => [BackupScope::MAIL => true]],
);
assert_eq(1, count($resultGuestPersonal['protected_azure_ids']), 'personally selected guest is a protected object');

// Room/equipment-style mailbox (TYPE_MAILBOX, no user_type Guest)
$inventoryRoom = [
    'resources' => [
        TenantResource::build(TenantResource::TYPE_MAILBOX, 'room-1', 'Conference Room', null, [
            'id' => 'mailbox:room-1',
            'email' => 'room1@example.com',
            'meta' => ['user_type' => ''],
        ]),
    ],
];
$resultRoom = ProtectedUserResolver::resolve(
    $inventoryRoom,
    ['mailbox:room-1'],
    ['mailbox:room-1' => [BackupScope::CALENDAR => true]],
);
assert_eq(1, count($resultRoom['protected_azure_ids']), 'room mailbox personally selected counts as protected object');
```

- [ ] **Step 4: Update file header comment**

Change the top comment to say Protected Objects / member-based metering tests.

- [ ] **Step 5: Run tests — expect failures on the new/changed assertions**

Run:

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
```

Expected: FAIL on guest inclusion, shared mailbox inclusion, SharePoint members, and/or room mailbox (old exclusions still in code).

- [ ] **Step 6: Commit (only if user asked)**

```bash
git add modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
git commit -m "$(cat <<'EOF'
test: expect Protected Objects inclusions and SharePoint member billing

EOF
)"
```

---

### Task 2: Widen billable identity rules in `ProtectedUserResolver`

**Files:**
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/ProtectedUserResolver.php`
- Test: `accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php`

**Interfaces:**
- Consumes: existing `resolve()`, `isBillableMember()`, inventory user index
- Produces: guests + `TYPE_MAILBOX` pass `isBillableMember`; personal mailbox selection counts

- [ ] **Step 1: Update class docblock**

```php
/**
 * Resolves billable Protected Object Azure IDs from job selection + inventory.
 *
 * One Azure object ID counts at most once (personal + team/group + site membership).
 */
```

- [ ] **Step 2: Remove guest / shared-mailbox exclusions from `isBillableMember`**

Replace the exclusion block in `isBillableMember` so it only rejects empty IDs (and later non-user odata if present on member rows — already handled upstream by `DiscoveryService::isGraphUserMember`). New body after gathering `$userType` / `$upn` / `$resourceType`:

```php
        // Protected Objects: Member users, guests, shared/room/equipment mailboxes all bill.
        // Devices / service principals are filtered before rows reach here (Graph member helpers).
        return true;
```

Delete these three rejection branches:

```php
        if ($resourceType === TenantResource::TYPE_MAILBOX) {
            return false;
        }
        if ($userType === 'guest') {
            return false;
        }
        if ($upn !== '' && str_contains($upn, '#ext#')) {
            return false;
        }
```

Keep the empty `$azureId` early return.

- [ ] **Step 3: Run tests**

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
```

Expected: guest/shared/room/personal-guest tests PASS; SharePoint site members and cross-source site path still FAIL until Task 3.

- [ ] **Step 4: Commit (only if user asked)**

```bash
git add modules/addons/ms365backup/lib/Ms365Backup/ProtectedUserResolver.php
git commit -m "$(cat <<'EOF'
fix: bill guests and shared/room mailboxes as Protected Objects

EOF
)"
```

---

### Task 3: SharePoint site membership in resolver + discovery + inventory

**Files:**
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/DiscoveryService.php`
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/InventoryService.php`
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/ProtectedUserResolver.php`
- Test: `accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php`

**Interfaces:**
- Consumes: `GraphClient::paginate`, existing `billableIdsFromMemberRows`
- Produces:
  - `DiscoveryService::listSiteMembers(string $siteId): list<array<string, mixed>>`
  - Site resources with `meta.member_azure_ids` / `member_count` / `members_fetched_at`
  - Resolver treats `TYPE_SHAREPOINT_SITE` like other membership sources

- [ ] **Step 1: Add `listSiteMembers` to `DiscoveryService`**

Add after `listGroupMembers`:

```php
    /**
     * List user principals granted on a SharePoint site (Graph permissions).
     *
     * @return list<array<string, mixed>> Rows shaped like team/group members:
     *   id, displayName, userPrincipalName, mail, userType
     */
    public function listSiteMembers(string $siteId): array
    {
        $siteId = trim($siteId);
        if ($siteId === '') {
            return [];
        }

        $byId = [];
        try {
            foreach ($this->graph->paginate(
                'sites/' . rawurlencode($siteId) . '/permissions',
                []
            ) as $permission) {
                if (!is_array($permission)) {
                    continue;
                }
                foreach ($this->extractUsersFromSitePermission($permission) as $user) {
                    $id = trim((string) ($user['id'] ?? ''));
                    if ($id === '' || !self::isGraphUserMember($user)) {
                        continue;
                    }
                    $byId[$id] = [
                        'id' => $id,
                        'displayName' => (string) ($user['displayName'] ?? ''),
                        'userPrincipalName' => (string) ($user['userPrincipalName'] ?? $user['email'] ?? ''),
                        'mail' => (string) ($user['email'] ?? $user['mail'] ?? ''),
                        'userType' => (string) ($user['userType'] ?? ''),
                        '@odata.type' => '#microsoft.graph.user',
                    ];
                }
            }
        } catch (\Throwable $_) {
            return [];
        }

        $members = array_values($byId);
        $this->storage->writeJson($this->siteMembersDiscoveryPath($siteId), [
            'fetched_at' => gmdate('c'),
            'site_id' => $siteId,
            'source' => 'site_permissions',
            'count' => count($members),
            'value' => $members,
        ]);

        return $members;
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<array<string, mixed>>
     */
    private function extractUsersFromSitePermission(array $permission): array
    {
        $users = [];
        $identitySets = [];
        foreach (['grantedToIdentitiesV2', 'grantedToIdentities', 'grantedToV2', 'grantedTo'] as $key) {
            $val = $permission[$key] ?? null;
            if ($val === null) {
                continue;
            }
            if (isset($val['user']) || isset($val['application'])) {
                $identitySets[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $identity) {
                    if (is_array($identity)) {
                        $identitySets[] = $identity;
                    }
                }
            }
        }
        foreach ($identitySets as $identity) {
            $user = $identity['user'] ?? null;
            if (is_array($user) && trim((string) ($user['id'] ?? '')) !== '') {
                $users[] = $user;
            }
        }

        return $users;
    }

    private function siteMembersDiscoveryPath(string $siteId): string
    {
        $dir = $this->storage->discoveryDir() . '/site_members';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $siteId) ?? 'unknown';

        return $dir . '/' . $safe . '.json';
    }
```

- [ ] **Step 2: Enrich sites during inventory refresh**

In `InventoryService`, after `enrichTeamAndGroupMembers(...)` is called (find that call site), also call a new private method:

```php
$this->enrichSharePointSiteMembers($resources, $discoveryCounts);
```

Implement `enrichSharePointSiteMembers` mirroring `enrichTeamAndGroupMembers`:

1. Collect all `TYPE_SHAREPOINT_SITE` resources with non-empty `graph_id`.
2. Progress label: `site_members` / `Loading SharePoint site members…`.
3. For each site:
   - `$rows = $this->discovery->listSiteMembers($siteId);`
   - `$billable = ProtectedUserResolver::billableIdsFromMemberRows($rows, ['resources' => array_values($resources)]);`
   - **Merge group-connected members:** scan `$resources` for `TYPE_TEAM` / `TYPE_M365_GROUP` whose `meta.sharepoint_site_id` equals this `$siteId` (or whose linked site resource id matches). Union their `meta.member_azure_ids` into `$billable` (string keys set).
   - Write `meta.member_azure_ids`, `meta.member_count`, `meta.members_fetched_at`.
4. On throwable per site: leave `member_azure_ids` as `[]` (do not abort refresh).

- [ ] **Step 3: Add SharePoint to resolver membership sources**

In `ProtectedUserResolver`:

1. Extend `MEMBER_SOURCE_TYPES` to include `TenantResource::TYPE_SHAREPOINT_SITE`.
2. In the membership branch of `resolve()`, after computing `$groupId` path for teams/groups, handle sites separately:

```php
            if ($type === TenantResource::TYPE_SHAREPOINT_SITE) {
                $siteGraphId = (string) ($resource['graph_id']
                    ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
                $fetchKey = 'site:' . $siteGraphId;
                if (!isset($resolvedGroupFetches[$fetchKey])) {
                    $resolvedGroupFetches[$fetchKey] = self::resolveSiteMembers(
                        $siteGraphId,
                        $resource,
                        $byId,
                        $userIndex,
                        $discovery,
                        $memberResolutionPending,
                    );
                }
                // same merge into $protected / $sources / $breakdown as teams
                ...
                continue;
            }
```

Add `resolveSiteMembers`:

```php
    /**
     * @param array<string, array{user_type: string, upn: string, resource_type: string}> $userIndex
     * @return array{ids: list<string>, pending: bool}
     */
    private static function resolveSiteMembers(
        string $siteGraphId,
        array $resource,
        array $byId,
        array $userIndex,
        ?DiscoveryService $discovery,
        bool &$memberResolutionPending,
    ): array {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        $cached = $meta['member_azure_ids'] ?? null;
        if (is_array($cached) && $cached !== []) {
            return [
                'ids' => self::filterBillableIds(array_map('strval', $cached), ['resources' => array_values($byId)]),
                'pending' => false,
            ];
        }
        // Empty array cache with members_fetched_at means "resolved, zero members"
        if (is_array($cached) && isset($meta['members_fetched_at'])) {
            return ['ids' => [], 'pending' => false];
        }
        if ($discovery === null || $siteGraphId === '') {
            $memberResolutionPending = true;

            return ['ids' => [], 'pending' => true];
        }
        try {
            $rows = $discovery->listSiteMembers($siteGraphId);
            $ids = self::billableIdsFromMemberRows($rows, ['resources' => array_values($byId)]);

            return ['ids' => $ids, 'pending' => false];
        } catch (\Throwable $_) {
            $memberResolutionPending = true;

            return ['ids' => [], 'pending' => true];
        }
    }
```

Keep the existing team/group path unchanged (do not route sites through `groupIdForMemberResource`).

- [ ] **Step 4: Run full resolver tests**

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
```

Expected: `All tests passed.`

- [ ] **Step 5: Commit (only if user asked)**

```bash
git add modules/addons/ms365backup/lib/Ms365Backup/DiscoveryService.php \
  modules/addons/ms365backup/lib/Ms365Backup/InventoryService.php \
  modules/addons/ms365backup/lib/Ms365Backup/ProtectedUserResolver.php
git commit -m "$(cat <<'EOF'
feat: meter SharePoint site members as Protected Objects

EOF
)"
```

---

### Task 4: Display names, WHMCS option labels, admin setting copy

**Files:**
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365BillingConfig.php` (`metricFriendlyName`)
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365ProductBootstrap.php` (`METRICS` name)
- Modify: `accounts/modules/addons/cloudstorage/lib/Provision/E3BackupUserProductBootstrap.php` (`protected_users` name)
- Modify: `accounts/modules/addons/ms365backup/ms365backup.php` (addon setting FriendlyName/Description)
- Modify: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365UsageMeter.php` (docblock only)

**Interfaces:**
- Consumes: `Ms365BillingConfig::METRIC_PROTECTED_USERS` (unchanged key)
- Produces: invoice descriptions via `ms365_invoice_description` → `metricFriendlyName` become “Protected Objects”

- [ ] **Step 1: Update friendly names**

`Ms365BillingConfig::metricFriendlyName`:

```php
self::METRIC_PROTECTED_USERS => 'Protected Objects',
```

`Ms365ProductBootstrap::METRICS`:

```php
Ms365BillingConfig::METRIC_PROTECTED_USERS => ['name' => 'Protected Objects'],
```

`E3BackupUserProductBootstrap` metric map entry:

```php
'protected_users' => ['name' => 'Protected Objects', 'default_price' => 0.00],
```

`ms365backup.php` setting:

```php
'protected_user_price_cad' => [
    'FriendlyName' => 'Protected Object price (CAD)',
    ...
    'Description' => 'Per Protected Object per month; applied via invoice hook (not tblpricing).',
],
```

Update `Ms365UsageMeter` file docblock to say Protected Objects.

If `Ms365ProductBootstrap` / `E3BackupUserProductBootstrap` only set option names on create (not update), add a small rename path: when the config option already exists with name `Protected Users`, update `tblproductconfigoptions.optionname` (or the sub-option name field the bootstrap already writes) to `Protected Objects`. Match the existing bootstrap update patterns in those classes — do not invent a second product.

- [ ] **Step 2: Smoke-check invoice label helper**

In a one-off PHP snippet or by reading `ms365_invoice_description`, confirm `metricFriendlyName('protected_users')` returns `Protected Objects`.

- [ ] **Step 3: Commit (only if user asked)**

---

### Task 5: Client area + admin UI copy

**Files:**
- Modify: `accounts/modules/addons/cloudstorage/templates/partials/ms365_job_wizard.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/e3backup_user_detail.tpl`
- Modify: `accounts/modules/addons/cloudstorage/templates/partials/e3backup_pricing_panel.tpl`
- Modify: `accounts/modules/addons/ms365backup/assets/js/jobs.js`
- Modify: wizard tooltip strings that mention Protected User counts

**Interfaces:**
- Consumes: API fields still named `protected_users` / `protected_user_price_cad`
- Produces: visible “Protected Objects” / “per object”

- [ ] **Step 1: Wizard billing dock**

In `ms365_job_wizard.tpl`:

- Label `Protected Users` → `Protected Objects`
- `Protected Users @ $…/user` → `Protected Objects @ $…/object`
- Tooltip text: replace “Protected User counts” with “Protected Object counts”; mention team/group/**site** member lists

- [ ] **Step 2: Usage & Billing drawer**

In `e3backup_user_detail.tpl`:

- Heading `Protected Users` → `Protected Objects`
- `Protected Users @ $…/user` → `Protected Objects @ $…/object`

- [ ] **Step 3: Pricing panel**

In `e3backup_pricing_panel.tpl`:

- `Protected Users` → `Protected Objects`
- `per user / month` → `per object / month` (for the MS365 protected-objects line only; leave OneDrive “per user” as-is)

- [ ] **Step 4: Admin Jobs table**

In `jobs.js` header: `Protected` → `Objects` (or `Prot. Objects` if space is tight). Keep reading `billing.protected_users`.

- [ ] **Step 5: Grep for leftover customer-facing copy**

```bash
rg -n "Protected User" modules/addons/ms365backup modules/addons/cloudstorage/templates modules/addons/cloudstorage/lib/Client modules/addons/cloudstorage/lib/Provision --glob '!**/Docs/**' --glob '!**/PROGRESS.md'
```

Expected: no customer-facing leftover except internal comments/keys. Fix any hits in templates/JS/PHP user strings.

- [ ] **Step 6: Commit (only if user asked)**

---

### Task 6: Docs + Azure permissions note + PROGRESS + legacy plan

**Files:**
- Modify: `accounts/modules/addons/ms365backup/Docs/MS365_BILLING_AND_STORAGE_DESIGN.md`
- Modify: `accounts/modules/addons/ms365backup/Docs/MS365_BILLING_GUIDE.md`
- Modify: `accounts/modules/addons/ms365backup/Docs/AZURE_SETUP.md`
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`
- Modify: `/root/.cursor/plans/ms365_billing_design_cef1654c.plan.md`

**Interfaces:** none (documentation only)

- [ ] **Step 1: Update design §2.1**

Replace Protected User definition with Protected Objects per the approved spec (inclusions, SharePoint members, dedupe, keep internal key note). Remove “Deferred SharePoint” language. Set **Last updated:** 2026-07-21.

- [ ] **Step 2: Rewrite customer guide sections**

Update `MS365_BILLING_GUIDE.md` summary table, definition, who counts / who does not, examples, wizard/UI wording, glossary. Guests and shared/room mailboxes move to the **counts** table. SharePoint site selection bills resolved members. Add changelog row for 2026-07-21.

- [ ] **Step 3: Azure setup**

In `AZURE_SETUP.md`, update TeamMember/GroupMember rows to say Protected Object metering, and note that site member metering uses `sites/{id}/permissions` under existing `Sites.Read.All`. If implementation finds an extra permission is required, document the exact Graph permission name here.

- [ ] **Step 4: PROGRESS session log**

Prepend a session entry: Protected Objects billing, files touched, SharePoint members in scope, next steps (refresh inventories on tenants, watch first meter run).

- [ ] **Step 5: Update legacy plan file**

In `/root/.cursor/plans/ms365_billing_design_cef1654c.plan.md`, revise overview/definitions from Protected Users to Protected Objects and note SharePoint member metering is included (not deferred).

- [ ] **Step 6: Commit (only if user asked)**

---

### Task 7: Dev verification checklist

**Files:** none required (manual / CLI)

- [ ] **Step 1: Re-run unit tests**

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/tests/ms365_protected_user_resolver_test.php
```

Expected: `All tests passed.`

- [ ] **Step 2: Inventory refresh on a connected dev tenant**

Trigger inventory refresh for a backup user that has SharePoint sites. Confirm a site resource in `inventory.json` has `meta.member_azure_ids` (array) and `meta.members_fetched_at`.

- [ ] **Step 3: Wizard preview**

Open job wizard step 2; select a mix of user + shared mailbox + team + site. Confirm billing dock shows **Protected Objects** and a count that matches deduped expectations (not sum of raw memberships).

- [ ] **Step 4: Usage drawer**

Open Usage & Billing; confirm label **Protected Objects** and current/peak numbers.

- [ ] **Step 5: Optional meter dry observation**

If safe on dev, run:

```bash
php /var/www/eazybackup.ca/accounts/crons/ms365_billing.php
```

Confirm `tblhostingconfigoptions.qty` for the protected_users option reflects the new (possibly higher) count. Do not run against production.

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Selection + team/group expansion | Task 2–3 (existing path kept) |
| Guests bill like users | Task 1–2 |
| Shared/room/equipment mailboxes | Task 1–2 |
| SharePoint site members | Task 3 |
| Strict Azure-ID dedupe | Task 1 (tests) + Task 3 (single `$protected` set) |
| Keep `protected_users` key | Tasks 2–5 (no key renames) |
| Display “Protected Objects” | Tasks 4–5 |
| No grandfathering | Task 7 / ops note (behavior of existing peak cron) |
| Update design + guide + plan file | Task 6 |
| Approach 1 (expand resolver in place) | Tasks 2–3 |

No TBD placeholders. Method names are consistent across tasks (`listSiteMembers`, `resolveSiteMembers`, `enrichSharePointSiteMembers`).
