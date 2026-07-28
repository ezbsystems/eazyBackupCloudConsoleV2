# Protected Users Billing Revision Plan

> **For agentic workers:** Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans`. Steps use checkbox syntax below.

**Goal:** Revert MS365 billing from Protected Objects back to **Protected Users** per [2026-07-27-protected-users-billing-revision.md](../specs/2026-07-27-protected-users-billing-revision.md): guests + membership expansion stay; shared mailboxes bill only when manually selected (EXCEPTION-SM); select-all mailboxes are backed up but `billing_exempt`; room/equipment treated as shared this ship (no Places API).

**Architecture:** Keep `ProtectedUserResolver` / metric key `protected_users`. Thread durable `billing_exempt_resource_ids` through select-all → job save → meter/preview. Narrow personal/membership billable rules. Rename customer-facing copy Objects → Users. No grandfathering on next daily meter.

**Tech stack:** PHP 8.2 (`Ms365Backup\`), WHMCS, existing PHP test harness, cloudstorage wizard (Alpine/Smarty).

**Environment:** Development WHMCS `192.168.92.79` only for this ship.

## Locked decisions

- **Room/equipment (this ship):** No `Place.Read.All` / Places enrichment. All `TYPE_MAILBOX` treated as **shared** for EXCEPTION-SM. Membership never bills mailboxes. Manually selected non-exempt mailboxes bill (including room/equipment if someone picks them — accepted gap; Places fast-follow later).
- **Exempt storage:** `billing_exempt_resource_ids: list<string>` in both `schedule_json` and `source_config_enc` (same pattern as `selected_resource_ids`).
- **Legacy jobs (no key):** If `billing_exempt_resource_ids` is **absent**, treat **all currently selected `TYPE_MAILBOX` IDs as exempt** so Objects-era select-all jobs drop immediately. Once the key is present (including `[]`), honor it strictly.
- **Multi-job merge:** A mailbox bills if **any** job has it personally selected with ≥1 mailbox scope **and** not exempt on that job.
- **Select All:** Still selects all resources for backup; every mailbox ID from select-all is added to `billing_exempt_resource_ids`.
- **Wizard:** Support clearing exempt on a mailbox (“Count as Protected User”) without removing backup.
- **Internal keys unchanged:** `protected_users`, `protected_user_price_cad`. Reconciliation display key renamed to `protected_users` in same change set.
- **No git commit** unless the user asks in the implementing session.

## File map

| File | Change |
|------|--------|
| `ProtectedUserResolver.php` | EXCEPTION-SM personal mailbox path; membership excludes mailboxes; accept `$billingExemptIds` |
| `Ms365UsageMeter.php` | Pass exempt set; merge per-job exempt correctly |
| `CustomerSelectionCodec.php` | `selectAllFromInventory` returns `billing_exempt_resource_ids`; preview helpers accept exempt |
| `Ms365CustomerJobService.php` | Persist/load exempt in schedule_json + source_config |
| `Ms365E3Controller.php` + plan/billing APIs | Wire select-all exempt into preview/plan/save |
| Wizard JS/TPL | Track exempt set; clear-exempt UX; Protected Users copy + recon footnote |
| Bootstrap/config | Objects → Users in-place rename |
| `jobs.js` | Column “Objects” → “Protected Users” |
| Tests, analyze script, docs | EXCEPTION-SM cases; PROGRESS; spec status |

---

### Task 1 — Failing tests for Protected Users + EXCEPTION-SM

- [x] Flip room mailbox personal case (option 2: without exempt bills; with exempt does not)
- [x] Guest via team still bills; guest personal still bills
- [x] Shared mailbox personal + not exempt → bills; + exempt → does not
- [x] Select-all-style: many exempt mailboxes + N users → count = N
- [x] Membership list containing mailbox Azure ID → mailbox not added via membership
- [x] Clear exempt on two mailboxes → +2
- [x] Legacy: omit exempt arg / key absent → all selected mailboxes treated exempt
- [x] SharePoint/team guest membership + cross-source dedupe remain green
- [x] Run test; confirm green after implementation

### Task 2 — Resolver + meter + select-all tagging

- [x] Extend `resolve(..., array $billingExemptIds, bool $billingExemptKeyPresent)`
- [x] Personal `TYPE_MAILBOX`: bill iff not exempt (legacy: key absent = all mailboxes exempt)
- [x] `selectAllFromInventory()`: return `billing_exempt_resource_ids`
- [x] `measureSelection` / `previewBillingForSelection` / `mergeJobSelections`: pass/merge exempt
- [x] Reconciliation key → `protected_users`
- [x] Re-run resolver tests → green

### Task 3 — Persist exempt through job save/load/preview APIs

- [x] Persist `billing_exempt_resource_ids` in schedule_json + source_config_enc
- [x] Load on job detail for wizard hydrate
- [x] Select-all preview/plan paths pass codec exempt list into billing preview
- [x] Manual save with explicit mailbox selection and partial exempt

### Task 4 — Wizard UX (clear exempt) + customer copy

- [x] `billingExemptIds` in wizard state; select-all sets exempt; clear exempt UX
- [x] Billing dock + reconciliation footnote per spec §11
- [x] Rename Protected Objects → Protected Users / per user / month

### Task 5 — WHMCS bootstrap + admin labels

- [x] Reverse in-place rename: Protected Objects → Protected Users
- [x] Admin setting FriendlyName → “Protected User price (CAD)”
- [x] Admin Jobs column → “Protected Users”

### Task 6 — Docs + analyze script + PROGRESS

- [x] Document EXCEPTION-SM, select-all vs billing, legacy-absent rule, option-2 room gap
- [x] Update analyze script to EXCEPTION-SM model
- [x] Session log in PROGRESS.md; bump module version to 1.52.34

### Task 7 — Dev verification (192.168.92.79)

- [x] PHP tests: `php tests/ms365_protected_user_resolver_test.php`
- [ ] `bin/analyze_billing_model.php` for philmgbuild, indigoblue, sa_eazybackup (run on dev WHMCS)
- [ ] Wizard manual: Select All, clear exempt on two shared, confirm estimate
- [ ] Trigger or wait for next `ms365_billing.php` meter; confirm WHMCS qty drops

## Out of scope (this ship)

- Graph Places / `Place.Read.All` room-equipment classification (fast follow).
- Matching Comet totals byte-for-byte when selections differ.
- Grandfathering peaks.
- Renaming DB/API metric key `protected_users`.
