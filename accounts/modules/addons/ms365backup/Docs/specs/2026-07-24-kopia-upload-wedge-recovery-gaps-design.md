# Kopia Upload Wedge Recovery Gaps

**Date:** 2026-07-24  
**Status:** Implemented (worker 0.4.12, PHP 1.52.11)  
**Related:** [2026-07-24-graph-content-idle-timeout-design.md](./2026-07-24-graph-content-idle-timeout-design.md), worker 0.4.11

## Summary

Worker 0.4.11 shipped per-read idle timeouts and upload-phase stall defaults, but production batch `bbf034af-ffe9-473d-916a-ad4350ef892b` still wedged for **2.5+ hours** on SharePoint site **Work Orders** (`ed83ae1b-d693-468c-a028-c14bd2c8acf7`). The child completed `graph_sync` (23,535 items) and then froze in `kopia_upload` with flat byte counters while the batch owner kept heartbeating.

Three independent gaps prevented automatic recovery:

1. **Stall watchdog** — parallel Kopia uploads can mask a single wedged file because the watchdog requires **both** hash and upload counters to be flat.
2. **Child reaper** — `reapStalledBatchChildren()` skips all children on batches with a live owner heartbeat, even when an individual child has been silent for hours.
3. **Idle timeout scope** — `content_read_idle_seconds` wraps the response **body** only; the observed wedge blocked in `streamClient.Do()` **before** headers arrived, so the idle reader never activated.

This document explains each gap, cites runtime evidence, and proposes targeted fixes.

---

## Production evidence

**Batch:** `bbf034af-ffe9-473d-916a-ad4350ef892b`  
**Child:** `ed83ae1b-d693-468c-a028-c14bd2c8acf7` (SharePoint drive **Work Orders**)  
**Workers:** 9013 → 9012 after manual release  
**Worker version:** 0.4.11

| Signal | Value |
|--------|--------|
| Phase | `upload` (`kopia_upload`) |
| Items | 23,535 / 23,535 (graph sync complete) |
| Bytes | 14.7 GB hashed, 4.2 GB uploaded — **flat for ~2.5 h** |
| Process CPU | ~0% |
| Graph `in_flight` | 1 (stuck) |
| Batch claim | `running`, heartbeat fresh |
| Child `last_progress_at` | Stale ~8,900 s |

**SIGQUIT stack (worker 9013):**

```
uploadFileData
  → GraphFile.Open
    → Client.getStream
      → streamClient.Do(reqClone)   // client.go:1048 — blocked here
```

Not in `idleTimeoutReader.Read` — the hang occurred while waiting for the HTTP response (headers/TCP), not during a mid-body read.

**Timeline highlights:**

- 10:53 / 11:26 — force deploy restarts on 9013 mid-run (batch resumed, wedge persisted).
- 11:28 — `graph_sync completed` on current attempt; upload tail begins.
- ~14:00 — still wedged; manual `Ms365BatchClaimRepository::release()` + worker restart required.
- 12:02 — batch reclaimed by 9012; Work Orders resumed with active Graph traffic.

**Operational unblock (not a code fix):** release batch lease, restart worker, allow another node to reclaim. Batch had 2,693/2,694 children already `success`.

---

## Gap 1: Stall watchdog masks single-file wedges

### Problem

`kopia.StartStallWatch` (`internal/kopia/stall_watch.go`) cancels the snapshot context only when **both** conditions hold for `StallSeconds` (upload phase default **900** via `upload_stall_seconds`):

```go
hashStalled := sinceHash >= int64(cfg.StallSeconds)
uploadStalled := sinceUpload >= int64(cfg.StallSeconds)
if !hashStalled || !uploadStalled {
    continue  // no cancel
}
```

Kopia runs `parallel_uploads` (default 6) goroutines. While one file blocks in `getStream` / `uploadFileData`, other files can still hash and upload, resetting `lastHashAt` and/or `lastUploadAt` on the shared `ProgressCounter`. The watchdog never fires even though one stream — and eventually the whole snapshot — is stuck behind a `WaitGroup`.

This matches Work Orders: items were 100% enumerated, bytes partially uploaded, then flat for hours while the process remained alive and heartbeats continued.

### Why 0.4.11 did not help

`upload_stall_seconds` (default 900) is wired in `runner.go`, but the **AND** semantics defeat it whenever any parallel shard makes progress. Large SharePoint drives with thousands of files are the worst case: one wedged large file + five healthy workers = infinite stall.

### Suggested fix

**Approach A (recommended): per-item stall tracking**

Track `lastProgressAt` per in-flight file (path or opaque item ID) inside the upload phase:

- When Kopia calls `HashingFile` / `UploadedBytes` / `FinishedFile`, touch that item's timestamp.
- A background goroutine (same interval as today, default 60 s) finds items with `now - lastProgress > upload_stall_seconds` and cancels the snapshot context (or signals the specific `streamReader` to abort via shared cancel).
- Global counter stall becomes a **secondary** backstop, not the only signal.

