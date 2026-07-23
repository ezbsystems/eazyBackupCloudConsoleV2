# Batch drain cancellation design

## Problem

During production rolling deploys, `BatchRunner.Run` launches one goroutine for every pending child. Goroutines waiting to acquire the child concurrency semaphore do not observe cancellation. `Scheduler.cooperativeDrain` cancels the batch and waits only for registered active children, so it can release the batch lease while pending goroutines remain alive. Those goroutines subsequently acquire the semaphore, start children with an already-cancelled context, and submit detached terminal reports after lease release. The control plane rejects those reports with HTTP 409 and can temporarily show failed workloads.

Production batch `bbf034af-ffe9-473d-916a-ad4350ef892b` demonstrated this ordering on worker 9012: update hand-off began at 23:19:30 UTC; new children continued starting from 23:19:40; terminal reports then failed with `Batch lease is not active for this node`. The reported child was automatically reclaimed by worker 9010 and completed successfully in 765 ms, ruling out a permanent Graph or authorization failure.

## Selected design

Make child dispatch cancellation-aware in `internal/jobs/batch_runner.go`:

1. Acquire the child concurrency semaphore with a `select` on `sem` and `ctx.Done()`.
2. Re-check `ctx.Err()` before reservation and before creating/registering the child context.
3. Preserve existing cooperative cancellation behavior for children already running.
4. Do not alter batch retry policy, control-plane lease semantics, or terminal-report detachment.

When drain cancels the batch, queued goroutines exit promptly instead of starting new workloads. The batch runner's wait group can then quiesce, allowing the existing scheduler drain sequence to release the lease after active work stops.

## Diagnostics

Temporary session `6f5f7c` NDJSON diagnostics will record:

- cancellation while waiting for a child slot;
- cancellation after slot acquisition but before reservation/start;
- batch-runner exit state.

Diagnostics must not include tokens, resource names, UPNs, or Graph identifiers. They remain through post-fix verification and are removed only after runtime proof and operator confirmation.

## Tests

Add a regression test with concurrency one:

1. Start a batch with one blocking child and at least one queued child.
2. Cancel the batch while the first child owns the slot.
3. Release the first child.
4. Assert the queued child never enters `RunSafe` and the batch runner exits.

Run the focused jobs tests, then the full worker test/build suite.

## Deployment and verification

Publish the next patch worker release and perform a rolling production deployment. Verify:

- all fleet nodes reach the new version;
- no old node starts a child after its hand-off log;
- no post-release `Batch lease is not active for this node` completion storm occurs;
- the active batch continues progressing;
- the previously reported child remains successful, without a redundant manual restart.

If existing runtime state contains another genuinely failed child, requeue only that child after confirming it is not currently running and the active batch lease can safely pick it up.
