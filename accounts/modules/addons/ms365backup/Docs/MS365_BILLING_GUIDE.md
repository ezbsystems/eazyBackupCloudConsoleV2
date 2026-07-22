# Microsoft 365 Backup — How Billing Is Calculated

**Audience:** MSPs, partners, support, and KB authors  
**Last updated:** 2026-07-21  
**Technical design reference:** [MS365_BILLING_AND_STORAGE_DESIGN.md](MS365_BILLING_AND_STORAGE_DESIGN.md)

This document explains **how eazyBackup calculates Microsoft 365 Backup charges** in plain language. Use it as the source for customer-facing KB articles, sales collateral, and support responses.

---

## Summary

Microsoft 365 Backup is a **metered monthly service**. The base WHMCS product is **$0**; charges come from two usage-based line items:

| Line item | What it measures | Typical unit |
|-----------|------------------|--------------|
| **Protected Objects** | Distinct Microsoft 365 identities covered by your backup jobs — people, guests, shared/room/equipment mailboxes | Per object / month |
| **OneDrive overage** | Total OneDrive storage above the included allowance | Per GiB / month |

**Storage for most M365 workloads is unlimited** (mailbox, Teams, SharePoint, groups, Planner, OneNote, etc.). Only **OneDrive** has a per-object included cap; usage above that cap is billed separately.

Billing is **per backup user** (one connected M365 tenant per backup user), not lumped across an entire MSP account.

---

## Who is billed

```text
WHMCS client (often an MSP)
  └── Backup user A  →  one M365 tenant  →  one WHMCS MS365 service  →  one invoice section
  └── Backup user B  →  one M365 tenant  →  one WHMCS MS365 service  →  one invoice section
```

- Each **backup user** represents one of your end customers (or one isolated M365 tenant you manage).
- When you create the first MS365 backup job for a backup user, a **dedicated WHMCS service** is provisioned for that user.
- The service **Username** matches the backup user's username so you can reconcile invoices to the correct customer.
- Metering runs **only for that backup user's active jobs** — selections on backup user A never affect backup user B's bill.

On unified **e3 Backup User** products, MS365 metrics (`protected_users`, `onedrive_overage_gib`) appear as config options on the same service alongside other e3 workloads.

---

## Protected Objects

A **Protected Object** is one distinct Microsoft 365 directory identity (by Azure object ID) — a person, guest, or mailbox — reached by your backup selections. You are charged once per identity per backup user per month, regardless of how many workloads you back up for it or how many ways it's reached (personal selection, team/group membership, or SharePoint site membership).

### What counts as a Protected Object

An identity becomes a Protected Object when **any** of these is true in your **active MS365 backup jobs**:

#### 1. Personal selection

You directly select a user or mailbox (including a **shared, room, or equipment mailbox**) with at least one enabled scope:

- Mailbox  
- Calendar  
- Contacts  
- Tasks  
- OneDrive / personal files  

If the same identity is selected multiple times (e.g. mailbox + OneDrive), it counts **once**.

#### 2. Team or Microsoft 365 Group membership

You select a **Team** or **Microsoft 365 Group** with at least one enabled backup scope (e.g. Teams messages, group mail, group files). Every **billable member** of that team or group is added to your Protected Object count — including guest members.

- Members are resolved from Microsoft Graph during **inventory refresh** and cached on each team/group resource.
- If you select **multiple teams or groups**, members are combined and **deduplicated** — the same identity in two groups is still one Protected Object.
- Selecting a **team channel** inherits membership from the parent team (channels do not add a separate member list).
- Selecting a team with **any** scope enabled (metadata, messages, or files) bills **all** billable members, even if you only care about one workload type.

#### 3. SharePoint site membership

You select a **SharePoint site** with at least one enabled site scope (files and/or lists). Every **billable member/permission principal** on that site — resolved from Microsoft Graph site permissions — is added to your Protected Object count, the same way team/group members are.

- Site members are resolved during **inventory refresh** and cached on the site resource, just like team/group members.
- If the site's membership overlaps a selected team or group (e.g. a Team-backed site), the identities are still counted **once** overall.

### Who counts

