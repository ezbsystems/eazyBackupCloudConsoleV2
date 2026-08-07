# Historical Reconcile Async + Performance Design

**Date:** 2026-08-07  
**Status:** Approved for implementation

## Problem

Historical Reconcile runs `HistoricalReconciler::report()` synchronously in the admin HTTP request. At ~19k charges (90d) or ~192k (all history), nginx returns **504** before PHP completes.

## Solution

1. **Background CLI** — `bin/historical_reconcile.php` via `cometbilling_spawnCli`, job status in `cb_settings` (same pattern as portal_pull).
2. **Persist + load** — CLI always persists to `cb_audit_runs` / `cb_audit_findings`; admin page loads from DB only (no live `report()` in HTTP).
3. **Query speedups** — skip orphan `observedDailyCadence` when no AS snapshot within ±48h; prefetch reversals; keyset pagination; bulk insert findings.

## UX

- Page opens instantly (form + last saved run for selected range).
- "Run audit" spawns background job; running badge + 15s auto-refresh.
- Exports/dispute packs require `audit_run_id` (no live rescan).

## Out of scope

- ms365 job queue; auto-cron; DisputePack claim content changes.
