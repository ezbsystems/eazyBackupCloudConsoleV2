MS365 Backup Platform — Agent Onboarding Prompt
You are working on the Microsoft 365 Backup product for eazyBackup (WHMCS). This is a multi-module SaaS offering: customers connect Entra tenants, back up M365 data via Microsoft Graph, store copies in eazyBackup-owned object storage, and manage everything from e3 Cloud Backup. Backup execution is a PHP control plane + Go Kopia worker fleet; the customer experience lives in cloudstorage.

Current versions (as of 2026-06-25):

ms365backup module: 1.52.0 (ms365backup_config()['version'] in ms365backup.php)
ms365-backup-worker (Go): 0.3.27 (internal/version/version.go)
Mandatory read-first (before coding)
Read these files in order. Paths are relative to WHMCS root /var/www/eazybackup.ca/accounts.

Priority	Document	Why
1
modules/addons/ms365backup/Docs/PRODUCT_ROADMAP.md
Product vision, goals, phases 0–6, feature checklist, explicit out-of-scope
2
modules/addons/ms365backup/Docs/PROGRESS.md
Living handoff: session log, versions, known gaps, tenant-owner rollout status
3
modules/addons/ms365backup/Docs/MS365_TENANT_OWNER_REDESIGN.md
Active architecture change: claim unit = tenant batch (read fully if touching queue/workers)
4
modules/addons/ms365backup/Docs/ARCHITECTURE_BOUNDARIES.md
Module roles, URLs, storage, auth split
5
modules/addons/ms365backup/Docs/ARCHITECTURE.md
Execution model, engines, Graph rules, DB tables
6
modules/addons/ms365backup/Docs/MS365_WORKER_FLEET.md
Proxmox fleet, build/deploy, concurrency limits, batch lease recovery
7
modules/addons/ms365backup/Docs/CUSTOMER_ONBOARDING.md
Customer connect flow (brief ops context)
8
modules/addons/ms365backup/Docs/Prompts/ms365_product_agent_prompt.md
Session startup checklist, doc map, conventions
Do not start implementation until you have read PRODUCT_ROADMAP.md, PROGRESS.md, and (if touching workers/queue) MS365_TENANT_OWNER_REDESIGN.md.

For deep engine/Graph debugging only, also see Docs/Prompts/ms365backup_agent_prompt.md.

1. What the MS365 backup platform is
Product summary
Microsoft 365 Backup is a WHMCS-integrated SaaS offering:

Customers admin-consent their Entra tenant to a platform multi-tenant Entra app (no secret paste in the happy path).
Backups pull data via Microsoft Graph (mail, calendar, contacts, tasks, OneDrive, SharePoint, Teams, groups, Planner, OneNote, directory export).
Bytes land in dedicated e3 RGW buckets (e3ms365-{token}) via Kopia snapshots — not Comet/LXD vaults.
Customers use e3 Cloud Backup only: index.php?m=cloudstorage&page=e3backup&view=ms365.
Scale target: hundreds–thousands of WHMCS clients, queue-backed workers, isolated storage per customer.
Module boundaries (do not blur these)
Customer → cloudstorage (e3backup view=ms365, api/ms365_*.php)
         → ms365backup (EntraConsentService, engines, queue, runs, admin dev UI)
         → cloudstorage RGW (Ms365StorageBootstrapService, e3ms365-* buckets)
Module	Responsibility
ms365backup
Graph client, inventory, BackupPlanner, job queue, WorkerClaimService, ms365_tenant_records, admin dev UI, PHP services consumed by cloudstorage
cloudstorage
e3 Cloud Backup customer UI, OAuth callback, bucket lifecycle, Ms365E3Controller bridge, api/ms365_*.php, worker API endpoints
eazybackup / Comet
Permanently out of scope for MS365 backup — do not add features here
Customer URL (canonical): index.php?m=cloudstorage&page=e3backup&view=ms365
Admin / dev engine UI: addonmodules.php?module=ms365backup
Legacy client URL: index.php?m=ms365backup → redirects to e3 MS365 view

Execution model (since module 1.18.0)
Layer	Responsibility
cloudstorage
e3 wizard, job save, customer APIs, worker HTTP endpoints
ms365backup PHP
Inventory, planning, queue, batch claims, restore orchestration, fleet admin
ms365-backup-worker (Go)
Graph sync + Kopia snapshots into e3ms365-* buckets
PHP is the control plane only for backup execution. The legacy PHP BackupOrchestrator / CLI worker path was removed in 1.18.0.

