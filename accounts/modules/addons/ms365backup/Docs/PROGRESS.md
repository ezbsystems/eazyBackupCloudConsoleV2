# MS365 Backup — Agent progress log

**Purpose:** Single handoff document so the next agent knows where work stopped. Update this file at the **end of every session** (or after each meaningful milestone).

**Last updated:** 2026-06-26  
**Module version (ms365backup):** 1.52.0  
**Worker version (ms365-backup-worker):** 0.3.34

---

## Session log

### 2026-06-26 — Archive human-readable export (worker 0.3.34)

- **Archive export pipeline:** New `MetadataIndex` + `ZipPathResolver` prefetch sidecars from Kopia and
  emit human-readable zip paths for mail, calendar, contacts, OneDrive, SharePoint, Teams, groups,
  Planner, and OneNote. Collision-safe `sanitizeZipSegment()` for cross-platform archives.
- **Mail → EML:** Graph message JSON converted to RFC 5322 MIME (`multipart/alternative` body,
  `multipart/mixed` when attachments present). Standalone `attachments/` files skipped when embedded
  in parent `.eml`.
- **Calendar → ICS:** Graph event/series JSON converted to `.ics` via `github.com/emersion/go-ical`;
  `attachments.json` sidecars skipped.
- **Backup metadata sidecars (backward-compatible):** `_site.json` (SharePoint), expanded team
  `metadata.json` (displayName + channels), contacts `_folder.json`, planner `_plan.json` /
  `_bucket.json`, OneNote `_notebook.json` / `_section.json`.
- **Tests:** `go test ./internal/archive/...` green; graphsync unit tests (`-short`) green.
- **Limitations (v1):** Old snapshots without directory sync or new sidecars fall back to GUID
  segments; inline Graph item attachments not embedded; calendar ICS has metadata-only attachments;
  very large mail messages (>16 MB JSON) still stream as raw content without EML transform.

### 2026-06-25 — Tenant-owner claim Phases 2 + 5 (Go batch runner + PHP cleanup)

- **Phase 2 (worker 0.3.27):** Go `BatchRunner` + scheduler claims `ms365_worker_batch_claim.php`; coalesced
  `BatchProgress`/`BatchComplete`/`BatchRelease`; restore stays per-run `Claim`. Tests green in
  `internal/jobs/`.
- **Phase 5 (PHP 1.52.0):** Removed per-child backup reaper suite (`releaseOrphanedClaimsFor*`,
  `reconcileZombieRuns`, `reconcileExhaustedRunningClaims`, `recoverStaleRunning` from claim path,
  `countRunningForTenant`, backup fair-claim pool, per-child tenant `GET_LOCK` in
  `WorkerClaimService`). Removed throttle-shield apparatus from `Ms365BatchRunRepository`
  (`shouldReapRunningChild`, `isThrottledWaitingAlive*`, `shouldSkipThrottleReaper`,
  `countsAgainstTenantWorkloadCap`). Deleted `Ms365EngineConfig::perTenantMaxConcurrentWorkloads()`.
  `GraphTenantBudgetService::workerShare` returns full tenant ceiling (advisory only).
  Per-run worker endpoints (`progress`/`complete`/`fail`/`release`) are **restore-only**; backup uses
  batch endpoints. Fleet cron + `fleet_release_leases` call `Ms365BatchClaimRepository::reapStaleBatches()`.
  Deleted `ms365_tenant_throttle_liveness_test.php`, `ms365_reaper_throttle_test.php`,
  `ms365_exhausted_claim_liveness_test.php`.
- **Phase 3 rollout (ops, not executed here):** Deploy worker **0.3.27** to **one** canary fleet node via
  Deployments tab; leave other nodes on prior binary until soak passes. Run one whale tenant batch + a
  multi-tenant mix. Verify: zero `Recovering this workload`, zero `Run is not active.`, exactly one batch
  lease/heartbeat in `ms365_batch_claims`, progress POST volume down ≥10× vs per-child path, tail-end
  completes with no stranded `Queued`.
- **Phase 4 rollout (ops, not executed here):** Roll **0.3.27** fleet-wide from Deployments tab; soak
  several whale + tail-end runs; monitor Worker Fleet dashboard (queue depth, batch claim heartbeats).

### 2026-06-25 — Tenant-owner claim Phases 0–1 (PHP control plane cutover)

- **Phase 0:** Migration `sql/upgrade_phase22_tenant_owner.sql` creates `ms365_batch_claims` (batch lease,
  heartbeat, attempts, `running_tenant_key` unique index for one running batch per tenant). Module version
  **1.51.0**. `Ms365EngineConfig`: `batchHeartbeatGapSeconds()` (180), `maxBatchesPerNode()` (1),
  `batchMaxAttempts()` (5).
- **Phase 1 (direct cutover, no feature flag):** New `Ms365BatchClaimRepository` (enqueue, atomic
  `claimForNode` under per-tenant `GET_LOCK`, renew/progress/complete/fail/release, `reapStaleBatches` with
  heartbeat-gap + lease backstop). `WorkerClaimService::claimNextBatch` + `buildBatchPayload` with
  `children[]`; `claimNext` is **restore-only**; backup workers use `ms365_worker_batch_claim.php`.
  `CustomerBackupService::startCustomBackup` enqueues batch claim after child queue rows. Batch API endpoints:
  `ms365_worker_batch_claim/progress/complete/release.php`. `WorkerLeaseService::renewForBatch`;
  `Ms365RestoreWorkerHooks::onBatchProgress/onBatchComplete` (per-child progress without per-child lease
  renewal). `refreshGraphTokenForRun` authorizes by live batch lease; inactive runs return soft
  `retry_after` instead of 500. `GraphTenantBudgetService::workerShare` returns full tenant ceiling (no
  division). Tests: `tests/ms365_batch_claim_test.php`.
- **Next:** Phase 3 canary deploy (ops) — see rollout notes above.

### 2026-06-25 — Tenant-owner redesign (design doc + plan)

- **Decision:** The fleet-of-workers-per-tenant claim model is the root cause of the recurring
  reaper/throttle/zombie firefight (the `Recovering this workload` flapping and
  `graph 401 after token refresh ... Run is not active.` races). Microsoft Graph throttling is
  per-application-per-tenant, so fanning one tenant across N workers contends for one externally-enforced
  limit that the control plane divides via DB rows and polices with unanswerable liveness guesses.
- **Plan:** Change the **claim unit from "child workload" to "tenant batch."** One worker owns an entire
  tenant's batch, runs all children in-process with its single `tenant_controller` Graph governor, emits
  one lease/heartbeat, and on loss the whole batch requeues and resumes from per-child checkpoints. This
  deletes the per-child reaper suite, the `ms365_graph_tenant_budget` division, and the threshold zoo.
- **Doc:** New **`Docs/MS365_TENANT_OWNER_REDESIGN.md`** — full as-is/to-be, data model
  (`ms365_batch_claims`), control-plane + Go worker changes, checkpoint/resume, whale handling, 6-phase
  migration plan, dev process, acceptance criteria. Feature-flagged (`ms365_claim_unit=child|batch`) for
  reversible rollout; both protocols coexist until the cleanup phase.
- **Mitigation skipped (per product owner):** product is pre-GA, so we go straight to the structural
  change rather than patching the current reaper race first.
- **Status:** Implemented — see Phases 0–2 + 5 entries above.

### 2026-06-24 — Live page UI polish (e3 MS365 batch)

- **Header:** Username links to user detail Profile tab (`#overview`) when `backup_user_route_id` is present.
- **Stats:** MS365 batch uses two-row stats grid (Workloads, Running Workloads, Processing, Items/s, Graph requests / Processed, Uploaded, Files, Folders, Elapsed); Workloads stat shows only `N / M complete`; new Running Workloads stat shows `N running · M queued`.
- **Workloads table:** Freshness in Status column is muted text (`.eb-live-workloads-freshness-text`), not a badge; stalled workloads use warning text color.
- **Details:** MS365 hides Mode/Destination; Run ID on second row below Source/Job/Started/Finished.
- **Removed:** Cloud Backup (Beta) warning alert from live page.
- **CSS:** `eb-live-stats--split`, `eb-live-stats-row`, `eb-live-stat--full`, `eb-live-details-row2`, `eb-live-workloads-freshness-text` in `tailwind.src.css` / `tailwind.css`.
- **Files:** `templates/e3backup_live.tpl`, `templates/eazyBackup/css/tailwind.src.css`, `templates/eazyBackup/css/tailwind.css`.
- **Verify:** MS365 live page — username link, two-row stats, split workload stats, details layout, no beta banner; non-MS365 live page layout unchanged.

### 2026-06-24 — Live page user context + jobs template removal

