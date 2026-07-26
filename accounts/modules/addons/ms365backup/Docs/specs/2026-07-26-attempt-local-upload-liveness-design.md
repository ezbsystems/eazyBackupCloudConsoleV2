# Attempt-local upload liveness

**Date:** 2026-07-26  
**Affected batch:** `4efc3484-ec99-453b-9fa1-41c480a4dcca`  
**Scope:** MS365 PHP control-plane progress handling

## Problem

Infrastructure requeues preserve `items_done`, `bytes_hashed`, and
`bytes_uploaded` so customer-visible progress does not move backward. A retried
worker starts its attempt-local counters below those preserved high-water
values. `Ms365RestoreWorkerHooks::backupProgress()` currently refreshes
`last_progress_at` only when the merged high-water value increases, so active
rehashing can appear silent until it catches the prior attempt.

Production session `b86288` proved the mismatch. Child `b43728c3` reported
attempt-local hash growth from approximately 90.7 GB to 99.2 GB while the
persisted value remained approximately 111.8 GB and `last_progress_at` remained
fixed. The batch owner and child lease stayed healthy, and the worker continued
posting every few seconds.

## Selected design

Keep the existing monotonic customer-visible counters. Add an internal
attempt-local progress snapshot to `stats_json`, keyed by the run's
`started_at` value and incoming worker phase:

- `attempt_progress_started_at`
- `attempt_progress_phase`
- `attempt_items_done`
- `attempt_bytes_hashed`
- `attempt_bytes_uploaded`

For each non-heartbeat progress post:

1. Decode the prior attempt-local snapshot.
2. If `started_at` or incoming phase changed, initialize the snapshot and treat
   a nonzero payload as current-attempt activity.
3. Otherwise, refresh `last_progress_at` only when at least one attempt-local
   counter increases.
4. Replace the attempt-local snapshot with the latest raw worker counters.
5. Continue merging the public run counters with `max()` exactly as today.

No schema migration is required.

## Safety properties

- Identical upload heartbeats do not refresh liveness, so a genuinely wedged
  upload remains reaper-visible.
- Retry-local progress refreshes liveness before it exceeds historical
  high-water counters.
- Public progress never regresses.
- A promotion changes `started_at`, automatically separating attempts.
- A Graph-to-upload phase transition resets the phase-local comparison without
  erasing aggregate progress.
- Existing worker and API payloads remain compatible.

## Verification

Add a regression to `ms365_batch_progress_liveness_test.php`:

1. Create a running upload child with preserved counters above the retry's raw
   counters and an old `last_progress_at`.
2. Post one lower retry-local progress sample to establish the snapshot.
3. Age `last_progress_at`, then post a larger retry-local sample that remains
   below the preserved high-water values.
4. Assert public counters remain unchanged and `last_progress_at` becomes fresh.
5. Post an identical sample after aging liveness again and assert it does not
   refresh.

Run the focused liveness and child-abort tests, deploy through
`bin/deploy-production.sh`, and retain session `b86288` instrumentation for a
post-fix production run. Success requires log evidence that retry-local growth
refreshes liveness while identical samples do not, with no new soft abort for
the affected batch.
