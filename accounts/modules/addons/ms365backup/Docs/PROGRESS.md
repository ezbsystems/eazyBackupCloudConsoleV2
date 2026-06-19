# MS365 Backup — Agent progress log

**Purpose:** Single handoff document so the next agent knows where work stopped. Update this file at the **end of every session** (or after each meaningful milestone).

**Last updated:** 2026-06-19  
**Module version (ms365backup):** 1.25.0

---

## Session log

### 2026-06-19 — Delta pagination guards + SharePoint list sharding

- **Go:** `PaginateDeltaOpts` reuses `paginationSession` (duplicate-page, empty-page, identical-link detection; `Outcome.Pages`/`TotalItems`); fleet release **0.2.4**.
- **Verify:** Build/publish worker **0.2.4** (not 0.2.3 — version already in `ms365_worker_releases`); bump ms365backup 1.25.0; refresh inventory on list-heavy sites.
- **Lists:** Inventory `meta.lists[]` with `item_count`; `list:{listId}` jobs at 50k+; `list:{listId}#shard:N` createdDateTime partitions at 500k+; `ListShardRangeHelper`; worker partition sync with `$filter` fallback.
- **Config:** `ms365_list_job_item_threshold`, `ms365_list_shard_item_threshold`, `ms365_list_shard_target_items`, `ms365_list_shard_max_count`.
- **Tests:** `go test ./internal/graph/... ./internal/graphsync/...`; `tests/ms365_shard_planner_test.php`; `tests/ms365_list_shard_range_test.php`.

### 2026-06-19 — Parallel SharePoint drives + batch shard auto-retry

- **Item 4 (Go):** `graph_sharepoint_drive_parallel` (default 4); parallel drive delta in `sharepoint.go` via `errgroup` + staging mutex; worker 0.2.2.
- **Item 5 (PHP):** `Ms365BatchRetryService` re-queues failed + never-started children on same batch; `ms365_batch_auto_retry_enabled` / `max_rounds`; `partial_success` aggregate; reconcile skips cancel when auto-retry rounds remain.
- **Tests:** `go test ./internal/graphsync/...`; `tests/ms365_batch_retry_test.php`; updated `ms365_batch_aggregate_test.php`.
- **Verify:** Deploy worker 0.2.2+; bump ms365backup 1.24.0 in WHMCS; trigger partial-failure batch — confirm only failed shards requeue.

### 2026-06-19 — Whale reliability (token refresh, pagination, sharding)

- **Phase 1:** `ms365_worker_graph_token.php`; `WorkerClaimService::refreshGraphTokenForRun()`; Go `graph.Client` 401 retry + proactive refresh (`graph_token_refresh_seconds`); `JobQueueRepository` only terminal-fails `graph 401 after token refresh`.
- **Phase 2:** `ms365_graph_pagination_json` + claim `graph_pagination`; Go `CapWarnContinue` for SharePoint delta; pagination warnings in run stats.
- **Phase 3:** Item-count shard triggers (`ms365_shard_item_threshold` / `target_items`); SharePoint Files → per-drive jobs when sharding enabled; inventory `meta.drives[]`; `site_id` in claim; site-scoped Kopia paths.
- **Tests:** `go test ./...`; `tests/ms365_shard_planner_test.php`.
- **Verify:** Deploy worker 0.2.1+; bump ms365backup 1.23.0 in WHMCS; refresh inventory; re-run whale batch SharePoint workloads.

### 2026-06-18 — MS365 wizard manual tenant connect

- **Wizard Step 1:** Automatic / Manual toggle. Manual mode: `REGION`, `CLIENT_ID`, `TENANT_ID`, `APP_SECRET` with Test connection + atomic Save credentials (mirrors admin dashboard).
- **Backend:** `connection_auth_mode` on `ms365_tenant_records`; `Ms365CustomerConnectService`; `ms365_connect_test.php` / `ms365_connect_save.php`; `TenantRecordRepository::credentials()` respects `customer_app`.
- **Files:** `upgrade_phase12_customer_app_auth.sql`, `Ms365CustomerConnectService.php`, `TenantRecordRepository.php`, `CustomerBackupService.php`, `Ms365E3Controller.php`, `ms365_job_wizard.tpl`, `ms365_job_wizard.js`.
- **Verify:** Wizard manual save with valid Entra app → connected + step 2 inventory; OAuth path regression on another user.

### 2026-06-18 — MS365 vault lifecycle & recycle bin

- **Delete job:** MS365 jobs require typed confirmation `DELETE JOB {name}`; job soft-deleted; per-job `e3ms365-*` vault moved to recycle bin for configurable grace (default 30 days, addon setting `ms365_vault_recycle_grace_days`).
- **Teardown:** `accounts/crons/ms365_vault_recycle_teardown.php` queues expired vaults into `s3_delete_buckets`; existing `s3deletebucket.php` performs physical delete.
- **UI:** User detail Vaults tab shows active MS365 vaults (read-only) + recycle bin with grace countdown; early-deletion request API (admin approval manual in v1).
- **Audit:** `s3_cloudbackup_audit_events`; optional owner email via `ms365_vault_delete_email_template`.
- **Owner:** `cloudstorage` (`Ms365VaultLifecycleService`, `Ms365VaultNotificationService`); no Kopia `retention_apply` on job delete (data restorable during grace).
- **Verify:** Delete MS365 job with correct phrase → vault `recycle_status=recycle`; vault in recycle UI; cron queues delete after `recycle_teardown_at`; configure email template + ops email for notifications.

### 2026-06-17 — Kopia retention + bucket-per-job

