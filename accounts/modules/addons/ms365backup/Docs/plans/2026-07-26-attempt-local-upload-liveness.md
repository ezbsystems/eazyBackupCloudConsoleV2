# Attempt-local Upload Liveness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent active retry-local hashing from being reaped as stale while preserving monotonic customer-visible progress counters.

**Architecture:** Continue storing public run counters as high-water values in `ms365_backup_runs`. Store the latest raw worker counters for the current `started_at` and worker phase in `stats_json`; use increases in that attempt-local snapshot as an additional `last_progress_at` signal. Identical snapshots remain silent so genuine upload wedges are still detected.

**Tech Stack:** PHP 8.2, WHMCS Capsule, MySQL JSON text in `ms365_backup_runs.stats_json`, existing standalone PHP regression tests.

## Global Constraints

- Do not add a schema migration or change the worker API payload.
- Public `items_done`, `bytes_hashed`, and `bytes_uploaded` remain monotonic.
- Upload `no_progress`/heartbeat payloads must not refresh `last_progress_at`.
- Retain session `b86288` instrumentation until post-fix production logs prove success and the operator confirms the issue is resolved.
- All MS365 changes are committed and pushed to `origin/main`, then deployed with `modules/addons/ms365backup/bin/deploy-production.sh`.

---

### Task 1: Track attempt-local progress below historical high-water counters

**Files:**
- Modify: `modules/addons/ms365backup/tests/ms365_batch_progress_liveness_test.php`
- Modify: `modules/addons/ms365backup/lib/Ms365Backup/Ms365RestoreWorkerHooks.php`
- Modify: `modules/addons/ms365backup/ms365backup.php`
- Modify: `modules/addons/ms365backup/Docs/PROGRESS.md`

**Interfaces:**
- Consumes: raw worker fields `phase`, `items_done`, `bytes_hashed`, `bytes_uploaded`; run fields `started_at`, `stats_json`, and public high-water counters.
- Produces: internal `stats_json` keys `attempt_progress_started_at`, `attempt_progress_phase`, `attempt_items_done`, `attempt_bytes_hashed`, and `attempt_bytes_uploaded`.

- [ ] **Step 1: Write the failing preserved-counter retry test**

Add this case before the `finally` block in `ms365_batch_progress_liveness_test.php`:

```php
$retryCounterRunId = test_uuid('retry-local-counter-liveness');
$runIds[] = $retryCounterRunId;
$retryStartedAt = $now - 60;
insertTestRun($retryCounterRunId, [
    'phase' => 'upload',
    'items_done' => 500000,
    'items_total' => 500000,
    'bytes_hashed' => 120000000000,
    'bytes_uploaded' => 10000000,
    'started_at' => $retryStartedAt,
    'last_progress_at' => $now - 900,
    'updated_at' => $now - 900,
]);
$lowerRetrySample = [
    'phase' => 'kopia_upload',
    'items_done' => 4000,
    'items_total' => 14000,
    'bytes_hashed' => 15000000000,
    'bytes_uploaded' => 500000,
    'message' => 'Uploading snapshot',
];
Ms365RestoreWorkerHooks::onProgress($retryCounterRunId, $lowerRetrySample);
Capsule::table('ms365_backup_runs')->where('id', $retryCounterRunId)->update([
    'last_progress_at' => $now - 900,
]);

$growingRetrySample = $lowerRetrySample;
$growingRetrySample['items_done'] = 4200;
$growingRetrySample['bytes_hashed'] = 16000000000;
Ms365RestoreWorkerHooks::onProgress($retryCounterRunId, $growingRetrySample);
$retryAfterGrowth = BackupRunRepository::get($retryCounterRunId) ?? [];
assert_true(
    (int) ($retryAfterGrowth['bytes_hashed'] ?? 0) === 120000000000
    && (int) ($retryAfterGrowth['items_done'] ?? 0) === 500000
    && (int) ($retryAfterGrowth['last_progress_at'] ?? 0) >= $now - 5,
    'retry-local upload growth refreshes liveness below preserved high-water counters',
);

Capsule::table('ms365_backup_runs')->where('id', $retryCounterRunId)->update([
    'last_progress_at' => $now - 900,
]);
Ms365RestoreWorkerHooks::onProgress($retryCounterRunId, $growingRetrySample);
$retryAfterReplay = BackupRunRepository::get($retryCounterRunId) ?? [];
assert_true(
    (int) ($retryAfterReplay['last_progress_at'] ?? 0) < $now - 60,
    'identical retry-local upload sample does not hide a wedged upload',
);
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
php modules/addons/ms365backup/tests/ms365_batch_progress_liveness_test.php
```

