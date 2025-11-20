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
│       ├── CloudBackupEmailService.php # Email notification service
│       └── CloudBackupLogFormatter.php # Log formatting service
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
│   └── cloudbackup_get_run_logs.php   # Get formatted run logs endpoint
└── docs/
    ├── CLOUD_BACKUP.md                # This file
    └── CLOUD_BACKUP_TASKS.md          # Phase task list
```

### Cron Jobs

```
accounts/crons/
├── s3cloudbackup_notify.php          # Email notification cron
└── s3cloudbackup_retention.php       # Retention policy cleanup cron
```

## Module Configuration

### Addon Settings

Located in `cloudstorage_config()`:

- **`cloudbackup_enabled`** (yesno) - Enable/disable feature
- **`cloudbackup_worker_host`** (text) - Worker VM hostname identifier
- **`cloudbackup_global_max_concurrent_jobs`** (text) - Max concurrent jobs globally
- **`cloudbackup_global_max_bandwidth_kbps`** (text) - Global bandwidth limit in KB/s
- **`cloudbackup_encryption_key`** (password) - Optional separate encryption key
- **`cloudbackup_email_template`** (dropdown) - WHMCS email template for notifications

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

### `api/cloudbackup_get_run_logs.php`
**Method**: GET  
**Parameters**: `run_id`  
**Returns**: JSON with formatted backup and validation logs (user-friendly format)

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
- `{$dest_bucket_id}`, `{$dest_prefix}`
- `{$started_at}`, `{$finished_at}`, `{$duration}`
- `{$bytes_transferred}`, `{$bytes_transferred_formatted}`
- `{$bytes_total}`, `{$bytes_total_formatted}`
- `{$progress_pct}`, `{$error_summary}`
- `{$client_id}`, `{$client_name}`, `{$client_email}`

### Cron Jobs

#### Email Notification Cron
`accounts/crons/s3cloudbackup_notify.php` runs every 5-10 minutes to:
- Find completed runs from last hour
- Send notifications based on settings
- Mark runs as notified

#### Retention Policy Cleanup Cron
`accounts/crons/s3cloudbackup_retention.php` runs daily or every few hours to:
- Find all active jobs with retention policies enabled (`keep_last_n` or `keep_days`)
- Apply retention policies using `CloudBackupController::applyRetentionPolicy()`
- Delete old backup data from destination buckets
- Update run records

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

## Retention Policies

Retention policies automatically clean up old backup data based on job configuration.

### Retention Modes

#### `keep_last_n`
Keeps only the N most recent successful backup runs. Older runs are automatically deleted from the destination bucket.

#### `keep_days`
Keeps backup data for N days. Data older than the specified number of days is automatically deleted.

### Implementation
- Retention cleanup is performed by the `s3cloudbackup_retention.php` cron job
- Uses `CloudBackupController::applyRetentionPolicy()` method
- Deletes objects from destination bucket using S3 API
- Supports batch deletion (up to 1000 objects per request)
- Handles both run-based prefixes (`run_<id>/`) and date-based prefixes (`YYYY-MM-DD/`)

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

### Overview

The Cloud Backup system automatically converts raw rclone JSON logs into user-friendly, readable format for end users. This transformation is handled by the `CloudBackupLogFormatter` class located at `lib/Client/CloudBackupLogFormatter.php`. The formatter processes technical rclone output and presents it in a clear, organized format that non-technical users can easily understand.

### Implementation

**Class**: `WHMCS\Module\Addon\CloudStorage\Client\CloudBackupLogFormatter`

**Key Methods**:
- `formatRcloneLogs($rawLogJson)` - Formats backup operation logs
- `formatValidationLogs($rawLogJson)` - Formats validation check logs
- `formatLogMessage($msg, $level, $entry)` - Converts individual log messages
- `formatValidationMessage($msg, $level, $entry)` - Converts validation messages
- `formatStatsSummary($stats, $nothingToTransfer)` - Formats statistics summary
- `formatTimestamp($timestamp)` - Converts ISO 8601 timestamps to readable format
- `formatDuration($seconds)` - Converts seconds to human-readable duration

### Log Parsing and Handling

The formatter handles multiple log storage formats:

1. **JSON Array Format**: Logs stored as a JSON array of log entries
2. **Double-Encoded JSON**: Handles cases where log entries are stored as an array of JSON strings (double-encoded)
3. **Line-by-Line JSON**: Parses logs stored as newline-separated JSON objects
4. **Plain Text Fallback**: Treats non-JSON lines as plain text messages

**Error Handling**:
- Gracefully handles empty or null log data
- Returns user-friendly error messages for unparseable logs
- Continues processing even if individual entries fail to parse

### Backup Log Formatting Features

#### Message Translation

The formatter converts technical rclone messages to user-friendly language:

- **Starting Operations**: "Starting sync" → "🔄 Starting backup process"
- **Progress Updates**: "Transferred: 10 / 100" → "📤 Transferred: 10 of 100 files"
- **Completion**: "Completed sync" → "✅ Backup completed successfully"
- **No Changes**: "nothing to transfer" → "✅ No files to transfer - source and destination are synchronized"
- **Errors**: Technical error messages → Clear explanations with context

#### Error Message Enhancement

Common error types are automatically enhanced with helpful context:

- **Permission Errors**: Explains that source credentials need read access
- **File Not Found**: Clarifies that the file doesn't exist at source location
- **Connection Errors**: Provides network troubleshooting guidance
- **Authentication Errors**: Suggests verifying access keys and permissions

#### Section Organization

Logs are automatically organized into logical sections:

- **🚀 Starting Backup**: Initial backup process start
- **📤 Transferring Files**: Active file transfer operations
- **✅ Completing Backup**: Backup completion and summary
- **📊 Backup Summary**: Final statistics (data transferred, files processed, speed, duration)
- **❌ Errors Encountered**: Any errors that occurred during backup
- **⚠️ Warnings**: Non-critical warnings

#### Statistics Summary

The formatter extracts and formats statistics from rclone log entries:

- **Data Transferred**: Shows bytes transferred vs total, formatted in human-readable units (MiB, GiB, etc.)
- **Progress Percentage**: Calculates and displays completion percentage
- **Files Processed**: Shows number of files transferred vs total files
- **Average Speed**: Displays transfer speed in human-readable format
- **Duration**: Converts elapsed time to readable format (hours, minutes, seconds)

#### Special Handling: No Files to Transfer

When a backup completes with no files to transfer (source and destination are synchronized), the formatter:

- Detects this condition from log messages and statistics
- Displays a special "✅ Backup Completed - No Changes Needed" section
- Explains that synchronization is already complete
- Suppresses confusing "0 files transferred" messages

### Validation Log Formatting

Validation logs use a similar formatting approach but focus on data integrity verification:

**Features**:
- Clear indication of validation start
- File-by-file verification status
- Mismatch detection with clear explanations
- Final validation result summary (success or issues found)

**Message Translation**:
- "Starting check" → "🔍 Starting validation check"
- "mismatch" → "❌ Data mismatch detected"
- "identical" → "✅ Files are identical"
- "OK" → "✅ Verified"

**Output Structure**:
```
═══════════════════════════════════════════════════════════
VALIDATION CHECK DETAILS
═══════════════════════════════════════════════════════════