- **Storage:** New MS365 jobs provision dedicated `e3ms365-{jobHash}` buckets via `Ms365StorageBootstrapService::ensureForJob`; legacy jobs keep shared tenant bucket (grandfathered v1 repo password).
- **Retention:** `Ms365RetentionTierPolicyService` maps wizard tiers 1y–7y to Comet policies (30 daily + weekly); `retention_json` on jobs; `Ms365KopiaRepoOperationService` enqueues `retention_apply` + `maintenance_quick`.
- **Worker:** `ms365-backup-worker` 0.2.0 polls `ms365_worker_maintenance_claim.php`, runs Kopia retention apply + maintenance, reports via `_complete.php`.
- **Schema:** `upgrade_phase11_job_bucket_retention.sql` — `ms365_delta_state.e3_job_id` for per-job incrementals.
- **Backfill:** `bin/ms365_enqueue_retention.php` for one-time retention enqueue.
- **Verify:** Activate ms365backup 1.21.0 (runs SQL upgrade); deploy worker 0.2.0; create new job → new bucket; run backup → batch success enqueues retention; worker completes op with `deleted_count`.

### 2026-06-17 — Graph delta + pagination + calendar tiers (Go worker)

- **Phase A:** Ported PHP `PaginationMonitor` to Go `internal/graph/pagination.go`; wired `PaginateOpts` / `PaginateDeltaOpts` with loop detection, duplicate-page wedge, empty-page guard, 500-page cap.
- **Phase B:** Persist delta tokens for contacts, tasks, teams via `ms365_delta_state` round-trip; `paginateDeltaResilient` handles 410 reset.
- **Phase C:** Replaced bare `calendar.go` paginate with tiered inventory (watermark incremental → page-size ladder → `createdDateTime` partitions); per-calendar state in `ms365_delta_state`; series/attachment enrichment.
- **Phase D:** Directory sync uses `/users/delta` + `/groups/delta`.
- **Phase G:** Mail/contacts/tasks/teams/calendar phase + per-page logs via `WorkloadRunner.RunLog` → `ms365_worker_log_lines`.
- **Planner:** Deferred — `plannerUser` delta documented in ARCHITECTURE; full bucket scan unchanged.
- **Files:** `ms365-backup-worker/internal/graph/pagination.go`, `graphsync/calendar_*.go`, `contacts.go`, `tasks.go`, `teams.go`, `directory.go`, `mail.go`, `workloads.go`, `jobs/runner.go`, `Docs/ARCHITECTURE.md`.
- **Verify:** `go test ./...` in `ms365-backup-worker`; deploy worker binary; second backup run should show near-zero contacts/tasks/teams Graph items when unchanged; calendar regression user `f01a9550-b02f-4330-944c-dc9dfffcebb8` + `verify-calendar` CLI.

### 2026-06-17 — OneDrive upload session fix (worker 0.1.33)

- **Problem:** Restore run `4a30befd…` (batch `e4bf5fd4…`) exhausted 3 attempts in ~20s with `Run exceeded max attempts`. Each attempt processed `item 3/3` in ~3s with `items_done=0`. Worker called `ms365_worker_release.php` (not fail) between attempts.
- **Root cause:** `putViaUploadSession` sent `item.size` in the `createUploadSession` body. Graph returns **400 invalidRequest** when `size` is set on drive root upload sessions (verified via curl + live restore smoke). All three files failed instantly on upload. The 0.1.32 `Release` fallback on failed `Fail` API re-queued the job immediately, burning through max attempts before a real error surfaced.
- **Fix (0.1.33):** Omit `size` from `createUploadSession` request (size still sent in chunk `Content-Range` headers). Remove `Release` fallback from `failTerminal`. `releaseClaim()` updates `ms365_restore_runs` for restore jobs (not only backup runs).
- **Verify:** Deploy 0.1.33; retry Adele Vance OneDrive restore. Expect multi-minute uploads for large files, then success.

### 2026-06-16 — OneDrive restore Graph path fix (worker 0.1.32)

- **Problem:** After 0.1.31 deploy, restore run `93c942f3…` (batch `d6dacacf…`) still failed with orphan message. Progress reached `item 3/3` in ~3s (`items_done=0`) then silence until ~205s orphan fail. Not OOM — streaming extract worked.
- **Root cause:** `restoreDriveFileStream` built invalid Graph URLs: `root:file.deb:/content` instead of required `root:/file.deb:/content` (missing `/` after `root:`). All three uploads failed immediately; worker never recorded terminal fail/complete (no `ms365_worker_fail` access-log hits), so heartbeat orphan logic marked the run ~180s after claim.
- **Fix (0.1.32):** `graphDriveItemPath()` for correct `root:/…` addressing on GET (skip) and PUT/upload session; error logged via `RunLogf` before terminal callback; `failTerminal` message truncation + `Release` fallback; terminal HTTP timeout 2m for large upload completion.
- **Verify:** Deploy 0.1.32; retry Adele Vance OneDrive restore. Expect multi-minute upload for large `.deb`/`.exe`, then success. On Graph errors, UI should show real message (not orphan timeout).

### 2026-06-16 — OneDrive restore OOM fix (worker 0.1.31)

- **Problem:** Restore of 3 OneDrive root files failed with `Restore worker stopped responding before reporting completion` (run `88df4071…`, batch `249cf8ae…`). Worker logged only `starting restore` then exited ~200s later; phase stuck at `restore_graph` with `items_done=0`.
- **Root cause:** Snapshot files are large (`cometd_26.4.2_amd64.deb` ~325 MiB, `JellyfinMediaPlayer….exe` ~149 MiB, `MediaCreationTool_22H2.exe` ~19 MiB). Restore path buffered each file fully in memory via `kopia.Extract` + `PutBytes`, OOM-killing the worker before it could report fail/complete.
- **Fix (0.1.31):** Stream OneDrive/drive restores — `kopia.Pool.ExtractReader`, `graphrestore.ContentFetcher` with `Stream`, `restoreDriveFileStream` via Graph upload sessions (`PutStream`). Mail/calendar JSON restores still use byte payloads.
- **Verify:** Deploy 0.1.31 to fleet; retry Adele Vance OneDrive restore for the 3 root files. Expect progress through `restore_graph` and success (or skip if files already exist).