Expected: FAIL at `retry-local upload growth refreshes liveness below preserved high-water counters`, because the second raw sample remains below the persisted high-water counters.

- [ ] **Step 3: Add attempt-local progress detection**

In `Ms365RestoreWorkerHooks::backupProgress()`, immediately after `$isHeartbeat` is calculated, add:

```php
$attemptProgressPatch = [];
$attemptLocalProgress = false;
if (!$isHeartbeat) {
    $attemptStartedAt = (int) ($existing['started_at'] ?? 0);
    $attemptPhase = strtolower(trim($effectivePhase));
    $sameAttempt = (int) ($existingStats['attempt_progress_started_at'] ?? 0) === $attemptStartedAt
        && (string) ($existingStats['attempt_progress_phase'] ?? '') === $attemptPhase;

    if (!$sameAttempt) {
        $attemptLocalProgress = $incomingItemsDone > 0
            || $incomingBytesHashed > 0
            || $incomingBytesUploaded > 0;
    } else {
        $attemptLocalProgress = $incomingItemsDone > (int) ($existingStats['attempt_items_done'] ?? 0)
            || $incomingBytesHashed > (int) ($existingStats['attempt_bytes_hashed'] ?? 0)
            || $incomingBytesUploaded > (int) ($existingStats['attempt_bytes_uploaded'] ?? 0);
    }

    $attemptProgressPatch = [
        'attempt_progress_started_at' => $attemptStartedAt,
        'attempt_progress_phase' => $attemptPhase,
        'attempt_items_done' => $incomingItemsDone,
        'attempt_bytes_hashed' => $incomingBytesHashed,
        'attempt_bytes_uploaded' => $incomingBytesUploaded,
    ];
}
```

Extend the existing liveness condition:

```php
if ($effectiveItemsDone > $storedItemsDone
    || $effectiveBytesHashed > $storedBytesHashed
    || $effectiveBytesUploaded > $storedBytesUploaded
    || $attemptLocalProgress) {
    $fields['last_progress_at'] = time();
}
```

Initialize the later stats patch with the attempt-local fields:

```php
$statsPatch = $attemptProgressPatch;
```

Keep all existing session `b86288` regions unchanged.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run:

```bash
php -l modules/addons/ms365backup/lib/Ms365Backup/Ms365RestoreWorkerHooks.php
php modules/addons/ms365backup/tests/ms365_batch_progress_liveness_test.php
php modules/addons/ms365backup/tests/ms365_child_abort_reaper_test.php
php modules/addons/ms365backup/tests/ms365_tenant_owner_recovery_test.php
```

Expected: syntax check succeeds and every assertion reports `OK`, including both new retry-local assertions.

- [ ] **Step 5: Version and document the fix**

Change the module version in `modules/addons/ms365backup/ms365backup.php` from `1.52.25` to `1.52.26`.

Prepend a `2026-07-26` session entry to `Docs/PROGRESS.md` stating:

```markdown
### 2026-07-26 — Retry-local upload liveness below preserved counters (PHP 1.52.26)

- **Problem:** Retried Documents shards actively rehashed below preserved prior-attempt `items_done` / byte high-water counters, so `last_progress_at` remained stale despite increasing raw worker counters.
- **Runtime proof:** Session `b86288` child `b43728c3` increased attempt-local hash bytes from ~90.7 GB to ~99.2 GB while persisted hash stayed ~111.8 GB, the lease stayed fresh, and `last_progress_at` remained fixed.
- **Fix:** Track raw counters per `started_at` + worker phase in `stats_json`; refresh liveness on attempt-local increases while retaining monotonic public counters. Identical samples remain stale-detectable.
- **Status:** Focused tests pass; pending production verification with session `b86288` instrumentation retained.
```

- [ ] **Step 6: Commit the tested fix**

```bash
git add modules/addons/ms365backup/tests/ms365_batch_progress_liveness_test.php \
  modules/addons/ms365backup/lib/Ms365Backup/Ms365RestoreWorkerHooks.php \
  modules/addons/ms365backup/ms365backup.php \
  modules/addons/ms365backup/Docs/PROGRESS.md
git commit -m "Track retry-local upload progress below preserved counters (PHP 1.52.26)."
```

Expected: one commit containing the regression, implementation, version bump, and progress note; instrumentation remains present.

---

### Task 2: Deploy and prove production liveness

**Files:**
- Read: `/var/www/eazybackup.ca/.cursor/debug-b86288.log`
- Read: production `ms365_backup_runs`, `ms365_job_queue`, and `ms365_batch_claims`