**Approach B (minimal): OR semantics for upload tail**

When `items_done >= items_total` (upload tail after graph sync enumeration is complete), switch stall logic to:

```go
if tailPhase {
    stalled = sinceUpload >= stallSeconds || sinceHash >= stallSeconds
} else {
    stalled = sinceHash >= stallSeconds && sinceUpload >= stallSeconds
}
```

Lower risk, smaller diff, but does not catch wedges during mid-enumeration upload of a single huge file before `items_total` is known.

**Approach C: watchdog on `current_item`**

`ProgressCounter.DebugSnapshot()` already exposes `current_item`. Extend `StartStallWatch` to record the current file name and fail if it is unchanged for `stallSeconds` **even when bytes move on other goroutines** (detect "stuck on same file" vs "slow but moving").

### Configuration

```yaml
kopia:
  upload_stall_seconds: 900        # unchanged default
  upload_stall_per_item: true      # new; default true when upload_stall_seconds > 0
  upload_stall_mode: per_item      # per_item | tail_or | global_and (escape hatch)
```

### Tests

1. Simulate two logical upload goroutines: one frozen, one advancing bytes — global AND watchdog must **not** fire; per-item watchdog **must** fire.
2. Tail phase (`items_done == items_total`): OR mode fires when upload bytes flat for 900 s even if hash still ticks.
3. Regression: normal large upload with steady byte progress never fires.

---

## Gap 2: Child reaper shielded by live batch heartbeat

### Problem

`Ms365BatchClaimRepository::reapStalledBatchChildren()` intentionally **excludes** every child whose `e3_batch_run_id` belongs to a batch with a live claim:

```php
$liveBatchIds = ms365_batch_claims
    ->where('status', 'running')
    ->where('last_heartbeat_at', '>=', heartbeatCutoff)
    ->where('lease_expires_at', '>', now);

$query->whereNotIn('e3_batch_run_id', $liveBatchIds);
```

Rationale (correct for its time): requeueing a child in MySQL does not stop the worker's in-process goroutine; it caused status ping-pong until the owner exited.

Side effect: a batch owner that heartbeats regularly (batch-level progress from coalesced child snapshots) **shields wedged children indefinitely**, even when:

- A child has `last_progress_at` stale for 30+ minutes,
- `items_done >= items_total` in upload tail,
- Silence threshold is already reduced to **600 s** for upload-like phases.

Work Orders matched this exactly: batch heartbeat fresh, child progress stale ~2.5 h, reaper never selected the row.

`reapStaleBatches()` at the batch level has a `staleProgress` path, but it keys off **batch** `last_progress_at`, which the owner refreshed while the wedged child did not.

### Suggested fix

**Approach A (recommended): reap wedged children on live batches with owner hand-off**

For children matching existing stale-progress rules **on a live batch**:

1. Mark child `queued` in `ms365_backup_runs` and `ms365_job_queue` (existing `requeueBackupRuns` path).
2. **Do not** requeue siblings that are still making progress.
3. Call `handOffRunningBatchClaims([$batchRunId], 'Child progress stale on live owner')` so the worker receives `drain` on next heartbeat, checkpoints, and releases the batch lease cooperatively.
4. Another worker (or the same after restart) reclaims the batch and resumes only `queued` children; `success` children stay terminal.

This reuses the drain hand-off pattern from rolling deploys instead of fighting the in-process goroutine via DB alone.

**Approach B: worker-side child cancel API**

Add `POST ms365_worker_cancel_child.php` (or batch progress flag) so the control plane can signal the owning worker to `cancelRun(childId)` without draining the whole batch. Reaper calls this before DB requeue. More moving parts, but avoids full batch hand-off when only one of six parallel children is wedged.

**Approach C: narrow the live-batch exclusion**

Only exclude children when **batch** `last_progress_at` is also fresh (e.g. within `STALE_SILENCE_SECONDS`). If batch progress is fresh but a specific child is stale beyond the upload-tail threshold, allow reaper to select that child.

Risk: false positives if parent progress blends completed siblings and masks one stuck tail — needs careful definition of batch vs child freshness (see `Ms365RestoreWorkerHooks::onBatchProgress` coalescing).

### Thresholds (unchanged, but apply on live batches)

| Condition | Silence |
|-----------|---------|
| Default | 1800 s (`STALE_SILENCE_SECONDS`) |
| Upload-like phase, items incomplete | 600 s |
| Graph-bound, bytes zero, items done | 600 s |
| Upload tail (`items_done >= items_total`) | 600 s |

### Tests

1. Live batch + stale upload-tail child → child requeued + batch hand-off triggered.
2. Live batch + one stale child + one active child → only stale child requeued; active child keeps running until natural completion or cooperative drain.
3. Live batch + fresh child → no reaper action (regression).

---

## Gap 3: Idle timeout does not cover `streamClient.Do()`

### Problem

