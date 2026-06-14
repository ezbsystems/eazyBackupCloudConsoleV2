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
| Dashboard | Queue depth, capacity, version summary, audit log |
| Nodes | Drain / retire workers, release stale leases |
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

| Setting | Default |
|---------|---------|
| Platform max concurrent | 100 |
| Per Entra tenant | 3 |
| Per WHMCS client | 10 |
| Per worker node | 10 (config.yaml) |

## Proxmox template

See `ms365-backup-worker/deploy/proxmox/README.md`.