Data flow for a backup run:

Customer starts backup (e3 UI → api/ms365_start_backup.php or job scheduler).
PHP BackupPlanner builds physical jobs (user:{id}, drive:{id}, site:{id}, …).
One parent run in s3_cloudbackup_runs (cloudstorage); child rows in ms365_backup_runs (one per physical workload).
Queue + batch claim assigns work to a Go worker.
Worker syncs via Graph, writes Kopia snapshots to the customer's bucket.
Live UI aggregates child progress into the parent batch view (Ms365BatchLiveService).
Storage:

Environment	Backend
Production
Per-job RGW bucket e3ms365-{jobHash} (legacy jobs may share tenant bucket); Kopia packs inside bucket
Development
Local /var/www/eazybackup/ms365/ when no bucket linked
Auth: Platform Entra app + admin consent; ms365_tenant_records per whmcs_client_id. See CUSTOMER_ONBOARDING.md for connect flow (wizard → consent → inventory → job save).

Product phases (high level)
Work aligns with PRODUCT_ROADMAP.md:

Phase	Goal
0
Boundaries: ms365backup = engines; cloudstorage = UI
1
Platform Entra OAuth + ms365_tenant_records
2
Dedicated e3ms365-* buckets + CloudStorageBackupStorage
3
e3 UI MVP: connect, presets, run history
4
Queue scale, retries, access health
4b
Unified e3 M365 UX (before restore depth)
5
Restore platform (mail first; Kopia browse/restore via Go worker)
6
Hardening / GA
Live status: always check PROGRESS.md.

2. Tenant-Owner Claim Model (architectural change)
Authoritative spec: Docs/MS365_TENANT_OWNER_REDESIGN.md
Status: Implemented in code (Phases 0–2, 5). Operational rollout (Phases 3–4) pending — see below.

Why this changed
Microsoft Graph throttles per application + per Entra tenant — one shared rate limit per tenant. The old model fanned one tenant's backup batch across N workers, each claiming individual child workloads (user:{id}, drive:{id}, site:{id}, …). That created a structural mismatch:

Many workers contended for one tenant-wide Graph limit.
The control plane tried to subdivide the limit via ms365_graph_tenant_budget rows and GraphTenantBudgetService::workerShare().
When a throttled worker went quiet for Retry-After (up to 600s), the control plane could not tell throttled-but-alive from dead — leading to destructive requeue races.
Symptoms in production: Recovering this workload flapping, graph 401 after token refresh ... Run is not active., ~434k progress POSTs/run stalling WHMCS MySQL.
Decision: Change the claim unit from "child workload" to "tenant batch."

Old model (child workload claim) — superseded for backup
Fleet of workers
    │
    ├── Worker A claims child: user:alice
    ├── Worker B claims child: drive:bob
    ├── Worker C claims child: site:contoso
    └── ... (same tenant, N workers, N Graph controllers, DB budget divided)
Per-child: ms365_job_queue row independently claimable
Per-child: lease + heartbeat + progress POST
Per-child: reapers (orphan, zombie, throttle-shield, exhausted, stale running)
Graph budget: divided across workers via ms365_graph_tenant_budget
Concern	Old behavior
Claimable unit
One child run_id / physical_key
Workers per tenant batch
Up to perTenantMaxConcurrentWorkloads (fleet-wide cap per tenant)
Graph governance
N in-process tenant_controllers + DB budget division
Liveness
Per-child heartbeats + threshold zoo (120/180/600/1200/1800/2700s) + throttle shields
Failure recovery
Requeue individual child (racy vs live worker)
Worker API
ms365_worker_claim.php → one RunJob per poll
New model (tenant batch claim) — current for backup
Worker A claims TENANT BATCH (all children for tenant T, parent run R)
    │
    ├── runs children in-process (max_concurrent_runs goroutine pool)
    ├── one tenant_controller (sole Graph 429 governor)
    └── one batch lease + coalesced heartbeat/progress