### 2026-06-16 — OneDrive root heal verification (worker 0.1.28)

- **0.1.27 run `fe7940b2` still missing root files:** Snapshot `667ba8b5…` browse shows only `SeederData` under `onedrive/content`. Graph API live check confirms 3 root files (`cometd_26.4.2_amd64.deb`, `JellyfinMediaPlayer-….exe`, `MediaCreationTool_22H2.exe`) at `/drives/{id}/root/children`. `healOneDriveRootFiles()` logic verified via live Graph test (adds all 3 when overlay has only SeederData).
- **Timing:** Worker nodes reported `0.1.27` heartbeat *after* child run `00e7e2e7…` finished — backup likely executed on pre-heal binary despite user deploying 0.1.27 label.
- **Fix (0.1.28):** Post-heal `countMissingOneDriveRootFiles()` fails the run if any Graph root files are still absent from overlay (no silent success). Heal relocates items via `ItemPath`/`RemoveByItemID` when misrouted. Removed incorrect `driveID = GraphID` fallback in workloads (empty drive id now resolves via `/users/{id}/drive`).
- **Verify:** Deploy 0.1.28; re-run Adele Vance backup. Expect large `bytes_hashed`/`bytes_uploaded` for root `.exe` blobs; restore browse lists root files beside `SeederData`.

### 2026-06-16 — OneDrive root heal pass (worker 0.1.27)

- **Follow-up:** After 0.1.26 deploy, restore browse still showed only `SeederData` (no root `.exe`/`.deb`). Snapshot browse of manifest `94d4d616…` confirmed zero root-level files in Kopia; `content/drives/…/root:` subtree gone but root files never recaptured.
- **Cause:** Incremental OneDrive delta returned no changes for the 3 root files (delta token already past them). `shouldForceOneDriveFullResync` did not run because misrouted `content/drives/` had no file entries in prior overlay (only empty dirs), and `HasPathPrefix(user onedrive)` was true via `SeederData`. Path fix alone does not retroactively fetch skipped items.
- **Fix (0.1.27):** `healOneDriveRootFiles()` — after each OneDrive sync, lists `/drives/{id}/root/children` and adds any files missing from overlay at corrected `driveContentPath`. `OverlayBuilder.HasPath()` for exact-path checks. Stats key `root_heal`.
- **Verify:** Deploy 0.1.27; re-run backup for Adele Vance. Expect non-trivial Kopia upload if root file blobs were never stored; restore browse should list root files beside `SeederData`.

### 2026-06-16 — OneDrive root-level file path fix (worker 0.1.26)

- **Problem:** Root-level OneDrive files (e.g. `.deb`/`.exe` in drive root) missing from Kopia snapshot and restore browse; `SeederData` subfolder files present. Restore UI was faithful — not a browse bug.
- **Root cause:** `driveRelativePath()` treated `parentReference.path` `/drives/{id}/root:` as relative path `drives/{id}/root:` (no `":/"` after colon), storing root files under bogus `…/onedrive/content/drives/{id}/root:/` instead of `…/onedrive/content/{name}`. Incremental delta never re-emitted missing files; `shouldForceOneDriveFullResync` was suppressed once any OneDrive file existed under user prefix.
- **Fix (Go worker 0.1.26):** `driveRelativePath` strips relative path after first `:` (Graph `root:` sentinel). `shouldForceOneDriveFullResync` forces full resync when misrouted `…/onedrive/content/drives` subtree exists in prior overlay; full resync removes legacy + misrouted prefixes. Regression tests in `paths_test.go`, `onedrive_resync_test.go`.
- **Verify:** Deploy `ms365-backup-worker` 0.1.26 to fleet; re-run backup for affected user (incremental OK — auto full resync expected). Restore browse should show root files directly under OneDrive (no `drives/…/root:` nesting). Removed debug instrumentation from PHP browse services; browse cache key bumped to `v8-onedrive-root-path-fix`.
- **Files:** `ms365-backup-worker/internal/graphsync/paths.go`, `onedrive.go`, `paths_test.go`, `onedrive_resync_test.go`, `Makefile` (0.1.26).

### 2026-06-16 — OneDrive granular file browse in restore wizard

- **Problem:** Expanding OneDrive in restore browse showed no children; users could only select the entire OneDrive workload.
- **Cause:** Synthetic path stopped at `drives/{id}` (files live under `…/content/`); missing path errors returned empty lists; Go/PHP browse labels hid some drive entries.
- **Fix:** OneDrive shortcut opens `…/drives/{id}/content`; drive content path alias + auto-descend; drive-aware labels in `RestoreTreeBrowseService` and `browse_labels.go`; restore wizard shows file size on file nodes. Rebuilt `ms365-backup-worker` binary.

### 2026-06-16 — Restore wizard workload parity with backup job UI

- **Problem:** Restore browse tree under users showed Mail/Calendar/Contacts/Tasks but not OneDrive; Teams/Groups/SharePoint workloads used incomplete or missing synthetic shortcuts.
- **Fix:** `RestoreTreeBrowseService` scope-aware workload shortcuts — OneDrive via `_drive_id` / logical sources; SharePoint Files/Lists; Teams Metadata/Messages; Groups Mail/Calendar; sectioned restore tree in `ms365_restore_wizard` matching backup inventory categories.
- **API:** `Ms365E3Controller::browseRestoreSnapshot` aggregates shard child runs and returns `section_key` / `resource_type` for root nodes.