- **Live progress sidebar:** [`e3backup_live.php`](accounts/modules/addons/cloudstorage/pages/e3backup_live.php) resolves backup user from `user_id` query param or job/agent `backup_user_id`; passes `show_user_subnav`, username, and route id to shell so Users section stays expanded with user subnav (Jobs tab highlighted).
- **Live header:** Shows `Username > Job Name` in `e3backup_live.tpl`.
- **Live URLs:** `user_id` appended when navigating from user detail, job logs, and dashboard View Live links.
- **Jobs page removed:** Deleted `e3backup_jobs.tpl` and `e3backup_jobs.php`; `view=jobs` redirects to `view=users` (query string preserved). Updated agents create-job flow, oauth redirects, dashboard/hyperv links, provisioner return URL.
- **Files:** `pages/e3backup_live.php`, `templates/e3backup_live.tpl`, `pages/e3backup_dashboard.php`, `templates/e3backup_dashboard.tpl`, `templates/partials/e3backup_jobs_client_script.tpl`, `templates/e3backup_job_logs.tpl`, `cloudstorage.php`, `templates/e3backup_agents.tpl`, oauth pages, `Provisioner.php`, others.

### 2026-06-24 — Live progress navigation (e3 Cloud Backup UI)

- **Feature:** Customers can return to the live progress page after navigating away from an in-flight backup.
- **User Detail → Jobs tab:** "View Live" link (`var(--eb-info-text)`) beside Running/Starting/Queued last-run status when `last_run.run_id` is present.
- **Job Logs page:** Running rows show blue **View Live** in the actions column (replaces View log); row click routes to live page for active runs.
- **API:** `e3backup_job_list.php` now includes `run_id` on each job's `last_run` object (UUID + legacy SQL paths).
- **Files:** `api/e3backup_job_list.php`, `templates/partials/e3backup_jobs_client_script.tpl`, `templates/e3backup_user_detail.tpl`, `templates/e3backup_job_logs.tpl`.
- **Verify:** Start manual backup → leave live page → confirm View Live from Jobs tab and Job Logs for the same `run_id`.

### 2026-06-24 — Dual fleet worker deployment (PHP 1.50.0)

- **Feature:** Dev WHMCS remains build/deploy console; prod workers register/heartbeat against prod WHMCS (`192.168.92.75/accounts`).
- **Core:** `FleetContext`, `FleetRemoteAuth`, `FleetRemoteClient`, `FleetFacade`, `ReleaseSyncService`, `FleetProvisionService`; prod M2M endpoint `pages/admin/fleet_remote.php`.
- **UI:** Dev fleet target selector (development|production); Builds tab hidden on production server; `fleet` param on all fleet API calls.
- **Proxmox:** Production scale-up calls prod `fleet_provision_prepare` first; injects prod `MS365_WORKER_API_BASE` in `environment.conf`.
- **Release sync:** Push via `fleet_release_upsert` after build publish; optional prod cron `ms365_worker_release_sync.php`.
- **Settings:** `ms365_server_environment`, `ms365_production_system_url`, `ms365_fleet_deploy_shared_secret`, `ms365_production_release_sync_enabled`, `ms365_auto_sync_release_to_prod`.
- **Docs:** `MS365_WORKER_FLEET.md`, `ARCHITECTURE_BOUNDARIES.md`; smoke test extended in `bin/ms365_fleet_smoke.php`.

### 2026-06-24 — Per-workload throttle shield + zombie recovery (PHP 1.49.4)

- **Symptom:** Whale `39b9838c` resumed briefly then slowed; UI pacing banner (253× 429 @ 1.54% ratio) but Charity (`held=2919s`, `graph_req=0`) and Brad (`held=2153s`) blocked cap while Cathy actively paced Graph.
- **Root cause:** `isThrottledWaitingAlive` tenant-wide fallback + heartbeat `last_429_at` refresh shielded **sibling** workloads with stale per-workload `last_progress_at`. `countRunningForTenant(5)=7` vs cap=4.
- **Fix (1.49.4):** Throttle shield requires this workload's `last_progress_at` within `STALE_SILENCE_SECONDS` (1800s). Tenant-wide `recentlyThrottled` only applies while **this** run still has fresh progress. Cap excludes slots silent ≥1800s even if throttle heartbeats continue.
- **Tests:** `ms365_tenant_throttle_liveness_test.php` — zombie heartbeat excluded from cap; active pacer (fresh progress + own 429) still shielded.
- **Ops:** `reconcileBatchChildren` reaped Brad + Charity; manual requeue Brent; whale `running` 7→4, `countRunningForTenant(5)` 7→4.

### 2026-06-24 — Whale stall recurrence: stall errors requeued + cap counted zombies (PHP 1.49.3)

- **Symptom:** Batches `39b9838c` (2675 workloads, tenant 5) and `74abc070` (100 workloads, tenant 7) flat; UI `graph_sync stalled: no enumeration progress for 2700s`, `Recovering this workload`, `stale running slots blocking tenant cap`; 5/4 tenant slots occupied.
- **Evidence (live DB):**
  - Al Caron `attempts=5/5`, queue+run still `running`, error `graph_sync stalled: no enumeration progress for 2700s` — worker stall fired but never terminal-failed.
  - Betty Demarce `infra_requeue.count=3`, queue error `Workload stalled during Graph sync`, run still `running` — infra stall cap called `onFail` → `markFailed` **requeued** (retryable).
  - `reconcileZombieRuns` returned 0; throttle shield on Amy/Charity (`last_429_at` &lt;120s); Betty/Brad/Al not shielded but under 1800s reaper silence.
  - `countRunningForTenant(5)=5` with cap=4 blocked all new claims; fleet healthy, module 1.49.2 deployed.
  - Graph 429 **contributing** on Amy upload (`last_429_at` 31s ago) but tenant `recent_429_count=0`, `last_429_at` 7796s — not a sustained Graph storm; primary issue is **control-plane zombie slots**.
- **Root cause (code):** (1) Graph stall + infra stall messages not in `isNonRetryableError` → `onFail`/`permanentlyStalled` requeued instead of terminal fail. (2) Exhausted `attempts>=max_attempts` with queue `running` never reconciled. (3) `countRunningForTenant` counted progress-stale wedged slots against cap.
- **Fix (1.49.3):** `isNonRetryableError` adds graph_sync/kopia stall patterns; `terminalFailBackupRun()` for infra stall cap; `reconcileExhaustedRunningClaims()` in reaper slot; `countsAgainstTenantWorkloadCap()` excludes stalled (≥180s, not throttle-alive) and exhausted runs from cap.
- **Ops:** Ran `reconcileExhaustedRunningClaims` (failed Al), `terminalFailBackupRun` (Betty), `reconcileActiveBatches` — tenant-5 cap 5→2, new workloads claimed (Brandi/Brent/Brian/Ben), whale `running` resumed with fresh `held_s` &lt;10s.
- **Tests:** `ms365_non_retryable_error_test.php`, `ms365_tenant_throttle_liveness_test.php` — OK.

### 2026-06-24 — New batch `74abc070-…abec` starved: claim pool monopolization (PHP 1.49.2)

- **Symptom:** Batch `74abc070` (100 workloads, tenant 7) enqueued but 0 started; whale `39b9838c` (tenant 5) appears stalled at ~23%.
- **Evidence:** Fleet healthy (17 active nodes, HB &lt;60s, 9/162 load). Platform cap 5/200. Tenant-5 cap saturated (5 running, cap=4). New batch correctly enqueued (`ms365_job_queue` 100× `queued`). Top-50 FIFO claim pool is **100% whale/tenant-5** (queue ids 10322+); tenant-7 ids 12545+ never enter candidate set. All 50 candidates skipped at `perTenantMaxConcurrentWorkloads` gate → workers idle despite capacity.
- **Root cause:** `fetchBackupClaimCandidatesFairSql` pre-filtered global FIFO 500 rows; one backlog monopolized the pool. Not worker health, not enqueue bug, not platform cap. Whale slow due to tenant-5 Graph throttle + cap=4 (separate issue).
- **Fix (1.49.2):** Fair claim pool now takes per-batch head (`FAIR_CANDIDATE_PER_BATCH_POOL=50`) before fair-rank sort so other batches/tenants are claimable when an earlier tenant is cap-blocked.
- **Ops (immediate):** Requeue stale whale `graph_sync` slots if tenant 5 stays at 5/4; optional priority boost on `74abc070` queue rows (`priority=1`) until 1.49.2 deploys.

### 2026-06-24 (recurrence) — batch `39b9838c-…cca00` re-stalled: zombie claims + throttle shield

- **Symptom:** Batch flat again ~10 min after earlier cap-4 recovery; UI "Recovering this workload"; operator reports raising cap to 24 had no effect.
- **Evidence (live @ 10:33 UTC):**
  - Fleet **alive**: 17 nodes, heartbeats 2–30s; `current_load` sum 5 vs 4 queue-running (phantom +1 on worker-03/9021; worker-04 load=0 with 1 running claim).
  - **Cap is 4, not 24** in `tbladdonmodules` (`ms365_per_tenant_max_concurrent_workloads=4`, `ms365_per_tenant_max_concurrent=12`); PHP **1.49.0** deployed. Earlier incident intentionally lowered 24→4.
  - **4/4 tenant slots occupied**, all silent since 10:24:27 requeue wave: Christopher Schwartz (zero worker logs, zombie on worker-04); Al Caron / Ben Berlinguette (reassigned, no `starting run` on new nodes); Amy Boire (upload throttle 10:25:59 then silent).
  - **No claim loop** (0 requeue finishes in 2h); fleet cron reaped 0 zombies — `releaseOrphanedClaimsForIdleNode` skipped all four because **valid leases** (`lease_expires_at` ~11:24) even on idle nodes; `isThrottledWaitingAlive` shielded graph_sync for 1200s after tenant `last_429_at`.
  - Logs: 5 backup-log lines / 53 worker-log lines in prior 10 min, all ending 10:25:59; zero graph_requests delta on wedged children.
