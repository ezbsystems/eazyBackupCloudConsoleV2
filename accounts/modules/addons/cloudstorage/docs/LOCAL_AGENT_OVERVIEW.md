# Local Backup Agent – Developer Overview (Kopia + Rclone + Hyper-V)

## What it is
- Windows backup agent (single Go binary) that runs as a service/tray helper and polls the WHMCS addon via agent APIs.
- Executes local-to-S3 backups for `source_type=local_agent`.
- Engines:
  - **Sync (rclone)** – legacy file sync to S3 (embedded rclone).
  - **Backup (Kopia)** – dedup/encrypted snapshots to S3 (embedded Kopia library).
  - **Disk Image** – block-level backup of volumes via VSS snapshots to S3 (uses Kopia for storage).
  - **Hyper-V** – virtual machine backup with checkpoint-based consistency and RCT incremental support (Windows only).
- Authentication: per-agent token headers (`X-Agent-ID`, `X-Agent-Token`).

## Key repos and files
- Agent: `/var/www/eazybackup.ca/e3-backup-agent/`
  - `cmd/agent/main.go` – entrypoint/service.
  - `internal/agent/config.go` – config (adds `KopiaCacheDir`).
  - `internal/agent/api_client.go` – agent_* client; Kopia fields and command polling/completion.
  - `internal/agent/runner.go` – polling loop; engine switch (`sync`→rclone, `kopia`→Kopia, `disk_image`, `hyperv`); command handling (cancel/maintenance/restore).
  - `internal/agent/kopia.go` – Kopia repo connect/open, snapshot, restore, maintenance; S3/filesystem storage backends.
  - `internal/agent/disk_image.go` – disk image backup orchestration; VSS snapshot → Kopia streaming.
  - `internal/agent/disk_image_windows.go` – Windows-specific VSS snapshot and streaming.
  - `internal/agent/disk_image_linux.go` – Linux-specific LVM snapshot and streaming.
  - `internal/agent/hyperv_backup.go` – Hyper-V VM backup orchestration; checkpoint management, multi-VM progress tracking (Windows only).
  - `internal/agent/hyperv/manager.go` – PowerShell-based Hyper-V management (ListVMs, GetVM, checkpoints).
  - `internal/agent/hyperv/rct.go` – Resilient Change Tracking (RCT) for incremental VM backups.
  - `internal/agent/hyperv/types.go` – Hyper-V data structures (VMInfo, DiskInfo, CheckpointInfo).
  - `internal/agent/hyperv_stream.go` – VHDX streaming for Hyper-V disk backup.
  - `internal/agent/nas.go` – Cloud NAS mount/unmount operations; S3 VFS + WebDAV server.
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
- `s3_cloudbackup_jobs`: adds `engine`, `dest_type`, `dest_bucket/prefix`, `dest_local_path`, `bucket_auto_create`, `schedule_json`, `retention_json`, `policy_json`, `bandwidth_limit_kbps`, `parallelism`, `encryption_mode`, `compression`, `local_include_glob`, `local_exclude_glob`, `local_bandwidth_limit_kbps`, `agent_id`, `last_policy_hash`, `hyperv_enabled`, `hyperv_config`.
- `s3_cloudbackup_runs`: adds `engine`, `dest_type`, `dest_bucket/prefix`, `dest_local_path`, `stats_json`, `progress_json`, `log_ref`, `policy_snapshot`, `worker_host`, `agent_id`, `disk_manifests_json`, `cancel_requested`.
- `s3_cloudbackup_run_logs`: structured run log stream.
- `s3_cloudbackup_run_events`: vendor-agnostic event feed.
- `s3_cloudbackup_run_commands`: queued commands for agents (`cancel`, `maintenance_quick/full`, `restore`, `mount`, `nas_mount`, `nas_unmount`, `list_hyperv_vms`).
- **Hyper-V specific tables**:
  - `s3_hyperv_vms`: VM registry (id, client_id, agent_id, vm_guid, vm_name, state, generation, rct_enabled, last_seen_at).
  - `s3_hyperv_vm_disks`: VM disk details (id, vm_id, disk_path, size_bytes, rct_id, controller_type).
  - `s3_hyperv_checkpoints`: Hyper-V checkpoint tracking (id, vm_id, checkpoint_id, checkpoint_type, created_at).
  - `s3_hyperv_backup_points`: Backup metadata per VM (id, vm_id, run_id, backup_type, manifest_id, consistency_level, warnings_json).
- Settings: `cloudbackup_agent_s3_endpoint`, optional `cloudbackup_agent_s3_region`.

## Agent-server contract (sync + Kopia)
- Run assignment: `agent_next_run.php` filters queued runs for that agent (run.agent_id/job.agent_id), claims as `starting`, returns engine, source/dest, policy/retention/schedule JSON, decrypted access keys, endpoint/region.
- Progress/events: `agent_update_run.php` (progress snapshot, log_ref, stats/progress JSON), `agent_push_events.php` (structured events).
- Commands: server enqueues in `s3_cloudbackup_run_commands`; agent polls `agent_poll_pending_commands.php`; completion via `agent_complete_command.php`. Supported: cancel, maintenance_quick/full, restore, nas_mount, nas_unmount.
- Start (manual/UI): `cloudbackup_start_run.php` → `CloudBackupController::startRun()` inserts queued run, binds to agent for `local_agent`, optionally stores engine/dest fields; workers should ignore local_agent runs.
- Reclaim/watchdog: reclaim stale in-progress for same agent within grace; watchdog cron fails truly stale runs.

## Scheduling (Local Agent jobs)
- Scheduled runs are created by the **scheduler cron**, not by the agent.
- The agent only claims runs that already exist in `s3_cloudbackup_runs` (`status=queued`).
- The cron evaluates each active job and enqueues a run by calling `CloudBackupController::startRun($jobId, $clientId, 'schedule')`.
- Schedule source of truth:
  - Primary: `schedule_type`, `schedule_time`, `schedule_weekday`, `schedule_cron`
  - Fallback: `schedule_json.type/time/weekday/cron` when `schedule_type` is blank or `manual`
- Supported types in the cron: `hourly`, `daily`, `weekly` (cron type is currently skipped).

### Cron setup (every minute)
Add this to the system crontab so schedules are checked every minute:

```bash
* * * * * /usr/bin/php -q /var/www/eazybackup.ca/accounts/crons/s3cloudbackup_scheduler.php >/dev/null 2>&1
```

To verify it is installed:

```bash
crontab -l | grep s3cloudbackup_scheduler.php
```

## Heartbeats, watchdog, and resume
- Heartbeats: `agent_update_run.php` updates `updated_at`; agents should POST every ≤60–90s.
- Watchdog: `crons/agent_watchdog.php` fails stale `starting/running` runs past `AGENT_WATCHDOG_TIMEOUT_SECONDS` (default 720s) with `AGENT_OFFLINE`.
- Reclaim: `agent_next_run.php` can return the same agent's in-progress run if heartbeat is older than `AGENT_RECLAIM_GRACE_SECONDS` (default 180s) but before watchdog cutoff.
- Exclusivity: in-progress runs stay with their agent; reclaim requires matching `run.agent_id` (or job.agent_id legacy).
- Env knobs: `AGENT_WATCHDOG_TIMEOUT_SECONDS`, `AGENT_RECLAIM_GRACE_SECONDS` (grace < timeout).

---

## Hyper-V Backup Engine (Dec 2025)

### Overview

The Hyper-V backup engine enables backup of virtual machines running on Windows Hyper-V hosts. It supports:
- **Application-consistent backups** via VSS checkpoints
- **Crash-consistent fallback** for VMs with checkpoints disabled
- **Multi-VM jobs** with partial success handling
- **RCT (Resilient Change Tracking)** for efficient incremental backups
- **Cumulative progress tracking** across multiple VMs

### Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                      WHMCS Job Wizard                               │
│   Step 1: Select Hyper-V engine                                     │
│   Step 2: Select VMs from discovered list                           │
│   Step 3: Configure schedule, retention                             │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     PHP API Layer                                    │
│   cloudbackup_create_job.php → stores hyperv_config, VM list        │
│   agent_next_run.php → returns HyperVVMs[], HyperVConfig            │
│   agent_list_hyperv_vms.php → discovery via command queue           │
│   agent_update_run.php → stores hyperv_results, backup points       │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Windows Backup Agent                             │
│                                                                       │
│   hyperv_backup.go - runHyperV()                                    │
│   ├── Pre-scan all VMs for total size                               │
│   ├── Create cumulative progress tracker                            │
│   ├── For each VM:                                                  │
│   │   ├── Create VSS checkpoint (or crash-consistent fallback)     │
│   │   ├── For each disk:                                            │
│   │   │   ├── Query RCT for changed blocks (if incremental)        │
│   │   │   ├── Stream VHDX to Kopia (full or sparse)                │
│   │   │   └── Report cumulative progress                            │
│   │   ├── Merge checkpoint                                          │
│   │   └── Report VM completion                                      │
│   └── Determine final status (success/partial_success/failed)      │
│                                                                       │
│   hyperv/manager.go - PowerShell operations                         │
│   ├── ListVMs() - enumerate all VMs with disk info                  │
│   ├── GetVM() - get single VM details                               │
│   ├── CreateVSSCheckpoint() - application-consistent snapshot       │
│   ├── CreateReferenceCheckpoint() - for RCT baseline                │
│   └── MergeCheckpoint() - cleanup after backup                      │
│                                                                       │
│   hyperv/rct.go - Resilient Change Tracking                         │
│   ├── GetRCTInfo() - query changed blocks since last backup         │
│   └── GetCurrentRCTIDs() - get current RCT IDs for next run         │
└─────────────────────────────────────────────────────────────────────┘
```

### Backup Types

| Type | When Used | Description |
|------|-----------|-------------|
| **Full** | First backup or no valid RCT | Backs up entire VHDX file |
| **Incremental (RCT)** | Subsequent backups with valid RCT | Only reads changed block ranges |

### Consistency Levels

| Level | Description | When Used |
|-------|-------------|-----------|
| `Application` | VSS-quiesced, application-consistent | Default, checkpoints enabled |
| `Crash` | Standard Hyper-V checkpoint | VSS fails, falls back to reference checkpoint |
| `CrashNoCheckpoint` | Live backup, no checkpoint | Checkpoints disabled for VM |

### Error Handling

- **Checkpoints Disabled**: If a VM has checkpoints disabled, the engine falls back to crash-consistent live backup and logs a warning
- **Partial Success**: If some VMs succeed and others fail, status is `partial_success` with details
- **Continue on Failure**: Multi-VM jobs continue to next VM if one fails

### HyperVConfig Structure

```json
{
    "enable_rct": true,
    "consistency_level": "application",
    "quiesce_timeout": 300
}
```

### HyperVVMResult Structure

```json
{
    "vm_id": 1,
    "vm_name": "WebServer",
    "backup_type": "Full",
    "checkpoint_id": "84a70f44-9d7c-47f8-869f-36af6c8745cd",
    "rct_ids": { "C:\\path\\to\\disk.vhdx": "rct:abc123" },
    "disk_manifests": { "C:\\path\\to\\disk.vhdx": "manifest-id" },
    "total_bytes": 53687091200,
    "changed_bytes": 1073741824,
    "consistency_level": "Application",
    "duration_seconds": 120,
    "error": null,
    "warnings": ["Checkpoints disabled, using crash-consistent backup"],
    "warning_code": "CHECKPOINTS_DISABLED"
}
```

---

## Cancellation Polling (Dec 2025)

### Overview

All backup engines support graceful cancellation via a polling mechanism. The agent periodically checks for cancel requests during backup execution and stops gracefully when requested.

### Cancellation Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Live Progress UI                                │
│   User clicks "Cancel Run" button                                   │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│              cloudbackup_cancel_run.php                              │
│   1. Verify run ownership                                           │
│   2. Set cancel_requested = 1 in database                           │
│   3. For 'queued' runs: immediately set status = 'cancelled'        │
│   4. For 'running' runs: agent will poll and cancel                 │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│              agent_poll_commands.php                                 │
│   1. Check cancel_requested flag on run                             │
│   2. Return { type: 'cancel' } command if set                       │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Windows Backup Agent                             │
│   pollCommands(runID) - polls every 3 seconds                       │
│   1. Receive cancel command                                         │
│   2. Log "cancel requested for run X"                               │
│   3. Push CANCEL_REQUESTED event                                    │
│   4. Call cancel() on context                                       │
│   5. Cleanup (merge checkpoints, close connections)                 │
│   6. Set status = 'cancelled'                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Engine Cancel Polling Implementation

| Engine | Cancel Polling | Location | Method |
|--------|---------------|----------|--------|
| **sync** | ✅ Yes | `runner.go` | `commandTicker` in main loop |
| **kopia** | ✅ Yes | `runner.go` | `commandTicker` in main loop |
| **disk_image** | ✅ Yes | `disk_image.go` | Dedicated goroutine |
| **hyperv** | ✅ Yes | `hyperv_backup.go` | Dedicated goroutine |

### Implementation Pattern (for disk_image and hyperv)

```go
// Start cancel polling goroutine
cancelPollDone := make(chan struct{})
go func() {
    defer close(cancelPollDone)
    ticker := time.NewTicker(3 * time.Second)
    defer ticker.Stop()
    for {
        select {
        case <-ctx.Done():
            return
        case <-ticker.C:
            cancelReq, _, errCmd := r.pollCommands(run.RunID)
            if errCmd != nil {
                log.Printf("agent: cancel poll error: %v", errCmd)
                continue
            }
            if cancelReq {
                log.Printf("agent: cancel requested for run %d", run.RunID)
                r.pushEvents(run.RunID, RunEvent{
                    Type:      "cancelled",
                    Level:     "warn",
                    MessageID: "CANCEL_REQUESTED",
                })
                cancel()
                return
            }
        }
    }
}()
defer func() {
    cancel()
    <-cancelPollDone
}()
```

### Run Status After Cancellation

| Scenario | Status |
|----------|--------|
| Queued run cancelled | `cancelled` (immediate) |
| Running run cancelled | `cancelled` (after agent cleanup) |
| Hyper-V: some VMs completed before cancel | `cancelled` with completion info |

---

## Progress Tracking (Dec 2025)

### Overview

The agent reports detailed progress during backup execution, enabling real-time UI updates for:
- Progress percentage
- Bytes processed (scanned from source)
- Bytes transferred (uploaded to storage, shows deduplication)
- Speed (bytes/second)
- ETA (estimated time remaining)
- Current item being processed

### Progress Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                     Windows Backup Agent                             │
│                                                                       │
│   Kopia Progress Counter (kopiaProgressCounter)                     │
│   ├── UploadStarted() - reset timing                                │
│   ├── HashedBytes(n) - increment bytes scanned                      │
│   ├── UploadedBytes(n) - increment bytes actually uploaded          │
│   ├── FinishedHashingFile() - increment file count                  │
│   ├── EstimatedDataSize() - set total bytes/files                   │
│   └── reportProgressLocked() - send update every 2 seconds          │
│                                                                       │
│   Hyper-V Progress Tracker (hypervProgressTracker)                  │
│   ├── totalBytes - sum of all VM disk sizes                         │
│   ├── completedBytes - from fully completed disks                   │
│   ├── currentBytes - from current disk in progress                  │
│   ├── setCurrentBytes() - called by Kopia progress                  │
│   ├── addCompletedBytes() - after each disk finishes                │
│   └── reportProgress() - cumulative progress to server              │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│              agent_update_run.php                                    │
│   Updates s3_cloudbackup_runs:                                      │
│   - progress_pct                                                    │
│   - bytes_transferred (actual upload, with dedup)                   │
│   - bytes_processed (scanned from source)                           │
│   - bytes_total                                                     │
│   - speed_bytes_per_sec                                             │
│   - eta_seconds                                                     │
│   - current_item                                                    │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│              cloudbackup_progress.php                                │
│   Returns current run state for polling:                            │
│   - All progress fields from database                               │
│   - Formatted log entries                                           │
│   - Structured events                                               │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│              cloudbackup_live.tpl                                    │
│   JavaScript polls every 2 seconds:                                 │
│   - updateProgress() fetches cloudbackup_progress.php               │
│   - Updates progress bar, stats cards, ETA                          │
│   - updateEventLogs() fetches structured events                     │
│   - Renders live log stream                                         │
└─────────────────────────────────────────────────────────────────────┘
```

### RunUpdate Structure

```go
type RunUpdate struct {
    RunID              int64   `json:"run_id"`
    Status             string  `json:"status,omitempty"`
    ProgressPct        float64 `json:"progress_pct,omitempty"`
    BytesTransferred   *int64  `json:"bytes_transferred,omitempty"` // Actual upload
    BytesProcessed     *int64  `json:"bytes_processed,omitempty"`   // Scanned from source
    BytesTotal         *int64  `json:"bytes_total,omitempty"`
    ObjectsTransferred int64   `json:"objects_transferred,omitempty"`
    ObjectsTotal       int64   `json:"objects_total,omitempty"`
    SpeedBytesPerSec   int64   `json:"speed_bytes_per_sec,omitempty"`
    EtaSeconds         int64   `json:"eta_seconds,omitempty"`
    CurrentItem        string  `json:"current_item,omitempty"`
    // ... other fields
}
```

**Note**: `BytesTransferred`, `BytesProcessed`, and `BytesTotal` are pointers to allow sending 0 values (vs omitting the field). This is important for deduplication where actual upload may be 0.

### Progress Calculation by Engine

| Engine | Progress Source | Notes |
|--------|-----------------|-------|
| **sync** | rclone stats | `stats.GetBytes()`, `stats.GetTransfers()` |
| **kopia** | kopiaProgressCounter | Pre-scan estimates total, tracks hashed/uploaded |
| **disk_image** | kopiaProgressCounter | Single disk, progress 0-100% |
| **hyperv** | hypervProgressTracker | Cumulative across all VMs/disks |

### Hyper-V Cumulative Progress

For multi-VM jobs, progress is calculated across ALL VMs:

```go
type hypervProgressTracker struct {
    totalBytes     int64 // Sum of all VM disk sizes
    completedBytes int64 // Bytes from completed disks
    currentBytes   int64 // Bytes in current disk (atomic)
    // ...
}

func (p *hypervProgressTracker) reportProgress() {
    totalProcessed := p.completedBytes + atomic.LoadInt64(&p.currentBytes)
    progressPct := (float64(totalProcessed) / float64(p.totalBytes)) * 100.0
    // ...
}
```

This ensures the progress bar shows 0-100% across the ENTIRE job, not resetting between VMs.

### Deduplication Visibility

The UI shows two metrics to highlight deduplication savings:
- **Bytes Processed**: Total bytes scanned from source
- **Bytes Uploaded**: Actual bytes sent to storage (may be much smaller)
- **Dedup Savings**: Calculated as `100 - (uploaded/processed * 100)`

### Compression (Kopia engine)

Compression can be enabled for Kopia-based backup jobs to reduce storage usage and transfer time for compressible data.

**How it works:**
- The job wizard's Advanced Settings includes a **Compression** dropdown with options: `None`, `zstd-default`, `pgzip`, `s2`.
- When a non-`none` value is selected, `compression_enabled=1` is set on the job record.
- The agent receives `compression_enabled: true` in the run payload from `agent_next_run.php`.
- The Kopia runner (`kopia.go`) reads this flag via `parsePolicyOverrides()`:
  - If `compression_enabled` is `true` and no explicit compressor is in `policy_json`, defaults to `zstd-default`.
  - If an explicit compressor is provided in `policy_json.compression`, that value is used.
  - Setting compressor to `"none"` explicitly disables compression.
- The effective compression algorithm is applied to Kopia's policy before the snapshot runs.
- Compression is logged in run events with message ID `KOPIA_POLICY` showing the active `compression` setting.

**Observability:**
- The event log shows `KOPIA_POLICY` with `compression: "zstd-default"` (or the chosen algorithm).
- Bytes Processed vs Bytes Uploaded delta reflects both deduplication and compression savings.

### Event Message Formatting

Events pushed by the agent are formatted for display using `CloudBackupEventFormatter`:

**Location**: `lib/Client/CloudBackupEventFormatter.php`

**Key Message Templates**:
```php
'BACKUP_STARTING' => 'Starting backup.',
'KOPIA_UPLOAD_START' => 'Starting upload from {source} to {bucket}/{prefix}.',
'KOPIA_UPLOAD_COMPLETE' => 'Upload completed.',
'HYPERV_VM_STARTING' => 'Starting backup of virtual machine \'{vm_name}\'.',
'HYPERV_VM_COMPLETE' => 'VM \'{vm_name}\' backed up successfully ({backup_type}, {consistency_level}-consistent).',
'HYPERV_VM_FAILED' => 'VM \'{vm_name}\' backup failed: {message}.',
'HYPERV_CHECKPOINTS_DISABLED' => '⚠️ Checkpoints are disabled for \'{vm_name}\'. Using crash-consistent backup.',
'HYPERV_BACKUP_COMPLETE' => '{message}',
'CANCEL_REQUESTED' => 'Backup cancellation requested by user.',
'CANCELLED' => 'Backup cancelled.',
```

**Fallback**: If no template matches, the formatter uses the `message` param from the event's `params_json`.

---

## UI/UX (client)
- `cloudbackup_jobs.tpl` / `e3backup_jobs.tpl`: local-agent wizard modal with steps (Mode/Agent/Destination → Source → Schedule → Retention/Policy → Review); engine toggle (Sync/rclone vs Backup/Kopia vs Disk Image vs Hyper-V); agent dropdown (Alpine, `agent_list.php`); S3 bucket picker/search; fields for source path, include/exclude globs, schedule_json, retention_json, policy_json, bandwidth, parallelism, encryption_mode, compression. 
  - **Hyper-V Mode**: Step 2 shows VM browser with discovered VMs from agent; allows multi-select; displays VM state, generation, disk info.
- `cloudbackup_runs.tpl`: shows engine per job; run detail modal pulls structured events/logs.
- `cloudbackup_live.tpl`: Real-time progress display with:
  - Progress bar (percentage or indeterminate based on data)
  - Stats cards: Bytes Processed, Bytes Uploaded, Speed, ETA
  - Live event log with formatted messages
  - Cancel button (visible for running jobs)
  - Status badges with color coding (success/failed/partial_success/cancelled)

## UI/UX (admin)
- `admin/cloudbackup_admin.tpl`: engine/destination/log_ref columns; maintenance buttons enqueue commands for Kopia; runs table engine-aware.
- Logs/events APIs power admin/client views.

## Windows agent runtime
- Config: `%PROGRAMDATA%\\E3Backup\\agent.conf`.
- Runs/cache: `%PROGRAMDATA%\\E3Backup\\runs` (Kopia config under `runs/kopia/job_<job_id>.config`).
- Logs: `%PROGRAMDATA%\\E3Backup\\logs\\` (`agent.log` for service, `tray.log` for tray app).
- Rclone paths: in-memory config per run; Kopia repo config per job.
- Service wrapper via kardianos/service; can run foreground for debugging.
- Default poll interval: **5 seconds** (configurable via `PollIntervalSecs` in config).

## Tray App Auto-Enrollment (Jan 2025)

### Overview

The tray helper (`e3-backup-tray.exe`) automatically opens the enrollment UI when:
1. The device is **not enrolled** (no `agent_id`/`agent_token` in config)
2. No **MSP/RMM token enrollment** is pending (no `enrollment_token` in config)

This provides a seamless first-run experience for end users.

### Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Installer completes                               │
│   1. Writes initial agent.conf (api_base_url, device identity)     │
│   2. Starts tray helper via [Run] section                          │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    Tray App onReady()                                │
│   1. Loads config                                                   │
│   2. Checks: enrolled = (AgentID && AgentToken present)            │
│   3. Checks: pendingTokenEnroll = (EnrollmentToken present)        │
│   4. If !enrolled && !pendingTokenEnroll:                          │
│      └── Wait 2 seconds, then call openEnrollUI()                  │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    Local Enrollment UI                               │
│   1. Tray starts HTTP server on 127.0.0.1:<random_port>            │
│   2. Opens browser to http://127.0.0.1:<port>/enroll               │
│   3. User enters email + password                                   │
│   4. Tray calls agent_login.php API                                 │
│   5. On success:                                                    │
│      - Saves agent_id/agent_token to config                        │
│      - Starts Windows service                                       │
│      - Shows success page with "Create Backup Job" button          │
└─────────────────────────────────────────────────────────────────────┘
```

### Success Page

After successful enrollment, the tray displays a success page with:
- Green checkmark confirmation
- "Enrolled Successfully!" heading
- "What's Next?" panel directing user to create a backup job
- **"Create Backup Job →"** button linking to the Jobs page

The button URL is dynamically built from `api_base_url`:
- API: `https://accounts.eazybackup.ca/modules/addons/cloudstorage/api`
- Jobs page: `https://accounts.eazybackup.ca/index.php?m=cloudstorage&page=e3backup&view=jobs`

### Debug Logging

The tray app writes debug logs to `%PROGRAMDATA%\E3Backup\logs\tray.log`:

```
2026-01-13 14:50:00.000  onReady: enrolled=false, pendingTokenEnroll=false, AgentID="", EnrollmentToken=""
2026-01-13 14:50:00.001  onReady: will open local enrollment UI in 2 seconds
2026-01-13 14:50:02.005  openEnrollUI: starting local HTTP server
2026-01-13 14:50:02.010  openEnrollUI: opening browser to http://127.0.0.1:55123/enroll
2026-01-13 14:50:02.015  openEnrollUI: browser launched successfully
```

### Build Commands

```bash
cd /var/www/eazybackup.ca/e3-backup-agent

# Build both Windows binaries
make build-windows

# Or build individually:
make build-agent-windows   # Service only
make build-tray-windows    # Tray only
```

See `LOCAL_AGENT_BUILD.md` for full build and deployment instructions.

---

# Cloud NAS Feature – Complete Developer Guide

## Overview

Cloud NAS allows clients to mount S3 buckets as local Windows drive letters. The feature uses:
- **Embedded rclone S3 backend** – connects to S3-compatible storage
- **rclone VFS (Virtual File System)** – provides filesystem abstraction with caching
- **Embedded WebDAV server** – serves the VFS over HTTP on localhost
- **Windows WebDAV client** – maps network drive via `net use`

**No external dependencies required** – everything is embedded in the single agent binary.

## Architecture Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                        WHMCS Client Dashboard                        │
│                                                                       │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐             │
│  │   My Drives  │   │ Time Machine │   │   Settings   │             │
│  │              │   │              │   │              │             │
│  │ Mount/Unmount│   │  Snapshots   │   │ Cache/BW/etc │             │
│  └──────┬───────┘   └──────┬───────┘   └──────┬───────┘             │
└─────────┼──────────────────┼──────────────────┼─────────────────────┘
          │                  │                  │
          ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          PHP API Layer                               │
│                                                                       │
│  cloudnas_create_mount.php   cloudnas_mount.php                      │
│  cloudnas_list_mounts.php    cloudnas_unmount.php                    │
│  cloudnas_delete_mount.php   cloudnas_update_status.php              │
│  cloudnas_settings.php       cloudnas_mount_snapshot.php             │
│                                                                       │
│  Enqueues commands in: s3_cloudbackup_run_commands                   │
└─────────────────────────────────────────────────────────────────────┘
          │
          │ (commands: nas_mount, nas_unmount, nas_mount_snapshot)
          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Windows Backup Agent                             │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │                    Polling Loop (5s)                         │    │
│  │    agent_poll_pending_commands.php → executeNASCommand       │    │
│  └──────────────────────────┬──────────────────────────────────┘    │
│                             │                                        │
│                             ▼                                        │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │                   nas.go - mountNASDrive()                   │    │
│  │                                                               │    │
│  │  1. Create S3 filesystem (rclone s3.NewFs)                   │    │
│  │  2. Wrap in VFS with cache mode                              │    │
│  │  3. Start WebDAV server on 127.0.0.1:random_port             │    │
│  │  4. Run: net use Y: http://127.0.0.1:PORT/ /persistent:no    │    │
│  │  5. Notify Explorer via SHChangeNotify                       │    │
│  │  6. Report status back to cloudnas_update_status.php         │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                       │
│         ┌───────────────────────────────────────────────┐           │
│         │           Active Mount (in memory)            │           │
│         │                                                │           │
│         │   WebDAV Server ←─── VFS ←─── S3 Backend      │           │
│         │         ▲                                      │           │
│         │         │ http://127.0.0.1:PORT/              │           │
│         │         ▼                                      │           │
│         │   Windows Drive Letter (Y:)                   │           │
│         └───────────────────────────────────────────────┘           │
└─────────────────────────────────────────────────────────────────────┘
```

## Database Schema

### `s3_cloudnas_mounts` - Mount Configurations

```sql
CREATE TABLE s3_cloudnas_mounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,          -- WHMCS client ID
    agent_id INT UNSIGNED NOT NULL,           -- Which agent handles this mount
    bucket_name VARCHAR(255) NOT NULL,        -- S3 bucket name
    prefix VARCHAR(1024) DEFAULT '',          -- Optional path prefix in bucket
    drive_letter CHAR(1) NOT NULL,            -- Windows drive letter (A-Z)
    read_only TINYINT(1) DEFAULT 0,           -- Mount as read-only
    persistent TINYINT(1) DEFAULT 1,          -- Auto-mount on agent startup
    cache_mode VARCHAR(20) DEFAULT 'full',    -- off/minimal/writes/full
    status VARCHAR(20) DEFAULT 'unmounted',   -- mounted/unmounted/mounting/unmounting/error
    error TEXT DEFAULT NULL,                  -- Error message if status='error'
    last_mounted_at DATETIME DEFAULT NULL,    -- Last successful mount time
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_client (client_id),
    KEY idx_agent (agent_id),
    KEY idx_client_agent_letter (client_id, agent_id, drive_letter),
    KEY idx_status (status)
);
```

### `s3_cloudnas_settings` - Per-Client Settings

```sql
CREATE TABLE s3_cloudnas_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    settings_json TEXT,  -- JSON blob with all settings
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY idx_client (client_id)
);
```

**Settings JSON structure:**
```json
{
    "cache_mode": "full",
    "cache_size_gb": 10,
    "bandwidth_limit_enabled": false,
    "bandwidth_download_kbps": 0,
    "bandwidth_upload_kbps": 0,
    "auto_mount": true,
    "default_read_only": false
}
```

## WHMCS Dashboard UI

### Route
- URL: `index.php?m=cloudstorage&page=cloudbackup&view=cloudnas`
- Navigation: Cloud Backup → Cloud NAS tab

### Main Template Structure

```
templates/cloudnas.tpl                  # Main container + Alpine.js component
├── partials/cloudnas_drives.tpl       # "My Drives" tab content
├── partials/cloudnas_timemachine.tpl  # "Time Machine" tab content
├── partials/cloudnas_settings.tpl     # "Settings" tab content
└── partials/cloudnas_mount_wizard.tpl # Mount creation wizard modal
```

### Tab: My Drives (`cloudnas_drives.tpl`)

Displays all configured mounts as cards with:
- **Drive letter badge** (color-coded by status)
- **Bucket name and prefix**
- **Status indicator** (mounted/mounting/unmounting/error)
- **Config pills**: Read-only, VFS Cache, Auto-mount
- **Actions**: Mount, Unmount, Edit, Delete

**Summary cards at top:**
- Active Mounts count
- Configured Drives count
- Cache Used (placeholder - needs agent data)
- Agent Status (online/offline)

### Tab: Time Machine (`cloudnas_timemachine.tpl`)

Allows browsing and mounting Kopia backup snapshots as read-only drives.

**Features:**
- Backup job selector (filters to `engine=kopia` jobs only)
- Snapshot list with timestamps, sizes, file counts
- Time slider for visual snapshot selection
- Selected snapshot details panel
- Actions: Mount as Read-Only Drive, Browse Files, Compare Snapshots

**Implementation Status:**
- ✅ UI fully implemented
- ❌ Agent snapshot mount NOT implemented (returns error: "Kopia snapshot mounting not yet implemented")
- The `mountKopiaSnapshot()` function in `nas.go` is a placeholder

### Tab: Settings (`cloudnas_settings.tpl`)

Configurable options:
- **VFS Cache**: Mode (off/minimal/writes/full), size limit (GB)
- **Bandwidth**: Enable limiting, download/upload limits (KB/s)
- **Default Mount Options**: Auto-mount on startup, default read-only
- **Default Agent**: Which agent handles mount operations

## API Endpoints

### `cloudnas_list_mounts.php`
- **Method**: GET
- **Auth**: Client session
- **Returns**: List of mount configurations for the client

### `cloudnas_create_mount.php`
- **Method**: POST
- **Auth**: Client session
- **Body**: `{ bucket, prefix, drive_letter, read_only, persistent, cache_mode, agent_id }`
- **Returns**: `{ status, mount_id }` or error

### `cloudnas_mount.php`
- **Method**: POST
- **Auth**: Client session
- **Body**: `{ mount_id }`
- **Action**: Queues `nas_mount` command with S3 credentials
- **Command payload**:
```json
{
    "mount_id": 123,
    "bucket": "my-bucket",
    "prefix": "optional/path",
    "drive_letter": "Y",
    "read_only": false,
    "cache_mode": "full",
    "endpoint": "https://s3.example.com",
    "access_key": "decrypted_key",
    "secret_key": "decrypted_secret",
    "region": "us-east-1"
}
```

### `cloudnas_unmount.php`
- **Method**: POST
- **Auth**: Client session
- **Body**: `{ mount_id }`
- **Action**: Queues `nas_unmount` command

### `cloudnas_delete_mount.php`
- **Method**: POST
- **Auth**: Client session
- **Body**: `{ mount_id }`
- **Action**: Queues unmount if mounted, then deletes mount config

### `cloudnas_settings.php`
- **Method**: GET (load) / POST (save)
- **Auth**: Client session
- **Body (POST)**: Settings JSON object

### `cloudnas_update_status.php`
- **Method**: POST
- **Auth**: Agent headers (`X-Agent-ID`, `X-Agent-Token`)
- **Body**: `{ mount_id, status, error }`
- **Purpose**: Agent callback to update mount status in DB

### `bucket_list.php`
- **Method**: GET
- **Auth**: Client session
- **Returns**: List of S3 buckets owned by client (including tenant sub-users)

## Agent Implementation (`nas.go`)

### Key Structures

```go
type NASMount struct {
    MountID     int64
    DriveLetter string
    BucketName  string
    Prefix      string
    ReadOnly    bool
    CacheMode   string
    MountedAt   time.Time
    WebDAV      *http.Server  // WebDAV server instance
    VFS         *vfs.VFS      // rclone VFS instance
    ServerPort  int
}

type MountNASPayload struct {
    MountID     int64  `json:"mount_id"`
    Bucket      string `json:"bucket"`
    Prefix      string `json:"prefix"`
    DriveLetter string `json:"drive_letter"`
    ReadOnly    bool   `json:"read_only"`
    CacheMode   string `json:"cache_mode"`
    Endpoint    string `json:"endpoint"`
    AccessKey   string `json:"access_key"`
    SecretKey   string `json:"secret_key"`
    Region      string `json:"region"`
}
```

### Mount Process (`mountNASDrive`)

1. **Validate drive letter** (A-Z, not already mounted)
2. **Configure S3 backend**:
   ```go
   opt := configmap.Simple{
       "provider":           "Other",
       "access_key_id":      payload.AccessKey,
       "secret_access_key":  payload.SecretKey,
       "endpoint":           payload.Endpoint,
       "chunk_size":         "5Mi",        // Required minimum
       "copy_cutoff":        "5Gi",
       "upload_cutoff":      "200Mi",
       "force_path_style":   "true",       // For MinIO
       "disable_http2":      "true",
       "no_check_bucket":    "true",
   }
   ```
3. **Create VFS** with cache mode (off/minimal/writes/full)
4. **Start WebDAV server** on `127.0.0.1:0` (random port)
5. **Start WebClient service**: `net start webclient`
6. **Cleanup stale mapping**: `net use Y: /delete /y`
7. **Map drive**: `net use Y: http://127.0.0.1:PORT/ /persistent:no`
8. **Notify Explorer**: Call `SHChangeNotify` via PowerShell
9. **Report status** to `cloudnas_update_status.php`

