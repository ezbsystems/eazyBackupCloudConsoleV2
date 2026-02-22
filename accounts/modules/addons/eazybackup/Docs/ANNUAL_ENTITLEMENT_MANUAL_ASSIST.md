# Annual Entitlement Manual-Assist Mode

This document describes the manual-assist mode for annual entitlement tracking in the eazyBackup addon. It applies only when `AnnualEntitlementConfig::mode()` returns `manual`.

## Manual-Only Behavior

The system is explicitly manual-assist; it does not perform any of the following automatically:

- **No automatic config qty changes** - `tblhostingconfigoptions.qty` is never modified by this feature
- **No automatic invoice generation** - No WHMCS invoices, line items, or gateway charges are created
- **Display and audit only** - The panel shows usage vs entitlement for admin review; all billing actions are performed manually in WHMCS

## Cycle Reset Rule

Entitlement cycles are derived from the **service renewal boundary** (next due date):

- **cycle_end** = service `nextduedate` (Y-m-d)
- **cycle_start** = day after (cycle_end minus one year)

Example: `nextduedate = 2026-12-15` -> cycle_end=`2026-12-15`, cycle_start=`2025-12-16`.

When the service renews and `nextduedate` advances, a **new cycle** is used. New ledger rows for that cycle are created on the **first admin view** of the service in that cycle (during snapshot refresh); `max_paid_qty` is seeded from `config_qty` when no row exists (see Rollout Checklist).

Ledger rows are keyed by `(service_id, config_id, cycle_start)`. Each renewal boundary produces a distinct cycle.

## Admin Workflow

1. **Review annual entitlement panel**
   - Navigate: Clients -> Manage Clients -> [select client] -> Products/Services (clientsservices page) -> select an annual service
   - Panel appears below the main service form (only for billing cycles `annually` or `yearly`)
   - Columns: Config, Usage, Config qty, Max paid, Status, Suggested delta, Action

2. **Perform manual invoice/adjustments in WHMCS**
   - Create invoices, add configurable option line items, or apply credits as needed via standard WHMCS flows
   - This feature does not create or modify invoices

3. **Manually update max paid via "Mark paid" action**
   - The button suggests current usage as the new value (pre-filled from Usage column)
   - Backend enforces increases only: `new_max_paid_qty` must be >= current `max_paid_qty` (no decreases)
   - Optional note is prompted and stored in `eb_annual_entitlement_events`
   - Page reloads after success
   - Audit: each "Mark paid" inserts a row in `eb_annual_entitlement_events` with `event_type=manual_mark_paid`

## Future Automation (Not Implemented)

The following are **future work only**; they are not implemented in manual-assist mode:

- **Automatic config qty updates** - Cron or hook to sync `tblhostingconfigoptions.qty` from usage
- **Automatic invoice generation** - Cron or hook to create/update invoices for usage above entitlement
- **Automatic cycle transition** - Cron to create new ledger rows when renewal date advances
- **Proration calculation** - Automated prorate logic for mid-cycle changes (logic exists in `AnnualEntitlementSnapshotService` but is advisory only)

Do not assume any of the above behavior in current releases.

## Rollout Checklist for Existing Annual Services

When enabling this feature for the first time on an existing installation:

1. **Deploy** - Activate addon, run schema migration; tables `eb_annual_entitlement_ledger` and `eb_annual_entitlement_events` must exist.

2. **Seed max_paid from current config qty** - For each annual service in the current cycle, ensure a ledger row exists with `max_paid_qty` = current config option quantity.

   **Option A (manual):** Open each annual service on the clientsservices page. The panel snapshot refresh creates ledger rows on first view with `max_paid_qty` = `config_qty` when no row exists.

   **Option B (scripted):** Run a one-time backfill that inserts rows into `eb_annual_entitlement_ledger` for all annual services, per tracked config ID, with `max_paid_qty` = `tblhostingconfigoptions.qty`, `cycle_start`/`cycle_end` from `AnnualCycleWindow::fromNextDueDate(tblhosting.nextduedate)`.

3. **Verify** - Spot-check a few services; panel should show Usage, Config qty, Max paid, and correct Status/Suggested delta.

## Tracked Config IDs

Billable config IDs (from `AnnualEntitlementConfig::billableConfigIds()`): 67, 88, 89, 91, 60, 97, 99, 102 (Cloud Storage, Endpoints cid=88, Endpoints alt cid=89, Disk Image, M365, Hyper-V, VMware, Proxmox).

Usage is computed from Comet tables (`comet_devices`, `comet_items`, `comet_vaults`) per config ID.

## Files Involved

- Hooks: `accounts/includes/hooks/annualEntitlement_ClientServices.php`, `accounts/includes/hooks/annualEntitlement_ajax.php`
- Lib: `lib/Billing/AnnualEntitlementConfig.php`, `AnnualCycleWindow.php`, `AnnualEntitlementSnapshotService.php`, `AnnualEntitlementDecision.php`
- Schema: `eb_annual_entitlement_ledger`, `eb_annual_entitlement_events` (created by `eazybackup_migrate_schema()`)
