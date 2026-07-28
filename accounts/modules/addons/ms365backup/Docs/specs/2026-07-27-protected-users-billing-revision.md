# MS365 Billing — Protected Users Revision

**Status:** Approved / Implemented  
**Date:** 2026-07-27  
**Owners:** ms365backup (metering) + cloudstorage (customer UI / copy)  
**Supersedes (in part):** [2026-07-21-protected-objects-billing-design.md](2026-07-21-protected-objects-billing-design.md)  
**Updates:** [MS365_BILLING_AND_STORAGE_DESIGN.md](../MS365_BILLING_AND_STORAGE_DESIGN.md) §2.1 / §6, [MS365_BILLING_GUIDE.md](../MS365_BILLING_GUIDE.md)

---

## 1. Problem

The 2026-07-21 **Protected Objects** change expanded billing to guests, shared/room/equipment mailboxes, and SharePoint site members. That closed under-billing gaps but produced large over-counts vs Comet Backup / industry “protected user” licensing — especially on tenants that use “select all” and have many shared or project mailboxes.

| Tenant (dev) | Comet Protected Accounts | Platform Objects (live) | Proposed Protected Users (sim) |
|--------------|--------------------------|-------------------------|--------------------------------|
| **philmgbuild** | **10** | **115** | **10** (+ receipts/referrals exception → still 10 if both are explicitly selected mailboxes) |
| **sa_eazybackup** | **~317** (operator) | **447** | **275** |
| **indigoblue** | **19** (operator) / WHMCS Comet product qty **23** | **17** (live meter & WHMCS MS365 qty) | **17** |

**philmgbuild** is the clearest proof: Comet bills **10** selected accounts (including shared mailboxes `receipts` / `referrals`) while backing up **184 SharePoint / OneDrive sites** without billing per site. Platform Objects billed **105 extra mailbox identities** discovered in inventory. Membership expansion contributed **0** net new identities on that tenant.

**Goal:** Re-align with Comet/Acronis-style **Protected Users** — bill people (and guests) who can put data in the org, plus **explicitly selected** shared mailboxes that Comet treats as protected accounts — without billing every mailbox resource in inventory.

---

## 2. Goals

- Restore customer-facing **Protected Users** terminology (internal key `protected_users` unchanged).
- Bill **member users** and **guests** when personally selected or reached via Team / M365 Group / SharePoint site membership.
- **Do not** bill shared/room/equipment mailboxes by default (including when included via Select All — those remain **backed up** but tagged `billing_exempt`).
- **Exception:** bill a shared mailbox when it is **manually personally selected** (not select-all exempt) as a protected account (Comet parity for receipts/referrals-type accounts). Room/equipment never bill.
- Keep Select All as “include all inventory resources for **backup**”; billing is a separate filter.
- Never double-bill the same Azure object ID (personal ∩ team ∩ group ∩ site ∩ mailbox).
- Keep peak-of-period metering, OneDrive overage, WHMCS metric keys, trial/invoice plumbing.
- Keep Team / Group / SharePoint **membership expansion** (filtered to billable identities).

---

## 3. Non-goals

- Renaming DB/API metric key `protected_users` or setting key `protected_user_price_cad`.
- Changing OneDrive included GiB / overage pricing.
- Matching Comet `TotalAccountsCount` byte-for-byte when Comet and platform selections differ.
- Billing SharePoint/OneDrive/Teams **sites** as billable units (sites are workload storage under unlimited MS365 storage).
- Billing devices, service principals, or non-user directory objects.
- Grandfathering (counts apply on next daily meter unless leadership overrides).

---

## 4. Locked decisions

| Topic | Decision |
|-------|----------|
| Customer label | **Protected Users** (revert from “Protected Objects”) |
| Counting model | Personal user selection + Team/Group/Site member expansion + **explicit shared-mailbox selection exception** |
| Guests | **Bill** (personal or membership) — they can write org data |
| Backup vs billing | **Select All includes all resources for backup** (users, mailboxes, sites, teams, groups, etc.). Billing is a separate filter — backing up a resource does not imply it is billed |
| Shared / room / equipment mailboxes (default) | **Do not bill** when only included via select-all (`billing_exempt`), membership, or inventory discovery |
| Shared mailboxes (exception) | **Bill** when **manually / explicitly personally selected** in the job (not via select-all) — Comet-style protected account; see §6 |
| Room / equipment mailboxes | **Never bill** (even if manually personally selected) |
| User + same-person mailbox / OneDrive | **Bill once** (Azure ID dedupe) |
| Teams / M365 Groups | **Expand members** when any scope enabled |
| SharePoint sites | **Expand permission principals** when any site scope enabled; sites themselves are not billable units |
| Deduplication | One Azure object ID → one Protected User per backup user |
| Internal keys | Keep `protected_users` / `protected_user_price_cad` |
| Go-live | Next `ms365_billing.php` meter run; no grandfathering |