**Interfaces:**
- Consumes: session `b86288` phase and reaper diagnostics plus the new attempt-local `stats_json` keys.
- Produces: before/after evidence that active lower-than-high-water counters refresh liveness and identical samples do not.

- [ ] **Step 1: Push and deploy**

```bash
git push origin main
ssh -i /root/.ssh/whmcs_prod_root -o IdentitiesOnly=yes root@192.168.92.75 \
  "bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh"
```

Expected: deploy completes, health status is `ok`, fleet smoke checks are `OK`, and PHP-FPM reloads.

- [ ] **Step 2: Clear only the session log before verification**

Use the Cursor `delete_file` tool for:

```text
/var/www/eazybackup.ca/.cursor/debug-b86288.log
```

Do not delete or modify any other debug-session log. Because execution is on the separate production host, confirm that the next retrieved file contains only timestamps after the 1.52.26 deployment.

- [ ] **Step 3: Reproduce on the affected production batch**

Observe batch `4efc3484-ec99-453b-9fa1-41c480a4dcca` through at least one retry-local upload interval where a raw worker counter grows below the preserved public high-water value.

- [ ] **Step 4: Compare post-fix runtime evidence**

Retrieve the production session log to the exact local path and inspect it with `ReadFile`. Query active children read-only. Confirm:

- `attempt_bytes_hashed` or `attempt_bytes_uploaded` increases between posts.
- `last_progress_at` advances even while public `bytes_hashed` remains at its prior high-water value.
- Identical samples do not continuously advance `last_progress_at`.
- Reaper logs show `stale_progress=false`, `stale_lease=false`, and no `abort_age`.
- No new queue row receives `Child progress stale (live owner abort)` after deployment.

- [ ] **Step 5: Record production verification**

Update the 1.52.26 `PROGRESS.md` entry from pending to verified, including affected child prefixes and before/after `last_progress_at` evidence.

---

### Task 3: Remove diagnostics after operator confirmation

**Files:**
- Modify: `modules/addons/ms365backup/lib/Ms365Backup/Ms365BatchClaimRepository.php`
- Modify: `modules/addons/ms365backup/lib/Ms365Backup/Ms365RestoreWorkerHooks.php`
- Modify: `modules/addons/ms365backup/ms365backup.php`
- Modify: `modules/addons/ms365backup/Docs/PROGRESS.md`

**Interfaces:**
- Consumes: successful post-fix logs and explicit operator confirmation.
- Produces: production code with session `b86288` instrumentation removed while retaining attempt-local liveness logic.

- [ ] **Step 1: Remove only session `b86288` instrumentation**

Delete the `// #region agent log` blocks that write
`/var/www/eazybackup.ca/.cursor/debug-b86288.log`. Remove the diagnostic-only
`bytes_hashed` and `bytes_uploaded` additions from the reaper query select if no
non-diagnostic code uses them. Keep the 1.52.26 attempt-local tracking.

- [ ] **Step 2: Re-run focused verification**

```bash
php -l modules/addons/ms365backup/lib/Ms365Backup/Ms365BatchClaimRepository.php
php -l modules/addons/ms365backup/lib/Ms365Backup/Ms365RestoreWorkerHooks.php
php modules/addons/ms365backup/tests/ms365_batch_progress_liveness_test.php
php modules/addons/ms365backup/tests/ms365_child_abort_reaper_test.php
php modules/addons/ms365backup/tests/ms365_tenant_owner_recovery_test.php
```

Expected: all syntax and focused tests pass after diagnostics are removed.

- [ ] **Step 3: Version and document cleanup**

Bump `ms365backup.php` from `1.52.26` to `1.52.27`. Update `PROGRESS.md` to state
that session `b86288` instrumentation was removed after production proof.

- [ ] **Step 4: Commit, push, and deploy cleanup**

```bash
git add modules/addons/ms365backup/lib/Ms365Backup/Ms365BatchClaimRepository.php \
  modules/addons/ms365backup/lib/Ms365Backup/Ms365RestoreWorkerHooks.php \
  modules/addons/ms365backup/ms365backup.php \
  modules/addons/ms365backup/Docs/PROGRESS.md
git commit -m "Remove session b86288 diagnostics after upload liveness verification (PHP 1.52.27)."
git push origin main
ssh -i /root/.ssh/whmcs_prod_root -o IdentitiesOnly=yes root@192.168.92.75 \
  "bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh"
```

Expected: clean repository, deploy health `ok`, fleet smoke `OK`, and no remaining `debug-b86288` references in tracked source.