### 2026-06-16 — Backup engine performance (Kopia pool, skip unchanged, coalesce OneDrive)

- **Problem:** Tiny dev-tenant backups spent minutes per user in `graph_sync` / `user_onedrive` despite few items; ~2 min fixed overhead per resource from cold Kopia repo open with no cache.
- **Go worker:** Persistent per-repo config + content/metadata cache (`kopia/options.go`, `repository.go`); warm `kopia.Pool` reused across runs (`pool.go`, `scheduler.go`, `runner.go`); skip snapshot when incremental overlay has zero changes (`graphfs/overlay.go` `HasChanges()`); per-phase timing logs; Graph global concurrency limiter + default 8 parallel requests + adaptive concurrency default-on (`graph/limiter.go`, `config.go`); coalesced OneDrive on `user:` runs (`workloads.go`, `onedrive.go`, `api/client.go` `drive_id`).
- **PHP:** `BackupPlanner` merges user OneDrive into `user:` physical job; `BackupRunRepository` persists `_drive_id` in scope; `WorkerClaimService` emits `drive_id`, enables `onedrive` workload on user runs, merges delta states from prior `drive:` keys.
- **Verify:** `go build ./...` && `go test ./...` pass; publish new worker release and roll to one fleet node; re-run dev tenant backup — expect per-resource seconds not minutes, `no_changes` on unchanged incrementals, low `graph_429_hits`.

### 2026-06-16 — Orphan claim recovery fix

- **Problem:** Batch backups stuck at 1% `graph_sync` while workers reported `current_load=0`; tenant concurrency saturated; queued OneDrive runs never started.
- **Root cause:** `releaseOrphanedClaimsForNode` only matched `percent=0` / empty `phase`; heartbeat `renewForNode` extended leases for idle workers.
- **Fix:** Orphan detection uses stale `updated_at` (+ `claimed_at`); heartbeat renews leases only when `load > 0`; `requeueRuns` resets progress and syncs batch; public `requeueBackupRuns` / `releaseOrphanedClaimsForAllNodes`; `fleet_release_leases` returns `orphans_requeued`.
- **Ops:** Re-queued stuck runs in batch `d762f0b2-e866-4aa7-862f-3d95a88240d1`.
- **Docs:** `MS365_WORKER_FLEET.md` orphan recovery section.

### 2026-06-15 — Kopia-only engine + file backup gaps closed

- **Removed PHP backup execution:** Deleted `BackupOrchestrator`, all `*BackupEngine.php` / `*BackupService.php` graph sync classes, `bin/ms365_backup.php`, `bin/ms365_queue_worker.php`. Admin CLI → `bin/ms365_admin.php`.
- **Kopia-only control plane:** `Ms365EngineConfig` hardcodes `kopia`; `WorkerSpawner` enqueue-only; `upgrade_phase10_kopia_only.sql`; removed engine mode WHMCS dropdown.
- **Go worker file gaps:** `sharepoint_lists.go`, SharePoint files delta per drive, drive/site shard filtering (`shard.go`), scope-aware `workloads.go` + `WorkerClaimService::workloadsForRun` (`sharepoint` / `sharepoint_lists` split).
- **Ops:** Extended `ms365_fleet_smoke.php`; added `Docs/KOPIA_FILE_BACKUP_E2E.md`. Fleet smoke: 2 active nodes, 50 Kopia successes / 7d.
- **Verify:** `go test ./...` in `ms365-backup-worker`; rebuild worker binary for fleet release.
- **Next:** Publish new worker release (0.1.17+); run `KOPIA_FILE_BACKUP_E2E.md` file-focused checklist on staging tenant.

### 2026-06-14 — e3 job wizard hierarchical inventory selection

- **UI:** Step 2 uses expandable tree (restore-wizard styling); OneDrive nested under users; per-resource sub-checkboxes for Mail, Calendar, Contacts, Tasks, Files, Lists, Teams scopes, channels, planner plans.
- **Frontend:** `ms365_job_selection.js` builds trees, tri-state parents, `buildSavePayload` / `hydrateFromSavedJob`; wizard calls `ms365_job_plan.php` for dedup warnings.
- **Backend:** `BackupScope::fromAuthoritativeOverride`, extended `forResourceType` defaults, `CustomerSelectionCodec`, `scope_overrides` persisted on jobs and passed through scheduler / `startCustomBackup`.
- **Files:** `ms365_job_wizard.tpl/js/css`, `ms365_job_selection.js`, `ms365_job_plan.php`, `CustomerSelectionCodec.php`, `BackupScope.php`, `BackupPlanner.php`, `Ms365CustomerJobService.php`, `CustomerBackupService.php`, `Ms365JobScheduler.php`.
- **Next:** Staging E2E — partial user selection (OneDrive only), SharePoint Files-only, team+site dedup warning, edit legacy job hydration.

### 2026-06-14 — Whale-scale PHP: lease renewal, sharding, delta-per-shard, Kopia maintenance