---

## 5. Definition — Protected User

A **Protected User** is one distinct Microsoft 365 directory identity identified by **Azure object ID**, counted at most **once** per backup user for the billing window, when it is reached by any of:

1. **Personal user selection** — `user` or `user_onedrive` with at least one enabled personal scope (mail, calendar, contacts, tasks, OneDrive/files).
2. **Team / Group membership** — billable member of a selected Team, team channel (inherits parent team), or M365 Group with at least one enabled shared scope.
3. **SharePoint site membership** — billable user principal on a selected SharePoint site with at least one enabled site scope.
4. **Explicit shared-mailbox selection (exception)** — `TYPE_MAILBOX` resource that is a **shared** mailbox (not room/equipment), is **personally selected** with at least one enabled mailbox scope, and is **not** tagged `billing_exempt` (select-all mailboxes are exempt; manually added shared mailboxes are not).

**Included:** Member users, guest users (`userType = Guest` or `#EXT#` in UPN), and manually selected shared mailboxes (§6).

**Excluded:** Room mailboxes, equipment mailboxes, devices, service principals, and shared mailboxes that are only present via select-all (`billing_exempt`) or membership alone.

**Storage note (unchanged):** Shared workload data (Teams, SharePoint, group files, etc.) remains unlimited storage. Billing is per identity in scope, not per site or per byte (except OneDrive overage).

---

## 6. Spec exception — Comet-style shared mailboxes in the protected set

### 6.1 Problem

Comet’s Protected Accounts list can include shared mailboxes the operator **checked as users** (e.g. philmgbuild: `receipts`, `referrals`). Those are part of the **10** billable accounts. A blanket “never bill mailboxes” rule would under-count vs Comet for those cases.

### 6.2 Rule (EXCEPTION-SM)

| ID | Rule |
|----|------|
| EXCEPTION-SM-01 | A `TYPE_MAILBOX` identity **bills** if and only if it is **personally selected** in `selected_resource_ids` with ≥1 enabled mailbox scope, classified as a **shared mailbox** (not room/equipment), **and not** marked `billing_exempt`. |
| EXCEPTION-SM-02 | Shared mailboxes included only via **Select All** are tagged `billing_exempt`: they are **backed up** but **do not bill**. Membership-only shared mailboxes (Team/Group/SharePoint) also do not bill. |
| EXCEPTION-SM-03 | Room and equipment mailboxes **never** bill, even if manually personally selected (and even if somehow not `billing_exempt`). |
| EXCEPTION-SM-04 | The shared mailbox’s Azure object ID participates in the same dedupe set as users — selecting the mailbox and a user with the same ID still counts **once**. |
| EXCEPTION-SM-05 | OneDrive overage does **not** apply to shared mailboxes unless OneDrive is personally selected for a user identity (shared mailboxes typically contribute 0 overage). |

### 6.3 Select All vs manual mailbox selection (locked)

**Principle:** Select All means “back up everything in inventory.” It does **not** mean “bill for everything.”

| Action | Backup | Billing |
|--------|--------|---------|
| **Select All** | Includes **all** resources: users, OneDrive, mailboxes, SharePoint sites, teams, groups, etc. | Mailboxes added by Select All are tagged **`billing_exempt`** → shared/room/equipment mailboxes **do not** count. Users/guests still bill via personal + membership rules. |
| **Manually add a shared mailbox** later (or instead of relying on select-all billing) | Backed up | **Bills once** (EXCEPTION-SM-01) |
| **Manually add a room/equipment mailbox** | Backed up | **Never bills** |

**Implementation (locked alternative):**

1. `CustomerSelectionCodec::selectAllFromInventory()` **continues to select** `TYPE_MAILBOX` resources for backup (all scopes as today).
2. Select-all records those mailbox IDs in a durable job flag set (e.g. `billing_exempt_resource_ids` in `schedule_json` / MS365 job config, or per-resource `billing_exempt: true` in scope metadata).
3. `ProtectedUserResolver` / EXCEPTION-SM skips any mailbox whose ID is in the exempt set.
4. When the customer **manually toggles on** a shared mailbox that was not select-all-exempt (or clears exempt after an individual add), that mailbox becomes billable under EXCEPTION-SM-01.
5. If a mailbox was first included via Select All (`billing_exempt`) and the customer later wants Comet-style billing for it (receipts/referrals), the UI must support marking it as an explicit billable selection (clear `billing_exempt` for that ID) without removing it from backup.

**Explicit (billable) shared mailbox:** Personally selected, shared classification, ≥1 mailbox scope enabled, **and** not `billing_exempt`.

### 6.4 Classification