0.4.11 wraps the response body in `idleTimeoutReader` **after** a successful `streamClient.Do()`:

```go
resp, err := c.streamClient.Do(reqClone)   // can block unbounded in practice
// ...
body = newIdleTimeoutReader(resp.Body, c.contentReadIdle)
```

`streamClient` uses `Timeout: 0` (no absolute deadline) and `ResponseHeaderTimeout: 120s` on the transport. In theory headers should bound `Do()`. Production evidence shows a **2.5 h** block at `Do()` on 0.4.11, implying:

- TCP connection hung in a state where `ResponseHeaderTimeout` did not fire (kernel buffering, HTTP/2 stream stall, proxy half-open), or
- Rare Go/net/http edge case with connection reuse.

The idle reader cannot help until headers return and the body is wrapped.

### Suggested fix

**Approach A (recommended): context deadline around each `Do()` attempt**

Wrap each stream `Do()` in a derived context:

```go
attemptCtx, cancel := context.WithTimeout(ctx, time.Duration(headerTimeoutSeconds)*time.Second)
defer cancel()
resp, err := c.streamClient.Do(reqClone.WithContext(attemptCtx))
```

Use `graph.stream_response_header_seconds` (default **120**). On `context.DeadlineExceeded`, treat as retryable (same path as transient network error), release transport, retry with backoff up to `max_retries`.

Distinct from body idle timeout: this bounds **time-to-first-byte / headers** per attempt.

**Approach B: dial + TLS handshake timeout**

Tighten transport:

```go
DialContext: (&net.Dialer{Timeout: 30 * time.Second}).DialContext,
TLSHandshakeTimeout: 15 * time.Second,
```

Complements A; does not replace it.

**Approach C: force new connection for content streams**

Set `req.Close = true` or use a dedicated `Transport` without keep-alive for `/content` reads to avoid poisoned pooled connections. Trade-off: more latency and Graph load.

### Interaction with idle reader

| Phase | Timeout |
|-------|---------|
| `Do()` (headers/TCP) | `stream_response_header_seconds` per attempt (context) |
| Body `Read()` | `content_read_idle_seconds` per read (idle reader) |
| Whole file | `content_read_retries` Range resumes |

### Tests

1. Mock server: accept TCP, send nothing → `Do()` returns within header timeout, retries, then fails clearly.
2. Mock server: send headers slowly within limit, then stall body → idle reader fires.
3. Regression: large file download with steady bytes succeeds.

---

## Recommended implementation order

| Priority | Gap | Fix | Effort |
|----------|-----|-----|--------|
| 1 | Header/TCP wedge | Context deadline on `streamClient.Do()` (Gap 3A) | Small |
| 2 | Stall watchdog | Per-item or tail-OR stall (Gap 1A or 1B) | Medium |
| 3 | Child reaper | Stale child on live batch + cooperative hand-off (Gap 2A) | Medium |

Gap 3 is the smallest change and closes the exact stack frame observed in production. Gap 1 prevents long silent tails even when header timeout works. Gap 2 ensures the control plane recovers when the worker-level watchdog still fails.

---

## Rollout

1. Bump worker to **0.4.12** (patch).
2. PHP-only reaper change can ship independently if hand-off logic is control-plane only; prefer single release for test coherence.
3. Canary on one prod worker; run a large SharePoint site job.
4. Confirm fleet config exposes new knobs in worker template / `WorkerConfigService` if added.
5. Production worker `config.yaml` currently sets `kopia.stall_seconds: 0` (overridden to 2700 by `applyDefaults()` at runtime) — document that explicit `0` in YAML does **not** disable defaults; consider fleet template defaults for `upload_stall_seconds: 900` and `content_read_idle_seconds: 120`.

---

## Non-goals

- Skipping or soft-failing wedged files silently (always fail child with clear error after retries).
- Force-deploying workers mid-batch (operational policy; use `all_idle` deploy strategy).
- Changing Graph adaptive concurrency or fleet-wide batch parallelism in this pass.

---

## Open questions

1. **Per-item stall vs tail-OR** — is tail-OR sufficient for SharePoint-heavy tenants, or do we need per-item tracking for huge single files mid-snapshot?
2. **Hand-off granularity** — full batch hand-off on one stale child vs worker cancel-child API?
3. **Header timeout value** — is 120 s per `Do()` attempt too aggressive for very slow Graph first-byte on multi-GB files, or acceptable given Range resume?

---

## References

- `ms365-backup-worker/internal/kopia/stall_watch.go` — AND stall semantics
- `ms365-backup-worker/internal/jobs/runner.go` — `StartStallWatch` wiring, `upload_stall_seconds`
- `ms365-backup-worker/internal/graph/client.go` — `getStream`, `streamClient.Do`
- `accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365BatchClaimRepository.php` — `reapStalledBatchChildren`, live-batch exclusion
- `accounts/modules/addons/ms365backup/Docs/MS365_WORKER_FLEET.md` — drain hand-off, deploy strategies
