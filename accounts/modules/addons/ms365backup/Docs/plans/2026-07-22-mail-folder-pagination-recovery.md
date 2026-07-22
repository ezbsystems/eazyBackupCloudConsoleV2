# Mail Folder Pagination Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep MS365 mail backups running when Microsoft Graph repeats a `mailFolders` nextLink, while preserving strict message-delta pagination.

**Architecture:** Add an opt-in repeated-nextLink soft-stop to the shared Go pagination session and expose it through `PaginationOutcome`. A mail-only helper retries folder enumeration at page sizes 100, 50, and 25; it prefers natural completion and accepts deduplicated partial folders only after the final wedge.

**Tech Stack:** Go, `net/http/httptest`, existing graph pagination monitor, existing graphsync run logger.

## Global Constraints

- Keep session `6f5f7c` instrumentation active through post-fix runtime verification.
- Do not change message `messages/delta` behavior.
- Do not change global pagination defaults.
- Do not log user IDs, folder names, tokens, nextLink values, or other PII/secrets.
- Worker release target: next version after production `0.4.1`.

---

### Task 1: Repeated-nextLink DetectOnly outcome

**Files:**
- Modify: `ms365-backup-worker/internal/graph/pagination.go`
- Modify: `ms365-backup-worker/internal/graph/client.go`
- Modify: `ms365-backup-worker/internal/graph/client_delta_pagination_test.go`

**Interfaces:**
- Consumes: `PaginationMonitor.DuplicatePageMode`
- Produces: `PaginationOutcome.StoppedOnRepeatedNextLink bool`

- [ ] **Step 1: Write failing DetectOnly test**

Add a test that serves two pages whose `@odata.nextLink` is the same and calls:

```go
outcome := &PaginationOutcome{}
items, delta, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
    Monitor:           ForCalendarNormalScan("link-detect", nil),
    Outcome:           outcome,
    DuplicatePageMode: DuplicatePageDetectOnly,
})
```

Assert `err == nil`, two unique items are returned, `delta == ""`, `outcome.StoppedOnRepeatedNextLink == true`, and only two HTTP calls occur.

- [ ] **Step 2: Run test to verify RED**

Run:

```bash
go test ./internal/graph -run TestPaginateDeltaOptsIdenticalLinkDetectOnly -count=1 -v
```

Expected: FAIL because repeated nextLinks still return `GraphPaginationError`.

- [ ] **Step 3: Implement minimal opt-in soft-stop**

Add the outcome/session field and make `checkNextLink()` set the stop flag only when the monitor uses `DuplicatePageDetectOnly`:

```go
if s.seenNextLinks[key] {
    if s.monitor != nil && s.monitor.DuplicatePageMode == DuplicatePageDetectOnly {
        s.stoppedOnRepeatedLink = true
        s.log("warning", "Graph pagination stopped: identical @odata.nextLink repeated (known Graph defect)")
        return nil
    }
    return &GraphPaginationError{Message: "Graph pagination loop detected: identical @odata.nextLink URL repeated; see " + graphDefectURL, Context: s.context()}
}
```

After `checkNextLink()`, both regular and delta pagination loops must break before issuing the repeated request. `finish()` copies the stop flag to `PaginationOutcome`, and `stopped()` includes both duplicate-page and repeated-link flags.

- [ ] **Step 4: Verify GREEN and strict regression**

Run:

```bash
go test ./internal/graph -run 'TestPaginateDeltaOptsIdenticalLink(Repeated|DetectOnly)' -count=1 -v
```

Expected: PASS; Strict still errors and DetectOnly soft-stops.

- [ ] **Step 5: Commit**

```bash
git add ms365-backup-worker/internal/graph/pagination.go ms365-backup-worker/internal/graph/client.go ms365-backup-worker/internal/graph/client_delta_pagination_test.go
git commit -m "fix: allow opt-in repeated Graph nextLink soft-stop"
```

### Task 2: Mail-folder page-size recovery

**Files:**
- Modify: `ms365-backup-worker/internal/graphsync/mail.go`
- Modify: `ms365-backup-worker/internal/graphsync/mail_test.go`

**Interfaces:**
- Consumes: `graph.Client.PaginateOpts`, `graph.PaginationOutcome`
- Produces: `paginateMailFolders(context.Context, *graph.Client, MailSyncOptions) ([]map[string]any, error)`

- [ ] **Step 1: Write failing page-size recovery test**

Change the diagnostic mail test into a behavior test whose fake Graph server wedges for `$top=100`, naturally completes for `$top=50`, and serves message deltas for both returned folders.

Assert:

```go
if err != nil {
    t.Fatalf("SyncMail: %v", err)
}
if res.Stats.Folders != 2 {
    t.Fatalf("folders = %d, want 2", res.Stats.Folders)
}
if strings.Join(folderPageSizes, ",") != "100,100,50" {
    t.Fatalf("folder request page sizes = %v", folderPageSizes)
}
```

- [ ] **Step 2: Run test to verify RED**

Run:

```bash
go test ./internal/graphsync -run TestSyncMailRetriesRepeatedFolderNextLinkWithSmallerPage -count=1 -v
```

Expected: FAIL with the current identical-nextLink pagination error.

- [ ] **Step 3: Implement the mail-only ladder**

Add:

```go
var mailFolderPageSizes = []string{"100", "50", "25"}

func paginateMailFolders(ctx context.Context, client *graph.Client, opts MailSyncOptions) ([]map[string]any, error) {
    var last []map[string]any
    for i, top := range mailFolderPageSizes {
        outcome := &graph.PaginationOutcome{}
        monitor := graph.NewPaginationMonitor("mail:folders", graph.DuplicatePageDetectOnly, graphLog(opts.Log))
        folders, err := client.PaginateOpts(ctx, fmt.Sprintf("/users/%s/mailFolders", opts.UserID), map[string]string{"$top": top}, &graph.PaginateOptions{
            Monitor:     monitor,
            Outcome:     outcome,
            TrackDupIDs: true,
        })
        if err != nil {
            return nil, err
        }
        if outcome.CompletedNaturally {
            return folders, nil
        }
        last = folders
        if opts.Log != nil {
            opts.Log("warning", fmt.Sprintf("Mail folder pagination wedged at page size %s; %s", top, map[bool]string{true: "keeping unique folders returned", false: "retrying smaller page size"}[i == len(mailFolderPageSizes)-1]))
        }
    }
    return last, nil
}
```

Call the helper from `SyncMail`. Keep all session `6f5f7c` log regions unchanged.

- [ ] **Step 4: Add final-wedge regression**

Add a test where all three sizes repeat their nextLink. Assert `SyncMail` succeeds, unique final-attempt folders are processed, and the captured run logger contains `keeping unique folders returned`.

- [ ] **Step 5: Verify focused and package tests**

Run:

```bash
go test ./internal/graphsync -run 'TestSyncMail(RetriesRepeatedFolderNextLinkWithSmallerPage|KeepsUniqueFoldersAfterFinalRepeatedNextLink)' -count=1 -v
go test ./internal/graph/... ./internal/graphsync/...
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add ms365-backup-worker/internal/graphsync/mail.go ms365-backup-worker/internal/graphsync/mail_test.go
git commit -m "fix: recover mail folder pagination wedges"
```

### Task 3: Documentation, release, and production verification

**Files:**
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`
- Modify: `ms365-backup-worker/internal/version/version.go`

**Interfaces:**
- Consumes: existing worker build/release and production fleet deployment workflow
- Produces: worker release `0.4.2`

- [ ] **Step 1: Run complete verification**

Run:

```bash
go test ./...
go build ./...
php accounts/modules/addons/ms365backup/tests/ms365_non_retryable_error_test.php
```

Expected: all commands exit 0.

- [ ] **Step 2: Update version and progress**

Set:

```go
var Version = "0.4.2"
```

Prepend a `2026-07-22` PROGRESS entry with production run evidence, confirmed root cause, recovery behavior, tests, and deployment/verification status.

- [ ] **Step 3: Commit and push**

```bash
git add accounts/modules/addons/ms365backup/Docs/PROGRESS.md ms365-backup-worker/internal/version/version.go accounts/modules/addons/ms365backup/Docs/plans/2026-07-22-mail-folder-pagination-recovery.md
git commit -m "chore: release MS365 worker 0.4.2"
git push origin main
```

- [ ] **Step 4: Build and publish worker**

Use the existing worker build runner to build version `0.4.2`, confirm artifact SHA-256, publish the release, and synchronize it to production.

- [ ] **Step 5: Deploy production**

Run:

```bash
ssh -i /root/.ssh/whmcs_prod_root -o IdentitiesOnly=yes root@192.168.92.75 \
  "bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh"
```

Deploy worker `0.4.2` to the production fleet and confirm active nodes report the target version.

- [ ] **Step 6: Post-fix runtime verification**

Clear only `/var/www/eazybackup.ca/.cursor/debug-6f5f7c.log`, retry the affected workload or run the next backup, then compare runtime logs:

- Before: `GraphPaginationError` at `SyncMail:folder-pagination-error`, no throttling/cancellation.
- After: folder pagination reaches `after-folder-pagination`; worker run logs show smaller-page recovery or final unique-folder warning; Graph sync and Kopia snapshot complete.

Keep instrumentation until the operator confirms no remaining issue. Remove it in a separate cleanup commit only after that confirmation.