| Signal | Treatment |
|--------|-----------|
| `resource_type = user` | Billable user path (§5.1–3) |
| `resource_type = mailbox` + shared + not `billing_exempt` | Bill (EXCEPTION-SM-01) |
| `resource_type = mailbox` + shared + `billing_exempt` (select-all) | Backup only — do not bill |
| `resource_type = mailbox` + room/equipment | Never bill |
| `userType = Guest` / `#EXT#` | Bill as user (membership or personal) |
| Inventory heuristic `TenantResource::classifyGraphUser()` → mailbox | Treat as mailbox; apply EXCEPTION-SM |

### 6.5 Worked examples

| Selection | Backup | Protected Users |
|-----------|--------|-----------------|
| 8 users + shared mailboxes `receipts` + `referrals` **manually** selected | All of the above | **10** (Comet philmgbuild parity) |
| Select All including 109 mailboxes (all `billing_exempt`) + 10 users | Everything selected | **10 users only** (mailboxes backed up, not billed) |
| Select All, then customer clears exempt / marks `receipts` + `referrals` billable | Unchanged backup set | **12** if both were previously exempt among the 109 |
| Shared mailbox only as SharePoint permission principal | Site data | **0** from that mailbox |
| Room mailbox manually selected | Backed up | **0** |

---

## 7. Billable paths (decision table)

| ID | Path | Bills whom |
|----|------|------------|
| B-01 | Personal `user` / `user_onedrive` with scope | That user’s Azure ID |
| B-02 | Personal `mailbox` (shared) with scope, not `billing_exempt` | That mailbox Azure ID (EXCEPTION-SM) |
| B-02b | Personal `mailbox` (shared) with scope + `billing_exempt` (select-all) | Backup only — do not bill |
| B-03 | Personal `mailbox` (room/equipment) | Never (backup allowed) |
| B-04 | Team / channel with shared scope | Billable members (users + guests); not mailbox-only principals unless they qualify as users |
| B-05 | M365 Group with mail/calendar/files scope | Billable members (users + guests) |
| B-06 | SharePoint site with files/lists scope | Billable permission principals (users + guests) |

---

## 8. Membership expansion

| ID | Resource | Expand? | Notes |
|----|----------|---------|-------|
| M-01 | Team | Yes | `teams/{id}/members` |
| M-02 | Team channel | Yes via parent | Inherit team roster |
| M-03 | M365 Group | Yes | Group mail/files are member-written data |
| M-04 | SharePoint site | Yes | Permission principals; sites are not billable units |
| M-05 | Planner / OneNote / directory baseline | No | Not membership sources |

**Why keep Groups:** Users and guests can store data in group mail and group files (SharePoint-backed). Excluding group expansion would under-bill guests/members who only write via groups.

---

## 9. Deduplication

| Scenario | Count |
|----------|------:|
| User + same user on Team + Site | 1 |
| User + `user_onedrive` | 1 |
| Explicit shared mailbox + same ID elsewhere | 1 |
| Dual Azure users (same person, two UPNs/tenants aliases) | **2** (two Azure IDs — see indigoblue) |

**Dedupe key:** Azure object ID only.

---

## 10. Evidence from tenant attribution (2026-07-27)

### 10.1 philmgbuild (backup_user_id=19, service 4955)

| Metric | Value |
|--------|------:|
| Comet Protected Accounts | 10 |
| Platform Objects | 115 |
| Proposed Protected Users (exclude all mailboxes) | 10 |
| Only via personal user | 10 |
| Only via personal mailbox | 92 |
| Only via membership (net) | 0 |
| Pre–2026-07-21 snapshots | 9 |

**Driver of +105:** Mailbox-type identities under Objects billing. Sites/teams/groups did not add net unique billable IDs. Comet backs 184 SP/OD sites under 10 accounts without per-site billing.

### 10.2 sa_eazybackup (backup_user_id=18, service 4956)

| Metric | Value |
|--------|------:|
| Platform Objects (live) | 447 |
| WHMCS MS365 qty | 430 |
| Proposed (exclude all mailboxes) | 275 |
| Inventory users / mailboxes | 275 / 172 |
| Pre–2026-07-21 snapshots | 274–275 |
| Operator Comet figure | ~317 |

**Driver of Objects jump:** +172 mailboxes at Objects go-live. Proposed model returns to ~275 (may sit **below** Comet ~317; further gap analysis optional).

### 10.3 indigoblue (backup_user_id=21, service 4958)

| Metric | Value |
|--------|------:|
| Platform Objects (live + WHMCS MS365 qty) | **17** |
| Legacy Comet WHMCS product (service 4828) qty | **23** |
| Operator stated (eazy / Comet) | **22 / 19** |
| Proposed Protected Users | **17** (no mailbox selections in job) |
| Only via personal user | 17 |
| Only via mailbox / membership | 0 / 0 |
| Selected resources | 34 (17 users + 17 OneDrives); **0** teams/groups/sites selected |

