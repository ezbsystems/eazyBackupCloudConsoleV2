# MS365 worker fleet (Proxmox)

## Control plane

- `ms365_worker_nodes` — registered workers (`proxmox_node` = Proxmox host; `stopped` = container stopped, reusable)
- `ms365_job_queue` — claims, leases, `worker_node_id`
- `crons/ms365_worker_fleet.php` — autoscale (optional) + lease recovery + stale node detection
- `crons/ms365_worker_build_runner.php` — admin-triggered Go builds
- `ProxmoxProvisioner` — clone LXC from template; manual scale up/down (stop/start)

## Manual fleet scaling (1.34.0+)

Cron autoscale is **off by default** (`ms365_worker_fleet_autoscale_enabled`). Use **Worker Fleet → Nodes**:

| Action | Effect |
|--------|--------|
| **Scale up** | Clone N workers onto a chosen Proxmox node (`fleet_scale_up`); row starts `registering` with `proxmox_node` set; API waits for Proxmox `running` + worker heartbeat before reporting success |
| **Stop** | `pct stop` via API; row → `stopped` (excluded from capacity; container kept for restart) |
| **Start** | `pct start` via API; row → `active` |

**Proxmox settings** (Setup → Addon Modules → MS365 Backup):

- `proxmox_node` — hostname where the golden LXC template lives (clone **source** when scaling other nodes)
- `proxmox_lxc_template_vmid` — template VMID (e.g. `9010`)
- `proxmox_cluster_nodes` — CSV for the scale-up node picker (empty = live `/nodes` discovery)
- `proxmox_template_vmid_map` — JSON `{"nodeName":9010}` when templates are local per node

**Proxmox API token permissions** (required for manual scale-up and autoscale):

Create a dedicated role (e.g. `MS365Fleet`) and assign it to the API token user on these ACL paths:

| ACL path | Privileges |
|----------|------------|
| `/vms/<template_vmid>` (e.g. `/vms/9010`) | `VM.Clone` |
| `/storage/local-lvm` (each node, or `/storage` with propagate) | `Datastore.Allocate`, `Datastore.AllocateSpace` |
| `/vms` (propagate) or per worker VMID range | `VM.Allocate`, `VM.Config`, `VM.PowerMgmt`, `VM.Monitor` |

Cross-node clone also needs the above on **both** the template home node and the target node storage. A `403` with `Permission check failed (/vms/9010, VM.Clone)` means the token lacks `VM.Clone` on the template VMID — fix ACLs in **Datacenter → Permissions**, not WHMCS code.

**Cross-node clone:** The golden template (e.g. VMID 9010) must exist on the target node or on shared storage. Cloning to another node without either fails with a clear Proxmox error — configure a per-node template map or shared `local-lvm`. With current settings, scale-up on node `X` uses `proxmox_template_vmid_map[X]` when set, otherwise clones from `proxmox_node` with API `target=X`.

**VMID allocation (1.35+):** Next worker VMID is chosen from max(`ms365_worker_nodes.proxmox_vmid`) and live Proxmox cluster VMIDs (`/cluster/resources?type=vm`), skipping IDs still tied to `registering`/`active`/`stopped` rows unless Proxmox confirms the CT is gone. Range 9000–9999.

**Post-clone identity (autoscale):** `ProxmoxProvisioner` sets container `hostname` via the LXC config API (valid schema key). It does **not** set `env` / `PROXMOX_VMID` — the REST config endpoint rejects non-schema keys on PVE 8+ (`pct set --env` remains CLI-only). WHMCS inserts a `registering` row with `proxmox_vmid` before `pct start`; the worker adopts that row on first register by matching hostname.

**Post-clone secrets (1.37+):** After hostname config and before start, the provisioner writes `environment.conf` with `MS365_WORKER_TOKEN` and `MS365_WORKER_API_BASE` from WHMCS (`FleetSettings::workerApiBaseUrl()` → `{SystemURL}/modules/addons/cloudstorage/api`). Injection uses the LXC exec API when available; otherwise optional `proxmox_ssh_target` SSH (`pct push`) on the Proxmox host. If neither is available, clones inherit the golden template drop-in — it **must** include the full API path (not just `http://192.168.92.79`). A wrong base causes `ms365_worker_register.php http 404` in the worker journal. WHMCS addon UI uses `proxmox_ssh_target` (not `proxmox_shell_target`; keys containing `shell` may be stripped by the admin UI).