- **Root cause:** Tenant cap saturated by **zombie running claims** (PHP assigned workers, Go never started or cancelled without load decrement) plus **throttle-reaper shield** blocking auto-recovery for up to 30 min. Raising cap to 24 never persisted (still 4) and would re-amplify Graph throttle anyway.
- **Fix applied:**
  - **Operational:** `WorkerClaimService::requeueBackupRuns()` on the 4 stalled run IDs — slots freed; 4 new workloads claimed by 10:35:53 with fresh `graph_requests` within 34s.
  - **Code (1.49.1):** `releaseOrphanedClaimsForIdleNode` — idle node (`current_load=0`) + stale progress now reclaims even when lease is still valid (previously blocked until lease expiry). **Version bumped** in `ms365backup_config()` so WHMCS addon page reflects 1.49.1.
- **Do not raise** `ms365_per_tenant_max_concurrent_workloads` above 4 for this tenant until Graph budget governor 429 feedback is fixed.


- **Symptom:** Whole batch stalled in `graph_sync` ("Recovering this workload"); `graph_requests`/bytes/items flat. Operator had raised `ms365_per_tenant_max_concurrent_workloads` 6 → **24** with **no effect**.
- **Evidence gathered (read-only):**
  - Fleet **alive**: 17 nodes heartbeating <30s; backup-log lines flowing; leases renewed (expire ~11:16). Rejected "dead/hung fleet".
  - Deploy **confirmed**: module `1.49.0`, cap setting = 24 in `tbladdonmodules`, nodes show `max_concurrent_runs=16`. Rejected "not deployed".
  - **All 24 running children belong to ONE tenant** (`tenant_record_id=5`, azure `f2d17fb3-…`). Workloads held **up to 85 min** (`held_s=5147`) by single workers, `attempts` not climbing → genuine stall, not lease churn.
  - **Throttling active**: children logged `Throttled by Microsoft — waiting` on an exact **45s cadence** (honoring `Retry-After: 45` → re-throttle → repeat). `last_429_at` updating within 30–120s; 20/24 had no progress >10 min.
  - **Smoking gun:** `ms365_graph_tenant_budget` for the tenant was pinned at `graph_budget=96, recent_429_count=0` while the HTTP budget ceiling `ms365_per_tenant_max_concurrent` was **96**. The PHP adaptive governor was **not registering the ongoing 429s**, so the fleet never backed off; worker per-process tenant controllers held the in-flight slot during each 45s backoff and shrank `limit→1`, starving sibling workloads on the same node.
- **Root cause:** Tenant-wide Microsoft Graph throttling (hypothesis **B**) driven by gross over-concurrency against a single tenant (24 workloads × Graph budget 96), sustained because the adaptive budget governor stayed pinned at max (429 feedback gap) and the per-process held-slot backoff synchronized retries. Raising the workload cap **amplified** the throttle — hence "no difference / worse". (A, C, D rejected.)
- **Fix applied (operational, reversible):**
  - `tbladdonmodules`: `ms365_per_tenant_max_concurrent` **96 → 12**; `ms365_per_tenant_max_concurrent_workloads` **24 → 4**.
  - Reset pinned governor row: `ms365_graph_tenant_budget` for `f2d17fb3-…` → `graph_budget=12, recent_429_count=8, last_429_at=now`.
  - Requeued stalled running children (no-progress >600s, then the remaining wedged slots) — mirroring `WorkerClaimService::requeueRuns` field updates (`status=queued`, null worker/claim/lease, `scheduled_at=now`) — so the lower cap re-gates them.
- **Result:** Fleet-wide throttle waits dropped from a continuous 45s storm to ~1 per 180s. Running steady at cap=4. Formerly-wedged `60eef596` (Amy Boire) advanced `graph_sync items 1430 → upload items 1867`; logs show `Syncing from Microsoft Graph`, `graph_sync checkpoint`, `Uploading snapshot to repository`. Batch resuming normally.
- **Tests:** `ms365_tenant_throttle_liveness_test`, `ms365_batch_aggregate_test`, `ms365_graph_budget_test`, `ms365_reaper_throttle_test` — all OK (invariant `workload cap (4) < Graph budget (12)` holds).
- **Follow-up (code, not yet done):** (1) The 429→`GraphTenantBudgetService::recordTenant429` feedback path is not shrinking `graph_budget` (stayed 96 / `recent_429_count=0`) during a real storm — the governor is blind; needs a fix so budget auto-shrinks fleet-wide. (2) Worker `tenant_controller` holds the in-flight slot during the full `Retry-After` while `limit` is shrunk to 1, starving siblings; consider releasing the slot during long backoff or adding cross-process coordination. (3) Reaper `STALE_PROGRESS_SILENCE_SECONDS=1800` is slow to reclaim wedged slots that still heartbeat; consider keying on `graph_requests`/items deltas rather than `last_progress_at` alone.

### 2026-06-24 — Throughput cap + wedged graph_sync slot recovery (PHP 1.49.0)

- **Goal:** Raise per-tenant running-workload claim cap; stop node-level lease renewal from masking per-run stalls; cap infrastructure requeues for permanently wedging `graph_sync` workloads so concurrency slots free up.
- **Settings:** New WHMCS module field `ms365_per_tenant_max_concurrent_workloads` (default **24**, was code-only fallback **6**). `ms365_per_tenant_max_concurrent` remains the Graph HTTP budget ceiling.
- **PHP 1.49.0:** `Ms365EngineConfig::perTenantMaxConcurrentWorkloads()` reads the new setting (fallback 24). `WorkerLeaseService::renewForNode()` joins `ms365_backup_runs` and skips lease renewal when `last_progress_at` (or `updated_at`) is older than `STALE_SILENCE_SECONDS` (**1800s**) so reapers can reclaim wedged runs. `WorkerClaimService::requeueRuns()` tracks `stats_json.infra_requeue` — after **4** infrastructure requeues with no items/bytes/`graph_requests` improvement on `graph_sync` / `prior_snapshot`, marks the workload `error` ("Workload stalled during Graph sync") instead of infinite requeue. `Ms365BatchRunRepository::STALE_SILENCE_SECONDS` made public for lease scoping.
- **Wedge investigation (batch `39b9838c-…cca00`):** `ddbb9352` (Al Caron) and `60eef596` (Amy Boire) were `graph_sync` at ~68–71% with `last_progress_at` frozen at claim time (~82 min silence) while `graph_requests` in `stats_json` showed prior enumeration (289 / 224). Both were infrastructure-requeued ("Stale workload reconciled during batch sync" / "Stale progress (worker busy)") with worker unassigned — slots freed but workloads churn without terminal fail. Last worker logs show brief `Graph sync: onedrive` then silence; prior throttle waits (`Throttled by Microsoft — waiting`). Local `/var/www/eazybackup/ms365/_logs/worker.log` has no entries for these run IDs (fleet logs live on worker nodes). Root cause: node heartbeat renewed leases across all node claims, masking per-run `last_progress_at` stall; repeated infra requeue without progress cap allowed indefinite slot hold/churn.
- **Tests:** `ms365_batch_aggregate_test.php`, `ms365_tenant_throttle_liveness_test.php`.
- **Verify:** Deploy PHP 1.49.0; optional set `ms365_per_tenant_max_concurrent_workloads` (e.g. 24); confirm wedged `graph_sync` children lose lease renewal after 30m silence, fail after 4 no-progress infra requeues, and `countRunningForTenant` excludes >2700s-stale slots.

### 2026-06-24 — Phase-scoped throttle shield + stalled slot cap (PHP 1.48.0)

- **Goal:** Free upload-wedged workloads that held per-tenant slots for hours while SharePoint Graph jobs kept the tenant 429 signal hot; stop counting progress-stale slots against the per-tenant claim cap.
- **PHP 1.48.0:** `Ms365BatchRunRepository::isGraphBoundPhase()` — tenant-wide `recentlyThrottled()` shield only for `''` / `graph_sync` / `prior_snapshot`; upload/snapshot phases shield only on the child's own fresh `last_429_at`. `shouldReapRunningChild()` evaluates `isUploadStalled()` before throttle liveness so silent `kopia_upload` past **2700s** reaps even when the tenant is hot. `WorkerClaimService::countRunningForTenant()` excludes runs whose `last_progress_at` (or `updated_at`) is older than `STALE_UPLOAD_SECONDS`. `STALE_UPLOAD_SECONDS` exposed as public const.
- **Tests:** `ms365_reaper_throttle_test.php`, `ms365_tenant_throttle_liveness_test.php` — `kopia_upload` + hot tenant + stale own `last_429_at` is not shielded and is reapable; `graph_sync` wedge remains shielded.
- **Verify:** Deploy PHP 1.48.0; wedged upload child reaps within ~45 min of upload silence and frees a slot for queued workloads; `countRunningForTenant` no longer pins at cap with a stalled slot.

