# MS365 worker fleet (Proxmox)

## Control plane

- `ms365_worker_nodes` — registered workers
- `ms365_job_queue` — claims, leases, `worker_node_id`
- `crons/ms365_worker_fleet.php` — autoscale + lease recovery + stale node detection
- `crons/ms365_worker_build_runner.php` — admin-triggered Go builds
- `ProxmoxProvisioner` — clone/destroy LXC from template

## Admin UI

**Addons → MS365 Backup → Worker Fleet**

| Tab | Purpose |
|-----|---------|
| Dashboard | Queue depth, capacity, stale/exhausted job counts, version summary, audit log |
| Nodes | Drain / retire workers, release stale leases (also re-queues orphaned claims) |
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

**Version labels** must be unique semver (`x.y.z`). `ms365_worker_releases.version` has a unique index — reusing a published label (e.g. `0.2.3`) fails at the **publish** step even when `go build` succeeds. Use the next patch (Fleet UI suggests via `suggest_next_version`). Source default: `ms365-backup-worker/internal/version/version.go` (currently **0.2.4**).

No SSH/`pct push` required after golden template is in place.

## Cron scheduling

```bash
# Fleet autoscale + lease recovery (every 2–5 min)
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
| Per Entra tenant | `ms365_per_tenant_max_concurrent` | **3** | WHMCS addon settings |
| Per WHMCS client | `ms365_per_client_max_concurrent` | 10 | WHMCS addon settings |
| Per worker node | `max_concurrent_runs` | **4** | `config.yaml` |
| Graph HTTP (per workload) | `graph_parallel_requests` | **16** | `config.yaml` |
| Mail folders (per workload) | `graph_folder_parallel` | **4** | `config.yaml` |
| SharePoint drives (per site workload) | `graph_sharepoint_drive_parallel` | **4** | `config.yaml` |

**Tuning for faster large-tenant backups** (increase gradually; watch Graph 429 throttling and worker RAM):

| Knob | Conservative → aggressive | Notes |
|------|---------------------------|-------|
| `ms365_per_tenant_max_concurrent` | 3 → **5–10** | More users syncing at once |
| `max_concurrent_runs` | 4 → **6** | Needs ~16 GB RAM per CT |
| `graph_parallel_requests` | 16 → **32** | See `MS365_KOPIA_ENGINE.md` |
| `graph_folder_parallel` | 4 → **8** | Parallel mail-folder delta |
| `graph_sharepoint_drive_parallel` | 4 → **6** | Parallel SharePoint drive delta (monolithic `site:{id}` jobs) |
| Fleet size | 2 → **3+** nodes | More worker capacity |

OneDrive workloads use the **heavy job** RAM/disk budget (`heavy_job_ram_budget_mib`); raising `max_concurrent_runs` without more RAM can block claims.

**UI note:** The live page stage shows e.g. `Syncing from Microsoft Graph (3 of 12 workloads active)` when multiple children run in parallel; parent progress blends completed workloads plus in-flight graph_sync fraction.

## Orphan claim recovery

When a worker restarts (e.g. after self-update) without completing or failing in-flight runs, the control plane can leave jobs stuck at `running` while the worker reports `current_load=0`. That blocks per-tenant concurrency slots.

**Detection** (`WorkerClaimService::releaseOrphanedClaimsForNode`):

- Worker heartbeat or fleet cron reports `current_load=0`
- Queue row `status=running` on that node
- `claimed_at` and run `updated_at` older than **120 seconds** (no progress)

Matching runs are re-queued (progress fields reset).

**Lease renewal:** `ms365_worker_heartbeat.php` calls `WorkerLeaseService::renewForNode` only when `current_load > 0`. Active runs still renew via `ms365_worker_progress.php` on each progress POST.

**Manual recovery:** Admin API `fleet_release_leases` also runs `releaseOrphanedClaimsForAllNodes` and returns `orphans_requeued` in the JSON response.

Fleet cron (`ms365_worker_fleet.php`) invokes orphan release on every active node each cycle.

## Zombie / stale job recovery

Runs can wedge the fleet when a worker fails without clearing its claim, retries exhaust, or progress stops updating. The control plane now reconciles these automatically.

**`WorkerClaimService::reconcileZombieRuns()`** (fleet cron + each `claimNext`):

| Condition | Action |
|-----------|--------|
| `attempts >= max_attempts` but queue still `queued`/`running` | Terminal fail via `ms365_worker_fail` hooks |
| Queue `running`, child `updated_at` older than **120s**, lease expired, worker `load=0` | Requeue if attempts remain, else terminal fail |
| Child `running` but queue row missing or not `running` | Re-queue child and sync batch parent |

**Fail-fast (no retry):** `JobQueueRepository::isNonRetryableError()` matches permanent failures (e.g. Graph gzip/JSON parse `invalid character '\x1f'`, auth/token errors). `markFailed()` terminal-fails these immediately.

**Concurrency slots:** `countRunningForTenant()` / `countRunningForClient()` ignore stale `running` rows (expired lease and no progress within 120s) so zombies do not block new claims while reconciliation runs.

**Dashboard fields:** `stale_running_jobs` / `exhausted_jobs` on the Worker Fleet dashboard. Hourly `logActivity` alert when either count is non-zero.

**Worker requirement:** Deploy worker **0.1.19+** for the Graph gzip response fix (`internal/graph/client.go`). Without it, mail/OneDrive workloads fail instantly with `invalid character '\x1f'`.

Cron output includes `zombies_reconciled: { requeued, failed, synced }`.

See `ms365-backup-worker/deploy/proxmox/README.md`.
