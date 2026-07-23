# Worker Token Refresh Retry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep a transient WHMCS-to-Microsoft OAuth connection failure from terminally failing an active backup child.

**Architecture:** Reuse the worker API client’s bounded, context-aware retry helper only for `RefreshGraphToken`. Preserve all existing token, Graph, batch, and error-handling interfaces.

**Tech Stack:** Go 1.25, `net/http`, `httptest`, existing worker API client and fleet release pipeline.

## Global Constraints

- Three total token-refresh attempts.
- No token, tenant, run, URL, request-body, or response-body values in diagnostics.
- Do not manually restart child `1780312f-80f8-423e-9154-2f3caa2fe90d`; it is already `success`.
- Preserve unrelated working-tree changes under `accounts/modules/addons/eazybackup/`.

---

### Task 1: Reproduce and fix transient token refresh

**Files:**
- Modify: `ms365-backup-worker/internal/api/client.go`
- Test: `ms365-backup-worker/internal/api/client_test.go`

**Interfaces:**
- Consumes: `Client.postWithRetry(ctx, endpoint, body, out, attempts)`.
- Produces: unchanged `RefreshGraphToken(ctx context.Context, runID string) (string, error)` with three-attempt resilience.

- [ ] **Step 1: Add temporary session diagnostics**

Add a compact `agentDebugTokenRefresh` NDJSON helper in `client.go`, wrapped in region markers. In `postWithRetry`, only when `endpoint == "ms365_worker_graph_token.php"`, log attempt number before `postOnce`, then success or failure with `APIHTTPError.StatusCode` when available. Use `/var/www/eazybackup.ca/.cursor/debug-6f5f7c.log`.

- [ ] **Step 2: Clear the session log**

Delete only `/var/www/eazybackup.ca/.cursor/debug-6f5f7c.log` with the file deletion tool.

- [ ] **Step 3: Write the failing regression test**

Add `TestRefreshGraphTokenRetriesTransientServerError`. Its test server returns HTTP 500 on call one and:

```json
{"status":"success","data":{"graph_token":"fresh-token","expires_in":3600}}
```

on call two. Assert:

```go
token, err := client.RefreshGraphToken(context.Background(), "run-1")
if err != nil {
	t.Fatalf("RefreshGraphToken: %v", err)
}
if token != "fresh-token" {
	t.Fatalf("token = %q, want fresh-token", token)
}
if calls.Load() != 2 {
	t.Fatalf("calls = %d, want 2", calls.Load())
}
```

- [ ] **Step 4: Run RED and inspect diagnostics**

Run:

```bash
go test ./internal/api -run TestRefreshGraphTokenRetriesTransientServerError -count=1 -v
```

Expected: FAIL after one HTTP call. Diagnostics show attempt 1 failed with HTTP 500 and no attempt 2.

- [ ] **Step 5: Implement the minimal fix**

Change:

```go
err := c.post(ctx, "ms365_worker_graph_token.php", body, &out)
```

to:

```go
err := c.postWithRetry(ctx, "ms365_worker_graph_token.php", body, &out, 3)
```

- [ ] **Step 6: Run GREEN and full verification**

Clear the session log, rerun the focused test, then:

```bash
go test ./...
go build ./...
```

Expected: focused test passes; diagnostics show HTTP 500 on attempt 1 and success on attempt 2; full tests/build pass.

- [ ] **Step 7: Commit and push**

Commit the API client/test change and the next worker patch version, then push `main`.

---

### Task 2: Deploy and verify production

**Files:**
- Modify: `ms365-backup-worker/internal/version/version.go`
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`

- [ ] **Step 1: Publish the next unique worker patch release**

Deploy the WHMCS tree, queue a tested fleet build, verify release sync to production, and perform a rolling deployment.

- [ ] **Step 2: Verify fleet and active batch**

Confirm eight active nodes on the new version, healthy production checks, a fresh active batch heartbeat, rising success/progress counts, and recovered child state `success` with queue state `done`.

- [ ] **Step 3: Record evidence**

Update `Docs/PROGRESS.md` with root cause, test evidence, build/release/deploy IDs, fleet status, and the decision not to restart the already-successful child. Commit, push, and deploy.

- [ ] **Step 4: Remove diagnostics after production confirmation**

After runtime evidence or operator confirmation, remove session `6f5f7c` diagnostics, delete its log file, rerun focused/full verification, and publish a cleanup patch if diagnostics reached production.