| Identity | Billed? |
|----------|---------|
| Member (licensed) users | Yes, when personally selected or reached via team/group/site membership |
| **Guest users** (`#EXT#` in UPN or `userType = Guest`) | **Yes**, when personally selected or reached via team/group/site membership |
| **Shared mailboxes** | **Yes**, when personally selected or reached via team/group/site membership |
| **Room / equipment mailboxes** | **Yes**, when personally selected or reached via team/group/site membership |
| Devices, service principals, other non-identity objects | No — never billed |

### Deduplication examples

| Selection | Protected Objects |
|-----------|-----------------|
| User Alice (mailbox only) | 1 |
| Team "Technical" (29 members), no individual users selected | 29 |
| Team "Technical" (29 members) **and** Alice is also selected individually | 29 (Alice counted once) |
| Team A (10 members) + Team B (8 members), 3 people in both | 15 |
| SharePoint site only, 12 billable members resolved | 12 |
| Shared mailbox "Sales" personally selected + Alice | 2 |
| Guest user only as a Team member | 1 |
| Room mailbox personally selected | 1 |
| Alice selected personally + also on Team + also on Site | 1 (counted once) |
| SharePoint site only, member list not yet resolved | 0, with **"Member counts incomplete"** shown until you refresh inventory |

---

## OneDrive overage

Each Protected Object that has **OneDrive personally selected** in a backup job receives a large included OneDrive allowance (default **1 TiB per object**, configurable by eazyBackup).

| Concept | Detail |
|---------|--------|
| **Included storage** | Per object, per backup user (default 1,024 GiB) |
| **Measured usage** | Live size reported by Microsoft Graph during inventory refresh — not backup repository size |
| **Overage** | `max(0, used − included)` per object |
| **Billed quantity** | **Sum of all objects' overage**, converted to GiB, then peak-of-period (see below) |

Important:

- OneDrive overage applies only when **you explicitly include OneDrive** for that identity in a job selection.
- Team/group/site membership alone does **not** automatically meter OneDrive for every member — only identities with OneDrive in scope contribute to overage.
- An identity with mailbox only (no OneDrive) is a Protected Object but adds **0** OneDrive overage. This also applies to guests and shared/room/equipment mailboxes — they never have OneDrive to select, so they never contribute overage.

---

## What is unlimited (no per-GiB storage charge)

Backup data for these workloads does **not** incur object-storage-style per-GiB fees on the MS365 product:

- Exchange mailboxes (personal and group)  
- Teams (metadata, messages, files via SharePoint)  
- Microsoft 365 Groups (mail, calendar, files)  
- SharePoint sites (files and lists)  
- Planner, OneNote, directory baseline  

MS365 backup data is stored in **dedicated `e3ms365-*` buckets** that are **excluded from your regular e3 object storage bill**. You pay for **Protected Objects** and **OneDrive overage**, not for total backup bytes for these workloads.

---

## Peak-of-period billing

Usage is sampled **once per day**. Your invoice uses the **highest (peak) value seen during the billing period**, not the value on the last day.

| Metric | Peak rule |
|--------|-----------|
| Protected Objects | **Maximum** distinct Protected Object count on any day in the period |
| OneDrive overage | **Maximum** total overage GiB (summed across objects) on any day in the period |

### What this means in practice

- If you add users, guests, mailboxes, or sites mid-month, your peak (and invoice) can **increase** for that period.
- If you remove them or shrink jobs mid-month, the peak **does not drop** until the **next** billing period starts.
- OneDrive usage can fluctuate daily; peak-of-period billing uses the worst day in the window.

The billing period is anchored to each service's **next due date** (typically monthly from provisioning or signup).

---

## Monthly charge formula

For each backup user's WHMCS service:

```text
Monthly MS365 charge =
    (Peak Protected Objects × Protected Object unit price)
  + (Peak OneDrive overage GiB × OneDrive overage unit price)
```

Unit prices are set in eazyBackup admin settings (`protected_user_price_cad`, `onedrive_overage_price_per_gib_cad`) and applied at invoice time. The customer portal and job wizard show current rates from these settings.

During a **trial**, quantities are still metered and displayed, but line **amounts are $0** until the trial converts.

---

## How usage is measured (technical overview)