- **Phase 0 (lease):** `WorkerLeaseService` renews `ms365_job_queue.lease_expires_at` from `Ms365RestoreWorkerHooks::onProgress` and `ms365_worker_lease.php`. Heartbeat `renewForNode` runs only when `current_load > 0` (2026-06-16). Orphan release re-queues stale running claims when worker is idle (`releaseOrphanedClaimsForNode`, 120s). Default `ms365_worker_lease_seconds` **7200**.
- **Phase 2 (sharding):** `ResourceShardPlanner` + `PhysicalKeyHelper` split large drives/sites (`drive:{id}#shard:{n}`) and mailboxes (`user:{id}#mail:{folderId}`) when inventory `size_bytes` ≥ threshold (default 100 GiB). `WorkerClaimService::buildRunPayload` emits `parent_physical_key`, `kopia_source_path`, `shard` object. `ShardRunAggregateService` groups shard child runs for restore snapshot list + browse base key.
- **Phase 3 (delta):** `DeltaStateRepository::advanceOnShardSuccess` — delta tokens keyed by per-shard `physical_key`, written only on successful shard completion (not on progress).
- **Phase 4 (maintenance):** `Ms365KopiaMaintenanceService` + weekly `crons/ms365_kopia_maintenance.php`; worker APIs `ms365_worker_maintenance_claim.php` / `_complete.php`; batch finalize enqueues `maintenance_quick` when due.
- **Config:** `ms365_sharding_enabled`, `ms365_shard_threshold_bytes`, `ms365_shard_target_bytes`, `ms365_shard_max_count`, `ms365_kopia_maintenance_interval_days`.
- **Schema:** `sql/upgrade_phase9_whale_scale.sql` (documented; no new columns — shards encoded in `physical_key`).
- **Verify:** `php -l` on all touched PHP files; Go worker must consume new claim fields (see `MS365_KOPIA_ENGINE.md`).

### 2026-06-14 — Go worker Graph parallelism + delta round-trip

- **Go worker** (`ms365-backup-worker`): hardened Graph client (Retry-After, 429/503 retry, concurrency semaphore, adaptive limit); mail delta `$select` eliminates per-message GET; bounded folder-level + per-message parallel hydration; calendar per-calendar fan-out; disk staging before Kopia snapshot (memory-safe); Graph `$batch` fallback; incremental seed from previous manifest + delta token round-trip.
- **PHP**: `DeltaStateRepository` persists delta links to `ms365_delta_state`; claim payload injects `delta_states` + `incremental_enabled`; completion saves delta states to DB and `delta_states_json` column (`upgrade_phase8_delta_states.sql`).
- **Config**: default `graph_parallel_requests=32`, `graph_folder_parallel=4`, `adaptive_concurrency` + `use_batch_fallback`.
- **Verify**: `go test ./...` in `ms365-backup-worker`; deploy worker binary; run two consecutive mailbox backups and confirm second run fetches near-zero Graph items.

---

## Roadmap status (Phases 0–5 baseline)

| Phase | Focus | Status | Notes |
|-------|--------|--------|-------|
| 0 | Product boundaries, docs, deprecate standalone client area | **Done** | `ARCHITECTURE_BOUNDARIES.md`; `ms365backup_clientarea()` redirects to e3 |
| 1 | Platform Entra OAuth + `ms365_tenant_records` consent fields | **Done** | `EntraConsentService`, `PlatformEntraConfig`, `upgrade_phase4_entra_oauth.sql` |
| 2 | `e3ms365-{token}` bucket + `CloudStorageBackupStorage` | **Done** | `Ms365StorageBootstrapService` in cloudstorage; bootstrap on consent |
| 3 | e3 UI + APIs (connect, backup preset, runs) | **MVP done** | Inventory refresh, onboarding stepper, 3 presets, inline run detail/logs |
| 4 | Queue hardening, run search, retry, health API | **Baseline done** | Per-client concurrency; `ms365_health`, `ms365_retry_run` |
| **4b** | **Unified e3 M365 UX (full client area)** | **Baseline done** | Job wizard in user detail + jobs page; per-backup-user OAuth; `MS365_E3_UI_SPEC.md` updated |
| 5 | Restore platform (Kopia granular) | **Implemented** | Restore tab + wizard; Go `graphrestore`; skip duplicates; live progress |
| 6 | Hardening / GA | **Partial** | Kopia worker fleet + Proxmox autoscale scaffold; load test script |
| **Kopia engine** | Go worker + Graph parallel + Kopia dedup | **Kopia-only (1.18)** | PHP execution removed; file lists/shard/delta in Go |

---

## Known gaps / next work (prioritized)

1. **File backup staging E2E** — Execute `Docs/KOPIA_FILE_BACKUP_E2E.md` on dev tenant (OneDrive + SP files/lists + mail attachments); confirm browse shows `content/` bytes.
2. **Publish worker release** — Build/publish Go worker with `sharepoint_lists` + shard filtering; roll fleet to new artifact.
3. **Tenant Seeder E2E** — Register seeder Entra app; run Light profile; verify backup picks up seeded files.
4. ~~**Metering / billing**~~ — MS365 billing per `MS365_BILLING_AND_STORAGE_DESIGN.md` (meter/rate cron, trial, invoice hook, Usage & Billing drawer).
5. **Admin support view** — Impersonate client tenant, re-run inventory from admin addon.
6. **Remove Comet LXD path** — `Provisioner::provisionMs365` still provisions legacy order/LXD.
7. **Async inventory refresh** — Large tenants may need background job instead of synchronous POST.
8. **Calendar verify on Kopia** — `CalendarVerifier` still reads legacy PHP layout paths; port to snapshot browse or drop.

---

## Session log

Append newest entries at the **top**.

### 2026-06-17 — Billing & storage (design implementation)

- **Storage:** Global `ms365_platform_owner` RGW user; per-backup-user `e3ms365-{guid}` buckets; `S3Billing` skips `e3ms365-*`.
- **Product:** `Ms365ProductBootstrap` + addon settings (`pid_ms365_backup`, prices, trial days); `Provisioner::provisionMs365` uses bootstrapped PID + trial.
- **Metering:** `Ms365BillingService`, `Ms365UsageMeter`, tables in `upgrade_phase7_billing.sql`, daily `accounts/crons/ms365_billing.php`.
- **Pricing:** `hooks/ms365_invoice.php` (DailyCronJob + InvoiceCreationPreEmail); `Ms365BillingTrial` + `crons/ms365_trial_check.php`.
- **UI:** MS365 job card **Usage & Billing** drawer on `e3backup_user_detail.tpl`; `api/ms365_usage.php`; admin Jobs columns for Protected / OD overage.

