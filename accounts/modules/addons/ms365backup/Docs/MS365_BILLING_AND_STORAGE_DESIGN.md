# MS365 Backup — Billing & Storage Design

**Status:** Implemented (v1.20.0+); Protected Objects expansion implemented 2026-07-21
**Last updated:** 2026-07-21
**Owners:** ms365backup (engines + metering services) + cloudstorage (customer UI, buckets, APIs)

This document specifies how to make the **Microsoft 365 Backup** product billing-ready: a free WHMCS product driven by **config options** for Protected Objects and OneDrive overage, **per-backup-user daily metering** sourced from Microsoft Graph, **peak-of-period** billing, admin-configurable pricing, a trial, and **platform-owned, isolated object storage** that never touches the existing object-storage bill.

Read alongside: [PRODUCT_ROADMAP.md](PRODUCT_ROADMAP.md) (product intent/phases), [ARCHITECTURE_BOUNDARIES.md](ARCHITECTURE_BOUNDARIES.md) (module split), and `modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_BILLING.md` (the metered-billing pattern this design mirrors).

**Protected Objects expansion (2026-07-21):** billing now counts **Protected Objects**, not only "Protected Users" — see [specs/2026-07-21-protected-objects-billing-design.md](specs/2026-07-21-protected-objects-billing-design.md) for the approved design this section was updated from. §2.1 below reflects the implemented definition; internal metric/setting keys (`protected_users`, `protected_user_price_cad`) are unchanged.

---

## 1. Goals & billing model

- MS365 Backup is sold **per Protected Object**, with **unlimited storage** for all workloads **except OneDrive**. Each identity with OneDrive personally selected includes a configurable amount of OneDrive storage (default **1 TiB**); usage above the included amount is billed **per GiB**.
- The WHMCS product itself is **free, recurring monthly**; all charges come from **config options**. It has a **trial period**.
- Billing adjusts automatically with usage:
  - **Protected Objects:** bill the **peak (MAX) count seen during the billing period**. If the count drops mid-period, the reduction does **not** take effect until the next billing period.
  - **OneDrive overage:** bill the **peak total overage GiB** across all users during the period.
- Usage is **tracked daily** and surfaced to the customer with a **per-backup-user, per-user OneDrive breakdown**, so an MSP can see exactly which Microsoft 365 user is over the included limit and by how much (required for re-billing their own end customers).
- **Admin-configurable** from the ms365backup addon settings: OneDrive included GiB, OneDrive overage price per GiB, and per-Protected-Object price.

---

## 2. Definitions & tenancy model

### 2.1 Protected Object

A **Protected Object** is one distinct Microsoft 365 directory identity, identified by **Azure object ID**, counted at most **once** per backup user for the billing window, when it is reached by any of:

1. **Personal selection** — the identity is directly selected as a `user`, `mailbox` (including shared / room / equipment), or `user_onedrive` with at least one enabled personal scope (mail, calendar, contacts, tasks, OneDrive/files).
2. **Team / Group membership** — the identity is a billable member of a **selected Team** (channel selections inherit parent-team membership) or **M365 Group** with at least one enabled shared scope.
3. **SharePoint site membership** — the identity is a billable user/mailbox principal granted on a **selected SharePoint site** (via `sites/{id}/permissions`) with at least one enabled site scope.

**Included** (billable): Member users, **guest users** (`userType = Guest` or `#EXT#` in UPN), **shared mailboxes**, **room mailboxes**, **equipment mailboxes** — whether reached by personal selection, team/group membership, or SharePoint site membership.

**Excluded** (not billable): devices, service principals, and other non-identity directory objects that are not backupable user/mailbox resources.

Rules:

- The same Azure object ID is counted **once** across personal selections, team memberships, group memberships, and SharePoint site memberships — strict dedupe on a single `$protected[$azureId] = true` map (the resolver's dedupe law; see §6).
- **Shared workload data** (Teams, M365 Groups, SharePoint files, Planner, OneNote) remains **unlimited storage**; billing is per **identity in scope**, not per workload byte.
- **SharePoint site membership is in scope** (no longer deferred): selecting a SharePoint site resolves its member/permission principals (`sites/{id}/permissions`, cached on the site resource as `meta.member_azure_ids`) into the same billable set as team/group members. If member resolution has not run yet and no live discovery is available, the site contributes **0** and the UI/API surface `member_resolution_pending` rather than inventing a count.
- **Wizard:** job step 2 shows a live billing preview (`protected_users` + estimated monthly) for the **current selection**, not only saved jobs.
- **Internal keys unchanged:** the metric key remains `protected_users` and the admin setting remains `protected_user_price_cad` (see §3, Non-goals in the approved spec); only customer-facing/admin **labels** changed to "Protected Objects" / "Protected Object price (CAD)".
- **Go-live:** the expanded definition applies starting with the **next daily meter run** (`ms365_billing.php`) — no grandfathering of prior, narrower counts.

### 2.2 Tenancy model (how clients, backup users, and WHMCS services relate)

- One **WHMCS client** (often an MSP) → many **backup users** (`s3_backup_users`), each representing one of the MSP's end customers.
- Each **backup user** connects **one** MS365 / Azure tenant via its own `ms365_tenant_records` row (`backup_user_id` → one `azure_tenant_id`).
- **One WHMCS MS365 Backup service per backup user** (product PID from `pid_ms365_backup`, production default **107**). The service **`Username`** field is the backup user's `s3_backup_users.username` so MSPs reconcile invoices by username.
- **Provisioning trigger:** first MS365 job creation (`Ms365CustomerJobService::create` → `Provisioner::ensureMs365ServiceForBackupUser`). Existing clients are **not** put on trial; first invoice is due **registration date + 1 month**.
- Metering and billing are **per backup user / per WHMCS service** (not summed across all backup users on the client).

```mermaid
flowchart TB
  WClient["WHMCS Client (MSP)"]
  BU_A["s3_backup_users A"]
  BU_B["s3_backup_users B"]
  TR_A["ms365_tenant_records A"]
  TR_B["ms365_tenant_records B"]
  Svc_A["WHMCS MS365 service A (username=A)"]
  Svc_B["WHMCS MS365 service B (username=B)"]
  WClient --> BU_A --> TR_A --> Svc_A
  WClient --> BU_B --> TR_B --> Svc_B
```

---

## 3. Current state and why it must change

- **Object-storage billing** in `accounts/crons/s3Billing.php` → `S3Billing::gatherBillingData()` bills `pid_cloud_storage` services at **$9/TiB base + ~$0.0088/GiB overage**, summing the primary `s3_users` account **plus every sub-user** via `S3Billing::handleTenants()` (`accounts/modules/addons/cloudstorage/lib/Admin/S3Billing.php`). There is **no billing-exemption flag** anywhere.
- Today MS365 buckets (`e3ms365-{token}`) are created **one per WHMCS client** by `Ms365StorageBootstrapService` under a system `cloudbackup_owner` sub-user whose `parent_id` is the **customer's e3 storage root** (`CloudBackupBootstrapService::ensureBackupOwnerUser`). Consequently, MS365 bytes are **silently rolled into the customer's object-storage bill** — the central problem to fix.
- There is **no `pid_ms365` setting**. `ms365_tenant_records.whmcs_service_id` exists but is **unused** (`accounts/modules/addons/ms365backup/lib/Ms365Backup/TenantRecordRepository.php`). Legacy MS365 PIDs 52/57 live in the eazybackup addon and are not part of this design.
- **Kopia note (critical for metering):** MS365 data is stored as a **deduplicated Kopia repository** inside the bucket. You **cannot** meter per-user OneDrive bytes by listing S3 prefixes — Kopia stores packed/deduped blobs, not per-source objects. Per-user OneDrive size must come from **Graph-reported drive usage**, which `InventoryService` already records as `meta.size_bytes` + `meta.owner_user_id` per drive in `inventory.json`. This is also the most customer-meaningful figure (the data they are protecting), and it is the confirmed source of truth for OneDrive billing.

---

## 4. Storage architecture (platform-owned, isolated, per backup user)

### 4.1 Target shape

- **One global platform-owned RGW owner**: a single `s3_users` row with `system_key='ms365_platform_owner'`, `is_system_managed=1`, `parent_id=NULL`, under a dedicated platform tenant, with RGW `max_buckets` set to unlimited. **Auto-created on demand** the first time any MS365 bucket is needed (idempotent `ensurePlatformOwner()`).
- **One `e3ms365-{guid}` bucket per backup user** (per MS365 tenant). The GUID is a stable hash of `backup_user_id`, preserving the required `e3ms365-` prefix. Buckets are created under the global platform owner via the existing keyless `createBucketAsAdmin` temp-key flow.
- **The customer never receives keys** to this account (mirrors the keyless owner pattern already used for `cloudbackup_owner`), which prevents accidental object deletion that would corrupt the Kopia repository.

```mermaid
flowchart TB
  subgraph customerTree [Customer storage tree - billed by S3Billing]
    Root["s3_users root (pid_cloud_storage)"]
    CustBkts["customer buckets"]
    Root --> CustBkts
  end
  subgraph platformTree [ONE global platform MS365 owner - NOT billed]
    PlatOwner["s3_users: system_key=ms365_platform_owner, parent_id=NULL, max_buckets=unlimited"]
    BktA["e3ms365-{guidA} (backup user A / tenant A)"]
    BktB["e3ms365-{guidB} (backup user B / tenant B)"]
    PlatOwner --> BktA
    PlatOwner --> BktB
  end
```

### 4.2 Code changes

- Add `ensurePlatformOwner()` (idempotent) that creates/returns the single `ms365_platform_owner` `s3_users` row and its RGW user (set `max_buckets` unlimited).
- Refactor the MS365 storage path so bootstrap is **keyed by `backup_user_id`** (currently client-only):
  - `Ms365StorageBootstrapService::ensureForBackupUser($clientId, $backupUserId)` resolves the platform owner, derives `e3ms365-{guid(backupUserId)}`, creates the bucket if missing, ensures the Kopia repo, and links `s3_bucket_id` / `s3_bucket_name` / `s3_user_id` onto that backup user's `ms365_tenant_records` row via `TenantRecordRepository::linkCloudStorageBucket`.
  - `TenantRecordRepository::ensureCloudStorageBucketForBackupUser` calls the per-backup-user bootstrap (today it ignores `backupUserId` and bootstraps client-level).
- **Billing safety guard:** in `S3Billing`, skip buckets named `e3ms365-*` and skip the `ms365_platform_owner` account. Because the owner sits outside any customer `parent_id` tree, `handleTenants()` will not traverse it anyway; the guard is defense-in-depth so MS365 storage can never leak into object-storage billing even if linkage changes.

### 4.3 Migration

**None required** — there is no customer data. Existing `e3ms365-*` buckets created under the old per-client owner can be left in place or cleaned up; new connects use the platform owner and per-backup-user buckets.

---

## 5. WHMCS product, settings & config options

### 5.1 Product

- "eazyBackup Microsoft 365 Backup" — **Free** base product, recurring **monthly**; charges from config options. **One service per backup user**, provisioned on first MS365 job (`Provisioner::ensureMs365ServiceForBackupUser`). New-signup onboarding via `provisionMs365()` may still use trials; existing MSP clients provisioning at first job do **not**.

### 5.2 Admin settings (ms365backup addon — `accounts/modules/addons/ms365backup/ms365backup.php`)

| Setting | Purpose | Default |
|---------|---------|---------|
| `pid_ms365_backup` | WHMCS product PID(s) for MS365 Backup (comma-separated supported), read like `pid_cloud_storage` in `s3Billing.php` | — |
| `onedrive_included_gib` | Included OneDrive storage per user, in GiB | `1024` |
| `onedrive_overage_price_per_gib_cad` | Price per GiB above the included amount | — |
| `protected_user_price_cad` | Price per Protected Object (key name unchanged; FriendlyName "Protected Object price (CAD)") | — |
| `ms365_trial_days` | Trial length in days | `30` |
| `ms365_config_option_ids` | Auto-stored mapping of metric → WHMCS config option id (mirrors `e3cb_config_option_ids`) | — |

### 5.3 Config options (quantity type)

- `protected_users` — Qty = number of Protected Objects (service total). Internal key/config-option key unchanged; WHMCS `optionname` display renamed to "Protected Objects" (existing installs migrated in place, see §14).
- `onedrive_overage_gib` — Qty = total overage **GiB** across all users (Qty 1 = 1 GiB).

Prices are taken from the **admin settings** above and applied via the invoice hook (Section 8), so pricing is configurable from module settings rather than baked into WHMCS product pricing.

### 5.4 Service linkage

Populate `ms365_tenant_records.whmcs_service_id` for the **specific backup user** when the WHMCS service is provisioned so metering binds to the correct `tblhosting` row (one service per backup user).

---

## 6. Metering — Protected Objects

A new daily cron `accounts/crons/ms365_billing.php` (mirroring `accounts/crons/e3_cloudbackup_billing.php`) drives a `Ms365BillingService` in **ms365backup** (engines/services own metering; cloudstorage surfaces it).

For each active MS365 service (resolved via `pid_ms365_backup`), meter the **single backup user** bound via `ms365_tenant_records.whmcs_service_id` (fallback: match `tblhosting.username` to `s3_backup_users.username`):

1. Load the backup user's `inventory.json` and **selected backup scope** (union of all active MS365 jobs).
2. Compute distinct billable Azure IDs via `ProtectedUserResolver` (personal selection + team/group members + SharePoint site members). The class name `ProtectedUserResolver` is kept internally; it now resolves **Protected Objects**, no longer excluding guests, `#EXT#` UPNs, or `TYPE_MAILBOX`.
3. Record counts for that backup user only.

**Billing value = MAX(qty) over the current billing window** (anchored to `nextduedate`). Write peak qty to `tblhostingconfigoptions` and update `tblhosting.amount` from rated lines so recurring amount tracks usage during the month.

### 6.1 Member resolution (Teams, M365 Groups & SharePoint sites)

Member/permission-principal lists are sourced from Microsoft Graph and cached during inventory refresh:

| Resource | Graph endpoint | Permission |
|----------|----------------|------------|
| Team | `GET /teams/{groupId}/members` | `TeamMember.Read.All` |
| M365 Group | `GET /groups/{groupId}/members` | `GroupMember.Read.All` |
| SharePoint site | `GET /sites/{siteId}/permissions` | `Sites.Read.All` (existing; no additional Graph permission required — see [AZURE_SETUP.md](AZURE_SETUP.md)) |

- Cached on each team/group/site resource in `inventory.json` as `meta.member_azure_ids`, `meta.member_count`, `meta.members_fetched_at`.
- Discovery snapshots: `discovery/team_members/{groupId}.json` and `discovery/site_members/{siteId}.json` (ops/debug).
- `ProtectedUserResolver` uses cache first; on-demand Graph fetch when cache is empty (e.g. tenant refreshed before deploy). If neither cache nor live discovery is available, the resource contributes **0** members and the resolver/meter surface `member_resolution_pending` rather than guessing.
- Team channels inherit parent team membership (no per-channel member fetch).
- Selecting a team/group with **any** enabled scope (metadata, messages, files) bills all billable members; selecting a SharePoint site with any enabled site scope bills all billable site principals.
- A team/group whose `meta.sharepoint_site_id` matches a selected site's members are merged into that site's billable set (`InventoryService::enrichSharePointSiteMembers`), so a site backed by a team roster still resolves correctly even before the site's own permissions are fetched.
- Site permission principals are filtered through the same billable-identity rules as team/group members: users and mailboxes (including guests) count; devices and service principals are skipped.
- OneDrive overage is still computed only for identities with **personally selected** OneDrive scope.

---

## 7. Metering — OneDrive overage (Graph-sourced, per user)

- Source per-user OneDrive usage from **Graph-reported drive size** in `inventory.json` (`meta.size_bytes`, keyed to `meta.owner_user_id`), refreshed by inventory discovery. (Not from RGW prefixes — see the Kopia note.)
- `included_bytes = onedrive_included_gib` (admin setting). Daily, for each protected user (per backup user) with OneDrive selected:
  - `overage_bytes = max(0, used_bytes - included_bytes)`.
  - Persist a **per-user row** for that day (drives the MSP breakdown + audit).
- **Billing value = MAX(total overage GiB across all the client's users) over the billing window** → `tblhostingconfigoptions.qty` for `onedrive_overage_gib`.

A single overage config option keeps invoicing simple, while the per-user daily table provides full visibility into *which* user is over and by how much.

---

## 8. Pricing, invoice application & trial

- **Quantity automation:** the daily cron writes peak metered quantities to `tblhostingconfigoptions.qty`.
- **Pricing:** an `InvoiceCreationPreEmail` hook (plus a `DailyCronJob` pass) mirroring `accounts/modules/addons/cloudstorage/hooks/e3cb_invoice.php` sets each MS365 config-option line amount from the **admin-setting prices**:
  - `protected_user_price_cad` × Protected Objects
  - `onedrive_overage_price_per_gib_cad` × overage GiB
- **Trial:** reuse the e3cb pattern — a `ms365_billing_trial_state` lifecycle (`trialing → converted / suspended_no_payment / cancelled`) with a daily trial-check cron. While `trialing`, line amounts are **zeroed** but qty/unit price are still recorded, so the customer/MSP sees what the trial would cost. Trial length comes from `ms365_trial_days`.

---

## 9. Database — daily tracking tables

| Table | Columns (key) | Purpose |
|-------|---------------|---------|
| `ms365_billing_usage_snapshots` | `service_id`, `client_id`, `backup_user_id`, `metric` ENUM(`protected_users`,`onedrive_overage_gib`), `qty`, `taken_at` | Daily captures; MAX-in-window for rating. Mirrors `s3_cloudbackup_usage_snapshots`. |
| `ms365_onedrive_usage_daily` | `client_id`, `backup_user_id`, `tenant_record_id`, `azure_user_id`, `upn`, `display_name`, `drive_id`, `used_bytes`, `included_bytes`, `overage_bytes`, `collected_date` | Per-user OneDrive breakdown for UI + history. |
| `ms365_billing_trial_state` | service lifecycle fields (e3cb-style) | Trial lifecycle per service. |

---

## 10. UI

### 10.1 Customer (per backup user)

- **Pricing panel** (persistent): `partials/e3backup_pricing_panel.tpl` on the Add User modal and user-detail Billing tab — e3 + MS365 rates (not shown per job).
- **Usage & Billing drawer** on MS365 job cards (`e3backup_user_detail.tpl`) — live usage, peaks, per-user OneDrive table. API: `api/ms365_usage.php`.

### 10.2 Admin (support)

Surface the same Protected Objects + storage stats on the **ms365backup admin Jobs page** so support can quickly reference them.

**Trials admin:** `addonmodules.php?module=ms365backup&action=trials` — mirrors `cloudstorage&action=cloudbackup_trials` (filter by status, preview invoice, convert/cancel/re-evaluate, run evaluation for all).

---

## 11. Cron schedule

```
0 6 * * *   php /var/www/eazybackup.ca/accounts/crons/ms365_billing.php                                   # daily meter + rate + applyToWhmcs
30 3 * * *  php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_trial_check.php    # daily trial lifecycle
```

---

## 12. Edge cases & decisions

- An identity with only a mailbox (no OneDrive selected) is a Protected Object but contributes **0** OneDrive overage.
- A guest or shared/room/equipment mailbox with no OneDrive selected is a Protected Object but contributes **0** OneDrive overage (same rule as any other identity).
- OneDrive size reflects **live Graph quota usage**, which can change daily; peak-of-period billing smooths this and matches the "no mid-period reduction" rule.
- Disconnected / `action_required` tenants: reuse the last good snapshot and flag staleness in the UI; do not bill from missing inventory.
- A WHMCS client with multiple backup users: **one MS365 WHMCS service per backup user**, each with its own metered config-option quantities (MSP reconciles by service Username).
- One backup user maps to one Azure tenant at a time; metering treats the backup user as the unit.

---

## 13. Phasing

1. **Storage isolation** — global platform owner (auto-created) + per-backup-user `e3ms365-{guid}` bucket + `S3Billing` guard.
2. **Product + settings** — `pid_ms365_backup`, OneDrive/price/trial settings, product + config-option bootstrap, `whmcs_service_id` linkage.
3. **Metering** — tables + `Ms365BillingService` (per backup user, aggregated to service) + daily cron + qty automation.
4. **Pricing + trial** — invoice override hook driven by settings + trial lifecycle cron.
5. **Customer UI** — MS365 job-card button → Usage & Billing panel + `api/ms365_usage.php`.
6. **Admin stats** — Protected Objects + storage on the ms365backup admin Jobs page.
7. **Protected Objects expansion (2026-07-21)** — widen resolver to guests/shared/room/equipment mailboxes, add SharePoint site member resolution, relabel UI to "Protected Objects" (keys unchanged). See [specs/2026-07-21-protected-objects-billing-design.md](specs/2026-07-21-protected-objects-billing-design.md).

---

## 14. Implementation index (paths)

| Area | Path |
|------|------|
| Object-storage billing (to guard) | `accounts/crons/s3Billing.php`, `accounts/modules/addons/cloudstorage/lib/Admin/S3Billing.php` |
| MS365 bucket bootstrap (to make per-backup-user) | `accounts/modules/addons/cloudstorage/lib/Client/Ms365StorageBootstrapService.php`, `CloudBackupBootstrapService.php` |
| Tenant linkage | `accounts/modules/addons/ms365backup/lib/Ms365Backup/TenantRecordRepository.php` |
| OneDrive size source | `accounts/modules/addons/ms365backup/lib/Ms365Backup/InventoryService.php` (`inventory.json` `meta.size_bytes` / `meta.owner_user_id`) |
| Protected Objects resolver + SharePoint member enrichment | `accounts/modules/addons/ms365backup/lib/Ms365Backup/ProtectedUserResolver.php`, `DiscoveryService::listSiteMembers()`, `InventoryService::enrichSharePointSiteMembers()` |
| Billing pattern to mirror | `accounts/crons/e3_cloudbackup_billing.php`, `accounts/modules/addons/cloudstorage/lib/Admin/E3CloudBackupBilling.php`, `lib/Admin/E3CloudBackupPricing.php`, `lib/Provision/E3CloudBackupProductBootstrap.php`, `hooks/e3cb_invoice.php` |
| Addon settings | `accounts/modules/addons/ms365backup/ms365backup.php` |
| Customer UI | `accounts/modules/addons/cloudstorage/templates/e3backup_user_detail.tpl`, new `api/ms365_usage.php` |
| New cron + tables | `accounts/crons/ms365_billing.php`, `accounts/modules/addons/ms365backup/crons/ms365_trial_check.php`, `ms365_billing_usage_snapshots`, `ms365_onedrive_usage_daily`, `ms365_billing_trial_state` |