### Unmount Process (`unmountNASDrive`)

1. **Remove drive mapping**: `net use Y: /delete /y`
2. **Shutdown WebDAV server** (graceful with timeout)
3. **Shutdown VFS** (flushes cache)
4. **Report status** to `cloudnas_update_status.php`

### VFS WebDAV Adapter

The `vfsWebDAVFS` struct adapts rclone's VFS to the `golang.org/x/net/webdav.FileSystem` interface:
- `Mkdir`, `OpenFile`, `RemoveAll`, `Rename`, `Stat`

---

## Known Issues & Troubleshooting

### ⚠️ CRITICAL: Agent Must Run Non-Elevated

**The #1 cause of Cloud NAS issues is running the agent as Administrator.**

Network drive mappings created by elevated (Administrator) processes are NOT visible to non-elevated processes like Windows Explorer. This is a Windows security feature called "session isolation."

**Symptoms when running elevated:**
- Agent reports "mount successful"
- `net use` shows the mapping exists (but often with empty Status instead of "OK")
- Drive is NOT visible in Windows Explorer
- Typing `Y:` in Explorer address bar shows "Windows can't find 'Y:'"
- Other CMD/PowerShell windows can't access the drive

**Solution**: Always run the agent from a **non-elevated** PowerShell or CMD window:
```powershell
# Check your window title - it should NOT say "Administrator"
cd "C:\Program Files\E3Backup"
.\e3-backup-agent.exe -config C:\ProgramData\E3Backup\agent.conf
```

The agent does NOT require Administrator privileges for Cloud NAS functionality.

### Agent Must Keep Running

**Important**: The WebDAV server that serves your S3 data runs inside the agent process. If you close the PowerShell/CMD window or stop the agent, the mounted drive becomes inaccessible.

**Symptoms when agent stops:**
- Drive letter still appears in `net use` output
- Status column is empty (not "OK")
- Accessing the drive shows "The network path was not found"
- Error code: `0x80070035`

**For production use**, the agent should run as a Windows Service (see Windows Agent Runtime section).

### Windows Drive Visibility in Explorer

**Problem**: Drive mounts successfully but isn't visible in all Explorer windows.

**Root Cause**: Windows Explorer caches its view of available drives. Different Explorer windows may not immediately see new network mappings.

**Solution Implemented (Dec 2025)**:

The agent uses multiple techniques to ensure drive visibility:

1. **WScript.Network COM Object**: Uses the same API that Windows Explorer uses for "Map Network Drive", ensuring proper session registration.

2. **Persistent Mapping**: Uses `net use /persistent:yes` to create a properly registered mapping.

3. **Drive Access**: Immediately accesses the drive after mapping to establish the WebDAV connection.

4. **Shell Notifications**: Broadcasts multiple Windows Shell events to force Explorer refresh:
   - `SHCNE_DRIVEADD` - Drive added
   - `SHCNE_ASSOCCHANGED` - Forces full shell refresh
   - `WM_SETTINGCHANGE` - System settings changed

5. **Explorer Auto-Open**: Opens the mounted drive directly in Explorer, which forces Windows to fully recognize it.

6. **Custom Drive Label**: Sets a friendly name via registry (`_LabelFromReg`) so the drive appears as "Cloud NAS (bucket-name)" instead of "DavWWWRoot".

### Troubleshooting Drive Visibility

If the drive still doesn't appear in Explorer:

**Step 1: Verify mapping exists**
```cmd
net use
```
Look for your drive letter. If Status is empty (vs "OK"), the WebDAV connection isn't active.

**Step 2: Check if agent is running**
The WebDAV server runs inside the agent. Is your PowerShell window still open with the agent running?

**Step 3: Test direct access**
```cmd
dir Y:\
```
If this works, the drive is functional but Explorer just hasn't refreshed.

**Step 4: Force Explorer refresh**
- Open Explorer, navigate to "This PC"
- Press **F5** to refresh
- Or type `Y:\` in the address bar and press Enter

**Step 5: Restart Explorer (nuclear option)**
```powershell
Stop-Process -Name explorer -Force
Start-Process explorer
```

**Step 6: Check WebDAV connectivity**
```powershell
# Use the port from agent log output
Invoke-WebRequest -Uri "http://127.0.0.1:PORT/" -Method PROPFIND -Headers @{"Depth"="0"}
```

### Registry Fix for Elevated Sessions

If you MUST run the agent elevated (not recommended), enable linked connections:

```
HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System
EnableLinkedConnections (DWORD) = 1
```

Requires system restart. This allows elevated and non-elevated processes to share drive mappings.

### WebDAV URL Format

The agent uses HTTP URL format for `net use`:
```
net use Y: http://127.0.0.1:PORT/ /persistent:no
```

This is more reliable than the UNC format (`\\127.0.0.1@PORT\DavWWWRoot`) on modern Windows.

### S3 Backend Configuration

The agent uses rclone's S3 backend with the following configuration:

```go
opt := configmap.Simple{
    "provider":          "Ceph",      // Use "Ceph" for Ceph RadosGW, "Minio" for MinIO
    "access_key_id":     accessKey,
    "secret_access_key": secretKey,
    "endpoint":          endpoint,    // Full URL: http://192.168.92.10:8000
    "chunk_size":        "5Mi",       // Required minimum for S3 multipart
    "copy_cutoff":       "5Gi",
    "upload_cutoff":     "200Mi",
    "force_path_style":  "true",      // Required for Ceph/MinIO
    "disable_http2":     "true",      // Better compatibility
    "no_check_bucket":   "true",
    "list_chunk":        "1000",      // Items per listing request
}
```

**Provider Selection**:
- Use `"Ceph"` for Ceph RadosGW - fixes empty directory listings
- Use `"Minio"` for MinIO
- Use `"Other"` only if the above don't work

**Common Errors and Fixes**:

| Error | Fix |
|-------|-----|
| `chunk size: 0 is less than 5Mi` | Add `"chunk_size": "5Mi"` to config |
| `--s3-copy-cutoff: value is too small` | Add `"copy_cutoff": "5Gi"` to config |
| `S3 root has 0 entries` (empty listing) | Change provider from `"Other"` to `"Ceph"` |
| `unable to create client: Endpoint url cannot have fully qualified paths` | For Kopia S3, parse endpoint to extract host-only |

### Database Column Naming

The `s3_cloudnas_mounts` table uses `bucket_name`, but `s3_buckets` uses `name`. When querying buckets:
```php
// Correct
Capsule::table('s3_buckets')->where('name', $bucketName)

// Wrong
Capsule::table('s3_buckets')->where('bucket_name', $bucketName)
```

### Foreign Key Constraints

Commands inserted into `s3_cloudbackup_run_commands` require:
- `run_id` set to `NULL` (not `0`) since Cloud NAS commands aren't tied to backup runs
- `agent_id` column was added later via `ALTER TABLE`

---

## Feature Status Summary

| Feature | Status | Notes |
|---------|--------|-------|
| My Drives - Mount/Unmount | ✅ Working | Via WebDAV + net use + WScript.Network |
| My Drives - Create/Delete | ✅ Working | |
| My Drives - Status Updates | ✅ Working | Agent → cloudnas_update_status.php |
| My Drives - Status Polling | ✅ Working | Dashboard polls every 3 seconds |
| My Drives - Custom Labels | ✅ Working | Shows "Cloud NAS (bucket)" in Explorer |
| Settings - Cache Mode | ✅ Working | Applied per mount (off/minimal/writes/full) |
| Settings - Bandwidth Limits | ❌ Not implemented | UI exists, not enforced by agent |
| Settings - Auto-mount | ⚠️ Partial | Stored but not auto-applied on agent restart |
| Time Machine - Snapshot List | ✅ Working | Via cloudbackup_list_snapshots.php |
| Time Machine - Snapshot Mount | ❌ Not implemented | Requires WinFSP + Kopia mount |
| Time Machine - Browse Files | ❌ Not implemented | No file browser UI |
| Time Machine - Compare Snapshots | ❌ Not implemented | Placeholder button |
| Explorer Visibility | ✅ Fixed | Agent must run non-elevated |
| S3 Directory Listing | ✅ Fixed | Use "Ceph" provider for Ceph RadosGW |

---

## Cloud NAS Fixes (Dec 9, 2025)

### 1. Drive Visibility in Windows Explorer

**Problem**: Mounted drives were not visible in Windows Explorer, even though `net use` showed them as mapped.

**Root Cause**: Running the agent as Administrator causes Windows session isolation. Elevated processes create drive mappings in a separate namespace that non-elevated processes (like Explorer) cannot see.

**Solution**: 
- Agent must run from a **non-elevated** PowerShell or CMD window
- Use WScript.Network COM object for drive mapping (same API as Explorer's "Map Network Drive")
- Use `/persistent:yes` flag for proper registration
- Broadcast shell notifications to force Explorer refresh
- Auto-open the drive in Explorer after mounting

### 2. Empty Directory Listings

**Problem**: Drive mounted successfully but showed 0 files, even though the S3 bucket contained data.

**Root Cause**: Using `provider: "Other"` in rclone S3 configuration doesn't work correctly with Ceph RadosGW.

**Solution**: Changed S3 provider from `"Other"` to `"Ceph"` in the rclone configuration. This properly handles Ceph-specific S3 API behaviors.

### 3. Stale Port Caching

**Problem**: After unmount/remount, Windows tried to connect to the old WebDAV port, showing "network path not found" errors.

**Root Cause**: Windows WebClient service caches WebDAV connections including port numbers.

**Solution**: 
- Clear existing mappings before creating new ones
- Use HTTP URL format (`http://127.0.0.1:PORT/`) which is less prone to caching issues
- Ensure WebClient service is running before mapping

### 4. Custom Drive Labels

**Problem**: Drives appeared as "DavWWWRoot" in Explorer, which is not user-friendly.

**Solution**: Set custom label via Windows registry:
```
HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\MountPoints2\##127.0.0.1@PORT#DavWWWRoot
_LabelFromReg = "Cloud NAS (bucket-name)"
```

Now drives appear as "Cloud NAS (localbackupbucket)" in Explorer.

---

## Changes/Fixes (Dec 2025, Kopia integration)
- Added Kopia library, engine switch, and command handling (maintenance/restore).
- Structured run logs/events and command tables added.
- `agent_next_run` returns engine/source/dest/policy/schedule JSON and decrypts access keys.
- Job create/update enforce S3-only dest, validate agent for local_agent, normalize JSON fields to avoid invalid inserts.
- `startRun` binds runs to agents; workers should ignore local_agent runs.

## Kopia Progress Reporting (Complete - Dec 2025)

The Kopia backup engine provides real-time progress reporting to the live progress page via the agent APIs.

### Progress Metrics Tracked

| Metric | Description | Source |
|--------|-------------|--------|
| `bytes_processed` | Bytes scanned/hashed from source | `HashedBytes()` callback |
| `bytes_transferred` | Bytes actually uploaded to storage | `OnUpload` callback in `WriteSessionOptions` |
| `bytes_total` | Estimated total size | Pre-scan via `EstimatedDataSize()` |
| `speed_bytes_per_sec` | Current throughput | Calculated from bytes/time |
| `eta_seconds` | Estimated time remaining | Calculated from speed + remaining |
| `current_item` | File currently being processed | `HashingFile()` callback |
| `progress_pct` | Completion percentage | `bytes_processed / bytes_total * 100` |

