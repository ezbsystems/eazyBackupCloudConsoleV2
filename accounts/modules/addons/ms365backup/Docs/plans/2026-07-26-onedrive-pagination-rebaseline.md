# OneDrive Pagination Rebaseline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recover OneDrive backups whose persisted incremental delta cursor repeatedly returns a duplicate-only Graph page, then requeue and complete the affected production workload.

**Architecture:** Keep strict pagination as the normal safety policy. When and only when a resumed OneDrive delta raises `GraphPaginationError`, discard that unapplied pass and retry once from the full `/root/delta` endpoint; persist the replacement delta cursor on success and propagate a second failure unchanged. Temporary worker/API diagnostics record the fallback lifecycle without tokens or item data.

**Tech Stack:** Go 1.x worker, PHP 8.2/WHMCS Capsule control plane, existing worker run-log ingestion, standalone Go and PHP tests, fleet build/deploy services.

## Global Constraints

- Retry at most once and only when the incoming OneDrive delta link is non-empty.
- Do not soft-complete a duplicate page and do not persist partial delta state.
- Do not log Graph delta/skip tokens, next-link URLs, credentials, or item data.
- Preserve the previous Kopia manifest overlay and all non-OneDrive workload behavior.
- Keep session `b86288` diagnostics until production logs prove success and the operator confirms completion.
- Commit and push changes to `origin/main`; deploy PHP with `modules/addons/ms365backup/bin/deploy-production.sh`.
- Release worker version `0.4.20` and verify every active production node reaches it before requeueing the child.

---

### Task 1: Add bounded OneDrive full-delta fallback and diagnostics

**Files:**
- Create: `ms365-backup-worker/internal/graphsync/onedrive_pagination_rebaseline_test.go`
- Modify: `ms365-backup-worker/internal/graphsync/onedrive.go`
- Modify: `ms365-backup-worker/internal/graphsync/workloads.go`
- Modify: `accounts/modules/addons/cloudstorage/api/ms365_worker_log.php`

**Interfaces:**
- Consumes: `graph.GraphPaginationError`, `OneDriveSyncOptions.DeltaLink`, `WorkloadRunner.RunLog`.
- Produces: `OneDriveSyncOptions.Log RunLogger`; `stats["pagination_rebaseline"] == 1` after successful recovery; session `b86288` NDJSON fallback events.

- [ ] **Step 1: Write the resumed-cursor failing regression**

Create an `httptest.Server` that serves:

```go
// Incremental pass: page 2 contains only the page-1 id and still has nextLink.
case r.URL.Path == "/resume":
    writeDelta(w, []map[string]any{driveItem("old")}, srv.URL+"/resume-2", "")
case r.URL.Path == "/resume-2":
    writeDelta(w, []map[string]any{driveItem("old")}, srv.URL+"/resume-3", "")
// Full rebaseline pass.
case strings.HasSuffix(r.URL.Path, "/drives/drive-1/root/delta"):
    writeDelta(w, []map[string]any{driveItem("fresh")}, "", srv.URL+"/delta-complete")
```

Invoke:

```go
res, err := SyncOneDrive(context.Background(), client, OneDriveSyncOptions{
    AzureTenantID: "tenant-1",
    UserID:        "user-1",
    DriveID:       "drive-1",
    DeltaLink:     srv.URL + "/resume",
    Overlay:       graphfs.NewOverlayBuilder(),
})
```

Assert `err == nil`, `res.DeltaLink == srv.URL+"/delta-complete"`,
`res.Stats["pagination_rebaseline"] == 1`, and exactly one request reached the
full delta endpoint.

- [ ] **Step 2: Add negative regressions**

Add one test with `DeltaLink == ""` whose full pass repeats a duplicate-only
page and assert it returns `*graph.GraphPaginationError` without a second
request. Add one resumed test whose first request returns Graph HTTP 500 and
assert no full-delta request occurs.

- [ ] **Step 3: Run the focused test and verify RED**

Run:

```bash
cd /var/www/eazybackup.ca/ms365-backup-worker
go test ./internal/graphsync -run 'TestOneDrivePagination(Rebaseline|DoesNot)' -count=1 -v
```

Expected: the resumed-cursor recovery test fails because the current
implementation returns `GraphPaginationError`; negative tests pass.

