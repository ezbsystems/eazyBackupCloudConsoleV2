# Tenant v2 Design Spec

Date: 2026-03-03  
Status: Approved for implementation planning  
Owner: eazyBackup Partner Hub / Cloud Storage

## 1) Problem Statement

Current tenant behavior has a model collision:

- `s3_backup_tenants` is the correct domain model for MSP customer organizations (billing, members, service ownership).
- `eb_whitelabel_tenants` currently drives Partner Hub tenant create/manage UI and exposes infrastructure fields (`product_id`, `server_id`, `servergroup_id`) to MSP users.
- MSP users should manage customer organizations, not provisioning internals.

Tenant v2 resolves this by making customer tenant semantics canonical and treating white-label as an optional capability under each tenant.

## 2) Domain Model (Canonical)

### Canonical Customer Tenant

- Canonical table: `s3_backup_tenants`
- Meaning: MSP customer organization (example: "Acme Engineering")
- Owns:
  - Profile and billing identity
  - Tenant members (`s3_backup_tenant_users`)
  - Storage billing linkage (`eb_tenant_storage_links`)
  - Optional white-label profile

### Optional White-Label Capability

- White-label is not the tenant itself.
- White-label provisioning artifacts remain in white-label tables.
- White-label rows are attached to canonical tenant through an explicit mapping key.

### Storage User Billing Association

- Cloud storage users (`s3_users`) continue to be associated for billing through `eb_tenant_storage_links`.
- Tenant v2 UI surfaces this linkage directly from canonical tenant detail.

## 3) Data Model Changes

## 3.1 New Columns / Constraints

1. `eb_whitelabel_tenants`
   - Add `canonical_tenant_id INT UNSIGNED NULL`
   - Add index `idx_canonical_tenant_id (canonical_tenant_id)`
   - Add unique key `uniq_canonical_tenant (canonical_tenant_id)` for 1:0..1 relationship
   - Add FK (soft-enforced first rollout, hard FK in Phase C):
     - `canonical_tenant_id -> s3_backup_tenants.id`

2. `s3_backup_tenants`
   - Ensure unique constraint `UNIQUE (client_id, slug)` already exists and is required for canonical routing.
   - No infra columns added here.

3. `eb_tenant_storage_links`
   - Confirm `tenant_id` references canonical tenant id (`s3_backup_tenants.id`).
   - Add/verify unique pair key `(tenant_id, s3_user_id)` to prevent duplicate links.

## 3.2 Migration Script

Create migration script:

- `accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php`

Responsibilities:

1. Add `canonical_tenant_id` column and index to `eb_whitelabel_tenants` if missing.
2. For each MSP (`client_id`):
   - Match existing white-label tenant to canonical tenant using deterministic precedence:
     1) exact `org_id` match to canonical slug/name normalization
     2) exact normalized `fqdn`/subdomain-to-slug match
     3) explicit manual mapping CSV input (optional flag)
   - Set `canonical_tenant_id`.
3. Emit report:
   - mapped rows
   - ambiguous rows
   - unmapped rows requiring manual review
4. Dry-run mode by default; `--apply` to write.

## 4) Route and IA Changes

Tenant v2 centralizes tenant operations in Partner Hub and demotes legacy e3 tenant routes to compatibility wrappers.

## 4.1 Primary Partner Hub Routes (new/updated)

- `index.php?m=eazybackup&a=ph-tenants-manage`  
  Canonical tenant list/create landing.
- `index.php?m=eazybackup&a=ph-tenant&id=<canonicalTenantId>`  
  Canonical tenant detail shell with tabs.
- `index.php?m=eazybackup&a=ph-tenant-members&id=<canonicalTenantId>` (JSON or page partial)
- `index.php?m=eazybackup&a=ph-tenant-storage-users&id=<canonicalTenantId>`
- `index.php?m=eazybackup&a=ph-tenant-billing&id=<canonicalTenantId>`
- `index.php?m=eazybackup&a=ph-tenant-whitelabel&id=<canonicalTenantId>`
- `index.php?m=eazybackup&a=ph-tenant-whitelabel-enable&id=<canonicalTenantId>` (POST)
- `index.php?m=eazybackup&a=ph-tenant-whitelabel-disable&id=<canonicalTenantId>` (POST, optional in phase B)

## 4.2 Legacy e3 Routes (compatibility)

Legacy:

- `index.php?m=cloudstorage&page=e3backup&view=tenants`
- `index.php?m=cloudstorage&page=e3backup&view=tenant_detail&tenant_id=<id>`
- `index.php?m=cloudstorage&page=e3backup&view=tenant_members`

Behavior in Tenant v2:

- Keep working but add top-level callout and deep link to canonical Partner Hub tenant pages.
- For create/edit actions, redirect to `ph-tenant` equivalent using canonical id.
- Maintain API compatibility for existing frontend code until cutover completion.

## 4.3 Navigation Changes

- Remove standalone "Tenants" as e3-only primary nav destination.
- Add "Customer Tenants" in Partner Hub as central tenant hub for all products.
- e3 pages should link to the same canonical tenant detail context rather than separate tenant ownership model.

## 5) UI/UX Specification