Worker B claims a DIFFERENT tenant's batch
Concern	New behavior
Claimable unit
Tenant batch (batch_run_id + tenant_record_id) in ms365_batch_claims
Workers per tenant batch
One owner (default maxBatchesPerNode = 1)
Graph governance
100% in-process tenant_controller; DB budget is advisory ceiling only
Liveness
One batch heartbeat (last_heartbeat_at); reaper asks "is batch owner heartbeating?"
Failure recovery
Requeue whole batch; new owner skips success children, resumes from delta_states checkpoints
Worker API
ms365_worker_batch_claim.php → children[] payload
What is unchanged: Graph sync engines (internal/graphsync/*), Kopia repo layout, per-child ms365_backup_runs rows, delta_states checkpoints, live UI aggregation, customer UI/inventory.

What is different for restore: Restore still uses per-run claimNext via ms365_worker_claim.php — batch claim is backup only.

How it works now (implementation)
Data model (ms365_batch_claims):

Column	Purpose
batch_run_id
Parent run (s3_cloudbackup_runs.run_id)
tenant_record_id
Throttling domain owned
worker_node_id
Current owner (null = unclaimed)
status
queued / running / done / failed
claimed_at, lease_expires_at
Single batch lease
last_heartbeat_at, last_progress_at
Single liveness signal
attempts, max_attempts
Batch-level retry
ms365_job_queue remains as a child manifest (per-child status/checkpoints) but rows are not independently claimable by other workers.

PHP control plane:

Component	Path
Batch claim repo
lib/Ms365Backup/Ms365BatchClaimRepository.php
Claim service
lib/Ms365Backup/WorkerClaimService.php → claimNextBatch(), buildBatchPayload()
Enqueue on backup start
lib/Ms365Backup/CustomerBackupService.php
Batch lease renewal
lib/Ms365Backup/WorkerLeaseService.php → renewForBatch()
Batch progress hooks
lib/Ms365Backup/Ms365RestoreWorkerHooks.php → onBatchProgress / onBatchComplete
Migration
sql/upgrade_phase22_tenant_owner.sql
Config
Ms365EngineConfig: batchHeartbeatGapSeconds() (180), maxBatchesPerNode() (1), batchMaxAttempts() (5)
Tests
tests/ms365_batch_claim_test.php
Worker HTTP APIs (cloudstorage, token auth):

Endpoint	Purpose
api/ms365_worker_batch_claim.php
Claim tenant batch; returns children[]
api/ms365_worker_batch_progress.php
Coalesced child progress; renews batch lease
api/ms365_worker_batch_complete.php
Batch finished; per-child final statuses
api/ms365_worker_batch_release.php
Drain hand-off; preserve checkpoints
api/ms365_worker_graph_token.php
Token refresh authorized by batch lease (no Run is not active. race)
Legacy per-run endpoints (restore only for backup path):

Endpoint	Backup	Restore
ms365_worker_claim.php
❌ restore-only
✅
ms365_worker_progress.php
❌ restore-only
✅
ms365_worker_complete.php / fail.php / release.php
❌ restore-only
✅
Go worker:

Component	Path
Batch runner
ms365-backup-worker/internal/jobs/batch_runner.go
Scheduler
internal/jobs/scheduler.go → polls ClaimBatch()
Graph governor
internal/graph/tenant_controller.go (sole authority per batch)
Per-child execution
Unchanged Runner / WorkloadRunner over children[]
Batch reaper (replaces per-child reaper suite):

if batch.status == running AND now - last_heartbeat_at > batchHeartbeatGapSeconds (180):
    requeue whole batch (or terminal-fail if attempts exhausted)
Triggered by fleet cron (crons/ms365_worker_fleet.php) and admin fleet_release_leases.

What was removed (Phase 5, module 1.52.0)
Deleted / retired from backup hot path:

Per-child reaper suite: releaseOrphanedClaimsFor*, reconcileZombieRuns, reconcileExhaustedRunningClaims, recoverStaleRunning (from claim path)
Throttle-shield apparatus: isThrottledWaitingAlive*, shouldSkipThrottleReaper, shouldReapRunningChild, countsAgainstTenantWorkloadCap
countRunningForTenant / per-tenant workload cap gate in WorkerClaimService
Ms365EngineConfig::perTenantMaxConcurrentWorkloads()
GraphTenantBudgetService::workerShare() division (now returns full tenant ceiling, advisory only)
Backup fair-claim child pool; per-child tenant GET_LOCK in claim path
Tests: ms365_tenant_throttle_liveness_test.php, ms365_reaper_throttle_test.php, ms365_exhausted_claim_liveness_test.php
Kept:

kopia.stall_seconds in-process stall watchdog (legitimate stuck-detector, not cross-process liveness guess)
GraphTenantBudgetService as optional ceiling hint (ms365_per_tenant_max_concurrent, default 16)
Worker-local knobs: max_concurrent_runs, graph_parallel_requests, graph_folder_parallel, graph_sharepoint_drive_parallel
Concurrency knobs after redesign
Knob	Meaning
max_batches_per_node (default 1)
How many tenant batches one worker node may own
platformMaxConcurrent
Max concurrent tenant batches fleet-wide
max_concurrent_runs (worker config.yaml)
Child workloads in parallel within one batch owner
tenant_controller AIMD
Sole Graph 429 governor for the tenant
perTenantMaxConcurrentWorkloads
Deleted — meaningless under tenant-owner model
Operational next steps (from PROGRESS.md — not yet executed)
Code is shipped; fleet rollout is ops work:

Phase 3 — Canary (ops):

Deploy worker 0.3.27 to one fleet node via Worker Fleet → Deployments tab.
Leave other nodes on prior binary until soak passes.
Run one whale tenant batch + multi-tenant mix.
Verify acceptance criteria:
Zero Recovering this workload events
Zero Run is not active. token errors
Exactly one lease + heartbeat per active batch in ms365_batch_claims
Progress POST volume ≥10× lower vs per-child baseline
Tail-end completes with no stranded Queued workloads
Phase 4 — Fleet rollout (ops):

Roll 0.3.27 fleet-wide from Deployments tab.
Soak several whale + tail-end runs.
Monitor Worker Fleet dashboard (queue depth, batch claim heartbeats).
Module upgrade: Ensure WHMCS module is at 1.52.0 (runs upgrade_phase22_tenant_owner.sql on upgrade if coming from earlier version). Bump triggered via Setup → Addon Modules when version increases.

Smoke test: php modules/addons/ms365backup/bin/ms365_fleet_smoke.php

3. Environment
Item	Value
Workspace root
/var/www/eazybackup.ca/accounts
PHP
8.2+, declare(strict_types=1);, namespace Ms365Backup\
Dev backup path
/var/www/eazybackup/ms365/ (not under eazybackup.ca/)
Worker repo
ms365-backup-worker/ (sibling to accounts/ under /var/www/eazybackup.ca/)
Worker logs
/var/www/eazybackup/ms365/_logs/
Fleet admin UI
Addons → MS365 Backup → Worker Fleet
Dual fleet (1.50.0+)
Dev WHMCS = build/deploy console; prod workers register against prod WHMCS — see MS365_WORKER_FLEET.md §Dual fleet
4. Key implementation paths
ms365backup (engines + control plane)
Area	Path
WHMCS addon entry
modules/addons/ms365backup/ms365backup.php
Entra / tenant
lib/Ms365Backup/PlatformEntraConfig.php, EntraConsentService.php, TenantRecordRepository.php
Planning
lib/Ms365Backup/BackupPlanner.php
Queue / claims
lib/Ms365Backup/WorkerClaimService.php, Ms365BatchClaimRepository.php, JobQueueRepository.php
Customer backup
lib/Ms365Backup/CustomerBackupService.php
Storage
lib/Ms365Backup/BackupStorageFactory.php, CloudStorageBackupStorage.php
Restore orchestration
lib/Ms365Backup/RestoreOrchestrator.php, RestoreJobService.php
Fleet
lib/Ms365Backup/Fleet/FleetFacade.php, crons/ms365_worker_fleet.php
Admin CLI
bin/ms365_admin.php
Admin UI / API
pages/admin/, pages/admin/api.php
Migrations
sql/upgrade_*.sql
cloudstorage (customer + worker APIs)
Area	Path
e3 MS365 page
pages/e3backup_ms365.php, templates/e3backup_ms365.tpl
OAuth callback
view=ms365_connect_callback
Bridge
lib/Client/Ms365E3Controller.php
Bucket bootstrap
lib/Client/Ms365StorageBootstrapService.php
Customer APIs
api/ms365_status.php, ms365_connect_start.php, ms365_start_backup.php, ms365_runs_list.php, …
Batch worker APIs
api/ms365_worker_batch_claim.php, _batch_progress.php, _batch_complete.php, _batch_release.php
Restore worker APIs
api/ms365_worker_claim.php, _progress.php, _complete.php, …
Go worker
Area	Path
Scheduler + batch runner
internal/jobs/scheduler.go, batch_runner.go
Graph sync engines
internal/graphsync/*
Graph client + throttle
internal/graph/tenant_controller.go
Kopia
per MS365_KOPIA_ENGINE.md
Version
internal/version/version.go



