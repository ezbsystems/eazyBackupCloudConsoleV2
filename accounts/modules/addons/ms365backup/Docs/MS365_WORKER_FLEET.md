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
| Platform | max concurrent | 100 | `WorkerClaimService` |
| Per Entra tenant (running workloads) | `ms365_per_tenant_max_concurrent_workloads` | **6** | `WorkerClaimService::claimNext` |
| Per Entra tenant (Graph HTTP budget) | `ms365_per_tenant_max_concurrent` | **16** | `GraphTenantBudgetService` / worker `graph_tenant_budget` |
| Per WHMCS client | `ms365_per_client_max_concurrent` | 10 | WHMCS addon settings |
| Per worker node | `max_concurrent_runs` | **16** | `config.yaml` |
| Per worker node | `heavy_job_cpu_cores` | **1** | `config.yaml` (I/O-bound site/drive jobs; was hardcoded 2) |
| Graph HTTP (per workload) | `graph_parallel_requests` | **32** | `config.yaml` |
| Graph HTTP (per Entra tenant, fleet-coordinated) | `graph_tenant_budget` (claim + progress) | Ceiling for worker controller | `ms365_graph_tenant_budget` |
| Graph adaptive concurrency (per tenant, in-process) | `graph.adaptive_limit` + `tenant_controller.go` | Fast AIMD loop below PHP ceiling | worker process-global |
| Mail folders (per workload) | `graph_folder_parallel` | **4** | `config.yaml` |
| SharePoint drives (per site workload) | `graph_sharepoint_drive_parallel` | **4** | `config.yaml` |

**Workload vs HTTP budget (1.44.0+):** `claimNext` gates how many child workloads may be **running** per tenant (`ms365_per_tenant_max_concurrent_workloads`, default 6). The separate `ms365_per_tenant_max_concurrent` (16) is the fleet-wide **in-flight Graph HTTP** budget divided across active workers (`GraphTenantBudgetService::workerShare`). Claiming too many workloads against the same HTTP budget starves children on the shared limiter (no progress, no per-child 429s) and inflates the 429 rate.

**Tuning for faster large-tenant backups** (increase gradually; watch Graph 429 throttling and worker RAM):

| Knob | Conservative → aggressive | Notes |
|------|---------------------------|-------|
| `ms365_per_tenant_max_concurrent_workloads` | 4 → **6–8** | Running child workloads per tenant (claim gate) |
| `ms365_per_tenant_max_concurrent` | 8 → **16** | Shared Graph HTTP slots per tenant (AIMD budget ceiling) |
| `max_concurrent_runs` | 4 → **6** | Needs ~16 GB RAM per CT |
| `graph_parallel_requests` | 16 → **32** | See `MS365_KOPIA_ENGINE.md` |
| `graph_folder_parallel` | 4 → **8** | Parallel mail-folder delta |
| `graph_sharepoint_drive_parallel` | 4 → **6** | Parallel SharePoint drive delta (monolithic `site:{id}` jobs) |
| Fleet size | 2 → **3+** nodes | More worker capacity |

OneDrive workloads use the **heavy job** RAM/disk budget (`heavy_job_ram_budget_mib`); raising `max_concurrent_runs` without more RAM can block claims.

**UI note:** The live page stage shows e.g. `Syncing from Microsoft Graph (3 of 12 workloads active)` when multiple children run in parallel; parent progress blends completed workloads plus in-flight graph_sync fraction. When Graph pacing is **material** (429 ratio ≥5% or active throttle window), a reassuring pacing banner appears — a handful of 429s alone does not alarm.

## Graph throttling and stall safety (worker 0.3.16+)

| Mechanism | Purpose |
|-----------|---------|
| Tenant congestion controller (`graph/tenant_controller.go`) | Single adaptive window per Entra tenant per worker: proportional shrink on 429, additive grow on success streak, slot-held Retry-After backpressure, jittered cooldown, idle decay |
| PHP fleet budget (`GraphTenantBudgetService`) | Slow loop: multiplicative shrink on 429 deltas; additive +1 ceiling grow per 600s decay when not recently throttled |
| `graph_sync` stall watchdog | Same `kopia.stall_seconds` as upload watchdog; 429 Retry-After backoff counts as activity |
| Per-tenant Graph budget ceiling | Control plane divides tenant budget across active workers; worker `setCeiling` clamps the controller ceiling |
| Adaptive budget floor (1.45.0+) | Under sustained `recent_429_count` (≥10 → floor 2, ≥20 → floor 1) so hammered tenants truly back off |
| `throttle_waiting` progress flag (worker 0.3.13+) | Control plane refreshes `last_429_at` during parked Retry-After waits even when `no_progress` |
| `graph_requests` liveness (worker 0.3.15+ / PHP 1.46.0+) | Monotonic completed Graph HTTP counter; rising value during `graph_sync` bumps `last_progress_at` so enumeration paging counts as alive |
| Mid-run delta checkpoints | `checkpoint_delta_states` on progress API → `DeltaStateRepository::saveStates`; requeued runs resume enumeration |

Default `kopia.stall_seconds` is **2700** in worker config (`applyDefaults()`); set `0` to disable both upload and graph_sync stall watches.

## Orphan claim recovery

When a worker restarts (e.g. after self-update) without completing or failing in-flight runs, the control plane can leave jobs stuck at `running` while the worker reports `current_load=0`. That blocks per-tenant concurrency slots.

**Detection** (`WorkerClaimService::releaseOrphanedClaimsForNode`):

