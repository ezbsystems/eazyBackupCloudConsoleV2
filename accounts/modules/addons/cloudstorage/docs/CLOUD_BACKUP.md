# Cloud-to-Cloud Backup Feature Documentation

## Overview

The Cloud-to-Cloud Backup feature extends the existing Cloud Storage WHMCS addon module, enabling customers to schedule and run backups from external sources (S3-compatible storage, SFTP servers) to their e3 buckets. The system uses rclone for data transfer and operates with a control plane (WHMCS addon) for job management and a data plane (dedicated worker VM) for executing backup operations.

## Architecture

### Control Plane (WHMCS Addon)
- **Location**: `accounts/modules/addons/cloudstorage/`
- **Responsibilities**:
  - Job definition and management
  - Client/Admin UIs
  - Schedule management
  - Notification configuration
  - Progress tracking (reads from database)

### Data Plane (Worker VM)
- **Location**: Separate VM (e.g., `/opt/e3-cloudbackup-worker/`)
- **Responsibilities**:
  - Polls database for queued jobs
  - Executes rclone operations
  - Updates progress in real-time
  - Handles cancellation requests
  - Manages rclone configuration

## Database Schema

### Tables

#### `s3_cloudbackup_jobs`
Stores backup job definitions.

**Key Fields**:
- `id` - Primary key
- `client_id` - WHMCS client ID (FK to `tblclients.id`)
- `s3_user_id` - S3 user ID (FK to `s3_users.id`)
- `name` - Job display name
- `source_type` - Enum: `s3_compatible`, `sftp`, `google_drive`, `dropbox`, `smb`, `nas`
- `source_display_name` - User-friendly source name
- `source_config_enc` - Encrypted JSON blob containing source credentials
- `source_path` - Path/bucket on source
- `dest_bucket_id` - Destination bucket ID (FK to `s3_buckets.id`)
- `dest_prefix` - Destination prefix/path
- `backup_mode` - Enum: `sync`, `archive`
- `encryption_enabled` - Boolean (0/1) - Enable rclone crypt backend encryption
- `compression_enabled` - Boolean (0/1) - Reserved for future use
- `validation_mode` - Enum: `none`, `post_run` - Enable post-run rclone check validation
- `schedule_type` - Enum: `manual`, `daily`, `weekly`, `cron`
- `schedule_time` - Time for daily/weekly schedules
- `schedule_weekday` - Weekday (1-7) for weekly schedules
- `retention_mode` - Enum: `none`, `keep_last_n`, `keep_days`
- `retention_value` - Retention value (N)
- `notify_override_email` - Job-specific email override
- `notify_on_success` - Boolean
- `notify_on_warning` - Boolean
- `notify_on_failure` - Boolean
- `status` - Enum: `active`, `paused`, `deleted`

#### `s3_cloudbackup_runs`
Tracks individual execution records.

**Key Fields**:
- `id` - Primary key (BIGINT)
- `job_id` - FK to `s3_cloudbackup_jobs.id`
- `trigger_type` - Enum: `manual`, `schedule`, `validation`
- `status` - Enum: `queued`, `starting`, `running`, `success`, `warning`, `failed`, `cancelled`
- `started_at` - Timestamp
- `finished_at` - Timestamp
- `notified_at` - Timestamp (when email notification was sent)
- `progress_pct` - Decimal (0.00-100.00)
- `bytes_total` - Total bytes to transfer
- `bytes_transferred` - Bytes transferred so far
- `objects_total` - Total objects
- `objects_transferred` - Objects transferred so far
- `speed_bytes_per_sec` - Current transfer speed
- `eta_seconds` - Estimated time remaining
- `current_item` - Current file/path being processed
- `log_path` - Path to full log file on worker VM
- `log_excerpt` - Last N lines of log (for UI display)
- `error_summary` - Error message if failed
- `worker_host` - Worker VM hostname
- `cancel_requested` - Boolean flag for cancellation
- `validation_mode` - Enum: `none`, `post_run`
- `validation_status` - Enum: `not_run`, `running`, `success`, `failed`
- `validation_log_excerpt` - Validation log excerpt

#### `s3_cloudbackup_run_events`
Stores sanitized, structured, customer‑facing events for each run. This table powers all client‑visible logs.

**Key Fields**:
- `id` - Primary key (BIGINT)
- `run_id` - FK to `s3_cloudbackup_runs.id`
- `ts` - Event timestamp (microsecond precision)
- `type` - High‑level type (`start`, `progress`, `summary`, `error`, `warning`, `cancelled`, etc.)
- `level` - Severity (`info`, `warn`, `error`)
- `code` - Stable code category (`NO_CHANGES`, `ERROR_NETWORK`, `COMPLETED_SUCCESS`, …)
- `message_id` - Message template key (looked up in PHP formatter)
- `params_json` - JSON parameters for message interpolation (bytes, speed, pct, eta, etc.)
- Foreign key: cascades on run delete

