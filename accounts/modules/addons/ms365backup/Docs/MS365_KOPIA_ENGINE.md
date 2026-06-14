# MS365 Kopia backup engine

## Overview

Microsoft 365 backups can run through a **Go worker fleet** that:

- Fetches data from Microsoft Graph in parallel (delta where supported)
- Stores snapshots in **Kopia** repositories inside each customer's `e3ms365-{token}` bucket
- Achieves **compression** (`zstd-default`) and **content deduplication** across runs

**Production uses Kopia only.** The legacy PHP JSON worker (`bin/ms365_backup.php`, `engine_mode=php`) is retained for local debugging only and is not used for customer backups or restores.

## Engine mode (`ms365_engine_mode`)

| Mode | Behavior |
|------|----------|
| `kopia` | **Production default** — Proxmox Go workers claim backup and restore jobs |
| `php` | Dev-only JSON-per-file PHP worker (not for production) |
| `kopia_shadow` | Dev comparison: PHP then Kopia re-queue |

## Components

| Component | Path |
|-----------|------|
| Go worker | `/var/www/eazybackup.ca/ms365-backup-worker` |
| Worker APIs | `cloudstorage/api/ms365_worker_*.php` |
| Queue / claim | `Ms365Backup/WorkerClaimService.php` |
| Kopia repo bootstrap | `Ms365Backup/KopiaRepoBootstrapService.php` |
| Workload flags | `Ms365Backup/Ms365EngineConfig.php` |

## Kopia repository

One repository per `e3ms365-*` bucket (`repository_id` = `ms365:{bucket}`), registered in `s3_kopia_repos` at M365 storage bootstrap.

Run rows store `manifest_id`, `bytes_hashed`, `bytes_uploaded`, `engine_mode`.

## Graph sync performance (2026-06-14)

| Knob | Default | Purpose |
|------|---------|---------|
| `graph_parallel_requests` | 32 | Max concurrent Graph HTTP calls (semaphore) |
| `graph_folder_parallel` | 4 | Concurrent mail folders per mailbox |
| `graph.adaptive_concurrency` | true | Shrink limit on sustained 429s |
| `graph.use_batch_fallback` | true | `$batch` GET for messages missing delta `$select` fields |

**Mail pipeline:** delta query includes full `$select` (no N+1 GET); folders processed in parallel; items written to per-run staging dir; Kopia snapshots from local FS. **Incrementals:** prior manifest seeds staging tree; delta tokens stored in `ms365_delta_state` and injected on claim as `delta_states`.

## Whale-scale sharding (2026-06-14)

Large resources split at plan time when inventory `meta.size_bytes` ≥ `ms365_shard_threshold_bytes` (default 100 GiB):

| Pattern | Example physical_key |
|---------|---------------------|
| Drive/site range shard | `drive:{driveId}#shard:0` … `#shard:{n-1}` |
| Mail folder shard | `user:{userId}#mail:{folderId}` |

Claim payload additions for Go worker:

| Field | Purpose |
|-------|---------|
| `parent_physical_key` | Logical resource key without shard suffix |
| `kopia_source_path` | Per-shard Kopia `SourceInfo.Path` (e.g. `{tenant}/drives/{id}/.shards/0`) |
| `shard` | `{index, total, kind, segment, parent_physical_key}` |
| `lease_expires_at` | Unix time; renew via `ms365_worker_progress.php` or `ms365_worker_lease.php` |

Delta tokens are stored per shard `physical_key` and advanced only on shard success.

## Workloads (Go)

Controlled by `ms365_kopia_workloads_json` and run scope:

mail, calendar, contacts, tasks, onedrive, sharepoint, teams, planner, onenote, directory

## Restore (Kopia → Graph)

Restore is **separate from backup**: `RestoreOrchestrator` + `ms365_restore_runs` + Go worker `restore` jobs.

| Step | Component |
|------|-----------|
| Snapshot list | `ms365_restore_snapshots_list.php` — MS365 batch runs with child `manifest_id` |
| Browse tree | `ms365_restore_browse.php` → Go `browse` CLI lists Kopia snapshot paths |
| Start | `ms365_restore_start.php` — enqueues restore jobs, returns `batch_run_id` for live view |
| Execute | Go `graphrestore` writes selected items to Graph (skip duplicates by default) |
| Progress | `Ms365BatchLiveService` aggregates child `ms365_restore_runs` into `e3backup_live.tpl` |

Virtual paths inside snapshots mirror backup layout, e.g. `{tenantId}/users/{userId}/mail/{folderId}/{messageId}.json`.

**Conflict policy (default):** skip duplicates — mail matched by `internetMessageId`, calendar by `iCalUId`, files by path+hash where possible.

**Calendar restore:** events are created without sending meeting invitations to attendees.