### 2026-06-14 — Restore browse: calendar item labels

- **Issue:** Calendar folders and events showed opaque Graph IDs / generic "Item" in restore tree.
- **Fix:** Go worker `browse_labels.go` reads event JSON for `subject`, start time, recurring/cancelled prefixes (mirrors mail labeling). Calendar folders read `_calendar.json` metadata; fallback short label for legacy snapshots. Backup now writes `_calendar.json` per calendar. Rebuilt worker binary; browse cache bumped to `v3-calendar-labels`.
- **Verified:** Events show titles like "Budget review 4", "(Recurring) Recurring Meeting #1"; legacy folders show "Calendar …SNl-AAA=" until next backup.
- **Files:** `browse_labels.go`, `calendar.go`, `restore_runner.go`, `RestoreTreeBrowseService.php`.

### 2026-06-14 — Restore browse: Calendar expand 500 fix

- **Root cause:** Restore tree synthetic paths used `.../calendars` (plural, PHP `StorageLayout`) but the Go Kopia worker stores calendar data at `.../calendar` (singular). Expanding Calendar in Step 2 hit `browse: path not found`.
- **Fix:** `RestoreTreeBrowseService` aliases `calendar` ↔ `calendars` on browse failure; returns empty list when a workload root is absent. Go worker `graphsync/calendar.go` aligned to `calendars` for new backups; `browse_labels.go` and `graphrestore/runner.go` accept both path forms.
- **Verified:** PHP test against manifest `c4f1f46a1a802aedd166bf60fc1320dd` — `/calendars` browse returns 2 calendar folders via alias to `/calendar`.
- **Files:** `RestoreTreeBrowseService.php`, `ms365-backup-worker/internal/graphsync/calendar.go`, `browse_labels.go`, `graphrestore/runner.go`.
- **Next:** Rebuild worker binary when `restore_runner.go` compile error is fixed.

### 2026-06-13 — MS365 granular restore platform (Phase 5)

- **Restore tab:** MS365 jobs list snapshots via `ms365_restore_snapshots_list.php`; Restore opens `ms365_restore_wizard`.
- **Engine:** Kopia-only async restore via Go worker `graphrestore` (mail, calendar, contacts, tasks, files, teams, planner); skip duplicates default.
- **APIs:** `ms365_restore_browse.php`, `ms365_restore_start.php`; worker hooks in `Ms365RestoreWorkerHooks`.
- **Schema:** `upgrade_phase5_restore_v2.sql` — restore batch linkage, `job_type` on queue.
- **Live:** `Ms365BatchLiveService` aggregates restore child runs; `e3backup_live.tpl` shows restore batch progress.
- **Docs:** Kopia-only engine; Azure write permissions for restore.
- **Next:** Staging E2E with `ms365_engine_mode=kopia`; verify browse CLI on production bucket.

### 2026-06-13 — MS365 live runs + unified log modal

- **Live UX:** MS365 **Run now** redirects to `view=live&run_id={batch_run_id}` (same page as agent backups).
- **Bridge:** `Ms365BatchLiveService` + `Ms365LogFormatter` aggregate child `ms365_backup_runs` / `ms365_backup_log_lines` into existing `cloudbackup_progress`, `cloudbackup_get_run_events`, `cloudbackup_get_run_logs`, `cloudbackup_cancel_run` APIs.
- **Snapshot:** `Ms365BatchRunRepository::updateLiveSnapshot()` called from `ms365_worker_progress.php` to keep parent `s3_cloudbackup_runs` progress fields current.
- **Runs page:** Removed MS365 `alert()` log bypass; **View live** + `ebE3RunModal` for all engines.
- **Cancel:** Customer cancel from live page stops MS365 child workers (`BackupRunRepository::requestCancel(..., 'user')`).
- **Files:** `Ms365BatchLiveService.php`, `Ms365LogFormatter.php`, `Ms365BatchRunRepository.php`, `cloudbackup_*.php` API branches, `e3backup_jobs_client_script.tpl`, `e3backup_runs.tpl`, `e3backup_live.php`/`tpl`, `e3backup_run_modal.js`
- **Next:** Staging E2E with multi-workload MS365 job (live progress, logs, cancel, completed log modal).

### 2026-06-10 — MS365 Kopia backup engine (architecture implementation)

- **Go worker:** New repo `/var/www/eazybackup.ca/ms365-backup-worker` — Kopia snapshot layer, `graphfs`, `graphsync` (all workloads), claim/heartbeat scheduler.
- **Control plane:** `upgrade_phase6_kopia_worker.sql`, `ms365_worker_nodes`, worker APIs (`ms365_worker_*.php`), `WorkerClaimService`, `Ms365EngineConfig`, enqueue-only `WorkerSpawner` when `engine_mode=kopia`.
- **Kopia repos:** `KopiaRepoBootstrapService` + hook in `Ms365StorageBootstrapService`; `manifest_id` on `ms365_backup_runs`.
- **Proxmox:** `ProxmoxProvisioner`, `crons/ms365_worker_fleet.php`, `deploy/proxmox/` template docs.
- **Restore bridge:** `KopiaSnapshotRestoreService`; restore orchestrator detects Kopia runs.
- **Docs:** `MS365_KOPIA_ENGINE.md`, `MS365_WORKER_FLEET.md`
- **Next:** Bump ms365backup module in WHMCS to run migration; configure worker token + Proxmox settings; build golden LXC template; staging E2E with `kopia` mode.