**Post-clone service bootstrap (1.53+):** After `pct start`, the provisioner runs `systemctl daemon-reload`, `enable`, and `restart` on `ms365-backup-worker` inside the CT (LXC exec or SSH `pct exec` fallback). Golden templates may ship with the unit disabled; without this step the worker never registers and verification fails — triggering **provision rollback** (`pct destroy`). Audit event: `provision_service_bootstrap`.

**Golden template check:** `deploy/proxmox/template-setup.sh` rejects `MS365_WORKER_API_BASE` values that do not end with `/modules/addons/cloudstorage/api`.

**Async clone wait (1.36+):** Full LXC clone returns a Proxmox task UPID; the provisioner polls `/nodes/{node}/tasks/{upid}/status` until `status=stopped` and `exitstatus=OK` (up to 180s) before config PUT or start. Config/start calls retry up to 5 times with exponential backoff (2–10s) if the CT is still locked.

**Provision rollback:** If clone succeeds but config, start, or post-create verification fails, the provisioner destroys the new CT (`cleanupOrphanVmid`), abandons the `registering` WHMCS row, and logs `provision_cleanup` in the fleet audit log. Orphan CTs left from older failures (e.g. VMID 9017 after a partial clone) must be removed manually on the host (`pct destroy <vmid>`) before scale-up can reuse that ID.

## Auto baseline update

When `ms365_worker_fleet_auto_baseline_update` is on (default):

- Idle nodes behind the **latest release** receive an `update` offer on heartbeat even when no admin deploy is active (`DeployService::updateInstructionForNode`).
- `claimNext` returns no job until the node version matches latest — new clones cannot take backups until self-updated.

Busy nodes are not force-drained by baseline update; only idle (`effectiveReportedLoad === 0`) nodes are offered.

## Admin UI

**Addons → MS365 Backup → Worker Fleet**

| Tab | Purpose |
|-----|---------|
| Dashboard | Queue depth, capacity, stale/exhausted job counts, version summary, audit log |
| Nodes | Drain / activate / retire / **stop** / **start** workers; **scale up** onto a Proxmox node; release stale leases |
| Builds | Queue `go test` + `go build`, publish versioned artifact |
| Deployments | Roll out release via HTTPS pull (heartbeat) |
| Settings | Repo path, artifact dir, API base |

## APIs (token auth)

| Endpoint | Purpose |
|----------|---------|
| `ms365_worker_register.php` | Register / adopt provisioned node |
| `ms365_worker_heartbeat.php` | Load, version, optional update offer |
| `ms365_worker_artifact.php` | Signed binary download (nonce + token) |
| `ms365_worker_claim.php` | Claim next run |
| `ms365_worker_progress.php` | Progress + manifest |
| `ms365_worker_complete.php` | Success |
| `ms365_worker_fail.php` | Failure + retry |

Header: `X-MS365-Worker-Token`

## Build & deploy workflow

1. Open **Worker Fleet → Builds**, enter version (e.g. `0.1.2`), queue build.
2. Ensure build cron runs: `php .../crons/ms365_worker_build_runner.php` every minute.
3. When build succeeds, open **Deployments**, select release, choose strategy (rolling / all_idle / canary).
4. Workers receive `update` on heartbeat, drain active runs, download artifact, verify sha256, restart.

**Drain (0.3.6+):** Admin **Drain** or a rolling/force deploy target sets `data.drain = true` on heartbeat. The worker checkpoints in-flight runs and releases them with `reason=drain` (infrastructure hand-off: progress preserved, attempt rolled back). Runs resume incrementally on other active workers. A drained node stops claiming new work until it updates/restarts (deploy) or an operator clicks **Activate** (standalone drain).

