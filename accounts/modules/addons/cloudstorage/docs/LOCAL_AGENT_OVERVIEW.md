# Local Backup Agent – Developer Overview (Kopia + Rclone)

## What it is
- Windows backup agent (single Go binary) that runs as a service/tray helper and polls the WHMCS addon via agent APIs.
- Executes local-to-S3 backups for `source_type=local_agent`.
- Engines:
  - **Sync (rclone)** – legacy file sync to S3 (embedded rclone).
  - **Backup (Kopia)** – dedup/encrypted snapshots to S3 (embedded Kopia library).
- Authentication: per-agent token headers (`X-Agent-ID`, `X-Agent-Token`).

## Key repos and files
- Agent: `/var/www/eazybackup.ca/e3-backup-agent/`
  - `cmd/agent/main.go` – entrypoint/service.
  - `internal/agent/config.go` – config (adds `KopiaCacheDir`).
  - `internal/agent/api_client.go` – agent_* client; Kopia fields and command polling/completion.
  - `internal/agent/runner.go` – polling loop; engine switch (`sync`→rclone, `kopia`→Kopia); command handling (cancel/maintenance/restore).
  - `internal/agent/kopia.go` – Kopia repo connect/open, snapshot, restore, maintenance; S3/filesystem storage backends.
  - `internal/agent/backends.go` – rclone backend registration (local, s3).
  - `go.mod` – includes `github.com/kopia/kopia v0.9.8`, `github.com/rclone/rclone v1.67.0`.
  - Build: `GOOS=windows GOARCH=amd64 go build -o bin/e3-backup-agent.exe ./cmd/agent`.
- WHMCS addon: `/var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/`
  - Agent APIs: `agent_next_run.php`, `agent_update_run.php`, `agent_push_events.php`, `agent_poll_commands.php`, `agent_complete_command.php`, `agent_fetch_jobs.php` (legacy), `agent_start_run.php` (legacy).
  - Client/admin command enqueue: `cloudbackup_request_command.php`, `admin_cloudbackup_request_command.php`.
  - Job APIs: `cloudbackup_create_job.php`, `cloudbackup_update_job.php`, `cloudbackup_start_run.php`, `cloudbackup_list_runs.php`.
  - Logs/events: `cloudbackup_get_run_logs.php`, `cloudbackup_get_run_events.php`.
  - UI: `templates/cloudbackup_jobs.tpl` (client wizard for local-agent + Kopia), `cloudbackup_runs.tpl` (history/logs), `admin/cloudbackup_admin.tpl` (jobs/runs/maintenance), `cloudbackup_live.tpl` (live view).
  - Module bootstrap/schema: `cloudstorage.php`.
- Worker (legacy cloud worker): `/var/www/eazybackup.ca/e3-cloudbackup-worker/` — should ignore `source_type=local_agent` runs; agents own those.

## Database tables (new/extended)
- `s3_cloudbackup_agents`: agent registry (id, client_id, agent_token, hostname, status, last_seen_at).
- `s3_cloudbackup_jobs`: adds `engine`, `dest_type`, `dest_bucket/prefix`, `dest_local_path`, `bucket_auto_create`, `schedule_json`, `retention_json`, `policy_json`, `bandwidth_limit_kbps`, `parallelism`, `encryption_mode`, `compression`, `local_include_glob`, `local_exclude_glob`, `local_bandwidth_limit_kbps`, `agent_id`, `last_policy_hash`.
- `s3_cloudbackup_runs`: adds `engine`, `dest_type`, `dest_bucket/prefix`, `dest_local_path`, `stats_json`, `progress_json`, `log_ref`, `policy_snapshot`, `worker_host`, plus `agent_id`.
- `s3_cloudbackup_run_logs`: structured run log stream.
- `s3_cloudbackup_run_events`: vendor-agnostic event feed.
- `s3_cloudbackup_run_commands`: queued commands for agents (`cancel`, `maintenance_quick/full`, `restore`, `mount`).
- Settings: `cloudbackup_agent_s3_endpoint`, optional `cloudbackup_agent_s3_region`.

