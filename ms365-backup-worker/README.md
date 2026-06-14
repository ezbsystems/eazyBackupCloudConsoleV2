# ms365-backup-worker

Server-side Go worker for Microsoft 365 backups using **Kopia** (compression + deduplication) and parallel **Microsoft Graph** ingestion.

Forked patterns from `e3-backup-agent` Kopia layer; does not modify the local agent product.

## Build

```bash
make tidy
make build
```

Binary: `bin/ms365-backup-worker`

## Configure

Copy `config/config.yaml.example` to `/etc/ms365-backup-worker/config.yaml`.

Required:

- `api.base_url` — WHMCS cloudstorage API base (e.g. `https://accounts.example.ca/modules/addons/cloudstorage/api`)
- `worker.token` — matches `ms365_worker_token` in ms365backup addon settings

## Run

```bash
./bin/ms365-backup-worker -config config/config.yaml
```

Or install `ms365-backup-worker.service` (see `deploy/proxmox/`).

## Architecture

1. Register + heartbeat with WHMCS (`ms365_worker_*.php` APIs)
2. Claim queued runs from `ms365_job_queue`
3. `graphsync` — parallel Graph delta fetch per workload
4. `graphfs` — virtual directory tree for Kopia
5. `kopia.Snapshot` — upload to `e3ms365-*` bucket repository