[2024-01-15 10:35:00] 🔍 Starting validation check
[2024-01-15 10:35:15] ✅ Files are identical

✅ Validation completed successfully - all files verified.
```

### API Integration

**Endpoint**: `api/cloudbackup_get_run_logs.php`

**Method**: GET

**Parameters**:
- `run_id` (required) - The backup run ID

**Authentication**: Requires WHMCS client authentication and verifies run ownership

**Response Format**:
```json
{
  "status": "success",
  "backup_log": "Formatted backup log text...",
  "validation_log": "Formatted validation log text...",
  "has_validation": true
}
```

**Usage Flow**:
1. Client requests logs for a specific run via API endpoint
2. System verifies authentication and run ownership
3. Formatter processes raw log data from database (`log_excerpt` and `validation_log_excerpt` fields)
4. Returns formatted logs as plain text strings in JSON response
5. Frontend displays formatted logs in UI

### Example Formatted Output

**Backup Log Example**:
```
═══════════════════════════════════════════════════════════
BACKUP RUN DETAILS
Started: 2024-01-15 10:30:00
═══════════════════════════════════════════════════════════

📋 Backup Process

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 Starting Backup
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[2024-01-15 10:30:00] 🔄 Starting backup process

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📤 Transferring Files
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[2024-01-15 10:30:05] 📤 Transferred: 10 of 100 files
[2024-01-15 10:30:10] 📤 Transferred: 25 of 100 files
[2024-01-15 10:30:15] 📤 Transferred: 50 of 100 files

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Backup Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Data Transferred: 10.00 MiB of 100.00 MiB
Progress: 10%
Files Processed: 10 of 100
Average Speed: 2.50 MiB/s
Duration: 5 minutes 0 seconds
```

**No Files to Transfer Example**:
```
═══════════════════════════════════════════════════════════
BACKUP RUN DETAILS
Started: 2024-01-15 10:30:00
═══════════════════════════════════════════════════════════

📋 Backup Process

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Backup Completed - No Changes Needed
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

The backup process completed successfully, but there were no files to transfer.
This means your source and destination are already synchronized - all files are up to date.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Backup Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Status: ✅ No files to transfer
Reason: Source and destination are already synchronized

ℹ️  No files were transferred because source and destination are already synchronized.
```

### Technical Details

**Dependencies**:
- Uses `HelperController::formatSizeUnits()` for byte formatting
- Relies on PHP `DateTime` class for timestamp parsing
- Handles ISO 8601 timestamp format from rclone logs

**Performance Considerations**:
- Processes logs synchronously during API request
- Handles large log files efficiently by processing entries sequentially
- Caches formatted output is not implemented (formats on each request)

**Edge Cases Handled**:
- Empty or null log data
- Malformed JSON entries
- Missing timestamp or level information
- Statistics with zero values
- Logs with no transfer activity
- Validation logs without validation data

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