## Agent-server contract (sync + Kopia)
- Run assignment: `agent_next_run.php` filters queued runs for that agent (run.agent_id/job.agent_id), claims as `starting`, returns engine, source/dest, policy/retention/schedule JSON, decrypted access keys, endpoint/region.
- Progress/events: `agent_update_run.php` (progress snapshot, log_ref, stats/progress JSON), `agent_push_events.php` (structured events).
- Commands: server enqueues in `s3_cloudbackup_run_commands`; agent polls `agent_poll_commands.php`; completion via `agent_complete_command.php`. Supported: cancel, maintenance_quick/full, restore (mount TODO).
- Start (manual/UI): `cloudbackup_start_run.php` → `CloudBackupController::startRun()` inserts queued run, binds to agent for `local_agent`, optionally stores engine/dest fields; workers should ignore local_agent runs.
- Reclaim/watchdog: reclaim stale in-progress for same agent within grace; watchdog cron fails truly stale runs.

## Heartbeats, watchdog, and resume
- Heartbeats: `agent_update_run.php` updates `updated_at`; agents should POST every ≤60–90s.
- Watchdog: `crons/agent_watchdog.php` fails stale `starting/running` runs past `AGENT_WATCHDOG_TIMEOUT_SECONDS` (default 720s) with `AGENT_OFFLINE`.
- Reclaim: `agent_next_run.php` can return the same agent’s in-progress run if heartbeat is older than `AGENT_RECLAIM_GRACE_SECONDS` (default 180s) but before watchdog cutoff.
- Exclusivity: in-progress runs stay with their agent; reclaim requires matching `run.agent_id` (or job.agent_id legacy).
- Env knobs: `AGENT_WATCHDOG_TIMEOUT_SECONDS`, `AGENT_RECLAIM_GRACE_SECONDS` (grace < timeout).

## UI/UX (client)
- `cloudbackup_jobs.tpl`: local-agent wizard modal with steps (Mode/Agent/Destination → Source → Schedule → Retention/Policy → Review); engine toggle (Sync/rclone vs Backup/Kopia); agent dropdown (Alpine, `agent_list.php`); S3 bucket picker/search; fields for source path, include/exclude globs, schedule_json, retention_json, policy_json, bandwidth, parallelism, encryption_mode, compression. Restore button per job opens restore wizard placeholder (snapshot list/file tree/destination—to complete).
- `cloudbackup_runs.tpl`: shows engine per job; run detail modal pulls structured events/logs.

## UI/UX (admin)
- `admin/cloudbackup_admin.tpl`: engine/destination/log_ref columns; maintenance buttons enqueue commands for Kopia; runs table engine-aware.
- Logs/events APIs power admin/client views.

## Windows agent runtime
- Config: `%PROGRAMDATA%\\E3Backup\\agent.conf`.
- Runs/cache: `%PROGRAMDATA%\\E3Backup\\runs` (Kopia config under `runs/kopia/job_<job_id>.config`).
- Rclone paths: in-memory config per run; Kopia repo config per job.
- Service wrapper via kardianos/service; can run foreground for debugging.

## Changes/Fixes (Dec 2025, Kopia integration)
- Added Kopia library, engine switch, and command handling (maintenance/restore).
- Structured run logs/events and command tables added.
- `agent_next_run` returns engine/source/dest/policy/schedule JSON and decrypts access keys.
- Job create/update enforce S3-only dest, validate agent for local_agent, normalize JSON fields to avoid invalid inserts.
- `startRun` binds runs to agents; workers should ignore local_agent runs.

## Critical Bug Fixes (Dec 7, 2025)

### 1. S3 Endpoint Format for MinIO SDK

**Symptom:** Kopia backup fails immediately with error:
```
unable to create client: Endpoint url cannot have fully qualified paths.
```

**Root Cause:** The Kopia S3 storage uses the MinIO Go SDK (`minio.New()`), which expects the endpoint to be **host-only** (e.g., `s3.example.com`), not a full URL with scheme (e.g., `https://s3.example.com`). When a scheme is included, MinIO interprets it as a path component and rejects it.

**Fix Location:** `internal/agent/kopia.go` → `kopiaOptions.storage()`

**Before (broken):**
```go
return s3.New(ctx, &s3.Options{
    Endpoint:    o.endpoint,  // "https://s3.example.com" ← WRONG
    DoNotUseTLS: false,
})
```

**After (fixed):**
```go
endpointHost := o.endpoint
doNotUseTLS := false
if u, err := url.Parse(o.endpoint); err == nil && u.Host != "" {
    endpointHost = u.Host                // "s3.example.com" ← host only
    doNotUseTLS = (u.Scheme == "http")   // derive TLS from scheme
}
return s3.New(ctx, &s3.Options{
    Endpoint:    endpointHost,
    DoNotUseTLS: doNotUseTLS,
})
```