1. **Daily cron** (`ms365_billing.php`) runs metering for each active MS365 / unified e3 Backup User service.
2. **Inventory** (`inventory.json`) supplies the user/mailbox list, OneDrive sizes, and cached team/group/site member IDs.
3. **Active jobs** — the union of all `selected_resource_ids` and `scope_overrides` on active MS365 backup jobs for that backup user — defines what is protected.
4. **ProtectedUserResolver** (internal class name; resolves Protected Objects) computes distinct billable Azure IDs from personal selection + team/group members + SharePoint site members, deduplicated across all three sources.
5. **OneDrive overage** is computed per identity with OneDrive in scope, using Graph-reported `size_bytes`.
6. Daily snapshots are stored; **peak in the billing window** is written to WHMCS config option quantities.
7. **Invoice hooks** multiply quantities by admin-configured unit prices.

If inventory is stale or member lists could not be loaded, the UI shows a warning (**"Member counts incomplete"** or **"Inventory may be stale"**). Refresh inventory from the job wizard or user detail page before relying on estimates.

---

## Where customers see usage

| Location | What it shows |
|----------|----------------|
| **Job wizard — step 2 (Inventory)** | Live **billing estimate** for the current job selection: Protected Objects, est. monthly, and a collapsed **How this is calculated** panel showing direct vs membership appearances, duplicates removed, and unique Protected Objects |
| **User detail → MS365 → Usage & Billing** | Current counts, **peak this period**, per-object OneDrive table, estimated period total |
| **Pricing panel** (add user / billing tab) | Published unit rates (Protected Objects, OneDrive included, overage per GiB) |
| **WHMCS invoice** | Peak quantities × unit prices for the billing period |

The wizard estimate reflects **this job's selection only**. After save, the Usage & Billing drawer reflects **all active MS365 jobs** for that backup user (union of selections).

---

## Trial

New signups may receive a **trial period** (default 30 days, configurable). While trialing:

- Protected Objects and OneDrive overage are **tracked and shown**.
- Invoice line **amounts are zero**.
- After trial conversion, normal metering and charging apply.

Existing MSP clients who add MS365 at first job creation are typically **not** placed on trial; first invoice is due one month after registration.

---

## Worked examples

### Example A — Small business

**Jobs:** 5 users with mailbox + OneDrive; no teams.

| Metric | Value |
|--------|-------|
| Peak Protected Objects | 5 |
| OneDrive overage | 0 GiB (all under 1 TiB) |
| Monthly charge (at $3.50/object) | 5 × $3.50 = **$17.50** |

### Example B — Team-heavy MSP customer

**Jobs:** One team "Operations" (28 members, messages + files); no individual users selected.

| Metric | Value |
|--------|-------|
| Peak Protected Objects | 28 |
| OneDrive overage | 0 GiB |
| Monthly charge (at $3.50/object) | 28 × $3.50 = **$98.00** |

### Example C — OneDrive overage

**Jobs:** 10 users with OneDrive; one user uses 1.2 TiB (≈ 1,229 GiB).

| Metric | Calculation |
|--------|-------------|
| Included | 1,024 GiB per object |
| Overage for heavy user | ~205 GiB |
| Peak total overage | 205 GiB (assuming no other users over) |
| Protected Objects | 10 |
| Monthly charge (at $3.50/object, $0.01/GiB overage) | (10 × $3.50) + (205 × $0.01) = **$37.05** |

*Illustrative prices only — actual rates appear in your portal.*

### Example D — Mid-month change

| Day | Protected Objects | Peak so far |
|-----|-----------------|-------------|
| 1–10 | 20 | 20 |
| 11–25 | 35 (added team) | **35** |
| 26–30 | 15 (removed team) | **35** (unchanged) |

Invoice for the period bills **35** Protected Objects, not 15.

### Example E — Guests, shared mailbox & SharePoint site

**Jobs:** 8 individual users; shared mailbox "Support" personally selected; SharePoint site "Marketing" (files) with 6 billable members, 2 of whom overlap with the 8 individual users; a guest user who is only a member of the "Marketing" site.

| Metric | Calculation |
|--------|-------------|
| Individual users | 8 |
| Shared mailbox "Support" | +1 |
| Marketing site members (6 total, 2 already counted as individual users) | +4 new |
| Guest on Marketing site (already in the 6) | included in the 4, not additional |
| Peak Protected Objects | 8 + 1 + 4 = **13** |
| Monthly charge (at $3.50/object) | 13 × $3.50 = **$45.50** |