### 2026-06-23 — Unified tenant Graph congestion control (PHP 1.47.0 / worker 0.3.16)

- **Goal:** Replace fragmented per-client AIMD + tenant semaphore + cooldown park with one process-global adaptive controller per Entra tenant; PHP fleet budget sets ceiling only; acceptable steady-state 429 ratio under ~5%.
- **Worker 0.3.16:** `internal/graph/tenant_controller.go` — shared acquire/release, proportional short-debounced shrink, slot-held 429 backpressure, jittered cooldown recovery, idle decay; `graph_429_ratio` + structured controller heartbeat log; restore runner shares tenant controller.
- **PHP 1.47.0:** `GraphTenantBudgetService` additive +1 growStep, no growth while `recentlyThrottled`; parent `ms365_graph_429_ratio` / `ms365_graph_requests_total`; live UI shows throttle banner only when material (ratio ≥5% or active window).
- **Tests:** `tenant_controller_test.go`, updated `client_test.go`, `ms365_graph_budget_test.php`; `go test ./...` + PHP suite.
- **Verify:** Deploy worker 0.3.16 + PHP 1.47.0 together; large tenant — 429 ratio under target, smooth window recovery in logs, no cooldown herd, UI calm on handful of 429s.

### 2026-06-23 — Graph-sync liveness + reaper guards (PHP 1.46.0 / worker 0.3.15)

- **Goal:** Stop false "Stale progress" reaps during long silent graph_sync enumeration (no item progress, no 429s); align remaining stale-progress reaper paths with full `shouldSkipThrottleReaper` guard.
- **Worker 0.3.15:** Monotonic `graph_requests` counter on Graph client (`RequestsTotal()`); included in progress heartbeats; rising `graph_requests` counts as liveness in `stallAwareProgressFn` (no `no_progress` while paging).
- **PHP 1.46.0:** `releaseStalledClaimsForBusyNode` + `reconcileZombieRuns` stalled-leased loops use `shouldSkipThrottleReaper` (with `worker_node_id` in select); `backupProgress` bumps `last_progress_at` when `phase=graph_sync` and `graph_requests` rose (both `no_progress` and normal paths); persists `graph_requests` in `stats_json`.
- **Tests:** `progress_stall_test.go`, `graph/client_test.go`, `ms365_reaper_throttle_test.php`.
- **Verify:** Deploy worker 0.3.15 + PHP 1.46.0 together; whale batch — no "Stale progress (worker busy)" / "Stale progress reconciled" during active Graph enumeration; `last_progress_at` advances while `graph_requests` rises.

### 2026-06-23 — Reaper coverage + budget tuning (PHP 1.45.0)

- **Goal:** Close remaining control-plane reaper holes that reap throttled-but-alive runs past the 1200s cliff; tune adaptive budget floor for hammered tenants; share tenant Graph budget with restore jobs.
- **PHP 1.45.0:** `reconcileZombieRuns` exhausted select includes `tenant_record_id`; `shouldSkipThrottleReaper` guards `releaseExpiredLeases`, staleRows, orphan-children, `recoverStaleRunning`; `isWedgeStuck` honors `isThrottledWaitingAlive`; `backupProgress` refreshes `last_429_at` on `throttle_waiting` / cumulative 429 even in `no_progress` path; azure tenant id resolved via `resolvedCredentialsForRecord` everywhere; `GraphTenantBudgetService::minBudget` adaptive floor (1–2 under sustained `recent_429_count`); restore claim/progress returns `graph_tenant_budget` + `recordTenant429`.
- **Tests:** `ms365_reaper_throttle_test.php`; extended `ms365_graph_budget_test.php`.
- **Verify:** Deploy PHP 1.45.0 (+ worker 0.3.13 for `throttle_waiting` payload); whale batch — no false reaps during long Retry-After waits; restore + backup on same tenant share one budget.

### 2026-06-23 — Tenant-aware throttle liveness + workload claim cap (PHP 1.44.0)

- **Goal:** Stop progress-silence reapers from requeuing children that are alive but starved on the shared tenant Graph limiter; reduce 429 burst by separating per-tenant running-workload claim cap from the Graph HTTP budget.
- **PHP 1.44.0:** `GraphTenantBudgetService::recentlyThrottled()` reads `ms365_graph_tenant_budget.last_429_at`; `isThrottledWaitingAlive` / `FromRow` fall back to tenant-level signal (azure tenant resolved from `tenant_record_id`, cached per reconcile pass); `RECENT_THROTTLE_SECONDS` **600 → 1200**; `releaseStalledClaimsForBusyNode` + `reconcileZombieRuns` stalled-leased loops select `tenant_record_id`; new `ms365_per_tenant_max_concurrent_workloads` (default **6**) gates `claimNext` while `ms365_per_tenant_max_concurrent` (**16**) remains the Graph HTTP budget.
- **Tests:** `ms365_tenant_throttle_liveness_test.php` — tenant throttle blocks reap when per-child `last_429_at` stale; idle tenant still reaps wedge; workload cap vs HTTP budget defaults.
- **Verify:** Deploy PHP 1.44.0; re-run whale batch — no "Stale workload reconciled" / "Stale progress (worker busy)" while tenant is throttled; fewer concurrent children per tenant; steady completion instead of churn.

### 2026-06-23 — MS365 archive restore (Phase 5 enhancement; PHP 1.43.0 / worker 0.3.13)

- **Goal:** Alternative restore path — export selected snapshot items to a streamed `.zip` in the job bucket (`exports/` prefix) with presigned download instead of writing back to Graph.
- **UI:** Restore wizard step 2 **Restore method** (`tenant` vs `archive`); archive mode skips Destination; review copy + expiry note; live run page **Download archive** button calling `ms365_restore_download.php`.
- **PHP:** `upgrade_phase20_archive_restore.sql`; `RestoreJobService` archive branch (single run); `Ms365ArchiveExportService` (lifecycle + presign); `ms365_restore_download.php`; claim/complete hooks.
- **Worker 0.3.13:** `internal/archive` — recursive Kopia browse, `zip.Store` stream via `io.Pipe`, minio `PutObject` to `exports/{run_id}/…`.
- **Settings:** `ms365_archive_export_ttl_days` (default 7) drives `exports/` bucket lifecycle expiration.
- **Verify:** Deploy PHP 1.43.0 + worker 0.3.13; module upgrade (phase20 SQL); wizard archive flow; confirm lifecycle rule + presigned download; tenant restore unchanged.

### 2026-06-23 — Orphan thrash fix + completion item reconcile (PHP 1.42.0)

- **Goal:** Stop `releaseOrphanedClaimsForIdleNode` from reclaiming healthy workers with fresh leases (root cause of perpetual cancel/restart thrash on slow graph_sync / throttle waits); reconcile confusing "Complete but 226/346 items" accounting.
- **PHP 1.42.0:** `releaseOrphanedClaimsForIdleNode()` gates reclaim on expired lease + throttle-aware skip (`isThrottledWaitingAliveFromRow`); `backupComplete()` sets `items_done = items_total = max(items_done, items_total, files)`; `backupProgress()` clamps `items_done <= items_total`.
- **Tests:** `ms365_orphan_lease_test.php` — fresh-lease not reclaimed, expired-lease reclaimed, throttled-waiting skipped, completion reconcile, progress clamp.
- **Verify:** Deploy PHP 1.42.0; re-run throttled whale batch — no "Orphaned claim released (worker idle)" on healthy running workloads; completed mailboxes read coherent 100% item ratio.

### 2026-06-23 — Graph throttle resilience (PHP 1.41.0 / worker 0.3.11)

- **Goal:** Treat Graph 429 as wait-it-out (never hard-fail); stop stall reapers from requeuing throttled-but-alive runs; persist conservative per-tenant Graph budget; lower default concurrency.
- **Worker 0.3.11:** Unbounded 429 retry path (honors `Retry-After` up to 600s, separate from `maxRetries`); `User-Agent: eazyBackup-MS365-Backup/<version>`; AIMD starts at `max(2, concurrency/2)`; `ThrottleWaiting()`/`LastThrottleAt()`; tenant cooldown in `graph/limiter.go`; rising 429 counts as progress (no `NoProgress`); `"Throttled by Microsoft — waiting"` progress message; `ThrottleStallCeilingSeconds` default **0** (disabled).
- **PHP 1.41.0:** `upgrade_phase19_throttle_liveness.sql` adds `last_429_at`; reapers skip throttled-but-alive runs (600s window + fresh lease); `perTenantMaxConcurrent` default **96 → 16**; `GraphTenantBudgetService` slower decay / faster shrink.
- **Tests:** `go test ./...`; `ms365_batch_aggregate_test.php`; `ms365_graph_budget_test.php`.
- **Verify:** Module upgrade (phase19 SQL) + deploy worker **0.3.11**; re-run whale batch `fc175718-c524-412d-a11b-644fc8446be6` — stuck SharePoint sites should advance without "Stale workload reconciled" or hard 429 failures.