**Key Insight:** The `DoNotUseTLS` option controls whether MinIO uses HTTPS (`false`) or HTTP (`true`). Extract the host from the URL and infer TLS usage from the scheme.

---

### 2. Missing `snapshot.SaveSnapshot()` Call

**Symptom:** Backup uploads data successfully (confirmed in S3 bucket), but fails with:
```
kopia: upload completed but manifest missing; no snapshots found for source
```
No manifest ID is recorded, making restores impossible.

**Root Cause:** The Kopia `Uploader.Upload()` function creates a manifest **structure** containing snapshot metadata, but it does **NOT** persist the manifest to the repository. You must explicitly call `snapshot.SaveSnapshot()` to:
1. Write the manifest to the repository's manifest store
2. Generate and assign the manifest ID

Without this call, data is uploaded but there's no snapshot record pointing to it.

**Fix Location:** `internal/agent/kopia.go` → `kopiaSnapshot()` inside the `WriteSession` callback

**Before (broken):**
```go
man, err := u.Upload(wctx, srcEntry, pol, srcInfo)
if err != nil {
    return err
}
if man != nil {
    manifestID = string(man.ID)  // ← man.ID is EMPTY here!
}
```

**After (fixed):**
```go
man, err := u.Upload(wctx, srcEntry, pol, srcInfo)
if err != nil {
    return err
}
if man == nil {
    return fmt.Errorf("kopia: upload returned nil manifest")
}

// CRITICAL: Upload() does NOT persist the manifest. Must call SaveSnapshot().
savedID, saveErr := snapshot.SaveSnapshot(wctx, w, man)
if saveErr != nil {
    return fmt.Errorf("kopia: save snapshot: %w", saveErr)
}
manifestID = string(savedID)  // ← Now we have a real ID
```

**Key Insight:** In Kopia's architecture:
- `Uploader.Upload()` → hashes files, deduplicates, uploads content blobs, returns manifest struct
- `snapshot.SaveSnapshot()` → persists manifest to repo, returns manifest ID

Both steps are required for a complete backup. The manifest ID is what allows future restores and snapshot listings.

---

## Restore Pipeline (Dec 2025)

### Architecture
The restore pipeline is independent of active backup runs. Key components:

1. **`cloudbackup_start_restore.php`** - Client API to initiate restore
   - Creates a "restore" run for progress tracking
   - Queues a restore command referencing the backup run for job context
   - Returns `restore_run_id` for UI redirect to live progress

2. **`agent_poll_pending_commands.php`** - Agent polls for pending commands
   - Called in the agent's main polling loop (not tied to active runs)
   - Returns full job context (credentials, endpoint, bucket, etc.)
   - Supports restore, maintenance_quick, maintenance_full commands

3. **Agent `executeRestoreCommand()`** - Handles restore execution
   - Extracts manifest_id, target_path from payload
   - Uses restore_run_id for progress tracking
   - Updates run status: queued → running → success/failed
   - Pushes structured events for live log display

### Client UI Flow
1. User clicks "Restore" on a job → opens restore wizard
2. User selects backup run (snapshot), enters target path
3. `cloudbackup_start_restore.php` creates restore run and queues command
4. UI redirects to `cloudbackup_live.tpl` with `restore_run_id`
5. Live progress page shows restore progress, events, status

### Event Messages
- `RESTORE_STARTING` - Restore initiated
- `RESTORE_PROGRESS` - Files/dirs/bytes restored
- `RESTORE_COMPLETED` - Success
- `RESTORE_FAILED` - Error with message
- `KOPIA_RESTORE_*` - Kopia-specific stages

### Email Notifications
Restore runs trigger the same email notification flow as backups when terminal status is reached and notifications are enabled.

## Outstanding items / gotchas
- Agent: implement Kopia mount command for browsing snapshots.
- Schema: ensure all envs have run columns (`engine`, `dest_type`, `dest_bucket`, `dest_prefix`, `dest_local_path`, `worker_host`, `run_type`); current code guards missing columns.
- Local destinations deferred until schema change and agent path handling are ready.
- Heartbeats: keep ≤90s to stay within reclaim window; watchdog still marks stale runs failed.

