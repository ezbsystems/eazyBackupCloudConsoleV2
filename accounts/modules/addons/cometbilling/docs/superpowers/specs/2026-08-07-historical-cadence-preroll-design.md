# Historical Reconcile Cadence Pre-Roll Design

**Date:** 2026-08-07  
**Status:** Approved for implementation

## Problem

Historical Reconcile false-positives period-end Bill History charges when the nearest Active Services snapshot was taken **after** portal `next_due` rolled forward.

Example: `dr_jacqueline_korol`, revoked device `69da4c…`, usage date `2026-08-05`.

- Aug 4 AS snapshot: `next_due = 2026-08-05` → expected end `2026-08-05` → charge is **within period**.
- Aug 5 AS snapshot: `next_due = 2026-09-04` (post-roll) → expected end `2026-08-04` → charge falsely **after expected end**.

Device IDs were matched correctly; Bill History attributed charges to the correct revoked vs replacement device hashes.

## Policy

**Option A:** Fix cadence/expected-end only. Still confirm true post-period residual charges on a revoked device ID even when a replacement device exists on the same tenant.

## Solution

### 1. Pre-roll cadence anchor (`BillingCadenceResolver`)

When resolving portal cadence for a Bill History charge:

1. Collect Active Services snapshots within ±48h of `usage_date`.
2. For each snapshot, find a category-matched AS row for the device.
3. Prefer anchors where `next_due_date >= usage_date` (pre-roll / not yet advanced).
4. Among pre-roll anchors, pick the snapshot closest to `usage_date`.
5. If no pre-roll anchor exists, fall back to nearest snapshot (post-roll) so future residual overbilling still confirms.

### 2. Category-aware AS row selection

Avoid binding a Device BH charge to an MSSQL booster AS line (same device prefix, higher row id).

Match by `extra.Type` when present; otherwise service_name patterns aligned with `ChargeCategoryResolver`.

### 3. Active Services ingest enrichment

`ActiveServicesNormalizer` extracts `tenant_id` and `device_id` from `service_name` when portal CSV columns are empty. Improves future joins; existing rows still match via `service_name`.

## Out of scope

- Device-replacement sibling suppress/downgrade.
- Snapshot Reconcile (`DeviceMatcher`) behavior changes.
- Backfilling historical `cb_active_services` identity columns.

## Verification

- Unit tests modeled on Korol case (pre-roll within period; post-only still confirms; category match).
- Prod spot-check: `69da4c` Aug 5 charges → `not_overbilled`; later residual on old ID still confirmable.