### 2026-06-23 — MS365 workloads live panel dedupe + styling fixes

- **Problem:** Same logical workload (e.g. SharePoint site after retries/shards) appeared on multiple table rows; workloads scroll area had extra padding/border and non-sticky header.
- **Fix:** `Ms365BatchLiveService::listWorkloadsForCustomer()` groups child runs by `resource_type` + `PhysicalKeyHelper::aggregateParentKey()`, merges status/progress, and exposes timestamped `events[]` for historical errors. `e3backup_live.tpl` renders events in the Error column. CSS: removed `eb-live-workloads-scroll` padding/border-radius/border; sticky `thead`; `eb-live-workloads-event*` classes.
- **Files:** `Ms365BatchLiveService.php`, `e3backup_live.tpl`, `tailwind.src.css`, `SEMANTIC-THEME-REFERENCE.md`
- **Verify:** PHP lint; Tailwind build; browser check run `fc175718-c524-412d-a11b-644fc8446be6` — one row per site with event history.

### 2026-06-23 — MS365 workloads live progress panel (e3 client UI)

- **Goal:** Customer-facing workloads status table on the e3 live run page for MS365 backup and restore batches — granular per-workload status, phase, error, and progress between the Beta notice and Live Logs.
- **UI:** `e3backup_live.tpl` — upgraded `#ms365WorkloadsPanel` with two-line workload cells (type + name), status badges, phase labels, wrapped errors, progress label + mini bar, active-row highlight; gated by `is_ms365_batch` only.
- **CSS:** New `eb-live-workloads-*` semantic classes in `tailwind.src.css` / compiled `tailwind.css`.
- **Backend:** `Ms365BatchLiveService::formatCustomerWorkloadError()` prefixes queue errors with `Queue: ` (admin parity).
- **Docs:** `SEMANTIC-THEME-REFERENCE.md` — workloads table section + live-page checklist item.
- **Verify:** PHP lint on `Ms365BatchLiveService.php`; template confirms MS365-only gating and Live Logs panel unchanged; Tailwind build succeeded.
- **Next:** Browser E2E on a multi-workload MS365 batch (live polling, summary line, long error wrapping).

### 2026-06-22 — Directory delta pagination guard + fail_requeue lifecycle (PHP 1.40.1 / worker 0.3.10)

- **Run 68acba53 follow-ups:** `/users/delta` returned users on page 1 then empty pages with advancing `$skiptoken` — the 3-empty-page guard aborted falsely; `fail_requeue` left child runs in `error` so `reconcileQueuedErroredRuns()` terminal-failed retries ~3 min later.
- **Worker 0.3.10:** Empty-page wedge detection only counts when `$skiptoken` does not advance (legacy behavior when token absent); advancing skip tokens on empty delta pages no longer trip the guard.
- **PHP 1.40.1:** `markFailed()` returns whether it requeued and resets child backup runs via `BackupRunRepository::resetForQueueRequeue()` (shared with batch auto-retry); `failClaimedRun` / `backupFail` only set terminal `error` when not requeued. `Ms365CustomerError` maps Graph pagination loop errors to friendly directory/generic sync messages.
- **Tests:** `pagination_test.go` (advancing skip token + same-token wedge); `ms365_non_retryable_error_test.php` (pagination customer messages).
- **Verify:** `go test ./...` in `ms365-backup-worker`; `php …/ms365_non_retryable_error_test.php`.

### 2026-06-22 — SharePoint site access probing + wizard selectability (PHP 1.40.0)

- **Goal:** Probe SharePoint site access during inventory refresh; disable inaccessible sites in job wizard; validate selections server-side; improve admin jobs observability for queue errors and workload skips.
- **WS3:** `ResourceAccessClassifier` maps Graph 403 `accessDenied` and site 404s to `unavailable` (skippable). `InventoryService::refresh()` runs `site_access` phase with `ResourceAccessService::probeSite()` per site and sets `access_checked_at`.
- **WS4:** `TenantResource::siteSelectability()` helpers; `CustomerInventoryService::loadForBackupUser` enriches sites; `CustomerSelectionCodec::validate` rejects non-selectable sites/capabilities; `BackupPlanner` warnings for legacy inaccessible selections.
- **WS5:** Job wizard JS/template/CSS — disabled but visible sites with per-capability Files/Lists disable; section note for inaccessible count.
- **WS6:** Admin jobs child detail exposes `queue_error` + `workload_skipped`; stale batch child reconcile logs as `warning` via `ProgressLogger`.
- **Tests:** `ms365_site_selectability_test.php` (classifier, selectability, codec validation, planner warnings).
- **Verify:** Run PHP tests; refresh inventory on dev tenant — Designer/inaccessible sites appear disabled in wizard.

### 2026-06-22 — Stall detection, progress-preserving reap, resume (PHP 1.39.0 / worker 0.3.7)

- **Goal:** Detect genuinely stalled child runs (even while heartbeating), reap via infrastructure requeue so re-claims resume incrementally, stop worker from masking stalls via lease renewal and 429-only "activity".
- **Schema:** `upgrade_phase18_progress_freshness.sql` — `last_progress_at` on `ms365_backup_runs` + index `(status, last_progress_at)`.
- **PHP:** `backupProgress()` sets `last_progress_at` only on strict items/bytes increases; honors `no_progress` (skip lease renew + field bump). `Ms365BatchRunRepository` reaps via `requeueBackupRuns` (not `onFail`); `isWorkerAlive` / wedge / upload / silence use `last_progress_at`. `WorkerClaimService` requeues stalled-but-leased zombies and per-run orphans when node load > 0.
- **Worker:** `ProgressUpdate.NoProgress`; `progress_stall_seconds` (default 600); `graph.throttle_stall_ceiling_seconds` (default 1800) cancels perpetual-429 no-forward-progress graph_sync.
- **Verify:** Apply phase18 migration; run PHP + Go tests; deploy worker 0.3.7; confirm stale whale shards requeue with phase/percent/delta_states preserved.

### 2026-06-22 — Manual worker fleet scaling (PHP/UI 1.34.0)

- **Goal:** Admin-driven scale up onto a chosen Proxmox node, scale down by stopping (not destroying) containers, disable cron autoscale by default, force new clones to latest release before claiming jobs.
- **Schema:** `upgrade_phase17_fleet_scaling.sql` — `proxmox_node` column; `stopped` status enum value.
- **Settings:** `ms365_worker_fleet_autoscale_enabled` (off), `ms365_worker_fleet_auto_baseline_update` (on), `proxmox_cluster_nodes`, `proxmox_template_vmid_map`.
- **Backend:** `ProxmoxProvisioner::scaleUp/stopWorker/startWorker/clusterNodes`; cross-node clone via `target` + per-node template map; `WorkerNodeRepository::stop/start/setProxmoxNode`; baseline update in `DeployService`; claim gate in `WorkerClaimService`.
- **Admin UI:** Nodes tab — Scale fleet panel, Stop/Start, PVE node column; API ops `fleet_proxmox_nodes`, `fleet_scale_up`, `fleet_node_stop`, `fleet_node_start`.
- **Verify:** Run module upgrade for phase17 migration; scale up 1 worker from UI; confirm `registering` + `proxmox_node`; stop/start cycle; stale clone gets no claim until updated; cron does not auto-clone/destroy with autoscale off.

### 2026-06-22 — Initial inventory collection fix (new backup job wizard)

- **Problem:** New MS365 backup wizard for backup user `06FEZ233X9ZS6VDN65YSVTZMSM` (internal id 20) stuck on "Discovering users and mailboxes…" with `refresh_in_progress: false` and empty counts. Existing-job inventory refresh on other users appeared fine.
- **Root cause:** `InventoryBackgroundRefresh::spawnWorker()` used `PHP_BINARY`, which under Apache PHP-FPM resolves to `php-fpm8.2` — the FPM binary prints help and exits without running the CLI script. `/tmp/ms365_inventory_refresh.log` was full of FPM help text; debug log showed `spawnWorker` success but no `ms365_customer_inventory_refresh.php:entry` for user 20. Progress API defaulted to misleading `phase: users` when `progress.json` was missing.
- **Fix:**
  - `WorkerSpawner::resolvePhpBinary()` skips FPM binaries; prefers `/usr/bin/php8.2`.
  - `InventoryBackgroundRefresh` uses shared resolver, `exec()` guard, per-user log path (`ms365_inventory_refresh_{client}_{backupUser}.log`), and bootstraps S3 bucket **before** writing `progress.json`.
  - `CustomerInventoryService::discoveryProgressForBackupUser()` returns `phase: idle` when no progress file; stale non-running phases (>10 min) flip to error.
  - Wizard `waitForInventoryRefreshComplete()` fails fast after 15s if worker never leaves `idle`.
