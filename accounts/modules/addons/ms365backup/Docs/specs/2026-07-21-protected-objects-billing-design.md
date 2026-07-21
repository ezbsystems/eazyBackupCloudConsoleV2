# MS365 Billing — Protected Objects Design

**Status:** Approved for implementation planning  
**Date:** 2026-07-21  
**Owners:** ms365backup (metering) + cloudstorage (customer UI / copy)  
**Supersedes (in part):** Protected User exclusion rules in [MS365_BILLING_AND_STORAGE_DESIGN.md](../MS365_BILLING_AND_STORAGE_DESIGN.md) §2.1 and [MS365_BILLING_GUIDE.md](../MS365_BILLING_GUIDE.md)  
**Related plan (update after implementation):** `/root/.cursor/plans/ms365_billing_design_cef1654c.plan.md`

---

## 1. Problem

The platform currently meters **Protected Users** and **excludes** shared mailboxes, room/equipment mailboxes, and guests. Customers can back up recoverable data from those resources without a corresponding charge.

We are aligning with the legacy Comet “protected object” idea: bill for backupable identities/resources that hold recoverable data, not only licensed “people.”

## 2. Goals

- Replace the customer-facing **Protected Users** model with **Protected Objects**.
- Bill shared mailboxes, rooms/equipment, guests, and SharePoint site members when they are in backup scope (directly or via membership).
- **Never double-bill** the same Azure directory identity within a backup user.
- Keep peak-of-period billing, OneDrive overage rules, WHMCS metric keys, and trial/invoice plumbing intact except where copy/rules must change.
- Update client area, wizard, admin Jobs, pricing surfaces, and billing docs to say **Protected Objects**.

## 3. Non-goals

- Renaming internal metric keys (`protected_users`) or admin setting keys (`protected_user_price_cad`).
- Changing OneDrive included GiB / overage pricing model.
- Grandfathering or delaying the new count until the next billing period.
- Billing SharePoint sites as a single opaque object without member expansion.
- Billing devices, service principals, or other non-backupable directory objects.
- Comet / OBC MS365 containers (permanently out of scope).

## 4. Locked decisions

| Topic | Decision |
|-------|----------|
| Counting model | Selection-based identities + Team/Group member expansion (**Approach A**) |
| Guests | Bill like Member users (personal selection **or** team/group/site membership) |
| Shared / room / equipment mailboxes | Bill when personally selected or when their Azure ID appears in a membership source |
| SharePoint | Resolve **site members** into the same billable set (was deferred; now in scope) |
| Deduplication | One Azure object ID → one Protected Object across personal + team + group + site |
| Internal keys | Keep `protected_users` / `protected_user_price_cad`; display “Protected Objects” |
| Go-live | Apply on next daily meter run; no grandfathering |
| Implementation approach | Expand `ProtectedUserResolver` in place (Approach 1) |

## 5. Definition — Protected Object

A **Protected Object** is one distinct Microsoft 365 directory identity identified by **Azure object ID**, counted at most **once** per backup user for the billing window, when it is reached by any of:

1. **Personal selection** — `user`, `mailbox` (including shared / room / equipment), or `user_onedrive` with at least one enabled personal scope (mail, calendar, contacts, tasks, OneDrive/files).
2. **Team / Group membership** — billable member of a selected Team, team channel (inherits parent team membership), or M365 Group with at least one enabled shared scope.
3. **SharePoint site membership** — billable user/mailbox identity granted on a selected SharePoint site with at least one enabled site scope.

**Included:** Member users, guest users (`userType = Guest` or `#EXT#` in UPN), shared mailboxes, room mailboxes, equipment mailboxes.

**Excluded:** Devices, service principals, and other non-identity objects that are not backupable user/mailbox resources.

**Storage note (unchanged):** Shared workload *data* (Teams messages, SharePoint files, group files, etc.) remains under unlimited storage; billing is per identity in scope, not per byte of those workloads. OneDrive remains the only metered storage dimension.

## 6. Architecture

### 6.1 Resolver (primary change)

Extend `ProtectedUserResolver` (class name kept):

- Remove exclusions in `isBillableMember` for guests, `#EXT#`, and `TYPE_MAILBOX`.
- Continue using a single `$protected[$azureId] = true` map as the **only** aggregation structure (this is the dedupe law).
- Add SharePoint site as a membership source (alongside Team / channel / M365 Group).

### 6.2 SharePoint member enrichment

Mirror the existing Teams/Groups pattern:

1. **Inventory refresh** (`InventoryService`): for each `sharepoint_site`, call a new `DiscoveryService` method to list site permission principals / members; write:
   - `meta.member_azure_ids`
   - `meta.member_count`
   - `meta.members_fetched_at`