**Disk pressure (0.3.81+):** Admission requires free space ≥ `disk_watermark_mib + active_reserved + update_reserve_mib + job budget`. Soft pressure (below `max(disk_flush_watermark_mib, watermark+reserved+update_reserve)`) pauses new child reservations, evicts idle Kopia caches, and holds `diskCritical` until reservations drain and free space exceeds the resume threshold plus `disk_hysteresis_mib` (default 512). Deploy/update drain uses ordered cooperative hand-off: checkpoint → cancel → wait for runners/repo refs → evict caches → release claim. Update offers include `artifact_size_bytes`; staging requires artifact size + `update_reserve_mib` (default 256). Kopia `content_cache_size_mib` stays 512 — index cache growth is bounded by filesystem safeguards, not by raising the contents cap. Full index maintenance runs only when index-blob count exceeds `index_maintenance_threshold` (default 5000).

**Rolling deploy:** One node updates at a time. A busy rolling target receives `update.drain = true` and evicts work instead of waiting for natural completion. A node already `deploy_status = updating` keeps its offer even while load > 0; the next `pending` node starts only when no other node is updating.

**Version labels** must be unique semver (`x.y.z`). `ms365_worker_releases.version` has a unique index — reusing a published label (e.g. `0.2.3`) fails at the **publish** step even when `go build` succeeds. Use the next patch (Fleet UI suggests via `suggest_next_version`). Source default: `ms365-backup-worker/internal/version/version.go` (currently **0.3.6**).

No SSH/`pct push` required after golden template is in place **when** `environment.conf` already has the correct `MS365_WORKER_API_BASE` (full `/modules/addons/cloudstorage/api` path). Otherwise set `proxmox_ssh_target` so each clone gets a fresh drop-in from WHMCS.

## Cron scheduling

```bash
# Fleet lease recovery + optional autoscale (every 2–5 min; autoscale off unless ms365_worker_fleet_autoscale_enabled)
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_worker_fleet.php

# Build runner (every 1 min)
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/crons/ms365_worker_build_runner.php
```

Example systemd timers ship in `deploy/systemd/` (optional).

## Smoke test

```bash
php accounts/modules/addons/ms365backup/bin/ms365_fleet_smoke.php
```

## Concurrency limits

Backups run **multiple workloads in parallel** (one child run per user mailbox, OneDrive, etc.). Throughput is capped at several layers:

| Layer | Setting | Default | Location |
|-------|---------|---------|----------|
| Platform | max concurrent tenant batches | 100 | `Ms365BatchClaimRepository` / `WorkerClaimService::claimNextBatch` |
| Per worker node | max batches owned | **1** | `ms365_max_batches_per_node` |
| Per Entra tenant (Graph HTTP advisory ceiling) | `ms365_per_tenant_max_concurrent` | **16** | `GraphTenantBudgetService` / batch payload `graph_tenant_budget` |
| Per WHMCS client | `ms365_per_client_max_concurrent` | 10 | WHMCS addon settings |
| Per worker node | `max_concurrent_runs` | **16** | `config.yaml` (in-process child pool under one batch owner) |
| Per worker node | `heavy_job_cpu_cores` | **1** | `config.yaml` (I/O-bound site/drive jobs; was hardcoded 2) |
| Graph HTTP (in-process) | `graph_parallel_requests` + `tenant_controller.go` | **32** / adaptive AIMD | worker process (sole governor per batch) |
| Mail folders (per workload) | `graph_folder_parallel` | **4** | `config.yaml` |
| SharePoint drives (per site workload) | `graph_sharepoint_drive_parallel` | **4** | `config.yaml` |

**Tenant-owner model (1.52.0+):** Backup claims one **tenant batch** per worker (`ms365_batch_claims`). The
batch owner runs all child workloads in-process under a single `tenant_controller`; there is no per-child
claim gate or fleet-wide budget division. Restore keeps per-run `claimNext` via `ms365_worker_claim.php`.

**Tuning for faster large-tenant backups** (increase gradually; watch Graph 429 throttling and worker RAM):

| Knob | Conservative → aggressive | Notes |
|------|---------------------------|-------|
| `ms365_per_tenant_max_concurrent` | 8 → **16** | Advisory Graph HTTP ceiling passed to worker controller |
| `max_concurrent_runs` | 4 → **8–16** | In-process child parallelism inside one batch |
| `graph_parallel_requests` | 16 → **32** | See `MS365_KOPIA_ENGINE.md` |
| Fleet size | 2 → **3+** nodes | More tenant batches in parallel |