A daily cron prunes events beyond the configured retention days (see “Cron Jobs”).

#### `s3_cloudbackup_settings`
Per-client notification defaults.

**Key Fields**:
- `id` - Primary key
- `client_id` - Unique client ID (FK to `tblclients.id`)
- `default_notify_emails` - Comma-separated or JSON array
- `default_notify_on_success` - Boolean
- `default_notify_on_warning` - Boolean
- `default_notify_on_failure` - Boolean
- `default_timezone` - Timezone string
- `per_client_max_concurrent_jobs` - Integer (nullable) - Per-client concurrency limit override

## File Structure

### Core Module Files

```
accounts/modules/addons/cloudstorage/
├── cloudstorage.php                    # Main module file (config, routing, schema)
├── lib/
│   └── Client/
│       ├── CloudBackupController.php   # Job/run CRUD operations
│       ├── CloudBackupEventFormatter.php # Event → user-facing message formatter (sanitized)
│       ├── CloudBackupEmailService.php # Email notification service
│       └── CloudBackupLogFormatter.php # Legacy rclone log formatter (fallback/transition)
├── pages/
│   ├── cloudbackup_jobs.php           # Job list page
│   ├── cloudbackup_runs.php           # Run history page
│   ├── cloudbackup_live.php           # Live progress page
│   ├── cloudbackup_settings.php       # Client settings page
│   └── admin/
│       └── cloudbackup_admin.php      # Admin overview page
├── templates/
│   ├── cloudbackup_jobs.tpl           # Job list template
│   ├── cloudbackup_runs.tpl           # Run history template
│   ├── cloudbackup_live.tpl           # Live progress template
│   ├── cloudbackup_settings.tpl       # Settings template
│   └── admin/
│       └── cloudbackup_admin.tpl      # Admin template
├── api/
│   ├── cloudbackup_create_job.php     # Create job endpoint
│   ├── cloudbackup_update_job.php     # Update job endpoint
│   ├── cloudbackup_delete_job.php     # Delete job endpoint
│   ├── cloudbackup_start_run.php      # Start run endpoint
│   ├── cloudbackup_cancel_run.php     # Cancel run endpoint
│   ├── cloudbackup_progress.php       # Progress polling endpoint
│   ├── cloudbackup_get_run_events.php # Get sanitized event stream for a run (primary)
│   └── cloudbackup_get_run_logs.php   # Legacy: formatted rclone logs (fallback/admin)
└── docs/
    ├── CLOUD_BACKUP.md                # This file
    └── CLOUD_BACKUP_TASKS.md          # Phase task list
```

### Cron Jobs

```
accounts/crons/
├── s3cloudbackup_notify.php          # Email notification cron
├── s3cloudbackup_events_prune.php    # Prune s3_cloudbackup_run_events by retention
└── s3cloudbackup_retention.php       # Retention policy cleanup cron (Cloud Backup only)
```

**Cloud-only retention path**: The `s3cloudbackup_retention.php` cron and its object-delete/prefix-delete flow (`CloudBackupController::applyRetentionPolicy()`) are **Cloud Backup only**. They do not process Local Agent jobs. Local Agent Kopia retention uses a separate repo-native queue and agent execution path; see `LOCAL_AGENT_OVERVIEW.md` and `KOPIA_RETENTION_ARCHITECTURE.md`.

## Module Configuration

### Addon Settings

Located in `cloudstorage_config()`:

- **`cloudbackup_enabled`** (yesno) - Enable/disable feature
- **`cloudbackup_worker_host`** (text) - Worker VM hostname identifier
- **`cloudbackup_global_max_concurrent_jobs`** (text) - Max concurrent jobs globally
- **`cloudbackup_global_max_bandwidth_kbps`** (text) - Global bandwidth limit in KB/s
- **`cloudbackup_encryption_key`** (password) - Optional separate encryption key
- **`cloudbackup_email_template`** (dropdown) - WHMCS email template for notifications
- **`cloudbackup_event_retention_days`** (text) - Days to retain event logs (default: 60)
- **`cloudbackup_event_max_per_run`** (text) - Max events recorded per run (default: 5000)
- **`cloudbackup_event_progress_interval_seconds`** (text) - Throttle for progress events (default: 2s)
- **`cloudbackup_google_client_id`** (text) - Google OAuth Client ID used for Drive listing API
- **`cloudbackup_google_client_secret`** (password) - Google OAuth Client Secret used for Drive listing API

## Client Area Routes

Access via: `index.php?m=cloudstorage&page=cloudbackup&view=<view>`