2. **Metering / wizard preview**: read cache first; if empty and live discovery is available, fetch once; otherwise set `member_resolution_pending` without inventing members.
3. Filter member rows through the same billable-identity rules as team/group members (users/mailboxes only; guests included; devices/SPs skipped).

Graph permissions: use the same Sites-scoped access already required for SharePoint backup where possible; document any additional Graph permission in `AZURE_SETUP.md` if required after implementation discovery.

### 6.3 Downstream systems

| Layer | Change |
|-------|--------|
| `Ms365UsageMeter` / `Ms365BillingService` / cron / snapshots | Keep metric key `protected_users`; counts come from expanded resolver |
| Invoice hook | Keep pricing from `protected_user_price_cad`; update customer-visible descriptions to “Protected Objects” where we control line text |
| WHMCS config option | Keep option key `protected_users`; FriendlyName / display → “Protected Objects” where product bootstrap or admin UI sets it |
| Admin setting | Key `protected_user_price_cad`; FriendlyName → “Protected Object price (CAD)” |
| Customer UI | Wizard billing dock, Usage & Billing drawer, pricing panel → “Protected Objects” |
| Admin Jobs | Column / labels → Protected Objects |
| Docs | Update design + guide; session `PROGRESS.md`; update legacy plan file after code lands |

### 6.4 OneDrive overage (unchanged)

- Only identities with OneDrive **personally selected** contribute overage.
- A guest or shared mailbox without OneDrive selected contributes **0** OneDrive overage.
- Included GiB and peak-overage GiB behavior unchanged.

## 7. Deduplication examples

| Selection | Protected Objects |
|-----------|-------------------|
| User Alice (mailbox) | 1 |
| Shared mailbox Sales + Alice | 2 |
| Team (29 members including Alice) + Alice selected | 29 |
| Team (29) + SharePoint site whose members are a subset of those 29 | 29 |
| Alice selected + same Alice on Team + same Alice on Site | 1 |
| Guest only as Team member | 1 |
| Room mailbox personally selected | 1 |
| SharePoint site only, 12 billable members resolved | 12 |
| SharePoint site only, member list unavailable | 0 + `member_resolution_pending` |

## 8. Edge cases & error handling

- Membership Graph failures are **per resource**: empty cache, pending flag, do not fail entire inventory or meter run.
- Disconnected / `action_required` tenants: reuse last good snapshot (existing behavior).
- Mid-period go-live may raise the period peak immediately; accepted (no grandfathering).
- Channel selection continues to inherit parent team membership (existing behavior).
- Under no situation may the same Azure ID increment the count more than once for a backup user measurement.

## 9. UI / copy map

Replace customer-visible “Protected User(s)” with “Protected Object(s)” in at least:

- `ms365_job_wizard.tpl` / wizard JS billing dock
- `e3backup_user_detail.tpl` Usage & Billing drawer
- `e3backup_pricing_panel.tpl` / `E3BackupPricingPanelData`
- Admin Jobs JS column labels
- `MS365_BILLING_GUIDE.md` (customer/KB voice)
- `MS365_BILLING_AND_STORAGE_DESIGN.md` §2.1 and related metering sections
- Invoice/description strings we own in MS365 billing hooks/bootstrap

API JSON may continue to return `protected_users` for compatibility; UI labels use Protected Objects.

## 10. Testing & verification

**Unit tests** (`tests/ms365_protected_user_resolver_test.php` and related):

- Shared mailbox personally selected → counted
- Guest personally selected → counted
- Guest as team member → counted
- Room/equipment mailbox → counted when selected / present as mailbox identity
- SharePoint site members → counted
- Cross-source dedupe: personal + team + site → single ID once
- Device/SP in site permissions → not counted
- Team-only regression: member expansion still works

**Manual / dev:**

1. Wizard step 2 preview on a mixed selection (user + shared mailbox + team + site)
2. Usage drawer label + count
3. Inventory refresh populates `meta.member_azure_ids` on sites
4. Meter pass updates WHMCS qty for a test service under the new rules

## 11. Rollout

1. Ship resolver + SharePoint enrichment + tests on **development** WHMCS.
2. Deploy copy/docs updates with the same change.
3. Next `ms365_billing.php` run applies new quantities (peaks may rise).
4. After verification, update `MS365_BILLING_AND_STORAGE_DESIGN.md`, `MS365_BILLING_GUIDE.md`, `PROGRESS.md`, and `/root/.cursor/plans/ms365_billing_design_cef1654c.plan.md`.

## 12. Implementation sketch (for planning)

1. Discovery: add site member/permission listing; cache on inventory sites.
2. Resolver: widen billable rules; add site membership path; keep single Azure-ID set.
3. Tests: rewrite expectations that asserted exclusions; add SP + dedupe cases.
4. UI/copy: Protected Objects labels across client + admin surfaces.
5. Docs + PROGRESS + billing plan file update.
6. Verify on development WHMCS (wizard, usage API, meter qty).