- [ ] **Step 4: Implement one bounded fallback**

Add `Log RunLogger` to `OneDriveSyncOptions`, pass `w.RunLog` from
`WorkloadRunner`, and in `syncOneDriveDelta` handle only a resumed pagination
error:

```go
items, deltaLink, err := client.PaginateDelta(ctx, deltaPath, opts.DeltaLink, selectFields, 200, onPage)
if err != nil {
    var paginationErr *graph.GraphPaginationError
    if strings.TrimSpace(opts.DeltaLink) != "" && errors.As(err, &paginationErr) {
        if opts.Log != nil {
            opts.Log("warning", "OneDrive incremental pagination loop; retrying full delta baseline")
        }
        retryOpts := opts
        retryOpts.DeltaLink = ""
        recovered, retryErr := syncOneDriveDelta(ctx, client, retryOpts, driveID)
        if retryErr != nil {
            if opts.Log != nil {
                opts.Log("error", "OneDrive full delta rebaseline failed")
            }
            return nil, fmt.Errorf("onedrive full delta rebaseline: %w", retryErr)
        }
        recovered.Stats["pagination_rebaseline"] = 1
        if opts.Log != nil {
            opts.Log("info", fmt.Sprintf(
                "OneDrive full delta rebaseline completed items=%d",
                recovered.Stats["items"],
            ))
        }
        return recovered, nil
    }
    return nil, err
}
```

The recursive call cannot recurse again because `retryOpts.DeltaLink` is empty.

- [ ] **Step 5: Add temporary session log ingestion**

In `ms365_worker_log.php`, after normalization and only for run
`de6322a3-b679-43a3-81a0-32f68a811e10`, append lines containing
`OneDrive incremental pagination` or `OneDrive full delta rebaseline` to
`/var/www/eazybackup.ca/.cursor/debug-b86288.log`.

Use a folded `// #region agent log` block and this payload:

```php
[
    'sessionId' => 'b86288',
    'runId' => $runId,
    'hypothesisId' => 'OD-H3',
    'location' => 'ms365_worker_log.php:onedrive-rebaseline',
    'message' => $entry['message'],
    'data' => ['level' => $entry['level']],
    'timestamp' => (int) floor(microtime(true) * 1000),
]
```

Reject any candidate message containing `skip_token=`, `next_link=`,
`deltatoken`, or `https://` before writing.

- [ ] **Step 6: Verify GREEN and no regressions**

Run:

```bash
cd /var/www/eazybackup.ca/ms365-backup-worker
gofmt -w internal/graphsync/onedrive.go internal/graphsync/workloads.go internal/graphsync/onedrive_pagination_rebaseline_test.go
go test ./internal/graphsync -run 'TestOneDrivePagination(Rebaseline|DoesNot)' -count=1 -v
go test ./...
go build ./...
php -l /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/api/ms365_worker_log.php
```

Expected: all commands exit 0.

---

### Task 2: Version, review, commit, and deploy

**Files:**
- Modify: `ms365-backup-worker/internal/version/version.go`
- Modify: `accounts/modules/addons/ms365backup/ms365backup.php`
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`

**Interfaces:**
- Consumes: tested fallback and session diagnostics from Task 1.
- Produces: worker `0.4.20`, PHP module `1.52.30`, release/build metadata.

- [ ] **Step 1: Bump versions and document the runtime proof**

Set worker version to `0.4.20` and PHP module version to `1.52.30`. Add a
PROGRESS entry recording:

- child `de6322a3` failed twice on the same persisted OneDrive cursor;
- the queue error was the duplicate-only-page branch, not repeated nextLink;
- the fallback retries exactly once from an empty cursor;
- session `b86288` instrumentation remains pending production verification.

- [ ] **Step 2: Run final local verification**

Run:

```bash
cd /var/www/eazybackup.ca/ms365-backup-worker
go test ./...
go build ./...
cd /var/www/eazybackup.ca/accounts
php -l modules/addons/cloudstorage/api/ms365_worker_log.php
php -l modules/addons/ms365backup/ms365backup.php
git diff --check
```

Expected: all commands exit 0.

- [ ] **Step 3: Request review and resolve all Critical/Important findings**

Review the complete branch diff against the design. Do not deploy with any
remaining Critical or Important finding.

- [ ] **Step 4: Commit and push**

Commit the implementation, tests, instrumentation, versions, and progress note
with a message focused on recovering poisoned OneDrive delta cursors. Push
`main` to `origin/main`.

- [ ] **Step 5: Deploy PHP and create the worker release**

Run production PHP deployment:

```bash
ssh -i /root/.ssh/whmcs_prod_root -o IdentitiesOnly=yes root@192.168.92.75 \
  '/var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh'