**Views**:
- `cloudbackup_jobs` (default) - Job list and creation wizard
- `cloudbackup_runs` - Run history for a specific job
- `cloudbackup_live` - Live progress view for a running job
- `cloudbackup_settings` - Client notification settings

### Job Cards – Button Actions

The default Jobs view (`cloudbackup_jobs`) shows a row of actions on each job card. These actions operate on the job itself; they do not directly manipulate in‑flight runs unless noted.

- Run now
  - Starts a new run immediately, only if the job is `active`.
  - Client: `runJob(jobId)` → `startRun(jobId)` → `api/cloudbackup_start_run.php`.
  - Server: `CloudBackupController::startRun()` inserts a `queued` run only when `job.status === 'active'`. If paused, the start request is rejected.

```2196:2201:accounts/modules/addons/cloudstorage/templates/cloudbackup_jobs.tpl
function runJob(jobId) {
    try {
        return startRun(jobId);
    } catch (e) {
        return Promise.reject(e);
    }
}
```

```344:355:accounts/modules/addons/cloudstorage/lib/Client/CloudBackupController.php
public static function startRun($jobId, $clientId, $triggerType = 'manual')
{
    // Verify job ownership and status
    $job = self::getJob($jobId, $clientId);
    if (!$job) {
        return ['status' => 'fail', 'message' => 'Job not found or access denied'];
    }
    if ($job['status'] !== 'active') {
        return ['status' => 'fail', 'message' => 'Job is not active'];
    }
    // … enqueue run as queued …
}
```

- Edit
  - Opens the slide‑over editor for the job where you can adjust source, destination, mode, schedule, retention, etc. Saves via `api/cloudbackup_update_job.php`.

```1747:1754:accounts/modules/addons/cloudstorage/templates/cloudbackup_jobs.tpl
function editJob(jobId) {
    ensureEditPanel();
    openEditSlideover(jobId);
}
```

- Pause / Resume
  - Toggles the job’s `status` between `active` and `paused`. A paused job is excluded from “Run now” and from the scheduler. It does not stop an already running job.
  - Client: `toggleJobStatus(jobId, currentStatus)` → `api/cloudbackup_update_job.php` with `status=paused|active`.
  - Server: `cloudbackup_update_job.php` persists the `status`; `startRun` enforces that only `active` jobs may run.

```1707:1715:accounts/modules/addons/cloudstorage/templates/cloudbackup_jobs.tpl
function toggleJobStatus(jobId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'paused' : 'active';
    fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams([['job_id', jobId], ['status', newStatus]])
    })
```

```147:150:accounts/modules/addons/cloudstorage/api/cloudbackup_update_job.php
if (isset($_POST['status'])) {
    $updateData['status'] = $_POST['status'];
}
```

- Trash (Delete)
  - Soft‑deletes the job (marks it as `deleted`) via `api/cloudbackup_delete_job.php`. Historical runs remain for audit/history; the job no longer appears in the default list.

- View logs
  - Quick link to the Run History / logs for the job (`cloudbackup_runs` view). From there you can open a specific run’s details or navigate to the Live view for an active run.

> Stopping a running job: Cancelling an in‑flight run is performed from the Live view (`cloudbackup_live`) via the “Cancel Run” button, which calls `api/cloudbackup_cancel_run.php` and sets `cancel_requested=1` for the active run.

```371:401:accounts/modules/addons/cloudstorage/lib/Client/CloudBackupController.php
public static function cancelRun($runId, $clientId)
{
    // … verify ownership …
    if (!in_array($run['status'], ['queued', 'starting', 'running'])) {
        return ['status' => 'fail', 'message' => 'Run cannot be cancelled in current status'];
    }
    Capsule::table('s3_cloudbackup_runs')
        ->where('id', $runId)
        ->update(['cancel_requested' => 1]);
    return ['status' => 'success'];
}
```

## API Endpoints

All endpoints require WHMCS client authentication and return JSON responses.

### `api/cloudbackup_create_job.php`
**Method**: POST  
**Parameters**:
- `name` - Job name
- `source_type` - Source type (s3_compatible, aws, sftp)
- `source_display_name` - Display name
- `source_config` - JSON string of source configuration
- `source_path` - Source path/bucket
- `dest_bucket_id` - Destination bucket ID
- `dest_prefix` - Destination prefix
- `backup_mode` - Backup mode (sync, archive)
- `encryption_enabled` - Enable encryption (0/1)
- `validation_mode` - Validation mode (none, post_run)
- `retention_mode` - Retention mode (none, keep_last_n, keep_days)
- `retention_value` - Retention value (N)
- `schedule_type` - Schedule type
- `schedule_time` - Time (for daily/weekly)
- `schedule_weekday` - Weekday (for weekly)
- Additional notification fields

### `api/cloudbackup_update_job.php`
**Method**: POST  
**Parameters**: Same as create, plus `job_id`

