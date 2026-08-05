# UTC Revoke Date Comparison — Design Addendum

Date: 2026-08-05  
Status: Implemented

## Problem

`comet_devices.revoked_at` is stamped with MySQL `NOW()` in session/system TZ (America/Toronto on WHMCS). Comet portal `usage_date` values are UTC calendar dates. Using local `dateOnly(revoked_at)` made evening revokes look one day early vs Comet, producing false ~$0.20 daily-booster confirmed overages.

## Fix

- Add `BillingPeriodCalculator::utcDateOnly()` — interpret datetime as DB-local wall time, return UTC `Y-m-d`; date-only inputs pass through.
- Use `utcDateOnly()` in `LifecycleResolver` and `DeviceMatcher` for all revoke/remove calendar dates.
- Keep comparison `usage_date > expected_end` (revoke UTC day remains billable).

## Out of scope

- Writing `UTC_TIMESTAMP()` in the WS worker (future improvement).
- Backfilling historical `revoked_at` rows.