OneDrive workloads use the **heavy job** RAM/disk budget (`heavy_job_ram_budget_mib`); raising `max_concurrent_runs` without more RAM can block claims.

**UI note:** The live page stage shows e.g. `Syncing from Microsoft Graph (3 of 12 workloads active)` when multiple children run in parallel; parent progress blends completed workloads plus in-flight graph_sync fraction. When Graph pacing is **material** (429 ratio ≥5% or active throttle window), a reassuring pacing banner appears — a handful of 429s alone does not alarm.

## Graph throttling and stall safety (worker 0.3.16+)

| Mechanism | Purpose |
|-----------|---------|
| Tenant congestion controller (`graph/tenant_controller.go`) | Single adaptive window per Entra tenant per worker: proportional shrink on 429, additive grow on success streak, slot-held Retry-After backpressure, jittered cooldown, idle decay |
| PHP fleet budget (`GraphTenantBudgetService`) | Slow loop: multiplicative shrink on 429 deltas; additive +1 ceiling grow per 600s decay when not recently throttled |
| `graph_sync` stall watchdog | Same `kopia.stall_seconds` as upload watchdog; 429 Retry-After backoff counts as activity |
| Per-tenant Graph budget ceiling | Batch payload `graph_tenant_budget` — full ceiling, not divided | Worker `setCeiling` clamps the in-process controller |
| Adaptive budget floor (1.45.0+) | Under sustained `recent_429_count` (≥10 → floor 2, ≥20 → floor 1) so hammered tenants truly back off |
| `throttle_waiting` progress flag (worker 0.3.13+) | Control plane refreshes `last_429_at` during parked Retry-After waits even when `no_progress` |
| `graph_requests` liveness (worker 0.3.15+ / PHP 1.46.0+) | Monotonic completed Graph HTTP counter; rising value during `graph_sync` bumps `last_progress_at` so enumeration paging counts as alive |
| Mid-run delta checkpoints | `checkpoint_delta_states` on progress API → `DeltaStateRepository::saveStates`; requeued runs resume enumeration |

Default `kopia.stall_seconds` is **2700** in worker config (`applyDefaults()`); set `0` to disable both upload and graph_sync stall watches.

## Batch lease recovery (1.52.0+)

Backup workloads use **one batch lease** in `ms365_batch_claims` per tenant batch. Worker loss or stale
heartbeat requeues the **whole batch** (preserving per-child checkpoints); a new owner resumes skipping
`success` children.

| Mechanism | Purpose |
|-----------|---------|
| `Ms365BatchClaimRepository::reapStaleBatches()` | `running` batch with `now - last_heartbeat_at > batchHeartbeatGapSeconds` → requeue or terminal-fail whole batch |
| Fleet cron (`ms365_worker_fleet.php`) | Runs batch reaper each cycle; `batches_reaped` in cron JSON |
| `fleet_release_leases` admin API | Manual batch reaper trigger (`recovered` = batches reaped) |

Restore orphan handling: `failOrphanedRestoreRunsForNode` on heartbeat when node reports zero load.

## Orphan / zombie recovery (legacy per-child — removed 1.52.0)

Per-child backup reapers (`releaseOrphanedClaimsFor*`, `reconcileZombieRuns`, throttle-shield apparatus)
were removed when backup moved to tenant-batch ownership. See **Batch lease recovery** above.

**Lease renewal:** Batch lease renews via `ms365_worker_batch_progress.php`. Restore per-run lease renews via
`ms365_worker_progress.php` and `WorkerLeaseService::renewForNode` when `current_load > 0`.

**Fail-fast (no retry):** `JobQueueRepository::isNonRetryableError()` matches permanent failures. `markFailed()` terminal-fails these immediately.

Cron output includes `batches_reaped` (replaces `zombies_reconciled`).

See `ms365-backup-worker/deploy/proxmox/README.md`.

## Dual fleet (dev control plane + prod runtime) — 1.50.0+

Development WHMCS remains the **sole build/deploy console**. Production workers register, heartbeat, and claim jobs against production WHMCS (`192.168.92.75/accounts` by default).