---

## Frequently asked questions

### Why does the wizard show "Member counts incomplete"?

Team, group, or site membership could not be fully loaded (missing cache, Graph permissions, or transient API error). The Protected Object count may be **lower than actual** until you **Refresh inventory**. Ensure the Entra app has `TeamMember.Read.All`, `GroupMember.Read.All`, and `Sites.Read.All` with admin consent.

### How does the wizard explain Protected Object counts?

Expand **How this is calculated** under the billing estimate. It shows directly selected appearances, group/Team/site membership appearances, duplicate appearances removed (deduplication across sources), and the resulting unique Protected Objects total. When member lists are incomplete, the panel notes that counts reflect resolved data only and the estimate may increase after inventory refresh.

### Why do some groups or sites show incomplete member counts?

Usually the member list is not yet cached or could not be loaded from Graph. Refresh inventory and reopen the wizard or usage panel.

### Does backing up SharePoint cost extra storage fees?

No per-GiB MS365 storage fee for SharePoint backup data. Selecting a SharePoint site **does** add Protected Objects for its resolved, billable members (see [SharePoint site membership](#3-sharepoint-site-membership) above) — that's an object count, not a storage fee.

### Do guests and shared/room/equipment mailboxes cost extra?

Yes, if they're reached by a personal selection or by team/group/site membership, they're billed as Protected Objects the same as any other identity. They only ever contribute OneDrive overage if you personally select OneDrive for them (which typically doesn't apply to shared/room/equipment mailboxes).

### Is MS365 backup data billed on my object storage invoice?

No. MS365 data lives in isolated `e3ms365-*` buckets excluded from standard object storage metering.

### How do I lower next month's bill?

Reduce Protected Objects by narrowing job selections (fewer users, guests, shared mailboxes, teams, groups, or sites) or disabling scopes you no longer need. Remember: lowering mid-month does not reduce the **current** period's peak.

### How do MSPs re-bill their customers?

Use **one backup user per end customer**, one WHMCS service per backup user. The Usage & Billing drawer includes a **per-object OneDrive breakdown** for passing through overage to clients.

---

## Glossary

| Term | Meaning |
|------|---------|
| **Backup user** | An e3 Cloud Backup user (`s3_backup_users`) with its own M365 tenant connection and jobs |
| **Protected Object** | A billable Microsoft 365 identity (person, guest, or mailbox) in your backup scope, reached via personal selection or team/group/site membership. Internal metric key remains `protected_users` for backward compatibility. |
| **Peak / peak-in-period** | Highest metered value recorded on any day in the current billing window |
| **Scope** | Per-resource toggles (mail, OneDrive, Teams messages, etc.) in a backup job |
| **Inventory refresh** | Discovery pass that updates users, mailboxes, teams, groups, sites, OneDrive sizes, and member lists from Graph |

---

## Related documentation

| Document | Purpose |
|----------|---------|
| [MS365_BILLING_AND_STORAGE_DESIGN.md](MS365_BILLING_AND_STORAGE_DESIGN.md) | Engineering design, schema, cron, implementation index |
| [ARCHITECTURE_BOUNDARIES.md](ARCHITECTURE_BOUNDARIES.md) | Module split (ms365backup vs cloudstorage) |
| `modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_BILLING.md` | Sibling metered-billing pattern for e3 Cloud Backup |

---

## Document history

| Date | Change |
|------|--------|
| 2026-07-22 | Job wizard billing dock: replaced per-group/site breakdown list with collapsed **How this is calculated** reconciliation panel (direct appearances + membership appearances − duplicates = unique Protected Objects). Backend exposes `billing.reconciliation` from `ProtectedUserResolver`. |
| 2026-07-06 | Initial guide: member-based Protected Users (teams/groups), peak billing, OneDrive overage, wizard estimate, KB-oriented structure |
| 2026-07-21 | Renamed **Protected Users → Protected Objects**. Guests and shared/room/equipment mailboxes moved from "does not count" to the **counts** table (billed like any other identity). Added **SharePoint site membership** as a third billable source (site permissions, no longer deferred). Updated deduplication examples, worked examples, FAQ, and glossary. Internal metric key (`protected_users`) and admin setting key (`protected_user_price_cad`) unchanged. |