### `api/cloudbackup_delete_job.php`
**Method**: POST  
**Parameters**: `job_id`

### `api/cloudbackup_start_run.php`
**Method**: POST  
**Parameters**: `job_id`, `trigger_type` (optional)

### `api/cloudbackup_cancel_run.php`
**Method**: POST  
**Parameters**: `run_id`

### `api/cloudbackup_progress.php`
**Method**: GET  
**Parameters**: `run_id`  
**Returns**: JSON with current progress data

### `api/cloudbackup_get_run_events.php`
**Method**: GET  
**Parameters**: `run_id` (required), `since_id` (optional), `limit` (optional; default 250, max 1000)  
**Returns**: JSON array of sanitized, user‑facing events for the run. Preferred for all client‑visible logs.

### `api/cloudbackup_get_run_logs.php`
Legacy endpoint for formatted rclone logs. Used only for older runs without events or for admin diagnostics; the client UI prefers the events endpoint.

## Source Configuration

### S3-Compatible Storage
Encrypted JSON stored in `source_config_enc`:
```json
{
  "endpoint": "https://s3.wasabisys.com",
  "access_key": "...",
  "secret_key": "...",
  "bucket": "my-bucket",
  "region": "us-east-1"
}
```

### SFTP/SSH
Encrypted JSON stored in `source_config_enc`:
```json
{
  "host": "sftp.example.com",
  "port": 22,
  "user": "username",
  "pass": "password"
}
```

### Google Drive (OAuth)
Jobs store only minimal Google Drive configuration in `source_config_enc`:
```json
{
  "root_folder_id": "optional-folder-id"
}
```
The reusable OAuth connection (per client) is stored in `s3_cloudbackup_sources` with an encrypted `refresh_token_enc`. The worker assembles the full rclone Drive configuration at runtime using:
- App credentials (client ID/secret) from environment on the worker
- The decrypted refresh token from the saved source connection
- Optional `root_folder_id` from the job

Scopes: `https://www.googleapis.com/auth/drive.readonly`

#### Google Drive Folder Picker (Client Area)

- Create/Edit Job includes a “Browse Drive” button in the Google Drive source section.
- Opens a slide‑over with:
  - My Drive / Shared Drives toggle
  - Lazy‑loaded folder tree with expansion (fetch children via pagination)
  - Quick search (name contains) within the current folder/scope
  - Single‑select (v1) folder selection
- On confirm:
  - Sets `root_folder_id` to the selected Drive folder ID (not the name)
  - Clears Path so users may optionally set a sub‑path under that root

Control‑plane API: `modules/addons/cloudstorage/api/cloudbackup_gdrive_list.php`
- Validates WHMCS client ownership and active Google Drive connection (`s3_cloudbackup_sources`)
- Exchanges the saved `refresh_token_enc` for an `access_token` using addon settings:
  - `cloudbackup_google_client_id`, `cloudbackup_google_client_secret`
- Calls Google Drive v3:
  - List Shared Drives: `GET /drive/v3/drives` (fields: `drives(id,name)`, pagination)
  - List child folders: `GET /drive/v3/files` with
    - `q = "mimeType='application/vnd.google-apps.folder' and 'PARENT_ID' in parents and trashed=false"`
    - `corpora=user` (My Drive) or `corpora=drive&driveId=...` (Shared Drives)
    - `supportsAllDrives=true&includeItemsFromAllDrives=true`
    - Pagination via `pageToken`; search via `name contains`
- Returns only sanitized fields (`id`, `name`, `parents`, `driveId`); never returns tokens
- Includes basic session rate‑limiting and error logging via `logModuleCall`

Why IDs not names:
- IDs are immutable and unambiguous, avoiding rename/duplication issues and improving reliability.

## Google Drive Backup – Flow, OAuth, and Debugging

### End-to-End Flow (Control → Worker → rclone)

- Control plane inserts a `queued` run in `s3_cloudbackup_runs`.
- Worker picks the run, loads the job and the client’s reusable Drive OAuth connection from `s3_cloudbackup_sources`.
- Worker decrypts the saved `refresh_token_enc` and assembles a Drive `source` remote:
  - `scope = drive.readonly`
  - `client_id` / `client_secret` from environment or addon settings
  - `token` JSON that includes a non‑empty `refresh_token` and a current `access_token`/`expiry`
- Worker writes both config files the runtime may use:
  - `rclone.conf` (canonical)
  - `eazyBackup.conf` (some wrappers use this filename)
- Worker enforces the full token JSON in both files and verifies presence.
- As a backstop, the worker injects remote‑specific env overrides at process start:
  - `RCLONE_CONFIG_SOURCE_CLIENT_ID`, `RCLONE_CONFIG_SOURCE_CLIENT_SECRET`
  - `RCLONE_CONFIG_SOURCE_TOKEN` (JSON with `access_token`, `refresh_token`, `token_type`, `expiry`)