### Kopia Progress Counter Implementation

**Location**: `internal/agent/kopia.go` → `kopiaProgressCounter`

The `kopiaProgressCounter` struct implements Kopia's `snapshotfs.UploadProgress` interface:

```go
type kopiaProgressCounter struct {
    runner        *Runner
    runID         int64
    bytesHashed   int64  // atomic - bytes scanned from source
    bytesUploaded int64  // atomic - bytes sent to storage
    totalBytes    int64  // atomic - estimated total
    totalFiles    int64  // atomic - estimated file count
    // ... other fields
}
```

**Key Callbacks**:
- `HashedBytes(n)` - Called when bytes are hashed; updates `bytesHashed`
- `UploadedBytes(n)` - Called when bytes are written to blob storage; updates `bytesUploaded`
- `EstimatedDataSize(files, bytes)` - Sets total estimates for progress calculation
- `UploadFinished()` - Logs final stats for debugging

### Critical: OnUpload Callback Required

**IMPORTANT**: Kopia's `UploadedBytes` callback is **only triggered** when the `OnUpload` callback is set in `WriteSessionOptions`:

```go
// CORRECT - bytes_uploaded will be tracked
uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{
    Purpose:  "snapshot",
    OnUpload: progressCounter.UploadedBytes,  // ← THIS IS REQUIRED
}, func(wctx context.Context, w repo.RepositoryWriter) error {
    u := snapshotfs.NewUploader(w)
    u.Progress = progressCounter
    // ...
})

// WRONG - bytes_uploaded will always be 0
uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{Purpose: "snapshot"}, ...)
```

This is because `UploadedBytes` is called by the content manager when bytes are flushed to blob storage, not by the uploader directly. The `OnUpload` callback in `WriteSessionOptions` is passed to the content manager's `SessionOptions.OnUpload`.

### Deduplication and bytes_uploaded

When Kopia deduplicates content (file already exists in repository), `bytes_uploaded` will be 0 or very small even though `bytes_processed` shows the full file size. This is expected behavior:

- `bytes_processed` = Total bytes scanned from source
- `bytes_uploaded` = **New** bytes written to storage (after dedup)
- Dedup savings = `100 - (bytes_uploaded / bytes_processed * 100)`

### Pre-scan for Total Size

Before starting the upload, the agent performs a lightweight pre-scan:

```go
totalBytes, totalFiles := estimateSourceSize(run.SourcePath)
progressCounter.EstimatedDataSize(totalFiles, totalBytes)
```

This populates `bytes_total` early so the UI can show progress denominators immediately.

---

## Live Progress Page (Dec 2025)

### Overview

The live progress page (`e3backup_live.tpl`) displays real-time backup progress without requiring page refreshes. It uses JavaScript polling to fetch updates from the server APIs.

### Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     Live Progress Page (e3backup_live.tpl)                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌───────┐ │
│  │ Status     │  │ Processed  │  │ Uploaded   │  │ Speed      │  │ ETA   │ │
│  │ Running    │  │ 1.2 GB     │  │ 800 MB     │  │ 45 MB/s    │  │ 2m 30s│ │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘  └───────┘ │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ Progress Bar [████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░] 45.2%   │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │ Live Logs                                                               ││
│  │ [INFO] Starting backup.                                                 ││
│  │ [INFO] Stage: storage_init.                                             ││
│  │ [INFO] Stage: upload_start.                                             ││
│  │ [INFO] Update received. (progress events)                               ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  [Cancel Run] button (visible when status is running/starting/queued)       │
└─────────────────────────────────────────────────────────────────────────────┘
                    │
                    │ JavaScript polling (every 2-3 seconds)
                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              API Endpoints                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  cloudbackup_progress.php (every 2.5s)                                       │
│  ├── Returns: status, progress_pct, bytes_processed, bytes_transferred,    │
│  │            bytes_total, speed_bytes_per_sec, eta_seconds, current_item   │
│  └── Source: s3_cloudbackup_runs table                                      │
│                                                                              │
│  cloudbackup_get_run_events.php (every 3.2s)                                 │
│  ├── Returns: Structured events with message_id, params, formatted message │
│  ├── Supports: after_event_id for incremental fetching                      │
│  └── Source: s3_cloudbackup_run_events table                                │
│                                                                              │
│  cloudbackup_get_live_logs.php (every 4s)                                    │
│  ├── Returns: Raw log entries                                               │
│  └── Source: s3_cloudbackup_run_logs table                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### JavaScript Polling Implementation

**File**: `templates/e3backup_live.tpl`

```javascript
// Polling intervals (staggered to avoid request bunching)
const PROGRESS_INTERVAL = 2500;   // 2.5 seconds
const EVENTS_INTERVAL = 3200;     // 3.2 seconds
const LOGS_INTERVAL = 4000;       // 4 seconds

let progressInterval, eventsInterval, logsInterval;
let isRunning = true;  // Set from initial page load

// Start polling when page loads (for running jobs)
document.addEventListener('DOMContentLoaded', () => {
    updateProgress();
    updateEventLogs();
    if (isRunning) {
        ensurePollingStarted();
    }
});

// Idempotent polling starter
function ensurePollingStarted() {
    if (!progressInterval) progressInterval = setInterval(updateProgress, 2500);
    if (!eventsInterval)   eventsInterval = setInterval(updateEventLogs, 3200);
    if (!logsInterval)     logsInterval = setInterval(updateLiveLogs, 4000);
}

// Stop polling when job completes
function clearIntervals() {
    if (progressInterval) { clearInterval(progressInterval); progressInterval = null; }
    if (eventsInterval)   { clearInterval(eventsInterval); eventsInterval = null; }
    if (logsInterval)     { clearInterval(logsInterval); logsInterval = null; }
}
```

### Smooth Updates (Flicker-Free)

To prevent visual flickering when values update, the `smoothUpdate()` helper applies a brief opacity transition:

```javascript
function smoothUpdate(el, newValue) {
    if (!el) return;
    if (el.textContent === newValue) return;  // Skip if unchanged
    el.style.transition = 'opacity 150ms ease-in-out';
    el.style.opacity = '0.5';
    setTimeout(() => {
        el.textContent = newValue;
        el.style.opacity = '1';
    }, 100);
}
```

### Progress Bar Animation

The progress bar uses CSS animations for a polished look:

- **Striped overlay**: Moving diagonal stripes during upload
- **Shimmer effect**: Subtle highlight sweep
- **Smooth tween**: `smoothProgressTo()` animates percentage changes over 600ms with easing

```javascript
function smoothProgressTo(targetPct, duration = 600) {
    const ease = t => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t);
    // ... animation loop using requestAnimationFrame
}
```

### Deduplication Savings Display

The "Bytes Uploaded" section shows dedup savings when applicable:

```javascript
if (processed > 0 && transferred > 0) {
    const savings = 100 - (transferred / processed * 100);
    if (savings > 0.5) {  // Only show if meaningful (> 0.5%)
        dedupEl.innerHTML = '<span class="text-emerald-400">' + 
            savings.toFixed(1) + '% dedup savings</span>';
    }
} else if (processed > 0 && transferred === 0 && ['running', 'starting'].includes(status)) {
    // Scanning phase - no uploads yet
    dedupEl.innerHTML = '<span class="text-slate-400">Scanning...</span>';
}
```

### ETA-Based Progress Fallback

When no direct progress percentage is available, the UI uses a time-based model:

```javascript
const etaModel = {
    startMs: null,              // Epoch ms when run started
    predictedTotalSec: null     // Non-decreasing predicted total duration
};

// If bytes-based progress unavailable, use elapsed / (elapsed + eta)
if (etaSec !== null) {
    const candidateTotal = elapsedSec + etaSec;
    if (!etaModel.predictedTotalSec || candidateTotal > etaModel.predictedTotalSec) {
        etaModel.predictedTotalSec = candidateTotal;
    }
    progressPct = Math.min(99.0, (elapsedSec / etaModel.predictedTotalSec) * 100);
}
```

### Retry Logic

The progress fetch includes retry logic for transient failures:

```javascript
const MAX_FETCH_RETRIES = 3;
const RETRY_DELAY_MS = 2000;

.catch(err => {
    fetchRetryCount++;
    if (fetchRetryCount <= MAX_FETCH_RETRIES && isRunning) {
        setTimeout(updateProgress, RETRY_DELAY_MS);
    } else if (fetchRetryCount > MAX_FETCH_RETRIES) {
        fetchRetryCount = 0;  // Reset for next interval
    }
});
```

---

## Event Message Formatting (Dec 2025)

### CloudBackupEventFormatter

**Location**: `lib/Client/CloudBackupEventFormatter.php`

Transforms raw agent events into user-friendly messages for display in the live logs.

### Message Dictionary

```php
private static function dictionary(): array {
    return [
        'BACKUP_STARTING'       => 'Starting backup.',
        'KOPIA_UPLOAD_START'    => 'Starting upload from {source} to {bucket}/{prefix}.',
        'KOPIA_UPLOAD_COMPLETE' => 'Upload completed.',
        'KOPIA_PROGRESS_UPDATE' => 'Update received.',
        'KOPIA_UPLOAD_FAILED'   => 'Upload failed: {error}.',  // Note: "Kopia" removed from message
        'HYPERV_VM_STARTING'    => 'Starting backup of virtual machine \'{vm_name}\'.',
        'HYPERV_VM_COMPLETE'    => 'VM \'{vm_name}\' backed up successfully.',
        'HYPERV_VM_FAILED'      => 'VM \'{vm_name}\' backup failed: {message}.',
        'CANCEL_REQUESTED'      => 'Backup cancellation requested by user.',
        'CANCELLED'             => 'Operation was cancelled.',
        // ... more templates
    ];
}
```

### Branding Sanitization

To maintain product branding, the formatter sanitizes any "Kopia" references:

```php
private static function sanitizeBranding(string $text): string {
    // Replace "kopia" (case-insensitive) with "eazyBackup"
    return preg_replace('/\bkopia\b/i', 'eazyBackup', $text);
}
```

This is applied to:
1. All interpolated parameter values
2. The final rendered message

### Cancellation Detection

The formatter detects cancellation errors and provides a clean message:

```php
private static function isCancellationError(string $message): bool {
    $cancellationPhrases = [
        'context canceled',
        'context cancelled',
        'operation cancelled',
        'operation canceled',
        'was cancelled',
        'was canceled',
    ];
    $lower = strtolower($message);
    foreach ($cancellationPhrases as $phrase) {
        if (strpos($lower, $phrase) !== false) return true;
    }
    return false;
}
```

When detected, the message is replaced with "Operation was cancelled." instead of showing raw error text.

---

## Graceful Cancellation (Dec 2025)

### Overview

All backup engines support graceful cancellation. When a user clicks "Cancel Run", the backup stops cleanly without leaving orphaned data or misleading error messages.

### Cancellation Flow

```
User clicks "Cancel Run"
        │
        ▼
cloudbackup_cancel_run.php
├── For 'queued' runs: Immediately set status = 'cancelled'
└── For 'running' runs: Set cancel_requested = 1
        │
        ▼
Agent polls agent_poll_commands.php (every 3s)
├── Sees cancel_requested = 1
├── Calls cancel() on context
└── Returns { type: 'cancel' } command
        │
        ▼
Backup engine detects context.Canceled
├── Stops upload gracefully
├── Cleans up (e.g., merge Hyper-V checkpoints)
├── Pushes CANCELLED event (info level, not error)
└── Sets status = 'cancelled'
```

### Go Agent Implementation

**Detection Helper** (`kopia.go`, `hyperv_backup.go`):

```go
func isCancellationError(err error) bool {
    if err == nil {
        return false
    }
    if errors.Is(err, context.Canceled) {
        return true
    }
    if errors.Is(err, blob.ErrCancelled) {
        return true
    }
    errStr := strings.ToLower(err.Error())
    return strings.Contains(errStr, "context canceled") ||
           strings.Contains(errStr, "operation cancelled")
}
```

**Graceful Handling**:

```go
if err != nil {
    if isCancellationError(err) {
        log.Printf("agent: upload cancelled for run %d: %v", run.RunID, err)
        r.pushEvents(run.RunID, RunEvent{
            Type:      "info",      // Not "error"
            Level:     "info",      // Not "error"
            MessageID: "CANCELLED",
            ParamsJSON: map[string]any{
                "message": "Upload was cancelled.",
            },
        })
        return err  // Propagate without error reporting
    }
    // ... normal error handling for non-cancellation errors
}
```

### Hyper-V Specific

For Hyper-V backups, cancelled VMs:
- Don't increment the failure count
- Still merge/remove checkpoints to avoid orphans
- Report as info-level cancellation, not error

---

## Critical Bug Fixes (Dec 11, 2025)

### 1. Bytes Uploaded Always Zero

**Symptom:** `bytes_uploaded` was always 0 in progress events, even for new backups with no deduplication.

**Root Cause:** Kopia's `WriteSession` has an `OnUpload` callback in `WriteSessionOptions` that must be explicitly set. This callback is triggered by the content manager when bytes are flushed to blob storage. Without it, the `UploadedBytes` method on the progress counter is never called.

**Fix Location:** `internal/agent/kopia.go` → both `WriteSession` calls

**Before (broken):**
```go
uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{
    Purpose: "snapshot",
}, func(wctx context.Context, w repo.RepositoryWriter) error { ... })
```

**After (fixed):**
```go
uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{
    Purpose:  "snapshot",
    OnUpload: progressCounter.UploadedBytes,  // ← REQUIRED for bytes tracking
}, func(wctx context.Context, w repo.RepositoryWriter) error { ... })
```

### 2. WHMCS HTML-Encoding of POST Data

**Symptom:** Backup jobs fail immediately with errors like:
- `CreateFile [&quot: The system cannot find the file specified.`
- `no VMs configured for backup` (for Hyper-V)

**Root Cause:** WHMCS HTML-encodes POST data, turning `["C:\path"]` into `[&quot;C:\path&quot;]`. When this is JSON-decoded without first HTML-decoding, parsing fails.

**Fix Location:** `api/cloudbackup_create_job.php`

**Fix:**
```php
// HTML-decode before JSON-decode for all JSON fields
$raw = trim($_POST['source_paths']);
$raw = html_entity_decode($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$decoded = json_decode($raw, true);

// Also for hyperv_vm_ids, hyperv_config, etc.
$hypervVmIdsRaw = $_POST['hyperv_vm_ids'] ?? '';
$hypervVmIdsRaw = html_entity_decode($hypervVmIdsRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```

### 3. Hyper-V VM Lookup by GUID

**Symptom:** Hyper-V backup fails with:
```
Hyper-V was unable to find a virtual machine with name "9e479325-c193-45e2-a918-5a09a06ff251"
```

**Root Cause:** The agent was passing VM GUIDs to `Get-VM -Name`, which expects display names. When job config stores GUIDs (from the frontend), the PowerShell lookup fails.

**Fix Location:** `internal/agent/hyperv_backup.go`

**Fix:** Added `getVMByNameOrGUID()` helper that detects GUIDs and uses appropriate lookup:

```go
func (r *Runner) getVMByNameOrGUID(ctx context.Context, mgr *hyperv.Manager, vmName string) (*hyperVVMInfo, error) {
    if isGUID(vmName) {
        vm, err := mgr.GetVMByGUID(ctx, vmName)
        if err == nil {
            return vm, nil
        }
        // Fall back to name lookup if GUID fails
        log.Printf("agent: GUID lookup failed for %s, falling back to name: %v", vmName, err)
    }
    return mgr.GetVM(ctx, vmName)
}

func isGUID(s string) bool {
    // Match standard GUID format: 8-4-4-4-12 hex chars
    matched, _ := regexp.MatchString(
        `(?i)^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`, s)
    return matched
}
```

---

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

---

## Hyper-V Restore - Phase 1 (Dec 2025)

### Overview

Phase 1 of Hyper-V restore enables exporting VHDX disk images from Kopia snapshots to local filesystem paths. This allows administrators to recover VM disks for manual attachment or VM recreation.

**Supported Operations:**
- Export individual VHDX files from backup points
- Restore to any local path on the agent host
- Full backup point restore (all disks)
- Real-time progress tracking in the UI
- Cancellation support during restore

**Future Phases:**
- Phase 2: Direct VM import to Hyper-V
- Phase 3: Instant VM recovery (mount VHDX directly from Kopia)

### Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      Hyper-V Restore Wizard UI                               │
│                                                                              │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │  Step 1: Select Backup Point                                         │   │
│   │  ├── Lists backup points from s3_hyperv_backup_points               │   │
│   │  ├── Shows backup type (Full/Incremental), date, consistency level  │   │
│   │  └── Displays disk manifests with sizes                             │   │
│   │                                                                       │   │
│   │  Step 2: Select Disks                                                │   │
│   │  ├── Multi-select checkboxes for each VHDX                          │   │
│   │  └── Shows disk path and size                                       │   │
│   │                                                                       │   │
│   │  Step 3: Choose Target Path                                          │   │
│   │  ├── Local filesystem path input (e.g., C:\Restored\MyVM)           │   │
│   │  └── Path validation on submit                                       │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          PHP API Layer                                       │
│                                                                              │
│   cloudbackup_hyperv_backup_points.php                                       │
│   ─────────────────────────────────────────────────────────────────────     │
│   GET: Lists backup points for a VM with disk manifests                     │
│   Returns: { backup_points: [...], vm: {...}, disks: [...] }                │
│                                                                              │
│   cloudbackup_hyperv_start_restore.php                                       │
│   ─────────────────────────────────────────────────────────────────────     │
│   POST: Initiates restore operation                                          │
│   1. Creates run with run_type='hyperv_restore'                             │
│   2. Queues hyperv_restore command with job context                         │
│   3. Returns { restore_run_id, redirect_url } for live progress             │
│                                                                              │
│   agent_poll_pending_commands.php                                            │
│   ─────────────────────────────────────────────────────────────────────     │
│   Includes 'hyperv_restore' in command types                                │
│   Returns full job context (S3 credentials, bucket, endpoint)               │
└─────────────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Windows Backup Agent                                 │
│                                                                              │
│   hyperv_restore.go - executeHyperVRestoreCommand()                         │
│   ─────────────────────────────────────────────────────────────────────     │
│   1. Parse HyperVRestorePayload (vm_name, target_path, disk_manifests)     │
│   2. Start cancellation polling goroutine (every 3 seconds)                │
│   3. Create target directory                                                │
│   4. For each disk:                                                          │
│   │   └── Call kopiaRestoreVHDX(ctx, run, manifestID, targetPath, ...)    │
│   5. Report final status (success/partial_success/failed/cancelled)        │
│                                                                              │
│   kopia.go - kopiaRestoreVHDX()                                             │
│   ─────────────────────────────────────────────────────────────────────     │
│   1. Open Kopia repository (connect if config missing)                      │
│   2. Load snapshot from manifest ID                                         │
│   3. Get actual file size from reader.Length()                             │
│   4. For large files: Use parallel segment restore                          │
│   5. Report progress to server via serverProgressFn callback                │
│                                                                              │
│   kopia.go - singleFileRestoreOutput.writeFileParallel()                    │
│   ─────────────────────────────────────────────────────────────────────     │
│   1. Divide file into segments (32MB minimum per segment)                   │
│   2. Spawn N parallel workers (based on CPU count, up to 64)               │
│   3. Each worker: Open reader → Seek to offset → Write segment             │
│   4. Report aggregate progress every 2 seconds                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Key Files

| File | Purpose |
|------|---------|
| **PHP API** | |
| `api/cloudbackup_hyperv_backup_points.php` | Lists backup points and disk manifests for a VM |
| `api/cloudbackup_hyperv_start_restore.php` | Initiates restore, creates run, queues command |
| `api/agent_poll_pending_commands.php` | Returns `hyperv_restore` commands to agent |
| **Templates** | |
| `templates/e3backup_hyperv.tpl` | Hyper-V management page with VM list |
| `templates/e3backup_hyperv_restore.tpl` | Restore wizard with Alpine.js |
| `templates/e3backup_live.tpl` | Live progress page (shared with backups) |
| **Page Controllers** | |
| `pages/e3backup_hyperv.php` | Controller for Hyper-V management page |
| `pages/e3backup_hyperv_restore.php` | Controller for restore wizard |
| **Agent (Go)** | |
| `internal/agent/hyperv_restore.go` | Restore command handler (Windows only) |
| `internal/agent/hyperv_restore_stub.go` | Stub for non-Windows builds |
| `internal/agent/kopia.go` | `kopiaRestoreVHDX()` and parallel restore logic |
| `internal/agent/runner.go` | Dispatches `hyperv_restore` commands |

### Data Structures

**HyperVRestorePayload** (Go):
```go
type HyperVRestorePayload struct {
    BackupPointID  int64             `json:"backup_point_id"`
    VMName         string            `json:"vm_name"`
    VMGUID         string            `json:"vm_guid"`
    TargetPath     string            `json:"target_path"`
    DiskManifests  map[string]string `json:"disk_manifests"`  // disk_path -> manifest_id
    RestoreChain   []RestoreChainEntry `json:"restore_chain"`
    BackupType     string            `json:"backup_type"`
    RestoreRunID   int64             `json:"restore_run_id"`
    RestoreRunUUID string            `json:"restore_run_uuid"`
}
```

**singleFileRestoreOutput** (Go):
```go
type singleFileRestoreOutput struct {
    targetPath       string
    knownFileSize    int64  // From snapshot metadata or reader.Length()
    progressCallback func(ctx context.Context, stats restore.Stats)
    serverProgressFn func(bytesWritten, bytesTotal int64, speedBps float64)
}
```

### Performance Optimization: Parallel Segment Restore

#### The Problem

Initial restore performance was limited to ~200 Mbps despite having a 20-core Xeon and 1 Gbps network capacity. Investigation revealed:

1. **Streaming entries have size=0**: For VHDX files backed up via streaming, both `snapshot.RootEntry.FileSize` and `entry.Size()` return 0
2. **Kopia's restore.Parallel is for multiple files**: The `restore.Entry()` `Parallel` option parallelizes restoring multiple files, not content blocks within a single file
3. **Sequential content block fetching**: The default restore reads content blocks one at a time from S3

#### The Solution: True Parallel Segment Restore

For large files (>64MB), we implemented parallel segment downloading:

```go
func (s *singleFileRestoreOutput) writeFileParallel(ctx context.Context, e kopiafs.File, fileSize int64) error {
    // Calculate workers based on CPU count (min 4, max 64)
    numWorkers := runtime.NumCPU()
    if fileSize > 10*1024*1024*1024 { // >10GB
        numWorkers *= 2
    }
    
    // Minimum 32MB segments to avoid overhead
    segmentSize := fileSize / int64(numWorkers)
    if segmentSize < 32*1024*1024 {
        segmentSize = 32 * 1024 * 1024
    }

    // Pre-allocate file (sparse file optimization on Windows)
    outFile.Truncate(fileSize)

    // Spawn parallel workers
    for i := 0; i < numWorkers; i++ {
        go func(workerID int, start, end int64) {
            // Each worker opens its own reader
            reader, _ := e.Open(ctx)
            reader.Seek(start, io.SeekStart)
            
            // Open file for writing at offset
            f, _ := os.OpenFile(targetPath, os.O_WRONLY, 0644)
            f.Seek(start, io.SeekStart)
            
            // Read/write segment
            // ... copy loop with progress reporting
        }(i, startOffset, endOffset)
    }
}
```

**Key Insight**: Kopia's `object.Reader` implements `io.Seeker`, allowing multiple independent readers to read different portions of the same file simultaneously.

#### Getting the Actual File Size

For streaming backups, the file size is not stored in the snapshot metadata. Solution:

```go
// If size is still 0, open reader and call Length()
if fileSize <= 0 {
    testReader, err := e.Open(ctx)
    if err == nil {
        if lr, ok := testReader.(interface{ Length() int64 }); ok {
            fileSize = lr.Length()  // Gets actual size from object metadata
        }
        testReader.Close()
    }
}
```