- **Verify:** `php8.2 bin/ms365_customer_inventory_refresh.php --client-id=2574 --backup-user-id=20` → `OK … resources=75` (~20s). FPM-skip test: `resolvePhpBinary()` returns `/usr/bin/php8.2` when `PHP_BINARY` is `php-fpm8.2`.
- **Files:** `WorkerSpawner.php`, `InventoryBackgroundRefresh.php`, `CustomerInventoryService.php`, `ms365_job_wizard.js`
- **Next:** Re-test wizard flow in browser for user 20; confirm web-spawned worker writes to per-user log (not FPM help).

### 2026-06-22 — Graceful worker drain + rolling deploy (worker 0.3.6)

- **Problem:** Admin **Drain** only cordoned new claims; in-flight backups kept `current_load` flat so rolling deploys stalled on busy nodes. Workers waited passively for runs to finish instead of handing them off.
- **PHP:** `releaseClaim()` accepts `reason=drain` — infrastructure hand-off (attempt rollback, progress preserved). Heartbeat returns `data.drain` when node status is `draining` or deploy offer includes `drain`. `DeployService` no longer skips busy nodes for rolling/force; offers `drain => true` and marks target `draining`. Rolling rollout allows the active `updating` node to proceed while load > 0; only one other node may start at a time.
- **Go 0.3.6:** `UpdateOffer.Drain` + `HeartbeatResponse.Drain`; `Release(runID, reason)`; scheduler evicts via `repoPool.Drain` + `releaseAllActiveClaims("drain")` with best-effort progress checkpoint flush on standalone drain and deploy/config apply.
- **Admin UI:** **Activate** button + `fleet_node_activate` API to uncordon operator-drained nodes.
- **Deploy:** build and roll out worker **0.3.6** via fleet build runner; PHP/UI changes are live on next request.

### 2026-06-22 — DB stall with 2 active jobs: progress flood + claim/release spin (worker 0.3.5)

- **Symptom:** With only ~2 MS365 batches active, WHMCS pages crawled, admin button clicks lagged, server CPU ~30%. `SHOW PROCESSLIST` showed a convoy of `UPDATE ms365_backup_runs … phase='upload'` each stuck 1–3s in `updating` / `waiting for handler commit`.
- **Evidence:** Apache access log: **434,800** `ms365_worker_progress.php` POSTs vs **4** completes (~108k posts/run), **~4.8 POSTs/sec** with 2 jobs; **27,171 claims vs 23,372 releases** (86% of claims released, reason `release` = 74,435). Reconcile/claim queries themselves were all fast (<2ms) — the load was write/commit volume. MySQL durability: `innodb_flush_log_at_trx_commit=1` + `sync_binlog=1` (two fsyncs/commit) with low `innodb_io_capacity=200` → every commit is an fsync, so the high commit rate forms an fsync convoy that stalls all other clients (WHMCS).
- **Root cause #1 — progress flood:** Kopia's `ProgressCounter` calls `notify()` on every `HashedBytes`/`UploadedBytes` chunk; the upload/graph-sync callbacks posted to `ms365_worker_progress.php` **unthrottled**, and each POST fanned out to ~3 committed transactions (run update + lease renew + tenant budget + parent live-snapshot).
- **Root cause #2 — claim/release spin:** `Scheduler.poll()` loops while `availableSlots()>0` (slot **count**), but `canAdmit()` rejects on **resource budget** (RAM/disk/CPU). With free slots but full budget, the worker claimed the head-of-queue job, `canAdmit`-rejected, released, and `continue`d — re-claiming the same row in a tight loop (each cycle = several committed writes). Same 7 runs were claimed ~480×/hr each.
- **Fixes:**
  - **Worker (Go 0.3.5):** `newThrottledProgressSender()` coalesces high-frequency progress callbacks to ≥`progress_min_interval_seconds` (default 5s) per run; applied to graph-sync `OnProgress` and the kopia upload counter callback. First event passes through; the periodic `StartProgressHeartbeat` still renews leases. Poll loop now (a) breaks if it can't admit even a light job (no budget), and (b) breaks instead of `continue` on admit/tryStart reject, bounding claim/release to ≤1 cycle per poll tick.
  - **PHP:** `WorkerLeaseService::renewForRun()` now writes only when the lease is >60s old (conditional 0-row UPDATE = no redo/binlog), so frequent progress posts no longer each commit a lease write; the 30s node heartbeat still renews all running leases.
  - **Retention:** new `Fleet\RetentionService::prune()` (wired into `ms365_worker_fleet.php` cron) batch-deletes `ms365_run_worker_assignments` (released >7d), terminal `ms365_job_queue` rows (finished >7d), and `ms365_worker_log_lines` (>30d) — these tables were unbounded (assignments had grown to 76k, queue held 6,139 terminal rows).
- **Recommended (server, not auto-applied):** set `innodb_flush_log_at_trx_commit=2` and raise `innodb_io_capacity` (e.g. 2000 on SSD) in `my.cnf` to cut fsync pressure — a server-global durability trade-off (≤1s of commits lost only on OS crash), so left for operator approval.
- **Deploy:** rebuild + roll out worker 0.3.5 via the fleet build runner / self-update; PHP changes are live on next request; retention runs on the next fleet cron tick.

### 2026-06-22 — Worker fleet telemetry + config push (PHP/UI)

- **Workstream 2 (PHP/UI):** `upgrade_phase16_worker_telemetry.sql` adds latest CPU/RAM/disk columns on `ms365_worker_nodes` plus `ms365_worker_telemetry` history; `WorkerNodeRepository::recordTelemetry()` + `pruneTelemetryHistory()`; heartbeat persists `telemetry` object; fleet cron prunes history >48h; `FleetSummaryService` fleet aggregates; `fleet_node_telemetry` API op; Nodes tab + dashboard show per-node and fleet-wide telemetry.
- **Workstream 3 (PHP/UI):** `upgrade_phase16_worker_config.sql` adds versioned `ms365_worker_config` table + node `config_version`/`target_config_version`/`config_status` columns; `WorkerConfigService` validates YAML (rejects non-empty `worker.token` / `api.base_url`, strips per-node identity keys); admin ops `fleet_config_get`/`save`/`rollout`/`status`; `ms365_worker_config.php` token+nonce config download; heartbeat emits `config` instruction and reconciles applied version; Fleet Settings tab is a YAML editor with validate/save + node-targeted rollout (all/idle/canary).
- **Module 1.33.0** — bump triggers phase16 migrations on upgrade.
- **Pending (Go worker):** telemetry sampling in heartbeat payload; config apply + `RestartSelf` on worker side (separate workstream).

### 2026-06-21 — Parent-row live-snapshot lock convoy (high mysqld CPU during batches)

- **Symptom:** During an active MS365 batch, WHMCS mysqld CPU was pinned and admin navigation crawled. Live `SHOW PROCESSLIST` showed **39 of 52 active queries** were concurrent `UPDATE s3_cloudbackup_runs …` writing the **same aggregate values to the same parent run row**, all stuck in `updating` / `waiting for handler commit`.
- **Root cause:** `Ms365RestoreWorkerHooks::backupProgress()` called `Ms365BatchRunRepository::updateLiveSnapshot($batchRunId)` on **every worker heartbeat**. Each call scans all children (`getChildrenForBatch`), recomputes aggregates, runs ~6 `information_schema` probes (`hasColumn`), and UPDATEs the single shared parent row. With N child workloads heartbeating at once this is O(N) work ×N concurrent, all serialized on one row lock → a lock convoy that saturates mysqld. The control plane (progress/logs/heartbeats/claims for the whole fleet) lives in the WHMCS DB, so "workers do the work" still drives heavy WHMCS DB load.
- **Fix (PHP 1.31.0):**
  - `Ms365BatchRunRepository::updateLiveSnapshot()` now throttles via `claimLiveSnapshotWindow()`: a lock-free point read on the parent `updated_at` skips heartbeats inside a 3s window, then an atomic conditional UPDATE lets exactly one heartbeat per window persist the snapshot (the rest match 0 rows and return). The live UI recomputes its own aggregate per poll, so the persisted snapshot being ≤3s stale is invisible to users. No schema change (reuses `updated_at`; `hasColumn` result cached per request).
  - `Ms365BatchLiveService::aggregateEvents()` no longer calls `getBatchChildren()` twice per 2s events poll.
- **Verified live:** parent-update convoy dropped **39 → 0** across repeated processlist samples immediately after the change; remaining activity is legitimate per-child heartbeat writes (distinct rows, no contention).
- **Secondary finding — `tblerrorlog` `Array to string conversion` spam (in progress):**
  - **Done:** `TRUNCATE TABLE tblerrorlog` (was ~3.05M rows; the dominant message was `Array to string conversion` at `vendor/illuminate/support/helpers.php:171` = Laravel `data_get()` reached with an array key segment).
  - **Investigation:** No app-level `data_get(` calls exist anywhere in `accounts/` (excluding vendor) — so it is triggered by a Laravel Collection/Eloquent internal invoked with an array key. Reproduced/ruled out every MS365 hot path via probes (live UI poll `aggregateProgress`/`aggregateEvents`, `getRun`, `updateLiveSnapshot` body, worker `backupProgress` incl. `checkpoint_delta_states`, log ingestion) — **all clean**. The warning was not recurring after truncate (the batch had wound down), so it could not be caught live.
  - **Capture armed:** Added sentinel-gated, chained error handler `accounts/includes/hooks/zzz_ms365_dataget_capture.php`. Active only while `accounts/.dataget_capture_on` exists; writes de-duplicated backtraces (with request URI) to `accounts/.dataget_capture.log`, capped at 3 MB; for this specific warning it returns handled so it is NOT re-logged to `tblerrorlog` (also stops re-bloat). Sentinel is currently **armed** — the next real backup run / page activity will record the exact caller. Then: read the log, fix the source, delete the hook + sentinel.
  - Also consider caching `hasColumn`/`hasTable` probes used per heartbeat (minor, now largely mitigated by the snapshot throttle).