- rclone/eazyBackup runs with `--config <runDir>/eazyBackup.conf` (or `rclone.conf`), performs Drive API token refresh automatically, and starts sync.

### OAuth Authentication Model

- A Drive connection per client is created from the portal and saved in `s3_cloudbackup_sources`:
  - Fields: `provider='google_drive'`, `status='active'`, and `refresh_token_enc` (AES‑256‑CBC).
- Worker decryption cascade for any encrypted blob:
  1. `CLOUD_BACKUP_ENCRYPTION_KEY`
  2. `CLOUD_STORAGE_ENCRYPTION_KEY` (or `ENCRYPTION_KEY`)
  3. Addon settings fallback: `cloudbackup_encryption_key` then `encryption_key`
- At runtime, the worker builds a minimal Drive remote; it does a pre‑refresh against `https://oauth2.googleapis.com/token` using `<client_id, client_secret, refresh_token>` to obtain a short‑lived `access_token` and a concrete `expiry`. This eliminates “no refresh token” and invalid_grant/invalid_client issues early and records diagnostics.

### Backup Encryption Model (data at rest)

- If a job enables `encryption_enabled=1`, the worker wraps the destination remote with rclone’s `crypt` backend. Keys for data encryption are managed on the worker; these are distinct from the AES keys used to encrypt credentials in the DB.
- Credentials and OAuth tokens are always encrypted at rest in MySQL and only decrypted transiently in the worker process memory.

### Implementation Overview (code)

- Worker Drive setup and guards: `e3-cloudbackup-worker/internal/jobs/runner.go`
  - Loads job, decrypts JSON credentials and `refresh_token_enc`
  - Pre‑refreshes Google token; populates `access_token`/`expiry` in token JSON
  - Writes/enforces token into both `rclone.conf` and `eazyBackup.conf`
  - Injects `RCLONE_CONFIG_SOURCE_*` env overrides as a safety net
  - Writes per‑run `diagnostics.json` with key sources and Drive token flags
- rclone config builder: `e3-cloudbackup-worker/internal/rclone/rclone.go`
  - Merges any existing token JSON from job source with the runtime refresh token
  - Ensures required fields exist (`refresh_token`, `token_type`, `access_token`, `expiry`)
- Preflight checks (non‑fatal warnings): `e3-cloudbackup-worker/internal/diag/preflight.go`
  - Warns on missing env keys or addon fallbacks

### Error Codes (worker → `s3_cloudbackup_runs.error_summary`)

- `ERR_GDRIVE_NO_CLIENT_CREDENTIALS`: Missing Google `client_id`/`client_secret` (env or addon).
- `ERR_GDRIVE_NO_REFRESH_TOKEN`: Could not decrypt or find a refresh token; re‑authorize Drive.
- `ERR_GDRIVE_SOURCE_LOOKUP`: Source connection lookup failed for the client.
- `ERR_GDRIVE_ENFORCE_TOKEN`: Worker couldn’t enforce the token JSON into config files.
- `ERR_GDRIVE_REFRESH_PRECHECK`: Pre‑refresh to Google OAuth endpoint failed (invalid client/grant).
- `ERR_DECRYPT_SOURCE_CONFIG`, `ERR_DECRYPT_DEST_ACCESS_KEY`, `ERR_DECRYPT_DEST_SECRET_KEY`: Decryption issues (verify keys/fallbacks).
- `ERR_WRITE_RCLONE_CONFIG`, `ERR_RCLONE_START`: I/O or process startup failures.

### Diagnostics Artifacts (per run)

Each run directory: `/var/lib/e3-cloudbackup/runs/<RUN_ID>` contains:

- `rclone.conf` and/or `eazyBackup.conf` (both include `[source]` with token JSON)
- `diagnostics.json` with fields like:
  - `gdrive.refresh_token_present: true`
  - `gdrive.pre_refresh_ok: true`, `gdrive.pre_refresh_expiry: "<RFC3339>"`
  - `gdrive.token_enforced_in_conf: true`
  - `gdrive.token_enforced_in_alt_conf: "/.../eazyBackup.conf"`
  - `gdrive.env_token_injected: true` (when env overrides are set)
  - `encryptionKeys.*: "<keySourceLabel>"` indicating which key source was used

### Operational Checks and Quick Commands

- Show latest run id and inspect configuration:

```bash
RID=$(ls -1 /var/lib/e3-cloudbackup/runs | sort -n | tail -1)
awk '/^\[source\]/{f=1;print;next} /^\[/{if(f){exit}} f{print}' \
  /var/lib/e3-cloudbackup/runs/$RID/eazyBackup.conf
grep -n '^token' /var/lib/e3-cloudbackup/runs/$RID/eazyBackup.conf
cat /var/lib/e3-cloudbackup/runs/$RID/diagnostics.json
```

