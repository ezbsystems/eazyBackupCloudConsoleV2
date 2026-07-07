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
| `graph_sharepoint_drive_parallel` | 4 | Concurrent SharePoint drive deltas per site workload |
| `graph.adaptive_concurrency` | true | Shrink limit on sustained 429s |
| `graph.use_batch_fallback` | true | `$batch` GET for messages missing delta `$select` fields |

**Mail pipeline:** delta query includes full `$select`; folders processed in parallel; mail **attachments** streamed as separate Graph files. **Incrementals:** prior manifest seeds overlay tree; delta tokens in `ms365_delta_state` injected on claim as `delta_states`.

## File workloads (Go)

| Workload | Scope / physical key | Snapshot paths |
|----------|----------------------|----------------|
| `onedrive` | `drive:{id}`, scope `onedrive`/`files` | `{tenant}/drives/{id}/content/…` |
| `sharepoint` | `site:{id}`, scope `files` | `{tenant}/sites/{siteId}/drives/…`, per-drive delta (parallel when multiple drives in one workload) |
| `sharepoint_lists` | `site:{id}` or `list:{listId}`, scope `lists` | `{tenant}/sites/{siteId}/lists/…` |
| Mail attachments | `user:{id}`, scope `mail` | `…/mail/…/attachments/…` |

Teams **files** use `site:{siteId}` jobs (planner dedupes Team + Site selection).

## Whale-scale sharding

When `ms365_sharding_enabled` is on:

1. **SharePoint Files** — each document library becomes its own workload (`drive:{libraryId}`; optional `#shard:N`). Lists stay on `site:{siteId}` unless promoted (below). Inventory stores `meta.drives[]` per site.
2. **SharePoint Lists** — inventory stores `meta.lists[]` with `item_count` (from Graph `$count`). Lists above `ms365_list_job_item_threshold` (default 50k) become `list:{listId}` jobs; the site job skips them via `_excluded_list_ids`. Lists above `ms365_list_shard_item_threshold` (default 500k) split into `list:{listId}#shard:N` **createdDateTime** range partitions (not FNV hash — each shard queries a time window).
3. **Range shards** — large drives/sites split when `meta.size_bytes` ≥ `ms365_shard_threshold_bytes` **or** `meta.item_count` ≥ `ms365_shard_item_threshold`.

| Pattern | Example physical_key |
|---------|---------------------|
| SharePoint per-library | `drive:{driveId}` (scope `_site_id`) |
| SharePoint per-list | `list:{listId}` (scope `_site_id`, `_list_id`) |
| List time-range shard | `list:{listId}#shard:0` … (scope `_shard.kind=list_created_range`) |
| Drive/site range shard | `drive:{driveId}#shard:0` … `#shard:{n-1}` |
| Mail folder shard | `user:{userId}#mail:{folderId}` |

Go worker partitions **drive files** by `fnv32(itemId) % shardTotal == shardIndex` (`graphsync/shard.go`). **List shards** use Graph `$filter` on `createdDateTime` (fallback: client-side range filter).

Delta tokens are stored per `physical_key` and advanced only on shard success.

## Graph pagination (configurable)

WHMCS setting `ms365_graph_pagination_json` (default: SharePoint `max_pages=2500`, `on_cap=warn_continue`). Limits are passed in the claim payload as `graph_pagination` and enforced in Go `PaginateDeltaOpts` (including duplicate-page and empty-page wedge detection). On `warn_continue`, partial delta sync completes without advancing the token.

## Mid-run Graph token refresh

Workers call `ms365_worker_graph_token.php` on Graph **401** and proactively every `graph_token_refresh_seconds` (default 2700). `graph 401 after token refresh` is terminal non-retryable.

## Kopia upload observability

| Knob | Default | Purpose |
|------|---------|---------|
| `kopia.stall_seconds` | `2700` (45m); `0` = disabled | Worker fails retryably when hashing progress stops during `kopia_upload` |
| `kopia.stall_check_interval_seconds` | `60` | Stall watchdog poll interval |
| `kopia.stall_grace_seconds` | `300` | Ignore stall checks during initial repo open |

**Live UI:** Parent batch throughput is phase-aware via `Ms365LiveSpeedMetrics` (EMA-smoothed, 30s staleness):

| Dominant phase | Speed metric | Counter |
|----------------|--------------|---------|
| `graph_sync` / `prior_snapshot` | Items/s or Graph requests/s | `objects_transferred` / `graph_requests_total` |
| `kopia_upload` | Upload speed (preferred) or Hash speed | `bytes_transferred` / `bytes_hashed` sum |

Progress API exposes `speed_metric_kind`, `speed_metric_label`, `speed_updated_at`, `dominant_phase`. Stale rates clear when counters stop moving (fixes frozen hash spikes during upload-only phases).

**Child stats:** `ms365_backup_runs.stats_json` stores `graph_sync_ms` and `kopia_snapshot_ms` (live during run, final on complete). Admin Jobs batch detail shows per-workload phase timings.

## Batch shard auto-retry

When `ms365_batch_auto_retry_enabled` is on (default), a parent batch with partial failures **re-queues only failed or never-started child workloads** on the same `e3_batch_run_id` instead of requiring a full manual Run Now. Eligible children:

- `error` / `failed` with a retryable error message
- `cancelled` with `Batch ended before workload started`

Capped by `ms365_batch_auto_retry_max_rounds` (default 2) in parent `stats_json.ms365_batch_auto_retry_round`. Parent stays `running` while retries are in flight; terminal mixed outcomes map to `partial_success`.

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
