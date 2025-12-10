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
- `s3_cloudbackup_jobs`: adds `engine`, `dest_type`, `dest_bucket/prefix`, `dest_local_path`, `bucket_auto_create`, `schedule_json`, `retention_json`, `policy_json`, `bandwidth_limit_kbps`, `parallelism`, `encryption_mode`, `compression`, `local_include_glob`, `local_exclude_glob`, `local_bandwidth_limit_kbps`, `agent_id`, `last_policy_hash`.
- `s3_cloudbackup_runs`: adds `engine`, `dest_type`, `dest_bucket/prefix`, `dest_local_path`, `stats_json`, `progress_json`, `log_ref`, `policy_snapshot`, `worker_host`, plus `agent_id`.
- `s3_cloudbackup_run_logs`: structured run log stream.
- `s3_cloudbackup_run_events`: vendor-agnostic event feed.
- `s3_cloudbackup_run_commands`: queued commands for agents (`cancel`, `maintenance_quick/full`, `restore`, `mount`, `nas_mount`, `nas_unmount`).
- Settings: `cloudbackup_agent_s3_endpoint`, optional `cloudbackup_agent_s3_region`.

## Agent-server contract (sync + Kopia)
- Run assignment: `agent_next_run.php` filters queued runs for that agent (run.agent_id/job.agent_id), claims as `starting`, returns engine, source/dest, policy/retention/schedule JSON, decrypted access keys, endpoint/region.
- Progress/events: `agent_update_run.php` (progress snapshot, log_ref, stats/progress JSON), `agent_push_events.php` (structured events).
- Commands: server enqueues in `s3_cloudbackup_run_commands`; agent polls `agent_poll_pending_commands.php`; completion via `agent_complete_command.php`. Supported: cancel, maintenance_quick/full, restore, nas_mount, nas_unmount.
- Start (manual/UI): `cloudbackup_start_run.php` → `CloudBackupController::startRun()` inserts queued run, binds to agent for `local_agent`, optionally stores engine/dest fields; workers should ignore local_agent runs.
- Reclaim/watchdog: reclaim stale in-progress for same agent within grace; watchdog cron fails truly stale runs.

## Heartbeats, watchdog, and resume
- Heartbeats: `agent_update_run.php` updates `updated_at`; agents should POST every ≤60–90s.
- Watchdog: `crons/agent_watchdog.php` fails stale `starting/running` runs past `AGENT_WATCHDOG_TIMEOUT_SECONDS` (default 720s) with `AGENT_OFFLINE`.
- Reclaim: `agent_next_run.php` can return the same agent's in-progress run if heartbeat is older than `AGENT_RECLAIM_GRACE_SECONDS` (default 180s) but before watchdog cutoff.
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
- Default poll interval: **5 seconds** (configurable via `PollIntervalSecs` in config).

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

## Kopia Progress Reporting (WIP)
- Added Kopia upload progress hooks in the agent to report bytes transferred, objects, speed, ETA, and current item via `agent_update_run` and `agent_push_events` for the live activity page.
- Includes a lightweight pre-scan to estimate total size/files so UI denominators populate early.
- Status: not fully validated; further development and end-to-end testing are required before relying on this data in production.
- Known caveats: ETA/speed may be noisy on small files; pre-scan may be slow on very large trees and is currently best-effort; ensure watchdog/heartbeat timings are respected during long uploads.

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

## Outstanding items / gotchas

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