- Validate the Google refresh flow end‑to‑end (paste values from the run config):

```bash
curl -s -X POST \
  -d "client_id=<CLIENT_ID>&client_secret=<CLIENT_SECRET>&refresh_token=<REFRESH>&grant_type=refresh_token" \
  https://oauth2.googleapis.com/token
```

- View service and preflight warnings:

```bash
journalctl -u e3-cloudbackup-worker -n 200 | grep PRECHECK || true
journalctl -u e3-cloudbackup-worker -f
```

- Verify the worker binary and helper flag are available:

```bash
/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker -h | grep print-drive-source-run
```

- Print computed Drive `[source]` for a run (ensure service env is loaded first):

```bash
ENVFILE=$(systemctl show -p EnvironmentFile e3-cloudbackup-worker | cut -d= -f2)
set -a; [ -f "$ENVFILE" ] && . "$ENVFILE"; set +a
sudo -u e3backup /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker \
  -config /opt/e3-cloudbackup-worker/config/config.yaml \
  -print-drive-source-run $RID
```

- Check DB has a non‑empty saved refresh token:

```sql
SELECT id, provider, status, LENGTH(refresh_token_enc) AS len
FROM s3_cloudbackup_sources
WHERE client_id=<CLIENT_ID> AND provider='google_drive'
ORDER BY updated_at DESC LIMIT 1;
```

### Common Pitfalls and Resolutions

- **Missing env keys on the worker**:
  - Set `CLOUD_BACKUP_ENCRYPTION_KEY` for decrypting Google refresh tokens.
  - Set `CLOUD_STORAGE_ENCRYPTION_KEY` (or addon `encryption_key`) for dest keys.
  - Set `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` (or configure addon fallbacks).
  - Restart service after edits; confirm with preflight warnings in logs.
- **Wrapper overwrites config**:
  - Worker now writes and enforces both `rclone.conf` and `eazyBackup.conf`.
  - Worker also injects `RCLONE_CONFIG_SOURCE_*` env overrides at process start.
- **invalid_client / invalid_grant during OAuth**:
  - Ensure the refresh token belongs to the same OAuth app (client_id/secret) configured.
  - Re‑authorize Drive connection in the portal if the refresh token is revoked/expired.
- **“token expired and there’s no refresh token”**:
  - Inspect `[source]` token JSON in the actual config file used by the run
  - Verify `diagnostics.json` shows `refresh_token_present: true` and enforcement flags
  - Use the curl test above with the same values to confirm Google accepts the refresh

## Encryption

### Source Credential Encryption
Source credentials are encrypted using AES-256-CBC with the encryption key from module config (`cloudbackup_encryption_key` or fallback to `encryption_key`). The `HelperController::encryptKey()` and `HelperController::decryptKey()` methods handle encryption/decryption.

### Backup Data Encryption (Crypt Backend)
When `encryption_enabled` is set to `1` for a job, the worker VM wraps the destination remote with rclone's crypt backend. This encrypts all backup data at rest. The encryption password is managed by the worker VM and stored separately from source credentials. **Note**: Once encryption is enabled for a job, it cannot be disabled without re-uploading all data.

## Email Notifications

### Configuration
- Admin selects email template from General category in addon settings
- Template stored as template ID in `cloudbackup_email_template` setting

### Notification Logic
1. Check if template is configured
2. Verify job/client notification settings (success/warning/failure toggles)
3. Get recipient emails (job override → client defaults → client email)
4. Build merge variables from run/job data
5. Send email via WHMCS `localAPI('SendEmail')`
6. Mark run as notified (`notified_at` timestamp)

### Merge Variables Available
- `{$job_name}`, `{$job_id}`, `{$run_id}`, `{$run_status}`
- `{$source_display_name}`, `{$source_type}`
- `{$dest_bucket_id}`, `{$dest_bucket_name}`, `{$dest_prefix}`
- `{$started_at}`, `{$finished_at}`, `{$duration}`
- `{$bytes_transferred}`, `{$bytes_transferred_formatted}`
- `{$bytes_total}`, `{$bytes_total_formatted}`
- `{$files_transferred}`, `{$files_total}`
- `{$progress_pct}`, `{$error_summary}`
- `{$client_id}`, `{$client_name}`, `{$client_email}`
- `{$job_log_report}` — Newline‑delimited, sanitized job event report (INFO/WARN/ERROR), e.g.:
- `{$job_log_report_html}` — Same report preformatted with `<br>` tags for HTML templates.

  ```
  INFO[2025-11-21 13:57:03] Starting backup.
  INFO[2025-11-21 13:57:03] Backup completed — no files to transfer.
  INFO[2025-11-21 13:57:04] Backup completed successfully.
  ```

