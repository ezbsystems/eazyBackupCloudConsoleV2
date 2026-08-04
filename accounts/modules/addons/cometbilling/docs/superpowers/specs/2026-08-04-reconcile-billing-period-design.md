# Reconcile billing period model (devices vs boosters)

**Date:** 2026-08-04  
**Status:** Approved for planning  
**Scope:** Comet Billing addon reconcile — portal-only billing status and overbill dollars  
**Approach:** Fix status rules in `DeviceMatcher` (Approach 1)

## Problem

Reconcile currently sets device expected billing end as `revoked_at + billing_cycle_days`. That is incorrect.

Comet bills **devices** on a fixed interval from **registration**, not from revoke. Comet bills **boosters** daily; the remove day is the last billable day.

Example of bad output today:

| Revoked at | Cycle | Next due | Expected end (wrong) |
|------------|-------|----------|----------------------|
| 2026-06-27 | 30 | 2026-08-06 | 2026-07-27 (`revoked + 30`) |

## Goals

1. Device expected end = end of the registration-aligned period that contained revoke.
2. Booster last billable day = remove day; still on portal after that day = overbilled.
3. Prefer `RegistrationTime` when present; fall back to walking periods from portal `next_due`.
4. Booster remove date: host `revoked_at`, plus inventory disappearance when snapshot history allows.
5. Keep existing UI filters, past-grace overbill totals, and CSV fields (`billing_status`, `overbill_amount`).

## Non-goals (this phase)

- Pro-rated booster dollar math (e.g. `$5/30 × days`)
- Rebuilding overbill from `cb_credit_usage` history
- Changing aggregate count-variance logic (server vs portal counts)
- Retroactively rewriting saved reconciliation reports

## Device rules

### Expected billing end

Given `revoked_at` (date), `cycleDays` (usually 30), and optional registration:

1. **Prefer registration:** If `comet_devices.content.RegistrationTime` is a positive unix timestamp, convert to UTC date `regDate`. Period starts are `regDate + n×cycleDays` for integer `n ≥ 0`. Find the period where `start ≤ revokedDate < end` with `end = start + cycleDays`. Expected end = that `end`.
2. **Fallback next_due:** If registration is missing/zero but portal `next_due_date` and `cycleDays` exist, walk periods backward from `next_due` by `cycleDays` until a period contains `revokedDate`. Expected end = that period’s end.
3. **Unknown:** If neither path works → `billing_status = unknown`, `expected_billing_end = null`, `overbill_amount = 0`.

### Status

Compare portal `next_due_date` (date only) to `expected_billing_end`:

| Condition | `billing_status` | `overbill_amount` |
|-----------|------------------|-------------------|
| `next_due ≤ expected_end` | `expected_grace` | `0` |
| `next_due > expected_end` | `overbilled_past_grace` | portal line `amount` |
| no expected end | `unknown` | `0` |

### Worked example

- Registered `2026-07-06`, cycle 30, revoked `2026-08-01` → period `2026-07-06` … `2026-08-05` → expected end `2026-08-05`.
- If portal `next_due` is still within that end → expected; if portal advances to a later cycle → overbilled.

## Booster rules

Categories: Hyper-V, VMware, Proxmox, Disk Image, MSSQL, M365.

### Last billable day (remove date)

First match wins:

1. Host device `revoked_at` calendar date (device fully revoked).
2. Else, if `cb_server_device_inventory` has consecutive snapshots for that host: last `snapshot_date` with booster qty &gt; 0 followed by a later snapshot with qty = 0 (or host absent) → that transition’s earlier date is last billable.
3. Else → `unknown`.

### Status

| Condition | `billing_status` | `overbill_amount` |
|-----------|------------------|-------------------|
| remove date known and portal snapshot day &gt; remove date (still portal-only) | `overbilled_past_grace` | portal line `amount` |
| remove date known and snapshot day ≤ remove date | `expected_grace` | `0` |
| no remove date | `unknown` | `0` |

For boosters, `expected_billing_end` = remove date (last billable day). Treat cycle as daily in UI (`daily` / cycle days `1`).

## Components

| Unit | Responsibility |
|------|----------------|
| `DeviceMatcher::enrichPortalOnlyRows` | Branch device vs booster; set expected end, status, overbill |
| Period helper (new private methods) | Registration-aligned period containing revoke; next_due walk-back |
| Revoked device index | Also load `RegistrationTime` from `content` (and optionally `created_at` only as display, not period source) |
| Inventory lookback (optional helper) | Booster qty disappearance across `cb_server_device_inventory` |
| UI partial | Show registered_at when known; daily for boosters; keep Overbill $ |
| README | Document correct Comet billing model |

## Data flow

```
portal-only row
  → match revoked device (hash/prefix)
  → if devices category: compute period end (reg → else next_due walk)
  → if booster category: remove date (revoked_at → else inventory drop)
  → set billing_status + overbill_amount
  → Reconciler sums past_grace_overbill; UI/CSV consume fields
```

## Error / edge handling

- `RegistrationTime = 0` or missing → next_due walk-back; if that fails → `unknown`.
- Ambiguous device prefix matches → keep existing account/username disambiguation; if unresolved → `unknown`.
- Inventory lookback unavailable (no prior snapshots) → fall back to revoked_at only; if neither → `unknown`.
- Do not invent registration from `comet_devices.created_at` (DB insert time is not Comet registration).

## Testing / verification

1. Re-run Reconcile after change.
2. Device with known `RegistrationTime` + mid-cycle revoke: expected end = `reg + n×30` containing revoke (not `revoked + 30`).
3. KaizaCorp-style row: expected end from registration/next_due walk; status vs portal `next_due` consistent with new rule.
4. Booster portal-only with host `revoked_at`: expected end = revoke date; overbilled if snapshot after that day.
5. Category `past_grace_overbill` and CSV still include only `overbilled_past_grace` rows.
6. Saved old reports unchanged until re-run.

## UI / CSV

- Keep filter “Overbilled past grace”, past-grace overbill column, summary total, CSV `overbill_amount`.
- Portal-only columns: add/show registration date when known; boosters show remove date as expected end; cycle = `30` or `daily`.
- Re-run Reconcile required for fresh statuses.