### 2026-06-21 — Fail-report retry classifier fed sanitized text (requeue storm on permanent errors)

- **Root cause:** The worker `reportFail` path (`ms365_worker_fail.php` → `Ms365RestoreWorkerHooks::backupFail`, and `WorkerClaimService::failClaimedRun`) ran the raw worker error through `Ms365CustomerError::message()` **before** passing it to `JobQueueRepository::markFailed()`. For any error >180 chars or containing internal markers, that returns the generic *"Something went wrong. Please try again or contact support."* — which matches none of `isNonRetryableError()`'s technical patterns (`graph 401`, `unauthorized`, `mailboxnotenabledforrestapi`, …). So **every permanent failure was treated as retryable and requeued to `max_attempts`** instead of failing fast. The 2026-06-20 mailbox-404 fix only appeared to work because that error is short (~157 chars) and passes the `looksInternal` filter, so it survived sanitization; the unit test also fed `isNonRetryableError()` raw strings, masking the integration gap. This is the source of the `fail_requeue` ×N loop seen in run `abef5a51`.
- **Fix (PHP 1.30.0):** Classify retryability on the **raw** worker error while keeping the **sanitized** message for customers:
  - `backupFail` / `failClaimedRun` now call `markFailed($runId, $message)` (raw) and still store `$customerMessage` in `ms365_backup_runs.error_message` (the only customer-facing field; the queue table is internal/ops, verified not surfaced by `ms365_runs_list`/`ms365_worker_log`).
  - `markFailed()` documented as requiring the raw error; stored queue message truncated to 500 chars.
  - `failSupersededRun` now `markTerminalFailed` (a newer run already succeeded — never retry).
  - `Ms365BatchRetryService::isEligibleForRetry` classifies on the raw queue `error_message` (falls back to the run message) so batch auto-retry doesn't re-run permanent failures.
- **Tests:** `ms365_non_retryable_error_test.php` (raw tasks 401 = terminal; asserts the sanitized form is the generic message and is NOT classifiable — guards the regression); `ms365_batch_retry_test.php` (sanitized run message + raw queue 401 = ineligible; + retryable queue error = eligible). All pass.
- **Relationship to worker 0.3.1:** The worker fix makes the *no-mailbox tasks* case a graceful skip, so it never reaches this path. This PHP fix is the broader correctness fix for **all** permanent failures (403, invalid_grant, expired token, gzip/JSON parse, etc.) that previously churned through retries.
- **Verify:** `php …/tests/ms365_non_retryable_error_test.php` and `…/ms365_batch_retry_test.php`; on a real permanent failure, queue `status=failed` (reason `fail`) on the **first** attempt — no `fail_requeue` loop — while the customer UI still shows the friendly message.

### 2026-06-21 — Tasks (To Do) 401 UnknownError graceful skip (no-mailbox users)

- **Root cause:** Extends the 2026-06-20 `MailboxNotEnabledForRESTAPI` work. For no-mailbox/unlicensed users, mail and contacts return `404 MailboxNotEnabledForRESTAPI` (skipped gracefully), but the To Do endpoint `/users/{id}/todo/lists` returns `401 Unauthorized` with an empty-message `UnknownError` body instead. `graph.IsMailboxNotEnabled()` only matched the 404 form, so `SyncTasks` hard-failed → `WorkloadRunner.Run` returned `tasks: graph 401 Unauthorized` → run failed and requeued until max attempts. Observed in run `abef5a51-f02b-497d-b4d9-0feea0e04464`: every resource skipped mail+contacts, then died on tasks within ~21s, completely failing the batch.
- **Go worker 0.3.1:** New `graph.IsMailboxUnavailable(err)` recognizes both the 404 form and the To Do/Outlook `401 + "code":"unknownerror"` form; explicitly excludes `graph 401 after token refresh` (real bad token) and other 401 error codes (e.g. missing scope) to avoid masking genuine auth failures. `WorkloadRunner.skipIfMailboxNotEnabled()` now uses it, so tasks/calendar/mail/contacts all skip gracefully and the run completes `no_changes`.
- **Tests:** `client_test.go` (`TestIsMailboxUnavailable`), `workloads_test.go` (`TestWorkloadRunnerSkipsTasksWhenMailboxNotEnabled` — reproduces the failing run via a 401 UnknownError on `/todo/lists`).
- **Verify:** `go test ./...` (all pass); build/publish worker **0.3.1** and roll out; re-run a no-mailbox tenant batch — tasks logs `tasks skipped: mailbox not enabled for REST API` (warning) and the run completes instead of requeuing. (Note: the server `isNonRetryableError()` safety net did **not** actually catch this in production — the fail-report path fed it the sanitized customer message; see the 1.30.0 entry. The worker fix removes the failure entirely; the 1.30.0 fix repairs the classifier for all other permanent errors.)

### 2026-06-21 — Whale-scale reliability (Graph AIMD, liveness reaps, tenant budget, checkpoints)

- **Root cause:** Graph 429 throttling ratcheted adaptive concurrency to 1 (`growAdaptiveLimit` never called); no `graph_sync` stall watchdog; false reaps on slow-but-heartbeating whales; delta tokens only on success; 429s invisible in live UI; fleet hammered single tenant without per-tenant Graph cap.
- **Go worker 0.3.0:** AIMD recovery in `graph/client.go` (success streak grows limit after 429 shrink); `graph_stall_watch.go` cancels wedged enumeration (429 backoff counts as activity); default `kopia.stall_seconds=2700` in `applyDefaults()`; progress heartbeats include `graph_429_hits` + `graph_adaptive_limit`; mid-run `checkpoint_delta_states` via `graphsync.OnCheckpoint`; per-tenant semaphore in `graph/limiter.go` sized from claim/progress `graph_tenant_budget`; `effectiveGraphParallel()` clamps workload parallelism.
- **PHP 1.29.0:** Liveness-based reaps (`isWorkerAlive`, heartbeat gap 180s) — wedge/upload stall only when worker dead; `WorkerClaimService::isActivelyRunningClaim()` treats fresh lease as alive; `GraphTenantBudgetService` + `ms365_graph_tenant_budget` table (`upgrade_phase14_graph_budget.sql`); claim/progress return `graph_tenant_budget` share; throttle aggregation (`graph_throttled`, `graph_429_hits_total`) in live snapshot; UI badge in `e3backup_live.tpl`; finer whale sharding defaults (`shard_item_threshold` 10k, `shard_target_items` 8k, `shard_max_count` 48).
- **Tests:** `client_test.go` (AIMD), `graph_stall_watch_test.go`, `limiter_test.go`, `ms365_batch_aggregate_test.php` (liveness + throttle).
- **Verify:** `go test ./...`; redeploy worker **0.3.0** + module upgrade for phase14 SQL; whale batch — adaptive limit recovers after 429 burst, no false "Stale workload reconciled", throttle badge when throttled, killed worker resumes enumeration from checkpointed deltas.

### 2026-06-20 — Cooperative worker cancel on backup cancellation

- **Root cause:** Cancelling a batch only updated DB status (`bulkCancelBatchChildren`); Go kopia workers were explicitly skipped by `WorkerProcess::terminate` and never polled cancellation. Workers kept cancelled runs in `s.running` for hours → `current_load` stayed high → nodes blocked new claims and fleet rollouts (`draining=true` while waiting for idle). `releaseOrphanedClaimsForNode` only runs when load=0.
- **PHP 1.28.0:** `ms365_worker_progress.php` returns `data.cancel_requested` when run is cancelled; skips `onProgress` (no lease renewal) via `Ms365RestoreWorkerHooks::isRunCancelled()`.
- **Go worker 0.2.9:** `Progress()` parses `cancel_requested`; `StartProgressHeartbeat` invokes `onAbort` once; scheduler passes run `cancel()` to runner; cooperative `context.Canceled` exits without Fail/Complete. Scheduler resets `draining=false` when heartbeat no longer offers an update (fixes stuck poll after withdrawn rollout).
- **Verify:** Build/publish worker **0.2.9** and roll out; cancel active whale batch — workers should drop load within ~45s (`progress_heartbeat_seconds`), accept new jobs, and resume rollouts.

### 2026-06-20 — MailboxNotEnabledForRESTAPI graceful skip (no-mailbox users)

