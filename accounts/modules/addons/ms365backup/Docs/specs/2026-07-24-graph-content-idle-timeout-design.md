# Graph Content Idle Timeout + Upload Stall Design

**Date:** 2026-07-24

## Goal

Stop wedged Kopia uploads that sit in `uploadFileData` waiting on Microsoft Graph content reads with 0% CPU and flat hash/upload byte counters, while heartbeats still report "Uploading snapshot to repository". Fail fast with a per-read idle timeout, bounded per-file retry via HTTP Range resume, and a tighter upload-only stall watchdog as secondary safety.

## Runtime evidence

Production worker 9013 (session `062be2`, worker 0.4.10):

1. SharePoint child completed `graph_sync` (`items_done == items_total`) then entered Kopia upload with flat `bytes_hashed` / `bytes_uploaded` for 14+ minutes.
2. SIGQUIT stacks showed main work blocked in `kopia/snapshot/upload.uploadFileData` → `WaitGroup.Wait`; parent dirs blocked in `processChildren` → `WaitGroup.Wait`.
3. Open sockets were mostly Microsoft Graph (`13.107…`, `20.190…`); process CPU ~0% (I/O wait, not compute).
4. Heartbeats renewed the lease with phase `kopia_upload` and message "Uploading snapshot…" while byte counters were frozen.
5. `kopia.stall_seconds=2700` had not yet fired; the hang was more precise than the global stall timer.

Root cause: Graph content streams (`GetStream` → `resp.Body.Read`) have no per-read idle deadline. The shared `http.Client{Timeout: 120s}` is an absolute wall clock for the entire request (headers + body), which does not reliably abort a wedged mid-body read and caps large continuous downloads.

## Design

### 1. Dedicated stream HTTP client

Split JSON API calls from content streaming:

- **JSON client** (`httpClient`): unchanged `Timeout: 120s` for `GetJSON`, pagination, etc.
- **Stream client** (`streamClient`): `Timeout: 0` (no absolute body deadline); `ResponseHeaderTimeout: 120s` on the shared transport so headers are still bounded.

Both clients share the same `http.Transport` (connection pool, semaphores, adaptive concurrency).

### 2. Per-read idle timeout wrapper

Wrap successful stream response bodies in an `idleTimeoutReader`:

- Timer resets on every `Read` that returns `n > 0`.
- If no bytes arrive for `content_read_idle_seconds` (default **120**), close the underlying body and return typed `ErrContentReadIdleTimeout`.
- `0` disables the idle wrapper (escape hatch for debugging).

The wrapper is applied inside `streamBody` so all stream consumers (Open, Seek reopen) get idle behavior.

### 3. Per-file retry via Range resume

In `graphfs.streamReader`:

- Store the Open context on the reader (fix `Seek` using `context.Background()` today).
- On idle timeout (and selected transient network errors): close body, `GetStreamRange(ctx, path, offset)`, swap reader, increment attempt.
- Max attempts: `content_read_retries` (default **3**) per Open lifetime.
- After exhaustion: fail the child/snapshot with a clear error including path, offset, and attempt count. No skip/soft-fail.

### 4. Upload-only stall backstop

Add `kopia.upload_stall_seconds` (default **900**):

- Used only during `kopia_upload` phase in `StartStallWatch`.
- `0` falls back to `stall_seconds`.
- Keep `stall_seconds=2700` for `graph_sync` and the graph stall watchdog unchanged.

### 5. Configuration

```yaml
graph:
  content_read_idle_seconds: 120
  content_read_retries: 3
  stream_response_header_seconds: 120

kopia:
  stall_seconds: 2700
  upload_stall_seconds: 900
```

## Tests

1. Idle reader: partial bytes then pause > idle → `ErrContentReadIdleTimeout`; continuous trickle never times out.
2. Stream client uses `streamClient` (no absolute body deadline on multi-chunk download).
3. Semaphore balanced after mid-body idle close.
4. `streamReader` retries on idle once then succeeds via Range.
5. Exhaust retries → typed error surfaces to caller.
6. `Seek` uses Open context, not `context.Background()`.
7. Runner wires `upload_stall_seconds` for upload phase only.

## Deployment and verification

1. Bump worker to **0.4.11**.
2. `go test ./...` and `go build ./...`.
3. Commit + push `origin/main` on dev; fleet build → rolling deploy.
4. Deploy docs via `deploy-production.sh` if WHMCS docs changed.
5. Verify: wedged Graph content reads abort within ~120s idle; retry logs show Range resume; upload stall at 900s only when hash+upload both flat outside Graph-read recovery path.

## Out of scope

- Skipping/soft-failing individual objects during snapshot upload.
- Lowering global `stall_seconds` for graph_sync.
- Changing control-plane `progress_stall_seconds` or UI "No progress" copy beyond natural effect of faster failure.