| Setting | Host | Purpose |
|---------|------|---------|
| `ms365_server_environment` | both | `development` or `production` |
| `ms365_production_system_url` | dev | Prod WHMCS base URL (ends with `/accounts`) |
| `ms365_fleet_deploy_shared_secret` | both | M2M auth header `X-MS365-Fleet-Deploy-Token` |
| `ms365_auto_sync_release_to_prod` | dev | Push release artifact after build publish |
| `ms365_production_release_sync_enabled` | prod | Enable pull sync cron fallback |
| `ms365_development_system_url` | prod | Dev URL for pull sync cron |

**Dev UI:** Worker Fleet adds a fleet target selector (session-persisted): Development fleet | Production fleet. Dashboard, Nodes, Deployments, and Settings call `FleetFacade` which routes production target ops to prod via `FleetRemoteClient` → `fleet_remote.php`.

**Prod UI:** No fleet picker; **Builds** tab hidden. Local prod fleet only.

**Production worker API base:** When scaling production fleet from dev, `environment.conf` injects  
`MS365_WORKER_API_BASE=http://192.168.92.75/modules/addons/cloudstorage/api`  
(secrets stay in `environment.conf`; `config.yaml` must not contain `api.base_url`).

**Production WHMCS scale-up checklist:** On the production server (`ms365_server_environment=production`), configure the same Proxmox API settings as dev plus `proxmox_ssh_target` and SSH key at `/var/www/.ssh/ms365_proxmox_ed25519`. WHMCS **SystemURL** must be the production base (e.g. `http://192.168.92.75`) so injected workers register locally. Scale up from **Worker Fleet → Nodes** on production (no fleet picker). Failed verification destroys the new CT — check audit for `provision_cleanup` vs `provision_service_bootstrap` / `provision_env_inject`.

**VMID allocation:** Production scale-up calls prod `fleet_provision_prepare` first so `ms365_worker_nodes` rows and VMIDs live on **prod DB**, not dev.

**Release sync:** Dev `ReleaseSyncService::publishToProduction()` POSTs `fleet_release_upsert` (multipart artifact). Prod optional cron `crons/ms365_worker_release_sync.php` pulls from dev manifest when push fails.

**Browse binary auto-sync:** Restore browse on the WHMCS host runs `ms365-backup-worker browse` from `ms365_worker_repo_path/ms365-backup-worker`. `BrowseBinaryInstaller` keeps that copy aligned with fleet release artifacts (same binary as LXC workers, different install path).

| Trigger | When browse sync runs |
|---------|----------------------|
| Build publish (dev) | `BuildRunner` after `ReleaseRepository::create()` |
| Prod release push | `fleet_release_upsert` after artifact stored |
| Prod release pull | `ReleaseSyncService::downloadAndInstall` |
| Pull skip (already have release) | `BrowseBinaryInstaller::reconcileIfNeeded()` |
| Fleet deploy start | `DeployService::startDeploy` after `setTargetRelease` |
| Manual / post-deploy | `bin/ms365_install_browse_binary.php`, `deploy-production.sh` |

Audit log actions: `browse_binary_synced`, `browse_binary_sync_failed`. Fleet dashboard shows `browse_binary` status (`synced` / `out_of_date` / `missing`). Manual recovery: API op `fleet_browse_binary_sync` or dashboard **Sync browse binary** button.

Ops: `ms365_worker_repo_path` parent must be writable by PHP-FPM (`www-data`). Diagnose with `bin/ms365_browse_binary_diag.php`; health check `bin/ms365_prod_health_check.php` requires `status=synced`.

**Remote API:** `addonmodules.php?module=ms365backup&action=fleet_remote&op=…` (shared secret; no admin session).

**Smoke:** `php bin/ms365_fleet_smoke.php` reports `fleet_context` and production URL normalization.

**Production SSH (dev host):** For issues that require a shell on prod WHMCS (browse binary `chown`, `deploy-production.sh`, prod-only paths), see **`PRODUCTION_SSH_ACCESS.md`**. Key: `/root/.ssh/whmcs_prod_root` → `root@192.168.92.75`. Fleet remote API covers most fleet DB/UI ops without SSH.