#### Performance Results

| Configuration | Transfer Rate |
|---------------|---------------|
| Sequential (before) | ~200 Mbps |
| Parallel 16 workers | ~600 Mbps |
| Parallel 40 workers (20-core) | ~800+ Mbps |
| Parallel 64 workers (large files) | Near line rate |

### Live Progress Integration

#### Challenge

The parallel restore runs entirely within `singleFileRestoreOutput.WriteFile()`, which is called by Kopia's `restore.Entry()`. Progress needs to be reported to the server for the live UI, but the restore output struct doesn't have access to the runner or API client.

#### Solution: Progress Callback Chain

1. **Server Progress Callback**: Added `serverProgressFn` to `singleFileRestoreOutput`:

```go
singleFileOut := &singleFileRestoreOutput{
    targetPath:    targetFilePath,
    knownFileSize: knownSize,
    serverProgressFn: func(bytesWritten, bytesTotal int64, speedBps float64) {
        progressPct := float64(bytesWritten) / float64(bytesTotal) * 100.0
        elapsed := time.Since(restoreStartTime)
        speedMBps := float64(bytesWritten) / elapsed.Seconds() / (1024 * 1024)
        
        _ = r.client.UpdateRun(RunUpdate{
            RunID:            runID,
            Status:           "running",
            ProgressPct:      progressPct,
            BytesTransferred: Int64Ptr(bytesWritten),
            BytesTotal:       Int64Ptr(bytesTotal),
            SpeedBytesPerSec: int64(speedBps),
            EtaSeconds:       etaSeconds,
            CurrentItem:      fmt.Sprintf("Restoring %s (%.1f MB/s)", diskName, speedMBps),
        })
    },
}
```

2. **Progress Aggregation in Parallel Workers**: Each worker reports bytes written, and the main thread aggregates and reports every 2 seconds:

```go
var lastServerReport time.Time
reportProgress := func(n int64) {
    progressMu.Lock()
    bytesWritten += n
    current := bytesWritten
    progressMu.Unlock()
    
    now := time.Now()
    if s.serverProgressFn != nil && now.Sub(lastServerReport) >= 2*time.Second {
        lastServerReport = now
        elapsed := now.Sub(startTime).Seconds()
        speedBps := float64(current) / elapsed
        s.serverProgressFn(current, fileSize, speedBps)
    }
}
```

3. **Live Progress Page**: The existing `e3backup_live.tpl` polls `cloudbackup_progress.php` every 2.5 seconds and displays:
   - Progress bar with percentage
   - Bytes transferred / total
   - Speed (MB/s, Gbps)
   - ETA countdown
   - Current item (disk name)

### Cancellation Support

Restore operations support graceful cancellation via the same mechanism as backups:

1. **Cancel Polling Goroutine**: Started at the beginning of `executeHyperVRestoreCommand()`:

```go
restoreCtx, cancelRestore := context.WithCancel(ctx)
defer cancelRestore()

go func() {
    ticker := time.NewTicker(3 * time.Second)
    for {
        select {
        case <-restoreCtx.Done():
            return
        case <-ticker.C:
            cancelReq, _, _ := r.pollCommands(runID)
            if cancelReq {
                log.Printf("agent: hyperv_restore cancel requested for run %d", runID)
                cancelRestore()  // Cancels context, stopping all workers
                return
            }
        }
    }
}()
```

2. **Worker Context Checking**: Each parallel worker checks `ctx.Done()` in its read/write loop:

```go
for segmentWritten < bytesToRead {
    select {
    case <-ctx.Done():
        return  // Exit immediately on cancellation
    default:
    }
    // ... read/write logic
}
```

3. **UI Integration**: Added a custom cancel confirmation modal in `e3backup_live.tpl` that replaces the browser's native `confirm()` dialog.

### Database Schema Changes

Added columns for restore support:

```sql
-- s3_cloudbackup_runs
ALTER TABLE s3_cloudbackup_runs ADD COLUMN run_type VARCHAR(50) DEFAULT 'backup';
-- Values: 'backup', 'restore', 'hyperv_restore'

-- s3_hyperv_backup_points (added for completeness)
ALTER TABLE s3_hyperv_backup_points ADD COLUMN warnings_json TEXT;
ALTER TABLE s3_hyperv_backup_points ADD COLUMN warning_code VARCHAR(100);
ALTER TABLE s3_hyperv_backup_points ADD COLUMN has_warnings TINYINT(1) DEFAULT 0;
```

The `agent_next_run.php` excludes restore runs to prevent the agent from picking them up as backup jobs:

```php
->whereNotIn('r.run_type', ['restore', 'hyperv_restore'])
```

### Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| "snapshot not found" | Empty manifest_id in backup_point | Verify backup completed successfully; re-run backup |
| Size shows 0 bytes | Streaming backup metadata | Agent now calls `reader.Length()` to get actual size |
| Slow restore (~200 Mbps) | Sequential restore (pre-fix) | Ensure parallel restore code is active; check worker count in logs |
| Progress not updating | Missing serverProgressFn | Verify callback is wired up in `singleFileRestoreOutput` |
| Cancel button doesn't work | Missing cancel polling | Check for `hyperv_restore cancel poll` in agent logs |
| "is a directory" error | Wrong target path handling | Fixed by properly creating target file within directory |

### Agent Log Examples

**Successful Parallel Restore:**
```
agent: kopia VHDX got size from reader.Length(): 23760732160 bytes (22.13 GB)
agent: kopia VHDX using parallel segment restore (fileSize=23760732160 = 22.13 GB)
agent: kopia VHDX parallel restore starting: size=23760732160 (22.13 GB) workers=64 segment_size=371261440 (354.06 MB)
agent: kopia parallel worker 0 starting: offset=0-371261440 size=371261440 (354.1 MB)
agent: kopia parallel worker 1 starting: offset=371261440-742522880 size=371261440 (354.1 MB)
...
agent: kopia VHDX parallel restore progress: 5368709120 / 23760732160 bytes (22.6%)
...
agent: kopia parallel worker 0 complete: 371261440 bytes in 45.2s (7.8 MB/s)
agent: kopia VHDX parallel restore complete: 23760732160 bytes (22.13 GB) to C:\Restored\... using 64 workers in 95.3s (237.8 MB/s = 1.90 Gbps)
```

**Cancellation:**
```
agent: hyperv_restore cancel requested for run 269
agent: hyperv_restore cancelled
```

---

---

## Agent Management APIs (Jan 2025)

### agent_delete.php

Permanently deletes an agent from the database. Use this when:
- Testing re-enrollment flows
- Removing decommissioned devices
- Cleaning up duplicate agent registrations

**Endpoint**: `POST api/agent_delete.php`

**Parameters**:
- `agent_id` (required): The agent ID to delete

**Authorization**: Must be the agent's owner OR an MSP parent of the agent's tenant.

**Response**:
```json
{
    "status": "success",
    "message": "Agent deleted",
    "agent_id": 123
}
```

**Note**: This is different from the `agent_disable.php` endpoint which only sets `status=disabled` and regenerates the token. The delete endpoint completely removes the agent record.

### Download Agent Flyout (Jan 2025)

The sidebar navigation now includes a "Download Agent" menu item that opens a flyout with:
- **Windows** download button → `/client_installer/e3-backup-agent-setup.exe`
- **Linux** download button → `/client_installer/e3-backup-agent-linux`

**Server Path**: The installer files must be placed in `/var/www/eazybackup.ca/accounts/client_installer/` since `accounts/` is the public web root.

Both buttons use orange styling (`bg-orange-600`) and include platform icons.

**Location**: `accounts/templates/eazyBackup/header.tpl` (sidebar navigation)

---

## Outstanding items / gotchas

### Hyper-V Engine - Remaining Work
- Hyper-V: Phase 2 restore - Direct VM import to Hyper-V.
- Hyper-V: Phase 3 restore - Instant VM recovery (mount VHDX directly from Kopia).
- Hyper-V: RCT incremental backup chain reconstruction for restore.
- Hyper-V: Admin UI for viewing backup points and VM history.
- Hyper-V: Test with clustered Hyper-V (CSV) environments.

### Hyper-V Engine - Completed (Dec 2025)
- ✅ Full backup: VSS checkpoint → stream VHDX to Kopia.
- ✅ Crash-consistent fallback: For VMs with checkpoints disabled.
- ✅ Multi-VM jobs: Partial success handling, continue on failure.
- ✅ Cumulative progress: Progress bar tracks all VMs, not resetting between VMs.
- ✅ Cancellation polling: Agent checks for cancel every 3 seconds.
- ✅ Event formatting: User-friendly messages in live log.
- ✅ VM discovery: Browser in job wizard via command queue.
- ✅ RCT infrastructure: Change tracking queries ready (incremental backup WIP).
- ✅ **Phase 1 Restore**: VHDX export to local filesystem with parallel segment download.
- ✅ **Restore Progress**: Live progress page integration with speed/ETA/bytes tracking.
- ✅ **Restore Cancellation**: Graceful cancellation via context propagation to parallel workers.
- ✅ **Performance Optimization**: True parallel restore achieving near line-rate speeds (1+ Gbps).

### Cancellation - Completed (Dec 2025)
- ✅ All engines have cancel polling (sync, kopia, disk_image, hyperv, hyperv_restore).
- ✅ PHP only immediately cancels 'queued' runs; 'running' runs wait for agent.
- ✅ Agent cleanup: Hyper-V merges checkpoints before marking cancelled.
- ✅ UI feedback: Button shows "Cancel requested...", status updates on poll.
- ✅ Graceful cancellation: Cancelled jobs show info-level "Operation was cancelled" instead of error messages.
- ✅ Branding sanitization: No "Kopia" text appears in error messages; replaced with "eazyBackup".
- ✅ **Custom Cancel Modal**: Beautiful confirmation modal replaces native browser `confirm()` dialog.

### Live Progress Page - Completed (Dec 2025)
- ✅ Flicker-free updates: `smoothUpdate()` helper with opacity transitions.
- ✅ Absolute fetch URLs: Fixed relative URL issues in WHMCS context.
- ✅ Dedup savings display: Shows "Scanning..." during hash phase, savings % when upload starts.
- ✅ Robust polling: Retry logic with `ensurePollingStarted()`/`clearIntervals()`.
- ✅ ETA-based fallback: Time-based progress when bytes not available.
- ✅ Bytes uploaded tracking: Fixed by adding `OnUpload` callback to `WriteSessionOptions`.

### Job Creation - Completed (Dec 2025)
- ✅ HTML-encoding fix: `html_entity_decode()` before JSON parsing for all POST fields.
- ✅ Hyper-V VM lookup: `getVMByNameOrGUID()` handles both GUID and display name lookups.

### Cloud NAS - Remaining Work
- Agent: implement Kopia snapshot mount command for browsing snapshots (Time Machine).
- Cloud NAS: Implement bandwidth limiting in VFS.
- Cloud NAS: Implement auto-mount on agent startup for persistent mounts.
- Cloud NAS: File browser UI for Time Machine.
- Cloud NAS: Service mode needs testing for drive visibility (may need to run as user, not SYSTEM).

### Cloud NAS - Fixed (Dec 2025)
- ✅ Windows Session Isolation: Documented - agent must run non-elevated.
- ✅ Drive visibility in Explorer: Fixed via WScript.Network + shell notifications.
- ✅ Empty directory listings: Fixed by using "Ceph" S3 provider.
- ✅ Custom drive labels: Implemented via registry MountPoints2.
- ✅ Stale port caching: Fixed by using HTTP URL format.

### General
- Schema: ensure all envs have run columns (`engine`, `dest_type`, `dest_bucket`, `dest_prefix`, `dest_local_path`, `worker_host`, `run_type`); current code guards missing columns.
- Local destinations deferred until schema change and agent path handling are ready.
- Heartbeats: keep ≤90s to stay within reclaim window; watchdog still marks stale runs failed.