### 2026-06-10 — MS365 Tenant Seeder (admin dev tool)

- **Feature:** Admin **Tenant Seeder** panel (`action=seeder`) to populate dev M365 tenants with synthetic mail, calendar, contacts, tasks, OneDrive, SharePoint, and Teams data.
- **Auth:** Separate `ms365_seeder_config` + dedicated Entra app (app-only write + delegated OAuth for Teams seed user).
- **Execution:** `bin/ms365_seeder_worker.php`, profiles Light/Standard/Heavy, progress polling, run history.
- **Docs:** `Docs/SEEDER_AZURE_SETUP.md`, `Docs/TENANT_SEEDER.md`
- **Next:** Register seeder app in Azure; connect seed user; run Light profile; E2E with Discovery + Backup.

### 2026-06-09 — MS365 batch run status reconciliation

- **Bug:** Parent row in `s3_cloudbackup_runs` stayed `running` after all `ms365_backup_runs` children succeeded; `finalize()` was only called on empty-queue failure.
- **Fix:** `Ms365BatchRunRepository::syncFromChildren()` aggregates child statuses and finalizes batch; called from `BackupOrchestrator` finally, run history load, and batch log API.

### 2026-06-09 — MS365 runs page autoload, queue isolation, run redirect

- **Runs page 500:** Wrong `ms365backup_autoload.php` path in `e3backup_runs.php` (and APIs/cron). Centralized `cloudstorage/lib/Ms365BackupBootstrap.php`.
- **Extra workloads on Run now:** Queue worker could claim stale `ms365_job_queue` rows; direct spawn left rows `queued`. Now `markRunning` before exec + `markDone`/`markFailed` in CLI.
- **No redirect after Run now:** ~~MS365 jobs redirect to `view=runs&job_id=…`~~ **Fixed 2026-06-13** — redirects to `view=live&run_id={batch_run_id}`.
- **Files:** `Ms365BackupBootstrap.php`, `e3backup_runs.php`, `WorkerSpawner.php`, `JobQueueRepository.php`, `ms365_backup.php`, `e3backup_jobs_client_script.tpl`

### 2026-06-09 — Fix MS365 job run (enum + cloud worker guard)

- **Bug:** `s3_cloudbackup_jobs.source_type` / `engine` ENUMs lacked `ms365` on dev DB; MySQL stored empty strings. Run now fell through to `cloudbackup_start_run` → rclone worker → `ERR_DECRYPT_SOURCE_CONFIG`.
- **Fix:** Applied `upgrade_ms365_job_source.sql` (jobs + runs ENUMs); backfilled job row; `CloudBackupController::startRun` rejects MS365 jobs; worker skips `ms365`; job save asserts schema + saved `source_type`; JS `isMs365Job()` fallback via `schedule_json.ms365`.
- **Files:** `upgrade_ms365_job_source.sql`, `Ms365CustomerJobService.php`, `CloudBackupController.php`, `e3backup_jobs_client_script.tpl`, `e3-cloudbackup-worker/internal/db/db.go`

### 2026-06-09 — Inventory refresh progress in MS365 job wizard

- Wizard polls `GET ms365_inventory_progress.php` every 2s during refresh; shows phase message, count badges (users, sites, teams, groups), and detail line (OneDrive X of Y).
- `InventoryService::refresh()` writes `discovery/progress.json` at each discovery phase.
- **Files:** `ms365_inventory_progress.php`, `CustomerInventoryService`, `InventoryService`, `ms365_job_wizard.js`, `ms365_job_wizard.tpl`

### 2026-06-19 — Repo operation null-claim log spam fix (Go worker)

- **Bug:** `ms365_worker_maintenance_claim.php` returns `data: null` when no Kopia maintenance job is queued; `postOnce` fallback-unmarshaled the envelope into `**RepoOperation`, producing operation ID 0 and spamming `unsupported repo operation:` every poll (~5s).
- **Fix:** `decodeEnvelopeResponse()` skips unmarshal when `data` is null; `ClaimRepoOperation` and `tryRepoOperation` validate `operation_id` / `op_type`.
- **Tests:** `ms365-backup-worker/internal/api/client_test.go`

### 2026-06-18 — MS365 batch run reliability (watchdog + progress + reconciliation)

- **Root cause:** `agent_watchdog.php` treated MS365 parent rows (`engine=ms365`) like local-agent runs and set `cancel_requested` when `updated_at` was stale. Go workers only heartbeat child `ms365_backup_runs`, not the parent. `updateLiveSnapshot()` then forced parent `status=running` while children were still active, and item-weighted progress ignored 184 queued workloads → admin showed Running + ~100%.
- **Fix 1:** Exclude `engine=ms365` from agent watchdog stale-run query.
- **Fix 2:** `Ms365BatchRunRepository::isParentStatusLocked()` — terminal parent status and watchdog cancel pattern (`cancel_requested` + `finished_at`) are not overwritten by live snapshot; parent `updated_at` touched on snapshot.
- **Fix 3:** `reconcileBatchChildren()` — propagate parent cancel to queued/running children, fail stale running workloads, cancel never-started queued when batch ends; wired into `syncFromChildren`, `cancelBatch`, and admin `listJobs`.
- **Fix 4:** Multi-workload batch progress uses workload-count fraction; `resolveProgressPct()` prefers fresh aggregate over stale stored parent pct.
- **Tests:** `tests/ms365_batch_aggregate_test.php`
- **Follow-up (out of scope):** Graph 500-page delta cap, worker stale threshold tuning, concurrency for 213-workload batches.

