# MS365 Backup — Agent progress log

**Purpose:** Single handoff document so the next agent knows where work stopped. Update this file at the **end of every session** (or after each meaningful milestone).

**Last updated:** 2026-06-14  
**Module version (ms365backup):** 1.17.0

---

## Session log

### 2026-06-14 — Whale-scale PHP: lease renewal, sharding, delta-per-shard, Kopia maintenance

- **Phase 0 (lease):** `WorkerLeaseService` renews `ms365_job_queue.lease_expires_at` from `Ms365RestoreWorkerHooks::onProgress`, `ms365_worker_lease.php`, and `ms365_worker_heartbeat.php` (`renewForNode`). Default `ms365_worker_lease_seconds` raised to **7200**. Fleet cron `releaseExpiredLeases` unchanged (respects renewed leases).
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
| **Kopia engine** | Go worker + Graph parallel + Kopia dedup | **Performance pass done** | Folder parallelism, delta reuse, disk staging; see `MS365_KOPIA_ENGINE.md` |

---

## Known gaps / next work (prioritized)

1. **Kopia engine staging E2E** — Set `ms365_engine_mode=kopia`, deploy 2 Proxmox LXC workers, run backup on dev tenant; verify `manifest_id` on run row and Kopia objects in `e3ms365-*` bucket.
2. **Tenant Seeder E2E** — Register seeder Entra app per `SEEDER_AZURE_SETUP.md`; run Light profile on dev tenant; verify backup picks up seeded data.
2. **Phase 4b staging E2E** — Re-test job save → run now → **live view** → run history + log modal on dev/staging tenant.
2. **Staging E2E** — Connect → inventory → backup (mail/calendar preset) on dev/staging tenant.
3. **Restore** — Granular Kopia restore UI + worker shipped; staging E2E on real tenant still required.
4. **Binary streams** — Verify OneDrive/SharePoint `content/` end-to-end in production bucket (staging checklist).
5. **Metering / billing** — Hook MS365 bucket usage into e3 billing (`E3_CLOUD_BACKUP_BILLING.md`).
6. **Admin support view** — Impersonate client tenant, re-run inventory from admin addon.
7. **Remove Comet LXD path** — `Provisioner::provisionMs365` still provisions legacy order/LXD.
8. **Platform Entra ops** — Register multi-tenant app in Azure; configure WHMCS addon settings in staging/prod.
9. **Async inventory refresh** — Large tenants may need background job instead of synchronous POST (Phase 4).
10. **Run list filters** — Fold into Phase 4b spec (API already partial).

---

## Session log

Append newest entries at the **top**.

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
