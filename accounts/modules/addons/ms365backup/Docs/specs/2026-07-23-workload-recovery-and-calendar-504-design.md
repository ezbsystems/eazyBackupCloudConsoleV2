# Workload Recovery and Calendar 504 Design

**Date:** 2026-07-23

## Goal

Stop healthy tenant-owner workloads from being falsely requeued as stale, make queued/retrying state truthful in the client UI, and let calendar backup fall through its existing recovery ladder when Microsoft Graph repeatedly returns 503/504.

## Runtime evidence

Production batch `bbf034af-…` provides all three failure modes:

1. The client projection initially contained 327 rows labeled `Recovering this workload`: 323 queued rows plus four running grouped rows whose event came from a queued child (`debug-062be2.log:5`). Later it still contained 246 recovery rows: 242 queued plus four running (`debug-062be2.log:5779`).
2. Live-owner children were selected by `reapStalledBatchChildren()` at 604–614 seconds with a fresh lease (`debug-062be2.log:311,601,1579,1717,4244,4489,4964,5186`). Child `daa7554b-…` was reaped twice, but the same worker subsequently completed its Graph phase successfully after 3,193 seconds with 14,255 items. The server reaper therefore made a false recovery decision while valid work continued.
3. Mailbox child `09ee3a8f-…` failed the same calendar at tier 2, `$top=1000`, with Graph 504 at 13:42 and again at 18:23 UTC. Each request had already passed through the Graph client's five bounded 503/504 retries. The calendar scanner returned immediately on the final error instead of trying its smaller page sizes or tier-3 partition fallback. The queue row remained retryable but `attempts=0/5`.

The current worker owner remained healthy throughout: fresh batch heartbeat and lease, current worker version, active service, and no disk pressure.

## Design

### 1. Calendar service-unavailable recovery

Expose a narrow Graph error classifier for exhausted 503/504 responses.

- Tier 1 incremental scan: on exhausted 503/504, log a warning and continue to tier 2.
- Tier 2 normal scan: on exhausted 503/504, continue through the existing page-size ladder instead of failing the workload at the first `$top=1000` request.
- After every tier-2 page size is exhausted, use the existing tier-3 partition scan.
- Tier 3: subdivide a partition on exhausted 503/504 using the same year → month → day → hour structure used for pagination wedges. If an hour-sized partition still exhausts retries, fail rather than claiming an incomplete calendar backup.
- Authentication, authorization, malformed-query, cancellation, and other errors remain terminal for the current workload attempt.

This does not increase retries against one request: the Graph client still performs its existing bounded retries before the scanner changes query shape.

### 2. Tenant-owner stale recovery

`reapStalledBatchChildren()` must not requeue a child while its tenant batch has a live owner (running claim, fresh heartbeat, and unexpired lease).

- A live owner remains responsible for child cancellation and terminal reporting.
- The worker Graph stall watchdog remains the authority for a genuinely wedged child.
- If the owner heartbeat/lease becomes stale, the existing batch-level reaper requeues the claim and pending children together.
- Standalone/legacy runs without a live tenant-batch claim retain existing stale recovery behavior.

This removes the observed DB status ping-pong where the control plane requeues a child but cannot stop the still-running child goroutine.

### 3. Retry attempt accounting

When a queued tenant-batch child is promoted to running, atomically increment its queue attempt count.

- Initial execution becomes attempt 1.
- A retryable failure can be attempted at most `max_attempts`.
- Promotion only increments on the `queued → running` transition, so repeated progress posts do not inflate attempts.

### 4. Customer-facing state

The live workload API will distinguish current state from historical queue text:

- For a running batch claim, suppress an old infrastructure hand-off message when the child was scheduled before or at the current claim time. Such children are ordinary claim-time semaphore waiters, not currently recovering.
- Do not expose raw Graph 503/504 JSON, request IDs, or internal `Queue:` wording. Show: `Microsoft 365 temporarily timed out. Waiting to retry.`
- Preserve technical errors in `ms365_job_queue.error_message` and worker logs for operations.
- Keep one deduplicated event per projected workload. The inspected API projection already emits one event for the reported 504, so no speculative duplicate-event change is included.

## Tests

1. Calendar tier 1 exhausted 504 escalates to tier 2.
2. Calendar tier 2 exhausted 504 tries the next page size.
3. Calendar tier 2 exhausted 504s fall through to partition scanning.
4. Calendar hour partition exhausted 504 remains a hard failure.
5. A child under a live, fresh batch claim is not selected by the per-child stale reaper.
6. A child without a live owner retains stale recovery.
7. Batch child promotion increments attempts exactly once.
8. Claim-time drain/handoff messages are suppressed from customer workload rows.
9. Graph 504 is rendered as the friendly retry message without raw JSON.

## Deployment and verification

1. Keep session `062be2` instrumentation active.
2. Deploy PHP changes through `deploy-production.sh`.
3. Build and roll worker `0.4.7`.
4. Verify the active batch has no new `reapStalledBatchChildren` entries while its owner remains live.
5. Verify recovery-row counts drop after refresh and running rows no longer inherit old queued hand-off events.
6. Allow child `09ee3a8f-…` to retry on worker `0.4.7`; verify logs show page-size descent or partition fallback rather than immediate failure at `$top=1000`.
7. Remove instrumentation only after post-fix logs and operator confirmation.

## Out of scope

Changing Graph concurrency, increasing per-request retry counts, suppressing genuine terminal failures, or redesigning Graph request attribution across concurrent children.
