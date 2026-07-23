# Batch Drain Cancellation Implementation Plan

> **Status:** Approved and implemented as an independent worker 0.4.5 follow-up on 2026-07-23. The OAuth transport fix remains a separate worker 0.4.4 change.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent cancelled production batches from starting queued children after their lease is released.

**Architecture:** Keep the existing goroutine-per-child batch runner and scheduler drain sequence. Make semaphore acquisition and pre-start dispatch cancellation-aware so queued goroutines exit promptly, while already-running children continue using the existing cooperative cancellation/checkpoint path.

**Tech Stack:** Go 1.25, `context`, goroutines/channels, existing `internal/jobs` test harness, WHMCS fleet build/deploy control plane.

## Global Constraints

- Do not restart a child that is already `success`.
- Do not change batch retry limits, lease ownership rules, or detached terminal delivery.
- Session diagnostics use `/var/www/eazybackup.ca/.cursor/debug-6f5f7c.log`, contain no tokens or Microsoft 365 identifiers, and remain until post-fix evidence and operator confirmation.
- Preserve unrelated working-tree changes under `accounts/modules/addons/eazybackup/`.

---

### Task 1: Reproduce and fix cancelled child dispatch

**Files:**
- Modify: `ms365-backup-worker/internal/jobs/batch_runner.go:1-15,353-385`
- Test: `ms365-backup-worker/internal/jobs/batch_runner_test.go`

**Interfaces:**
- Consumes: batch `context.Context`, `sem chan struct{}`, `Scheduler.tryReserve(*api.RunJob) bool`.
- Produces: cancellation-aware dispatch in `BatchRunner.Run`; no new public API.

- [ ] **Step 1: Add temporary runtime diagnostics**

Add a compact `agentDebugBatchDrain` helper, wrapped in `// #region agent log` / `// #endregion`, that appends NDJSON to the session log. Record only `batch_id`, `run_id`, cancellation state, and dispatch stage. Call it after semaphore acquisition, when cancellation prevents dispatch, immediately before `RunSafe`, and after `wg.Wait`.

- [ ] **Step 2: Clear the session log**

Delete `/var/www/eazybackup.ca/.cursor/debug-6f5f7c.log` with the file deletion tool.

- [ ] **Step 3: Write the failing regression test**

Add `TestBatchRunnerCancelDoesNotStartQueuedChildren`. Configure `MaxConcurrentRuns = 1`, start two queued children, block the first child until its context is cancelled, cancel the batch, and assert:

```go
select {
case id := <-started:
	if id != "active" {
		t.Fatalf("first child = %s, want active", id)
	}
case <-time.After(time.Second):
	t.Fatal("active child did not start")
}

cancel()
if err := <-done; err != nil {
	t.Fatalf("Run() err = %v, want cooperative nil", err)
}

select {
case id := <-started:
	t.Fatalf("queued child %s started after cancellation", id)
default:
}
```

- [ ] **Step 4: Run the focused test and inspect pre-fix diagnostics**

Run:

```bash
go test ./internal/jobs -run TestBatchRunnerCancelDoesNotStartQueuedChildren -count=1 -v
```

Expected: FAIL because the queued child starts after cancellation. The NDJSON log must show semaphore acquisition or pre-start with `context_cancelled=true`.

- [ ] **Step 5: Implement the minimal cancellation-aware dispatch**

Replace the unconditional semaphore send with:

```go
select {
case sem <- struct{}{}:
case <-ctx.Done():
	agentDebugBatchDrain("H1,H2", "internal/jobs/batch_runner.go:dispatch:slot-cancelled", "child dispatch cancelled while waiting for slot", map[string]any{
		"batch_id": batchRunID,
		"run_id":   child.RunID,
	})
	return
}
defer func() { <-sem }()

if ctx.Err() != nil {
	agentDebugBatchDrain("H1,H2", "internal/jobs/batch_runner.go:dispatch:post-slot-cancelled", "child dispatch cancelled after slot acquisition", map[string]any{
		"batch_id": batchRunID,
		"run_id":   child.RunID,
	})
	return
}
```

Inside the reservation loop, check `ctx.Err()` before every `tryReserve`, and re-check cancellation after reservation but before registering or starting the child.

- [ ] **Step 6: Re-run focused and full verification**

Run:

```bash
go test ./internal/jobs -run TestBatchRunnerCancelDoesNotStartQueuedChildren -count=1 -v
go test ./...
go build ./...
```

Expected: all commands pass. Post-fix diagnostics must show queued children exiting at `slot-cancelled` and no queued `before-run` entry.

- [ ] **Step 7: Commit the tested fix**

```bash
git add ms365-backup-worker/internal/jobs/batch_runner.go ms365-backup-worker/internal/jobs/batch_runner_test.go
git commit -m "fix: stop batch dispatch after drain cancellation"
```

---

### Task 2: Release, verify, and reconcile the active batch

**Files:**
- Modify: `ms365-backup-worker/internal/version/version.go`
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`

**Interfaces:**
- Consumes: fleet build runner, production release synchronization, rolling deploy, production batch state.
- Produces: next patch worker release on all production nodes and evidence-backed batch reconciliation.

- [ ] **Step 1: Set the next available patch version**

Update `internal/version/version.go` to the next unique patch label after 0.4.3.

- [ ] **Step 2: Verify and commit the release version**

Run `go test ./... && go build ./...`, then commit the version and push all task commits to `main`.

- [ ] **Step 3: Deploy the WHMCS tree and publish the worker**

Run production `accounts/modules/addons/ms365backup/bin/deploy-production.sh`, queue the fleet build with tests enabled, verify publication and production release sync, then start a rolling deployment.

- [ ] **Step 4: Verify runtime ordering**

Confirm all eight nodes reach the new version. During hand-off, verify no node logs `starting run` after cancellation and no completion reports receive `Batch lease is not active for this node`.

- [ ] **Step 5: Reconcile the reported workload**

Read production state for run `1780312f-80f8-423e-9154-2f3caa2fe90d`. If it remains `success` with queue status `done`, do not restart it. If a different child is genuinely failed, ensure it has no active owner, requeue only that child through the existing control-plane retry mechanism, and verify it reaches `success`.

- [ ] **Step 6: Record deployment evidence**

Update `Docs/PROGRESS.md` with build/release/deploy IDs, fleet version count, batch status, and the reported child's final state. Commit, push, and deploy the documentation.

- [ ] **Step 7: Remove diagnostics after confirmation**

After post-fix logs prove correct hand-off behavior and the operator confirms, remove only session `6f5f7c` instrumentation, delete the session NDJSON file, rerun focused/full tests, publish the cleanup patch if instrumentation reached production, and update `Docs/PROGRESS.md`.