### Cron Jobs

#### Email Notification Cron
`accounts/crons/s3cloudbackup_notify.php` runs every 5-10 minutes to:
- Find completed runs from last hour
- Send notifications based on settings
- Mark runs as notified

#### Retention Policy Cleanup Cron (Cloud Backup only)
`accounts/crons/s3cloudbackup_retention.php` runs daily or every few hours to:
- Find all active **Cloud Backup** jobs with retention policies enabled (`keep_last_n` or `keep_days`)
- Apply retention policies using `CloudBackupController::applyRetentionPolicy()`
- Delete old backup data from destination buckets via object/prefix deletion
- Update run records

This cron and its object-delete path are **cloud-only**. Local Agent jobs (`source_type=local_agent`) and Kopia-family engines are excluded; they use the repo-native retention path instead (see `LOCAL_AGENT_OVERVIEW.md`).

## Worker VM Integration

The worker VM service (separate project) should:

1. **Poll Database**: Query `s3_cloudbackup_runs` for `status='queued'` where job is `active`
2. **Respect Limits**: Check `GLOBAL_MAX_CONCURRENT_JOBS` before starting
3. **Update Progress**: Write to `progress_pct`, `bytes_transferred`, `speed_bytes_per_sec`, `current_item` every 5-10 seconds
4. **Check Cancellation**: Poll `cancel_requested` flag and send SIGTERM to rclone if set
5. **On Completion**: Update `status`, `finished_at`, `log_excerpt`, `error_summary`
6. **Trigger Notification**: Optionally call `CloudBackupController::sendRunNotification()` or let cron handle it

### Rclone Configuration
Worker builds rclone config files per job:
- Source remote (S3/SFTP based on `source_type` and decrypted `source_config_enc`)
- Destination remote (e3 endpoint with bucket/prefix)
- Crypt wrapper (if `encryption_enabled` is true)

For Google Drive sources, the worker now always writes a complete Drive remote with:
- `scope = drive.readonly`
- `client_id`/`client_secret` from worker environment
- `token` JSON containing a valid `refresh_token` (and placeholder `access_token`/`expiry`), allowing rclone to auto‑refresh without manual reconnects.
Optional fields from the job (when provided) are also included:
- `root_folder_id` to speed startup
- `team_drive` for Shared Drives

## Security Considerations

1. **Credential Encryption**: All source credentials encrypted at rest
2. **Access Control**: All operations verify `client_id` and `s3_user_id` ownership
3. **Worker Isolation**: Worker VM should use least-privilege DB user
4. **No Client Exposure**: Clients never see rclone configs or decrypted credentials
5. **Encryption Keys**: Separate encryption key option for backup configs

## Retention Policies (Cloud Backup only)

Retention policies automatically clean up old backup data based on job configuration. **This section applies only to Cloud Backup jobs.** Local Agent Kopia retention uses a separate architecture; see `LOCAL_AGENT_OVERVIEW.md` and `KOPIA_RETENTION_ARCHITECTURE.md`.

### Retention Modes

#### `keep_last_n`
Keeps only the N most recent successful backup runs. Older runs are automatically deleted from the destination bucket.

#### `keep_days`
Keeps backup data for N days. Data older than the specified number of days is automatically deleted.

### Implementation (cloud cron and object-delete path)
- Retention cleanup is performed by the `s3cloudbackup_retention.php` cron job
- Uses `CloudBackupController::applyRetentionPolicy()` method
- Deletes objects from destination bucket using S3 API (object/prefix deletion)
- Supports batch deletion (up to 1000 objects per request)
- Handles both run-based prefixes (`run_<id>/`) and date-based prefixes (`YYYY-MM-DD/`)
- **Scope**: Cloud Backup source types only (e.g. `s3_compatible`, `aws`, `sftp`, `google_drive`). Local Agent and Kopia-family jobs are excluded.

## Validation

Post-run validation uses rclone's `check` command to verify data integrity after backup completion.

### Configuration
- Set `validation_mode` to `post_run` in job configuration
- Worker VM executes `rclone check` after successful backup
- Validation status stored in `s3_cloudbackup_runs.validation_status`
- Validation log excerpt stored in `s3_cloudbackup_runs.validation_log_excerpt`

### Validation Status Values
- `not_run` - Validation not configured or not yet executed
- `running` - Validation in progress
- `success` - Validation passed
- `failed` - Validation failed (data mismatch detected)

## Archive Mode

Archive mode creates compressed archive files instead of syncing individual files.

### Configuration
- Set `backup_mode` to `archive` in job configuration
- Worker VM creates tar+compressed stream for archive jobs
- Uploads single archive file per run to destination
- Uses different rclone strategy than sync mode

**Note**: Archive mode execution is handled by the worker VM service.

## Log Formatting and Display