**Selected users (17 Azure IDs / 12 people):** Adrian Strickland, Aniko Budai, Harry Singh×2, Julie Nguyen×2, Kashra Charles, Marcus Cruz, Megan Foss, Narender Chakkala, Nimish Padia, Sanjay Ramwani×2, Sarah Wong×2, Yogi Narayan×2.

**Driver of platform count:** Dual `indigoblue.ca` + `wealthytoday.ca` Azure user objects for the same people (5 dual accounts → +5 vs unique people). **No** Objects-style mailbox inflation on this job (mailboxes not selected; membership not selected).

**Note on 22 vs 17:** Dev live meter, daily snapshots (since ≥ Jul 13), and MS365 service config option all show **17**. The operator’s **22** may refer to a different surface (peak elsewhere, Comet portal, or another environment). Legacy Comet product qty on the same client is **23**. Reconcile operator 22/19 against Comet UI / portal if needed; platform MS365 path is consistent at **17**.

---

## 11. UI / copy / WHMCS

| Surface | Label |
|---------|-------|
| Wizard, Usage drawer, pricing panel | **Protected Users** |
| Admin Jobs / Users columns | **Protected Users** |
| WHMCS config option display | **Protected Users** (in-place rename from Objects) |
| Setting `protected_user_price_cad` FriendlyName | **Protected User price (CAD)** |
| API JSON | `protected_users` (unchanged) |
| Wizard reconciliation panel | Direct users + membership − duplicates = Protected Users; footnote: *Select All backs up everything; shared mailboxes bill only when manually selected (not select-all exempt); room/equipment mailboxes never bill; SharePoint/Teams sites are not billable units.* |

---

## 12. Implementation sketch

1. **`ProtectedUserResolver::isBillableMember()`** — Exclude room/equipment; allow guests; allow shared mailbox **only** on personal-mailbox path + EXCEPTION-SM (not `billing_exempt`).
2. **`resolve()` personal path** — `TYPE_USER` / `TYPE_USER_ONEDRIVE` bill as today (filtered); `TYPE_MAILBOX` bills only under EXCEPTION-SM; room/equipment skip; honor `billing_exempt_resource_ids` / per-resource flag.
3. **Membership paths** — Filter through billable rules; shared mailboxes in member lists do **not** bill via membership alone.
4. **`selectAllFromInventory()`** — **Continue selecting all mailboxes for backup**; tag every mailbox ID added by select-all as **`billing_exempt`** in job config. Manually added shared mailboxes are not exempt and bill once.
5. **Wizard UX** — Allow clearing `billing_exempt` on a select-all mailbox (or “count as protected user”) so Comet-style shared accounts can bill without removing backup.
6. **Tests** — Manual receipts + referrals → bill; select-all with 100 mailboxes → backed up, users-only bill; clear exempt on two mailboxes → +2; guest via team → bill; room mailbox → 0.
7. **Docs / copy** — Guide, design §2.1, Jobs/Users labels, product bootstrap rename.
8. **Verify** — Re-run `bin/analyze_billing_model.php` on philmgbuild, sa_eazybackup, indigoblue.

---

## 13. Rollout

1. Ship resolver + select-all `billing_exempt` tagging + tests on development WHMCS.
2. Deploy copy/docs with the same change.
3. Next meter run applies new quantities (expect large drops where mailbox inventory was billed).
4. Spot-check philmgbuild (~10), indigoblue (~17), sa_eazybackup (~275 + any explicit shared mailboxes).
5. Update `PROGRESS.md` and design/guide docs.

---

## 14. Open items (non-blocking)

| Item | Notes |
|------|-------|
| Operator indigoblue 22 vs platform 17 | Confirm Comet UI TotalAccountsCount and which WHMCS product surface showed 22 |
| sa_eazybackup 275 vs Comet ~317 | Optional follow-up: which Comet identities are missing under proposed rules |
| Person-named `TYPE_MAILBOX` misclassification | Inventory may classify some people as mailboxes; audit `classifyGraphUser` if under-billing appears |

---

## 15. Approval checklist

- [ ] Protected Users label restore
- [ ] Guests bill; room/equipment never bill
- [ ] Shared mailboxes bill **only** when manually personally selected and not `billing_exempt` (EXCEPTION-SM)
- [ ] Select All still selects **all** resources for backup, including mailboxes
- [ ] Select-all mailboxes are tagged `billing_exempt` (backed up, not billed)
- [ ] Manually added shared mailboxes bill once; room/equipment never bill
- [ ] Keep Team / Group / SharePoint membership expansion (users + guests)
- [ ] Azure ID dedupe unchanged
- [ ] Peak metering / OneDrive overage unchanged
- [ ] No grandfathering on next meter run