```

Create a dev fleet build job for version `0.4.20`, `git_ref=main`, with
`run_tests=true`, `git_sync=true`, `auto_deploy=true`, and rolling strategy.
Run or await `crons/ms365_worker_build_runner.php`, then verify the build job
published a release and the rolling deploy started.

- [ ] **Step 6: Verify fleet rollout**

Poll fleet state until every active production node reports:

```text
version=0.4.20
deploy_status=current
config_status=current
```

Do not requeue the failed child while any eligible node remains on `0.4.19`.

---

### Task 3: Requeue the child and verify production recovery

**Files:**
- Runtime read/write: production `ms365_backup_runs`, `ms365_job_queue`, `ms365_batch_claims`
- Read: `/var/www/eazybackup.ca/.cursor/debug-b86288.log`

**Interfaces:**
- Consumes: fully deployed worker `0.4.20`.
- Produces: one queued child, then terminal success with a replacement OneDrive delta cursor.

- [ ] **Step 1: Clear only the session log**

Use the Cursor `delete_file` tool for:

```text
/var/www/eazybackup.ca/.cursor/debug-b86288.log
```

Do not delete any other debug-session log.

- [ ] **Step 2: Re-read and validate the target row**

Inside a production DB transaction, lock child
`de6322a3-b679-43a3-81a0-32f68a811e10` and its queue row. Abort unless the child
is still `error`/`failed`, the queue is still `failed`, and the queue error still
contains `onedrive: Graph pagination loop detected`.

- [ ] **Step 3: Requeue only the validated child**

Within the same transaction:

```php
WorkerClaimService::rollbackAttemptForBatchRequeue($runId);
Ms365WorkerLogRepository::releaseAssignment($runId, 'onedrive_rebaseline_retry');
Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
    'status' => 'queued',
    'worker_node_id' => null,
    'claimed_at' => null,
    'lease_expires_at' => null,
    'finished_at' => null,
    'scheduled_at' => time(),
    'error_message' => 'OneDrive full-delta recovery retry',
]);
BackupRunRepository::resetForQueueRequeue($runId, time());
```

Leave every sibling unchanged. The existing idle-owner handoff reconciler will
make the queued child available to the next batch claim.

- [ ] **Step 4: Observe runtime evidence**

Require session log entries in this order:

```text
OneDrive incremental pagination loop; retrying full delta baseline
OneDrive full delta rebaseline completed items=...
```

Also require worker logs for `graph_sync completed`, a non-empty replacement
OneDrive delta state, queue status `done`, child status `success`, and a
manifest ID.

- [ ] **Step 5: Ask the operator to confirm the live UI**

The workload must show Success with no current Error event. Keep
instrumentation until both runtime evidence and operator confirmation are
available.

---

### Task 4: Remove verified session diagnostics

**Files:**
- Modify: `accounts/modules/addons/cloudstorage/api/ms365_worker_log.php`
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`
- Modify: `accounts/modules/addons/ms365backup/ms365backup.php`

**Interfaces:**
- Consumes: successful Task 3 logs and operator confirmation.
- Produces: production code without session `b86288` runtime writers.

- [ ] **Step 1: Remove only the session `b86288` region**

Delete the temporary NDJSON writer from `ms365_worker_log.php`. Keep the
OneDrive fallback and its normal worker run-log messages.

- [ ] **Step 2: Bump PHP patch version and verify**

Bump PHP from `1.52.30` to `1.52.31`, update PROGRESS with production proof,
then run PHP lint, focused Go tests, and `git diff --check`.

- [ ] **Step 3: Commit, push, and deploy cleanup**

Commit and push the cleanup, run `deploy-production.sh`, and verify no
`debug-b86288` references remain in runtime PHP.
