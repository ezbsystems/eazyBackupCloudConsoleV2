# Kopia file backup — staging E2E checklist

Use this checklist after deploying the Kopia Go worker fleet and applying `upgrade_phase10_kopia_only.sql` (engine is Kopia-only; no PHP backup worker).

## Prerequisites

- WHMCS `ms365backup` module ≥ 1.18.0 activated (runs migrations).
- `ms365_worker_token` set in addon settings; worker nodes registered and heartbeating.
- Worker binary published via Fleet releases (`bin/ms365_fleet_smoke.php` passes).
- Cron: `modules/addons/ms365backup/crons/ms365_worker_fleet.php` every 2–5 minutes.
- Dev/staging Entra tenant connected via e3 MS365 wizard with `e3ms365-*` bucket bootstrapped.

## 1. Connect and inventory

1. Open `index.php?m=cloudstorage&page=e3backup&view=ms365`.
2. Complete connect + **Refresh inventory**.
3. Confirm OneDrive children, SharePoint sites, and Teams appear in step 2 tree.

## 2. Create file-focused job

Create a job selecting:

| Resource | Scopes |
|----------|--------|
| User → OneDrive child | Files |
| SharePoint site | Files + Lists |
| Team | Files (note dedup warning if site also selected) |
| User | Mail (attachment coverage) |

Save job and **Run now**.

## 3. Live run verification

1. Redirect to `view=live&run_id={batch_run_id}`.
2. All child `ms365_backup_runs` reach `success`.
3. Each child row has non-empty `manifest_id`.
4. `engine_mode` = `kopia` on run rows.

SQL spot-check:

```sql
SELECT id, physical_key, status, engine_mode, manifest_id, bytes_hashed
FROM ms365_backup_runs
WHERE batch_run_id = '{batch_run_id}'
ORDER BY created_at;
```

## 4. Binary content verification

1. Open Restore tab → select snapshot from the batch run.
2. Call browse API or restore wizard step 2:
   - Expand OneDrive path: `{tenantId}/drives/{driveId}/content/…`
   - Confirm file entries with non-zero size.
3. Expand SharePoint site: `{tenantId}/sites/{siteId}/…` (files + `lists/`).
4. Optional: mail attachment path under `{tenantId}/users/{userId}/mail/…/attachments/`.

## 5. Incremental second run

1. Run the same job again without changes.
2. Second run should complete faster; logs show delta/incremental phases.
3. Verify `ms365_delta_state` rows exist for the tenant + physical keys:

```sql
SELECT physical_key, workload, state_key, updated_at
FROM ms365_delta_state
WHERE tenant_record_id = {id}
ORDER BY updated_at DESC
LIMIT 20;
```

## 6. Large drive shard smoke (optional)

For a OneDrive with inventory `meta.size_bytes` ≥ `ms365_shard_threshold_bytes` (default 100 GiB):

1. Confirm planner emits `drive:{id}#shard:0` … `#shard:N` child runs.
2. Each shard succeeds with distinct `kopia_source_path` ending in `.shards/{n}`.
3. Go worker stats show `skipped_shard` on each shard (partitioning active).

## 7. Fleet smoke

```bash
sudo -u www-data php modules/addons/ms365backup/bin/ms365_fleet_smoke.php
```

Expected: `engine_mode_kopia`, `worker_token_configured` OK; `active_worker_nodes` OK when fleet deployed.

## Failure triage

| Symptom | Check |
|---------|--------|
| Runs stay `queued` | Worker nodes, token, fleet cron, `ms365_job_queue` leases |
| `manifest_id` empty | Worker logs, Graph 403, bucket/Kopia repo bootstrap |
| Lists missing in browse | `sharepoint_lists` workload + scope `lists: true` on site job |
| Duplicate shard work | Go worker version includes drive shard filtering (`graphsync/shard.go`) |