## Remote Filesystem Browser (Dec 2025)

- **Purpose**: Step 2 of the Local Agent Job Wizard now uses a visual filesystem browser so users can pick folders from their agent host instead of typing paths.
- **Flow**:
  - Client queues `browse_directory` via `agent_browse_filesystem.php` (command in `s3_cloudbackup_run_commands`).
  - Agent polls `agent_poll_pending_commands.php`, runs `BrowseDirectory()` (Go), and posts results to `agent_report_browse.php`.
  - `agent_report_browse.php` marks the command `completed` and stores the JSON listing in `result_message`; the request endpoint short-polls and returns it to the UI.
- **Agent**:
  - `internal/agent/filesystem.go`: `BrowseDirectory()` lists drives (empty path) or directory contents (folders first, max 1000 entries, icon hints), and guards against traversal.
  - `runner.go`: handles `browse_directory` pending commands and reports via `Client.ReportBrowseResult()`.
- **Frontend**:
  - `templates/cloudbackup_jobs.tpl` Step 2 replaced with an Alpine-powered explorer (breadcrumb, lazy loading, icons, multi-select checkboxes, selection summary, manual path input). Include/exclude globs remain.
  - Selected paths are synced to hidden inputs as `source_paths` (JSON array) plus `source_path` (first entry for backward compatibility).
- **Data model**:
  - If column `source_paths_json` exists on `s3_cloudbackup_jobs`, it is populated with the array; otherwise only `source_path` is set to the first selection (compatibility mode).

---

## Network Share Credentials for Service Mode (Dec 2025)

### Overview

When the backup agent runs as a Windows service (under SYSTEM account), it cannot access user-mapped network drives. This feature enables the agent to authenticate to network shares before running backups, making it possible to back up network locations when running as a service.

### Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                      WHMCS Job Wizard - Step 2                       │
│                                                                       │
│   ┌─────────────────────────────────────────────────────────────┐   │
│   │              Remote Filesystem Browser                       │   │
│   │                                                               │   │
│   │   ┌────────────┐   ┌────────────┐   ┌────────────────────┐  │   │
│   │   │    C:      │   │    D:      │   │   Z: (Network)     │  │   │
│   │   │   (Local)  │   │   (Local)  │   │   \\server\share   │  │   │
│   │   └────────────┘   └────────────┘   └────────────────────┘  │   │
│   │                                            ▲                 │   │
│   │                                            │ is_network:true │   │
│   └─────────────────────────────────────────────────────────────┘   │
│                                                                       │
│   ┌─────────────────────────────────────────────────────────────┐   │
│   │        Network Share Credentials (shown when needed)         │   │
│   │                                                               │   │
│   │   Username: [DOMAIN\user________________]                    │   │
│   │   Password: [••••••••••••________________]                   │   │
│   │   Domain:   [MYDOMAIN___________________]  (optional)        │   │
│   └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │ Save Job (credentials encrypted)
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                           PHP API Layer                              │
│                                                                       │
│   cloudbackup_create_job.php / cloudbackup_update_job.php           │
│   ─────────────────────────────────────────────────────────────     │
│   1. Receives network_username, network_password, network_domain    │
│   2. Encrypts credentials using HelperController::encryptKey()      │
│   3. Stores in source_config.network_credentials JSON blob          │
│                                                                       │
│   agent_next_run.php                                                 │
│   ─────────────────────────────────────────────────────────────     │
│   1. Reads source_config.network_credentials from job               │
│   2. Decrypts using HelperController::decryptKey()                  │
│   3. Passes to agent in NextRunResponse.network_credentials         │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                │ Decrypted credentials in API response
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         Windows Agent                                │
│                                                                       │
│   runner.go - runRun()                                              │
│   ─────────────────────────────────────────────────────────────     │
│   1. Check if NetworkCredentials present in run data                │
│   2. Call authenticateNetworkPath(sourcePath, creds)                │
│                                                                       │
│   authenticateNetworkPath()                                          │
│   ─────────────────────────────────────────────────────────────     │
│   1. Extract share root from UNC path (\\server\share)              │
│   2. Execute: net use \\server\share /user:DOMAIN\user password     │
│   3. Log success/failure                                            │
│                                                                       │
│   [Backup runs with authenticated network access]                    │
│                                                                       │
│   disconnectNetworkPath()  (via defer)                               │
│   ─────────────────────────────────────────────────────────────     │
│   1. Execute: net use \\server\share /delete /y                     │
│   2. Cleanup network connection                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Components

#### Agent: Volume Enumeration with UNC Resolution

**File**: `internal/agent/volumes_windows.go`

The volume enumeration now includes network (remote) drives with UNC path resolution:

```go
type VolumeInfo struct {
    Path       string `json:"path"`                  // e.g. C: or Z:
    Label      string `json:"label,omitempty"`
    SizeBytes  uint64 `json:"size_bytes,omitempty"`
    FileSystem string `json:"filesystem,omitempty"`
    Type       string `json:"type,omitempty"`        // "fixed" or "network"
    UNCPath    string `json:"unc_path,omitempty"`    // e.g. \\server\share
    IsNetwork  bool   `json:"is_network,omitempty"`  // true for mapped drives
}
```

- Uses `GetDriveTypeW` to detect `DRIVE_REMOTE` (network drives)
- Calls `WNetGetConnectionW` from `mpr.dll` to resolve drive letters to UNC paths
- Network drives display their UNC path in the browser UI

#### Agent: Network Authentication

**File**: `internal/agent/runner.go`

Before starting any backup, the agent checks for network credentials:

```go
func (r *Runner) runRun(run *NextRunResponse) error {
    // Authenticate to network share if credentials provided
    if run.NetworkCredentials != nil && run.NetworkCredentials.Username != "" {
        if err := r.authenticateNetworkPath(run.SourcePath, run.NetworkCredentials); err != nil {
            return fmt.Errorf("network authentication failed: %w", err)
        }
        defer r.disconnectNetworkPath(run.SourcePath)
    }
    // ... proceed with backup
}
```

The `authenticateNetworkPath` function:
1. Extracts the share root from UNC paths (e.g., `\\server\share\folder` → `\\server\share`)
2. Builds the `net use` command with credentials
3. Executes authentication before backup
4. Logs errors without exposing passwords

#### PHP: Credential Encryption

**Files**: `cloudbackup_create_job.php`, `cloudbackup_update_job.php`

Credentials are encrypted using the same encryption key used for S3 credentials:

```php
// Handle network share credentials for UNC paths
$netUser = $_POST['network_username'] ?? '';
$netPass = $_POST['network_password'] ?? '';
$netDomain = $_POST['network_domain'] ?? '';
if (!empty($netUser) && !empty($netPass)) {
    $encryptedUser = HelperController::encryptKey($netUser, $encryptionKey);
    $encryptedPass = HelperController::encryptKey($netPass, $encryptionKey);
    $sourceConfig['network_credentials'] = [
        'username' => $encryptedUser,
        'password' => $encryptedPass,
        'domain' => $netDomain,
    ];
}
```

**File**: `agent_next_run.php`

Credentials are decrypted when preparing the run data:

```php
$sourceConfig = json_decode($job->source_config ?? '{}', true) ?? [];
if (isset($sourceConfig['network_credentials'])) {
    $encNetCreds = $sourceConfig['network_credentials'];
    $decUser = HelperController::decryptKey($encNetCreds['username'], $encKey);
    $decPass = HelperController::decryptKey($encNetCreds['password'], $encKey);
    $networkCreds = [
        'username' => $decUser,
        'password' => $decPass,
        'domain' => $encNetCreds['domain'] ?? '',
    ];
}
```

#### Frontend: Credential Collection

**File**: `templates/cloudbackup_jobs.tpl`

The file browser tracks network paths and shows a credential form when needed:

- Network drives display with a purple network icon
- UNC path shown as subtitle (e.g., `\\fileserver\documents`)
- When network paths are selected, a credential panel appears
- Credentials sync to wizard state and are sent with job create/update

### Data Flow

1. **Job Creation**: User selects a network drive or UNC path in the file browser
2. **Credential Entry**: User enters domain, username, and password
3. **Encryption**: Server encrypts credentials with module encryption key
4. **Storage**: Credentials stored in `source_config.network_credentials` JSON blob
5. **Backup Execution**: Agent receives decrypted credentials in `NextRunResponse`
6. **Authentication**: Agent runs `net use` to authenticate before backup
7. **Cleanup**: Agent disconnects from share after backup completes

### Security Considerations

- **Encryption**: Credentials are encrypted at rest using the same key as S3 credentials
- **Transport**: Credentials travel over HTTPS between agent and server
- **Logging**: Passwords are never logged; only sanitized command strings shown
- **Cleanup**: Network connections are disconnected after backup via `defer`

### Limitations

- **Single Credential Set**: Currently supports one set of credentials per job. If backing up multiple shares requiring different credentials, create separate jobs.
- **Windows Only**: Network authentication via `net use` only works on Windows agents.
- **Interactive Testing**: When testing the agent interactively (not as a service), the agent may already have access to mapped drives from the user session.

---

## Developer Troubleshooting Guide

### Quick Diagnostics

| Symptom | Likely Cause | Check |
|---------|--------------|-------|
| `bytes_uploaded` always 0 | Missing `OnUpload` callback | Verify `WriteSessionOptions.OnUpload` is set |
| `[&quot` in paths/errors | WHMCS HTML-encoding | Add `html_entity_decode()` before `json_decode()` |
| "Kopia" in error messages | Branding not sanitized | Check `sanitizeErrorMessage()` in Go, `sanitizeBranding()` in PHP |
| Hyper-V VM not found | GUID vs name mismatch | Verify `getVMByNameOrGUID()` is used |
| Progress bar stuck | Missing `ensurePollingStarted()` | Check JS console for fetch errors |
| Cancellation shows error | Missing `isCancellationError()` check | Verify cancellation detection before error reporting |

### Agent Debugging

**Enable verbose logging**:
```cmd
e3-backup-agent.exe -config agent.conf -debug
```

**Key log patterns to watch**:
```
agent: kopia UploadedBytes callback: +X bytes (total: Y)  ← Bytes being uploaded
agent: kopia UploadFinished: bytesHashed=X bytesUploaded=Y ← Final stats
agent: cancel requested for run N                          ← Cancellation received
agent: upload cancelled for run N                          ← Graceful cancellation
```

### Database Quick Checks

**Check job source_path encoding**:
```sql
SELECT id, name, source_path FROM s3_cloudbackup_jobs WHERE source_path LIKE '%&quot%';
```

**Check run progress values**:
```sql
SELECT id, status, progress_pct, bytes_processed, bytes_transferred 
FROM s3_cloudbackup_runs WHERE id = ?;
```

**Check cancel_requested flag**:
```sql
SELECT id, status, cancel_requested FROM s3_cloudbackup_runs WHERE status = 'running';
```

### API Endpoint Testing

**Test progress endpoint**:
```bash
curl "https://example.com/modules/addons/cloudstorage/api/cloudbackup_progress.php?run_uuid=XXX"
```

**Test events endpoint**:
```bash
curl "https://example.com/modules/addons/cloudstorage/api/cloudbackup_get_run_events.php?run_uuid=XXX&limit=10"
```

### Rebuilding the Agent

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
GOOS=windows GOARCH=amd64 go build -o e3-backup-agent.exe ./cmd/agent
```

### Key Files Reference

| Component | Files |
|-----------|-------|
| **Live Progress UI** | `templates/e3backup_live.tpl` |
| **Progress API** | `api/cloudbackup_progress.php` |
| **Events API** | `api/cloudbackup_get_run_events.php` |
| **Event Formatter** | `lib/Client/CloudBackupEventFormatter.php` |
| **Job Creation** | `api/cloudbackup_create_job.php` |
| **Agent Next Run** | `api/agent_next_run.php` |
| **Kopia Engine** | `internal/agent/kopia.go` |
| **Hyper-V Engine** | `internal/agent/hyperv_backup.go` |
| **Progress Counter** | `internal/agent/kopia.go` → `kopiaProgressCounter` |