- **Idle node** (`current_load=0`): queue row `status=running` on that node with `claimed_at` and run progress freshness older than **120 seconds** (uses `last_progress_at`, falls back to `updated_at`). **1.42.0+:** skip when `lease_expires_at` is still in the future (worker alive) or throttled-but-alive (per-child `last_429_at` **or** tenant `ms365_graph_tenant_budget.last_429_at` within **1200s** with fresh lease). Fast unacked reclaim only when lease is expired/absent.
- **Busy node** (`current_load>0`): per-run scan — only individual runs whose `last_progress_at` is older than **1800s** are re-queued; healthy runs on the same node are left alone. **1.46.0+:** skip via `shouldSkipThrottleReaper` (tenant throttled + node heartbeat fresh).

Matching runs are re-queued via the infrastructure-requeue path (progress fields and `delta_states` preserved).

**Lease renewal:** `ms365_worker_heartbeat.php` calls `WorkerLeaseService::renewForNode` only when `current_load > 0`. Active runs renew via `ms365_worker_progress.php` on each **non-`no_progress`** progress POST. Worker **0.3.7+** emits `no_progress` heartbeats when items/bytes are flat longer than `progress_stall_seconds` (default 600s) so flat graph/upload phases do not renew leases.

**Manual recovery:** Admin API `fleet_release_leases` also runs `releaseOrphanedClaimsForAllNodes` and returns `orphans_requeued` in the JSON response.

Fleet cron (`ms365_worker_fleet.php`) invokes orphan release on every active node each cycle.

## Zombie / stale job recovery

Runs can wedge the fleet when a worker fails without clearing its claim, retries exhaust, or progress stops updating. The control plane reconciles these automatically.

**Liveness rule (1.39.0+):** A child is **alive** when `last_progress_at` (real items/bytes progress, rising `graph_requests` during `graph_sync` since **1.46.0**, or throttle signals) or a fresh queue lease refreshed within **180s** (`HEARTBEAT_GAP_SECONDS`). Flat heartbeats and `no_progress` beats do not bump `last_progress_at` or renew leases (except throttle / `graph_requests` liveness paths above).

**`WorkerClaimService::reconcileZombieRuns()`** (fleet cron + each `claimNext`):

| Condition | Action |
|-----------|--------|
| `attempts >= max_attempts` but queue still `queued`/`running` | Terminal fail via `ms365_worker_fail` hooks |
| Queue `running`, worker not alive (expired lease + stale `last_progress_at`) | Infrastructure requeue if attempts remain — **unless** `shouldSkipThrottleReaper` (tenant throttled + node heartbeat fresh) |
| Queue `running`, fresh lease but `last_progress_at` stale **≥1800s** | Infrastructure requeue (`Stale progress reconciled`) — **unless** `shouldSkipThrottleReaper` (tenant throttled + node heartbeat fresh, **1.46.0+**) |
| Child `running`, recent throttle signal (**≤1200s**) + fresh lease | **Skip** infrastructure requeue (worker waiting on Graph limiter / Retry-After) |
| Expired lease + stale `updated_at` (`releaseExpiredLeases`, staleRows, `recoverStaleRunning`) | **Skip** when tenant throttled and worker node heartbeat fresh (**1.45.0+**) |
| Wedge (`0` items/bytes after **1800s**) | Infrastructure requeue — **unless** throttled-but-alive (**1.45.0+**) |
| Idle node, `running` claim, fresh lease (even if progress stale) | **Skip** orphan reclaim (`releaseOrphanedClaimsForIdleNode`, **1.42.0+**) |
| Idle node, `running` claim, expired/absent lease + stale progress | Orphan reclaim (`Orphaned claim released (worker idle)`) |
| Child `running` but queue row missing or not `running` | Re-queue child and sync batch parent — **unless** `shouldSkipThrottleReaper` (**1.45.0+**) |
| Upload stall (**2700s** silence in kopia/upload phase) | Infrastructure requeue via `reconcileBatchChildren` / fleet cron |

**Batch child reaper:** `Ms365BatchRunRepository::reconcileBatchChildren()` uses `requeueBackupRuns` (not `onFail`) so `phase`/`percent`/`items_*` and `delta_states` survive re-claims.

**Fail-fast (no retry):** `JobQueueRepository::isNonRetryableError()` matches permanent failures (e.g. Graph gzip/JSON parse `invalid character '\x1f'`, auth/token errors). `markFailed()` terminal-fails these immediately.

**Concurrency slots:** `countRunningForTenant()` / `countRunningForClient()` ignore stale `running` rows (expired lease and no progress within 120s) so zombies do not block new claims while reconciliation runs.

**Dashboard fields:** `stale_running_jobs` / `exhausted_jobs` on the Worker Fleet dashboard. Hourly `logActivity` alert when either count is non-zero.

**Worker requirement:** Deploy worker **0.1.19+** for the Graph gzip response fix (`internal/graph/client.go`). Without it, mail/OneDrive workloads fail instantly with `invalid character '\x1f'`.

Cron output includes `zombies_reconciled: { requeued, failed, synced }`.

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
`MS365_WORKER_API_BASE=http://192.168.92.75/accounts/modules/addons/cloudstorage/api`  
(secrets stay in `environment.conf`; `config.yaml` must not contain `api.base_url`).

**VMID allocation:** Production scale-up calls prod `fleet_provision_prepare` first so `ms365_worker_nodes` rows and VMIDs live on **prod DB**, not dev.

**Release sync:** Dev `ReleaseSyncService::publishToProduction()` POSTs `fleet_release_upsert` (multipart artifact). Prod optional cron `crons/ms365_worker_release_sync.php` pulls from dev manifest when push fails.

**Remote API:** `addonmodules.php?module=ms365backup&action=fleet_remote&op=…` (shared secret; no admin session).

**Smoke:** `php bin/ms365_fleet_smoke.php` reports `fleet_context` and production URL normalization.