- **Root cause:** Users without Exchange Online mailboxes (e.g. `aidan`, `ali`) return Graph `404 MailboxNotEnabledForRESTAPI` on `/mailFolders`. Worker failed the whole run; server `isNonRetryableError()` did not classify the 404 as permanent, so each resource retried 5× then `Run exceeded max attempts`. Amplified by whale perf work (higher concurrency surfaces all no-mailbox users at once).
- **Go worker 0.2.8:** `graph.IsMailboxNotEnabled(err)`; `WorkloadRunner` skips mail/contacts/tasks/calendar with warning + `stats[workload].skipped=mailbox_not_enabled`; mail-only jobs complete as `no_changes`.
- **PHP 1.27.0:** `JobQueueRepository::isNonRetryableError()` includes `mailboxnotenabledforrestapi` patterns (defense-in-depth: terminal fail on first attempt if error still surfaces).
- **Tests:** `workloads_test.go`, `client_test.go` (`TestIsMailboxNotEnabled`), `tests/ms365_non_retryable_error_test.php`.
- **Deferred:** Plan-time inventory filter to exclude non-mailbox users from mail jobs.
- **Verify:** Build/publish worker **0.2.8** and roll out; new batch — no-mailbox users complete with warning, no 404 ERROR spam or max-attempts churn.

### 2026-06-20 — Whale-tenant backup performance (fleet unlock + sharding + claim fairness)

- **Root cause:** Worker admission budgets (`heavy_job_cpu_cores=2`, `max_cpu_cores=3`) capped fleet at ~10 concurrent jobs despite 96 capacity; claim head-of-line blocked light `user:` jobs behind heavy `site:` jobs; SharePoint sharding never fired because inventory lacked `meta.drives[]`/`lists[]` on plan.
- **Go worker 0.2.7:** Configurable `heavy_job_cpu_cores` (default 1); claim hints (`accept_heavy`) on claim API; admit-reject counter on heartbeat; template budgets right-sized for 20G/4-core CTs (`max_cpu_cores: 16`, `ram_budget_mib: 18432`); `kopia.parallel_uploads: 16`, `graph.global_max_concurrency: 48`.
- **PHP 1.26.0:** `WorkerClaimService::claimNext($nodeId, $claimHint)` skips heavy jobs when node cannot admit; `InventoryService::enrichResourcesForPlanning()` before backup plan; `BackupPlanner::absorbOneDriveJobsForUser()` carries size/item hints; `ProgressLogger::warning()` alias; fleet dashboard shows utilization, queued-by-type, claim admit rejects; `reconcileQueuedErroredRuns()` on fleet cron; shard defaults tuned (max 32, item threshold 25k).
- **Settings:** `ms365_per_tenant_max_concurrent` → 96 on dev server.
- **Verify:** Build/publish worker **0.2.7** and roll out; redeploy worker `config.yaml` on fleet CTs; start new whale batch — expect many `drive:`/`list:`/`#shard:` child runs and fleet load approaching capacity.

### 2026-06-19 — Progress observability + Kopia stall watchdog (worker 0.2.6)

- **Live UI:** `updateLiveSnapshot()` computes parent `speed_bytes_per_sec` / `eta_seconds` from `bytes_processed` (`bytes_hashed` sum), not upload bytes. `e3backup_live.tpl` shows "Processing speed (hashed)" during dedup-heavy phases with client-side fallback.
- **Child stats:** `ms365_backup_runs.stats_json` column (`upgrade_phase13_child_stats_json.sql`); `backupComplete` / `backupProgress` persist `graph_sync_ms` / `kopia_snapshot_ms`. Admin Jobs detail modal shows Phase / Graph / Kopia columns.
- **Go 0.2.6:** `kopia.stall_seconds` (default 2700, 0=off) cancels wedged snapshots and `reportFail` with retryable `kopia upload stalled: no hashing progress for Ns`. `ProgressCounter` tracks last hash/upload timestamps.
- **Verify:** `go test ./internal/kopia/...`; whale batch live page shows processing speed; admin batch detail shows timings; lower `stall_seconds` in dev to confirm retry.

### 2026-06-19 — Active batch child reconcile on fleet cron

- **Root cause (stuck children):** `reconcileBatchChildren()` only ran on worker complete/fail hooks, cancel, and admin job list — not on a timer. Stale running children blocked tenant concurrency slots (`ms365_per_tenant_max_concurrent`) while the parent batch stayed `running`. Fresh queue leases (heartbeat renewals) shielded wedged graph_sync workloads (0 items/bytes for hours). `reconcileZombieRuns()` also skipped stale rows when the worker node's `current_load > 0` (other jobs on same node).
- **PHP:** `Ms365BatchRunRepository::reconcileActiveBatches()` — fleet cron calls `syncFromChildren()` for every running MS365 backup parent. Improved `shouldReapRunningChild()`: wedge detector (0 items/bytes after 30m), upload stall (45m silence in kopia/upload phase), silence override (reap after 30m even if lease fresh). Removed worker `current_load > 0` skip from zombie reconcile.
- **Cron:** `ms365_worker_fleet.php` now emits `active_batches_reconciled` in JSON output (schedule every 2–5m).
- **Verify:** Run fleet cron during an active whale batch; confirm wedged mailboxes fail with "Stale workload reconciled during batch sync" and queued children resume claiming.

### 2026-06-19 — Fix worker self-cancellation on long runs (context deadline exceeded)

- **Root cause:** `scheduler.runContext` bound a run's working context to the claim-time lease (`job.LeaseExpiresAt`). Whale sites whose graph_sync + kopia snapshot exceeded the lease window self-cancelled mid-write → `error writing pack file: context deadline exceeded`. Because the (now cancelled) run context was reused for `Fail`/`Complete`/log flush, the worker could not report the failure, so the control plane saw it as worker loss → "Stale partial backup re-queued" → "Run exceeded max attempts". The server already renews a live run's lease (heartbeat `renewForNode` + progress `renewForRun`), so the lease was never the limiter — only the worker's own pinned deadline was.
- **Go (worker 0.2.5):** `runContext` no longer uses the lease; it applies a generous safety ceiling `worker.max_run_seconds` (default **43200 = 12h**, `0` = unbounded) that only bounds genuinely stuck runs. Terminal status reports (`Fail`/`Complete`) and the final log flush now run on a detached context (`context.WithoutCancel` + 2m timeout) via `Runner.reportFail`/`reportComplete`, so failures are always delivered even after cancellation. Scheduler per-run goroutine now propagates `cancel()` and logs run failures on the live parent context.
- **PHP (server reaper):** `JobQueueRepository::recoverStaleRunning()` was a blunt wall-clock reaper (`started_at < now-7200`) that re-queued *any* run still running after 2h — ignoring lease/progress — so it would have re-killed long single-resource runs even after the worker fix. Now lease/progress-aware (mirrors `reconcileZombieRuns`): only reaps when `(lease lapsed AND no progress for 15m)` OR past the absolute backstop. `STALE_RUNNING_SECONDS` raised **7200 → 50400 (14h)** as a backstop above the worker's 12h ceiling; added `STALE_PROGRESS_SECONDS = 900`.
- **Config:** new `worker.max_run_seconds` (config.yaml.example).
- **Verify:** Build/publish worker **0.2.5**; re-run a large PHL Capital site — confirm long snapshots complete (or fail reportably) without re-queue/max-attempts churn.

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

1. **Archive restore E2E** — Deploy PHP 1.43.0 + worker 0.3.12; run module upgrade (`upgrade_phase20_archive_restore.sql`); verify wizard archive path, `exports/` lifecycle on job bucket, presigned download via `ms365_restore_download.php`, and tenant restore regression.
2. **Manual fleet scaling E2E** — After 1.34.0 deploy: scale up on each Proxmox node; verify cross-node clone with `proxmox_template_vmid_map` or shared storage; confirm baseline auto-update + claim gate on fresh clones.
3. **File backup staging E2E** — Execute `Docs/KOPIA_FILE_BACKUP_E2E.md` on dev tenant (OneDrive + SP files/lists + mail attachments); confirm browse shows `content/` bytes.
4. **Publish worker release** — Build/publish Go worker with `sharepoint_lists` + shard filtering; roll fleet to new artifact.
5. **Tenant Seeder E2E** — Register seeder Entra app; run Light profile; verify backup picks up seeded files.
6. ~~**Metering / billing**~~ — MS365 billing per `MS365_BILLING_AND_STORAGE_DESIGN.md` (meter/rate cron, trial, invoice hook, Usage & Billing drawer).
7. **Admin support view** — Impersonate client tenant, re-run inventory from admin addon.
8. **Remove Comet LXD path** — `Provisioner::provisionMs365` still provisions legacy order/LXD.
9. **Async inventory refresh** — Large tenants may need background job instead of synchronous POST.
10. **Calendar verify on Kopia** — `CalendarVerifier` still reads legacy PHP layout paths; port to snapshot browse or drop.

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
| **Tenant-owner redesign (active; claim unit → tenant batch)** | `modules/addons/ms365backup/Docs/MS365_TENANT_OWNER_REDESIGN.md` |
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
