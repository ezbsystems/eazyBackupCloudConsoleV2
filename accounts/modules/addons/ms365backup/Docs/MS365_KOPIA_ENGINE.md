# MS365 Kopia backup engine

## Overview

Microsoft 365 backups run exclusively through a **Go worker fleet** that:

- Fetches data from Microsoft Graph in parallel (delta where supported)
- Stores snapshots in **Kopia** repositories inside each customer's `e3ms365-{token}` bucket
- Achieves **compression** (`zstd-default`) and **content deduplication** across runs

The legacy PHP backup execution layer (`BackupOrchestrator`, `*BackupEngine.php`, `bin/ms365_backup.php`) was **removed in module 1.18.0**. PHP remains the **control plane** (inventory, job planning, queue, worker claim APIs). Admin CLI ops use `bin/ms365_admin.php`.

## Components

| Component | Path |
|-----------|------|
| Go worker | `/var/www/eazybackup.ca/ms365-backup-worker` |
| Worker APIs | `cloudstorage/api/ms365_worker_*.php` |
| Queue / claim | `Ms365Backup/WorkerClaimService.php` |
| Job enqueue | `Ms365Backup/WorkerSpawner.php` (queue-only) |
| Kopia repo bootstrap | `Ms365Backup/KopiaRepoBootstrapService.php` |
| Workload flags | `Ms365Backup/Ms365EngineConfig.php` |
| Staging E2E checklist | `Docs/KOPIA_FILE_BACKUP_E2E.md` |

## Kopia repository

One repository per `e3ms365-*` bucket (`repository_id` = `ms365:{bucket}`), registered in `s3_kopia_repos` at M365 storage bootstrap.

Run rows store `manifest_id`, `bytes_hashed`, `bytes_uploaded`, `engine_mode` (`kopia`).

## Graph sync performance

| Knob | Default | Purpose |
|------|---------|---------|
| `graph_parallel_requests` | 32 | Max concurrent Graph HTTP calls (semaphore) |
| `graph_folder_parallel` | 4 | Concurrent mail folders per mailbox |
| `graph.adaptive_concurrency` | true | Shrink limit on sustained 429s |
| `graph.use_batch_fallback` | true | `$batch` GET for messages missing delta `$select` fields |

**Mail pipeline:** delta query includes full `$select`; folders processed in parallel; mail **attachments** streamed as separate Graph files. **Incrementals:** prior manifest seeds overlay tree; delta tokens in `ms365_delta_state` injected on claim as `delta_states`.

## File workloads (Go)

| Workload | Scope / physical key | Snapshot paths |
|----------|----------------------|----------------|
| `onedrive` | `drive:{id}`, scope `onedrive`/`files` | `{tenant}/drives/{id}/content/…` |
| `sharepoint` | `site:{id}`, scope `files` | `{tenant}/sites/{siteId}/drives/…`, per-drive delta |
| `sharepoint_lists` | `site:{id}`, scope `lists` | `{tenant}/sites/{siteId}/lists/…` |
| Mail attachments | `user:{id}`, scope `mail` | `…/mail/…/attachments/…` |

Teams **files** use `site:{siteId}` jobs (planner dedupes Team + Site selection).

## Whale-scale sharding

Large resources split when inventory `meta.size_bytes` ≥ `ms365_shard_threshold_bytes` (default 100 GiB):

| Pattern | Example physical_key |
|---------|---------------------|
| Drive/site range shard | `drive:{driveId}#shard:0` … `#shard:{n-1}` |
| Mail folder shard | `user:{userId}#mail:{folderId}` |

Go worker partitions drive/site file items by `fnv32(itemId) % shardTotal == shardIndex` (`graphsync/shard.go`).

Delta tokens are stored per shard `physical_key` and advanced only on shard success.

## Workloads (Go)

Controlled by `ms365_kopia_workloads_json`, run scope, and `WorkerClaimService::workloadsForRun()`:

`mail`, `calendar`, `contacts`, `tasks`, `onedrive`, `sharepoint`, `sharepoint_lists`, `teams`, `planner`, `onenote`, `directory`

## Restore (Kopia → Graph)

Restore is **separate from backup**: `RestoreOrchestrator` + `ms365_restore_runs` + Go worker `restore` jobs.

| Step | Component |
|------|-----------|
| Snapshot list | `ms365_restore_snapshots_list.php` |
| Browse tree | `ms365_restore_browse.php` → Go `browse` CLI |
| Start | `ms365_restore_start.php` |
| Execute | Go `graphrestore` (skip duplicates by default) |
| Progress | `Ms365BatchLiveService` → `e3backup_live.tpl` |

**Conflict policy (default):** skip duplicates — mail by `internetMessageId`, calendar by `iCalUId`, files by path+hash where possible.

## Fleet operations

- Smoke: `php modules/addons/ms365backup/bin/ms365_fleet_smoke.php`
- Autoscale cron: `modules/addons/ms365backup/crons/ms365_worker_fleet.php`
- Proxmox deploy: `ms365-backup-worker/deploy/proxmox/README.md`