### 2026-06-18 — MS365 scheduled overlap skip

- **Behavior:** `Ms365JobScheduler` skips a due slot when the same job already has an active MS365 **backup** batch (`Ms365JobOverlapGuard`). Active **restore** batches do not block scheduled backups.
- **History:** Each skip inserts a parent `s3_cloudbackup_runs` row (`status=warning`, `stats_json.ms365_schedule_skip`) with `error_summary` explaining the overlap.
- **UI:** Job cards and Job Logs show **Skipped** when `schedule_skipped` is set on the last run / run list row.
- **Files:** `Ms365JobOverlapGuard.php`, `Ms365BatchRunRepository::recordScheduledSkip()`, `Ms365JobScheduler.php`, `ms365_scheduled_backups.php`, `e3backup_job_list.php`, `e3backup_run_list.php`, `e3backup_jobs_client_script.tpl`, `e3backup_run_modal.js`.

### 2026-06-09 — Fix MS365 job save (selected_resource_ids)

- **Bug:** `ms365_job_save.php` used plain `json_decode` on `selected_resource_ids`; WHMCS HTML-encodes POST values (`"` → `&quot;`), so decode failed and API returned 500 / “Select at least one resource to back up.” despite a full selection in the wizard.
- **Fix:** `ms365DecodeJsonStringArray()` — same candidate chain as `cloudbackup_create_job.php` (`stripslashes`, `html_entity_decode`, trim quotes); accepts JSON string or PHP array.
- **Files:** `modules/addons/cloudstorage/api/ms365_job_save.php`
- **Next:** Re-run Phase 4b E2E job save on staging tenant; then run now + run history.

### 2026-06-04 — Phase 4b e3 MS365 job wizard (baseline)

- Per-backup-user `ms365_tenant_records.backup_user_id`; inventory path per user under tenant storage.
- Modal wizard: connect, inventory 50/50, schedule cards, retention placeholders.
- `Ms365CustomerJobService`, `Ms365ScheduleAssigner`, `Ms365JobScheduler` cron, batch runs in `s3_cloudbackup_runs`.
- APIs: `ms365_inventory.php`, `ms365_job_save/get`, `ms365_job_run_now`, `ms365_batch_run_detail`.
- UI: `ms365_job_wizard.tpl`, menu items on jobs + user detail, runs bridge in `e3backup_runs.php`.

### 2026-06-02 — Phase 3 customer MVP (complete)

- **Sprint 0:** `RunTenantContext`; `BackupOrchestrator` + CLI worker use per-tenant creds/storage; `StorageLayout` tenant-aware `readJson`/`writeJson`.
- **Sprint 1:** `CustomerInventoryService`; `ms365_inventory_refresh.php`, `ms365_inventory_status.php`; inventory card on `e3backup_ms365.tpl`.
- **Sprint 2:** `Ms365Onboarding`; `ms365_onboarding_status.php`; setup stepper; backup gated until inventory exists; auto-refresh after connect.
- **Sprint 3:** `BackupRunRepository::getForClient`; `ms365_run_detail.php`, `ms365_run_logs.php`; expandable inline run panel with log polling.
- **Sprint 4:** `CustomerPresetCatalog` (all users mail/cal, collaboration, full); updated `CustomerBackupService`; preset selector in UI.
- **Staging QA:** Manual checklist in plan § Sprint 5 — run in staging with test tenant before GA.

### 2026-06-02 — Product roadmap guide for agents

- Added **`Docs/PRODUCT_ROADMAP.md`** — vision, goals, phases 0–6, feature checklist, out of scope, agent workflow.
- **`ARCHITECTURE_BOUNDARIES.md`** kept as technical module split only; points to roadmap.
- **`PHASE3_PRD.md`** now points to roadmap; product agent prompt reads roadmap first.

### 2026-06-02 — Product roadmap Phases 0–5 (baseline)

- Implemented Entra admin consent, cloudstorage e3 MS365 page, bucket bootstrap, cloud storage adapter, queue ops, mail restore shell.
- Docs: `ARCHITECTURE_BOUNDARIES.md`, updated `PHASE3_PRD.md`, `CUSTOMER_ONBOARDING.md`, agent prompts.

---

## Quick links

| Doc | Path |
|-----|------|
| **Product roadmap (read first for goals/phases)** | `modules/addons/ms365backup/Docs/PRODUCT_ROADMAP.md` |
| Architecture boundaries | `modules/addons/ms365backup/Docs/ARCHITECTURE_BOUNDARIES.md` |
| Engine architecture | `modules/addons/ms365backup/Docs/ARCHITECTURE.md` |
| PRD summary (legacy) | `modules/addons/ms365backup/Docs/PHASE3_PRD.md` |
| Customer onboarding | `modules/addons/ms365backup/Docs/CUSTOMER_ONBOARDING.md` |
| Azure permissions | `modules/addons/ms365backup/Docs/AZURE_SETUP.md` |
| Product agent prompt | `modules/addons/ms365backup/Docs/Prompts/ms365_product_agent_prompt.md` |
| **Tenant Seeder** | `modules/addons/ms365backup/Docs/TENANT_SEEDER.md`, `Docs/SEEDER_AZURE_SETUP.md` |
| Engine-only prompt | `modules/addons/ms365backup/Docs/Prompts/ms365backup_agent_prompt.md` |
| e3 / cloudstorage docs | `modules/addons/cloudstorage/docs/` |
| UI style (required) | `modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` |
| **Phase 4b UI spec (draft here)** | `modules/addons/ms365backup/Docs/MS365_E3_UI_SPEC.md` |
| Phase 4b UI agent prompt | `modules/addons/ms365backup/Docs/Prompts/ms365_e3_ui_agent_prompt.md` |