### Event‑Driven, Sanitized Logging (Client‑Visible)

To avoid exposing sensitive implementation details or raw rclone output, the client area renders only sanitized, structured events:

- Worker
  - `e3-cloudbackup-worker/internal/logs/tail.go` tails `rclone.json` (and `eazyBackup.json` if present), parsing JSON stats and plain text “Transferred … MiB/s, ETA …s” lines.
  - `e3-cloudbackup-worker/internal/jobs/runner.go` updates progress in `s3_cloudbackup_runs` every few seconds and delegates user‑facing log emission to an event emitter.
  - `e3-cloudbackup-worker/internal/jobs/events.go` maps rclone messages to stable codes and message IDs, redacts sensitive details, throttles progress events, and inserts rows into `s3_cloudbackup_run_events`.
  - `e3-cloudbackup-worker/internal/db/db.go` provides `InsertRunEvent` and addon settings access (retention, max per run, progress interval).

- Database
  - `s3_cloudbackup_run_events` holds the sanitized stream: `id`, `ts`, `type`, `level`, `code`, `message_id`, `params_json`.

- API
  - `accounts/modules/addons/cloudstorage/api/cloudbackup_get_run_events.php` authenticates the client, validates run ownership, loads events (optionally incremental via `since_id`), and returns them.

- Formatter (PHP)
  - `accounts/modules/addons/cloudstorage/lib/Client/CloudBackupEventFormatter.php` renders end‑user messages from `message_id` and `params`, using `HelperController::formatSizeUnitsPlain()` for sizes/speeds (no HTML).

- UI
  - Live: `accounts/modules/addons/cloudstorage/templates/cloudbackup_live.tpl` polls `cloudbackup_progress.php` for metrics and `cloudbackup_get_run_events.php` for lines. Only sanitized events are displayed.
  - Run Details modal: `accounts/modules/addons/cloudstorage/templates/cloudbackup_runs.tpl` fetches `cloudbackup_get_run_events.php` and renders the same sanitized event list.
  - Transition helper: `api/cloudbackup_get_live_logs.php` prefers events where available and otherwise returns legacy formatted text for older runs (admin/diagnostics).

#### Safe identifiers and redaction
- Allowed: source/destination bucket names and truncated prefixes.
- Redacted: internal endpoints, credentials, full system paths, and explicit rclone flags.

#### Examples
```
[11:09:52] Starting backup.
[11:10:05] Transferred 250.00 MiB/1.99 GiB (12.56%), 40.00 MiB/s, ETA 43s.
[11:10:40] Backup completed — no files to transfer.
[11:10:53] Backup completed successfully.
```

### Legacy Formatter (Admin/Fallback)

`CloudBackupLogFormatter.php` and `api/cloudbackup_get_run_logs.php` remain available for older runs that predate events and for admin diagnostics. Client pages do not render raw rclone lines.

## Admin Area

Access via: `addonmodules.php?module=cloudstorage&action=cloudbackup_admin`

**Features**:
- **Metrics Dashboard**: Total jobs, active jobs, running jobs vs limit, success rate (24h)
- **Enhanced Filtering**: Filter by client, job name, status, source type, date range
- **Job Management**: View all jobs across all clients with detailed information
- **Run Management**: View all runs with status, progress, and validation status
- **Force Cancel**: Force stop running jobs
- **Log Viewer**: View backup and validation logs for runs
- **CSV Export**: Export run data to CSV format
- **Worker Configuration**: View worker host, concurrency limits, bandwidth limits (read-only)

## Future Enhancements (Phases 2-4)

See `CLOUD_BACKUP_TASKS.md` for detailed phase breakdown.

**Phase 2**: Live progress updates, retention policies, validation, bandwidth controls  
**Phase 3**: Archive mode, encryption (crypt backend), additional source types  
**Phase 4**: Sandboxed execution per job (optional)

## Troubleshooting

### Jobs Not Running
- Check worker VM is running and polling database
- Verify `cloudbackup_enabled` is enabled
- Check job status is `active`
- Verify worker hostname matches config

### Email Notifications Not Sending
- Verify email template is selected in addon settings
- Check job/client notification toggles are enabled
- Verify email addresses are configured
- Check cron job is running: `php accounts/crons/s3cloudbackup_notify.php`
- Review WHMCS Email Log for errors

### Progress Not Updating
- Verify worker is updating database fields
- Check `worker_host` matches configured hostname
- Review worker logs for errors

### Encryption Issues
- Verify encryption key is set in module config
- Check `HelperController` encryption methods are working
- Ensure worker has access to encryption key (if decrypting on worker)

## Related Documentation

- [README.md](../README.md) - Main module documentation
- [CLOUD_BACKUP_TASKS.md](CLOUD_BACKUP_TASKS.md) - Phase task breakdown