## 5.1 Tenant List (`ph-tenants-manage`)

Rename:

- "Tenant Management" -> "Customer Tenants"

Create form fields (MSP-facing):

- Name (required)
- Slug (auto-suggest + editable, required)
- Contact email (required)
- Contact name (optional)
- Status (`active|suspended`)

Do not show:

- `product_id`
- `server_id`
- `servergroup_id`
- direct infra internals

List columns:

- Tenant name
- Slug
- Status
- Members count
- Storage users count
- White-label status badge (Enabled/Not enabled)
- Actions: Manage

## 5.2 Tenant Detail (`ph-tenant`)

Tabs:

1. Profile
2. Members
3. Storage Users
4. Billing
5. White Label

### Profile tab

- Canonical org fields from `s3_backup_tenants`
- No white-label infra fields

### Members tab

- Reuse `s3_backup_tenant_users` model and role semantics (`admin`, `user`)
- Actions: add/edit/disable/reset password

### Storage Users tab

- Show linked storage users via `eb_tenant_storage_links`
- Action: link existing cloud storage user to tenant
- Action: unlink (with guard if active billing period lock is enabled)

### Billing tab

- Tenant usage summaries from canonical tenant scoped rollup
- Stripe customer status and links where available

### White Label tab

- Card: "Enable White Label for this customer tenant"
- On enable:
  - Run provisioning workflow
  - Create/update `eb_whitelabel_tenants` row with `canonical_tenant_id`
  - Auto-assign `product_id/server_id/servergroup_id` internally
- Display-only infra diagnostics (read-only, collapsible "Advanced" panel for support staff if needed)

## 6) Backend Behavior

## 6.1 Controller Split

1. Canonical tenant controllers (Partner Hub):
   - `pages/partnerhub/TenantsController.php` (updated to operate on `s3_backup_tenants`)
   - Add services for members/storage/billing/whitelabel tabs.

2. White-label provisioning:
   - Reuse `pages/whitelabel/BuildController.php` provisioning internals.
   - Add orchestrator method:
     - `enableWhiteLabelForCanonicalTenant(int $clientId, int $canonicalTenantId, array $options = [])`

## 6.2 Security and Authorization

- Continue MSP client ownership checks by `client_id` on canonical tenant.
- CSRF required on create/update/delete/enable actions.
- Enforce that linked white-label record and storage links belong to same MSP (`client_id` parity).

## 6.3 Infra Field Handling

- MSP can never input `product_id`, `server_id`, `servergroup_id`.
- Backend write paths to these fields must only run from provisioning orchestration.
- Manual edits allowed only through internal/admin path (not client area forms).

## 7) Migration and Rollout Plan

## Phase A: UI and Copy Safety (low risk)

1. Rename Partner Hub screens to Customer Tenant terminology.
2. Remove infra input fields from MSP forms in:
   - `templates/whitelabel/tenants.tpl`
   - `templates/whitelabel/tenant-detail.tpl`
3. Add white-label status badge and CTA placeholder.
4. No destructive data migration yet.

Exit criteria:

- MSP no longer sees infra fields anywhere in tenant create/edit UI.

## Phase B: Canonical Data Cutover

1. Update `TenantsController` read/write source to `s3_backup_tenants`.
2. Add `canonical_tenant_id` mapping in `eb_whitelabel_tenants`.
3. Run migration script in dry-run and apply mode.
4. Wire White Label tab enable action to provisioning flow.
5. Keep legacy e3 tenant pages as wrappers/deep links.

Exit criteria:

- Partner Hub tenant CRUD exclusively uses canonical table.
- White-label enablement is optional per canonical tenant.

## Phase C: Deprecation and Enforcement

1. Add hard FK for `canonical_tenant_id` after mapping is complete.
2. Convert legacy e3 tenant create/edit into redirects only.
3. Remove any stale dual-write fallback.

Exit criteria:

- Single source of truth for tenant identity and billing scope.

## 8) Acceptance Criteria

1. MSP can create a customer tenant without entering infrastructure IDs.
2. MSP can manage members (admin/user) under that tenant.
3. MSP can link cloud storage users to that tenant for billing.
4. MSP can optionally enable white-label for that tenant.
5. Enabling white-label auto-assigns infra references internally.
6. Existing white-label tenants remain accessible and correctly mapped.
7. Legacy e3 tenant URLs still resolve (direct or redirect) during transition.

## 9) Testing Matrix

Functional:

- Create/edit/suspend tenant in Partner Hub.
- Add/edit/remove tenant members.
- Link/unlink storage users and verify billing rollup linkage.
- Enable white-label and verify provisioning side effects.

Migration:

- Dry-run mapping report coverage (mapped/ambiguous/unmapped).
- Apply mapping idempotency (safe re-run).
- Backward compatibility for existing tenant bookmarks/routes.

Security:

- CSRF rejection on mutating routes.
- MSP cannot access another MSP tenant by id tampering.
- MSP cannot write infra fields through request injection.

## 10) Out of Scope (Tenant v2)

- Rebuilding white-label provisioning internals.
- New member role types beyond `admin` and `user`.
- Immediate removal of all legacy cloudstorage tenant templates/routes (deferred to Phase C cleanup).

