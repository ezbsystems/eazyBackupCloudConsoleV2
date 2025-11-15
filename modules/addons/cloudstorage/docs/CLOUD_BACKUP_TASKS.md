# Cloud-to-Cloud Backup Feature - Task List

This document tracks the implementation tasks for the Cloud-to-Cloud Backup feature across all phases.

## Phase 1: MVP Foundation

### Database Schema
- [x] Create `s3_cloudbackup_jobs` table
- [x] Create `s3_cloudbackup_runs` table
- [x] Create `s3_cloudbackup_settings` table
- [x] Add `notified_at` column to `s3_cloudbackup_runs`
- [x] Add foreign key constraints
- [x] Add indexes for performance

### Module Configuration
- [x] Add `cloudbackup_enabled` config field
- [x] Add `cloudbackup_worker_host` config field
- [x] Add `cloudbackup_global_max_concurrent_jobs` config field
- [x] Add `cloudbackup_global_max_bandwidth_kbps` config field
- [x] Add `cloudbackup_encryption_key` config field
- [x] Add `cloudbackup_email_template` dropdown config field
- [x] Create `cloudstorage_get_email_templates()` function

### Client Controllers
- [x] Create `CloudBackupController.php`
- [x] Implement `getJobsForClient()` method
- [x] Implement `getJob()` method
- [x] Implement `createJob()` method
- [x] Implement `updateJob()` method
- [x] Implement `deleteJob()` method
- [x] Implement `getRunsForJob()` method
- [x] Implement `getRun()` method
- [x] Implement `startRun()` method
- [x] Implement `cancelRun()` method
- [x] Implement `decryptSourceConfig()` method
- [x] Implement `sendRunNotification()` method

### Client Area Routing
- [x] Add `cloudbackup` route to `cloudstorage_clientarea()`
- [x] Add `cloudbackup_jobs` sub-view
- [x] Add `cloudbackup_runs` sub-view
- [x] Add `cloudbackup_live` sub-view
- [x] Add `cloudbackup_settings` sub-view

### Client Area Pages
- [x] Create `pages/cloudbackup_jobs.php`
- [x] Create `pages/cloudbackup_runs.php`
- [x] Create `pages/cloudbackup_live.php`
- [x] Create `pages/cloudbackup_settings.php`

### Client Area Templates
- [x] Create `templates/cloudbackup_jobs.tpl`
- [x] Create `templates/cloudbackup_runs.tpl`
- [x] Create `templates/cloudbackup_live.tpl`
- [x] Create `templates/cloudbackup_settings.tpl`
- [x] Implement job creation wizard (S3-compatible and SFTP)
- [x] Implement job list display
- [x] Implement run history table
- [x] Implement live progress UI
- [x] Implement settings form

### AJAX API Endpoints
- [x] Create `api/cloudbackup_create_job.php`
- [x] Create `api/cloudbackup_update_job.php`
- [x] Create `api/cloudbackup_delete_job.php`
- [x] Create `api/cloudbackup_start_run.php`
- [x] Create `api/cloudbackup_cancel_run.php`
- [x] Create `api/cloudbackup_progress.php`

### Admin Area Integration
- [x] Create `lib/Admin/CloudBackupAdminController.php`
- [x] Create `pages/admin/cloudbackup_admin.php`
- [x] Create `templates/admin/cloudbackup_admin.tpl`
- [x] Add admin route to `cloudstorage_output()`
- [x] Add sidebar link in `cloudstorage_sidebar()`
- [x] Implement global job/run overview
- [x] Implement filtering by client/status
- [x] Implement force cancel functionality

### Email Notifications
- [x] Create `CloudBackupEmailService.php`
- [x] Implement template name resolution
- [x] Implement email list parsing
- [x] Implement notification logic (respect job/client settings)
- [x] Implement merge variable preparation
- [x] Create `accounts/crons/s3cloudbackup_notify.php` cron job
- [x] Implement notification sending via WHMCS `localAPI('SendEmail')`
- [x] Add `notified_at` tracking to prevent duplicates

### Source Types (Phase 1)
- [x] S3-compatible storage wizard
- [x] AWS S3 storage wizard (UI and API implemented)
- [x] SFTP/SSH server wizard
- [x] Source config encryption/decryption

### Scheduling (Phase 1)
- [x] Manual trigger
- [x] Daily schedule
- [x] Weekly schedule
- [x] Schedule time selection
- [x] Weekday selection for weekly

### Security
- [x] Source config encryption using module encryption key
- [x] Access control verification (client_id, s3_user_id)
- [x] Ownership validation on all operations

## Phase 2: Polish & Live Features

### Live Progress Updates
- [ ] Worker service implements JSON log parsing from rclone `--use-json-log`
- [ ] Worker updates `s3_cloudbackup_runs` progress fields every 5-10 seconds
- [ ] Worker checks `cancel_requested` flag periodically
- [ ] Worker sends SIGTERM to rclone on cancellation
- [x] Client live view displays real-time progress updates (UI implemented)
- [x] Progress bar updates smoothly with animations (UI implemented)
- [x] Speed and ETA calculations display correctly (UI implemented)
- [x] Enhanced progress page animations and visual design (shimmer effect, gradients)
- [x] Progress bar fills smoothly with CSS transitions
- [x] Polling interval set to 2 seconds for frequent updates
- [x] Status badge redesigned with glowing dot and pulsing animation
- [x] Decimal formatting fixed to 2 decimal places for all values

### Retention Policies
- [x] Create retention cleanup cron job (`accounts/crons/s3cloudbackup_retention.php`)
- [x] Implement `keep_last_n` retention mode (`CloudBackupController::applyRetentionPolicy()`)
- [x] Implement `keep_days` retention mode (`CloudBackupController::applyRetentionPolicy()`)
- [x] Delete old backup data from destination bucket (using S3 API)
- [x] Update run records to mark pruned data
- [x] Add retention status to UI (job list and creation/edit forms)

### Validation
- [ ] Implement post-run `rclone check` option (worker-side)
- [x] Store validation status in `s3_cloudbackup_runs` (database schema ready)
- [x] Store validation log excerpt (database schema ready)
- [x] Display validation status in run history UI
- [x] Add validation toggle to job creation wizard
- [x] Add validation_mode field to jobs table (migration added)

### Bandwidth & Concurrency
- [ ] Worker respects `GLOBAL_MAX_CONCURRENT_JOBS` limit (worker-side)
- [ ] Worker applies `--bwlimit` based on `GLOBAL_MAX_BANDWIDTH_KBPS` (worker-side)
- [ ] Worker reads limits from addon config or DB (worker-side)
- [x] Add per-client concurrency limits (`per_client_max_concurrent_jobs` column in settings)
- [x] Display concurrency/bandwidth status in admin view (metrics dashboard)
- [x] Add per-client concurrency limit UI to settings page

### Admin Tools Enhancement
- [x] Add richer filtering options (date range, job name search)
- [x] Implement force stop functionality for running runs
- [x] Add deeper log inspection for support (log viewer modal)
- [x] Add export functionality for job/run data (CSV export)
- [x] Add metrics dashboard (total jobs, active jobs, running jobs, success rate)

### Client Area Log Display
- [x] Create log formatting service (`CloudBackupLogFormatter.php`)
- [x] Convert raw rclone JSON logs to user-friendly format
- [x] Create API endpoint for fetching formatted logs (`cloudbackup_get_run_logs.php`)
- [x] Update client area template to display formatted logs in modal
- [x] Format validation logs for user-friendly display

## Phase 3: Advanced Features

### Archive Mode
- [x] Implement `backup_mode='archive'` handling (database and API ready)
- [ ] Worker creates tar+compressed stream for archive jobs (worker-side)
- [ ] Upload single archive file per run to destination (worker-side)
- [ ] Update runner to detect archive mode (worker-side)
- [ ] Use different rclone strategy for archives (worker-side)
- [x] Add archive mode option to job wizard (UI implemented)

### Encryption (Crypt Backend)
- [x] Add `encryption_enabled` flag handling in job creation (API and UI implemented)
- [ ] Worker generates crypt remote config wrapping e3 destination (worker-side)
- [ ] Store crypt passwords encrypted in DB (separate from source config) (worker-side)
- [ ] Only worker decrypts; no client exposure (worker-side)
- [x] Add encryption toggle to job wizard (UI implemented)
- [ ] Implement restore flows (provider-only, portal-based) (future)

### Additional Source Types
- [ ] Add Google Drive wizard (`google_drive` source type)
- [ ] Add Dropbox wizard (`dropbox` source type)
- [ ] Add SMB/NAS wizard (`smb` source type)
- [ ] Update job wizard step 1 to include new source types
- [ ] Update source config structure for each new type
- [ ] Test each source type with rclone

### Admin Overview & Throttling
- [x] Add per-client concurrency controls UI (settings page)
- [ ] Implement per-client bandwidth limits (future enhancement)
- [x] Create high-level metrics dashboard (admin area)
- [x] Add job success rate tracking (24h success rate)
- [ ] Add transfer volume statistics (future enhancement)
- [ ] Add alerting for failed jobs (future enhancement)

## Phase 4: Ephemeral Sandbox Per Job (Future)

### Sandbox Architecture
- [ ] Design sandbox execution model
- [ ] Choose containerization method (systemd-nspawn, Docker, LXC)
- [ ] Implement sandbox spawner in worker service
- [ ] Configure filesystem isolation
- [ ] Configure network scope limitations
- [ ] Add resource limits per sandbox

### Worker Backend Options
- [ ] Implement `local_process` backend (current behavior)
- [ ] Implement `sandboxed_process` backend
- [ ] Add backend selection per job (optional)
- [ ] Add sandbox configuration to job schema
- [ ] Update worker to support both backends

### Benefits Implementation
- [ ] Reduce blast radius for rclone errors
- [ ] Implement stricter per-job resource limits
- [ ] Add sandbox status tracking
- [ ] Add sandbox logs isolation
- [ ] Document sandbox deployment

## Worker VM Service (Separate Project)

### Service Structure
- [ ] Create Go/Python service structure
- [ ] Implement config loading (`internal/config/config.go`)
- [ ] Implement database layer (`internal/db/db.go`)
- [ ] Implement scheduler (`internal/jobs/scheduler.go`)
- [ ] Implement runner (`internal/jobs/runner.go`)
- [ ] Implement rclone config builder (`internal/rclone/rclone.go`)
- [ ] Implement log tailer (`internal/logs/tail.go`)

### Database Functions
- [ ] `GetNextQueuedRuns(limit int)` - Fetch queued runs
- [ ] `UpdateRunStatus(runID, status, timestamps)` - Update state
- [ ] `UpdateRunProgress(runID, progress fields)` - Update metrics
- [ ] `MarkRunCancelled(runID, reason)` - Handle cancellation
- [ ] `GetJobConfig(jobID)` - Load job with decrypted config
- [ ] `CheckCancelRequested(runID)` - Poll cancellation flag

### Rclone Integration
- [ ] `BuildSourceRemote(job)` - Create source remote config
- [ ] `BuildDestRemote(job, bucket)` - Create e3 destination config
- [ ] `BuildCryptRemote(job, destRemote)` - Wrap with crypt if enabled
- [ ] `GenerateRcloneConfig(job)` - Write complete config file
- [ ] `BuildSyncCommand(job, configPath)` - Construct rclone command

### Runner Execution Flow
- [ ] Create working directory per run
- [ ] Generate rclone config file
- [ ] Start rclone process with JSON logging
- [ ] Tail log file and parse progress
- [ ] Update database every 5-10 seconds
- [ ] Check cancellation flag periodically
- [ ] Handle completion (parse exit code, update status)
- [ ] Run validation if enabled
- [ ] Write log excerpt to database

### Deployment
- [ ] Set up dedicated VM on Proxmox
- [ ] Install rclone binary
- [ ] Configure systemd service
- [ ] Set up log rotation
- [ ] Configure database connection
- [ ] Set up monitoring/alerting
- [ ] Document deployment process

## Testing Checklist

### Phase 1 Testing
- [ ] Job creation wizard validates all fields
- [ ] S3-compatible source configuration works
- [ ] SFTP source configuration works
- [ ] Job scheduling (manual/daily/weekly) works
- [ ] Job CRUD operations work correctly
- [ ] Run creation and status updates work
- [ ] Email notifications send correctly
- [ ] Admin view displays all jobs/runs
- [ ] Access control prevents unauthorized access

### Phase 2 Testing
- [ ] Live progress updates appear in real-time
- [ ] Cancellation stops running jobs
- [ ] Retention policies prune old backups correctly
- [ ] Validation runs post-sync when enabled
- [ ] Bandwidth limits are respected
- [ ] Concurrency limits prevent overloading

### Phase 3 Testing
- [ ] Archive mode creates compressed archives
- [ ] Crypt encryption works end-to-end
- [ ] Google Drive source works
- [ ] Dropbox source works
- [ ] SMB/NAS source works
- [ ] Restore flows work correctly

### Phase 4 Testing
- [ ] Sandboxed execution isolates jobs
- [ ] Resource limits are enforced
- [ ] Sandbox cleanup works correctly

## Documentation

- [x] Create main documentation (`CLOUD_BACKUP.md`)
- [x] Create task list (`CLOUD_BACKUP_TASKS.md`)
- [ ] Create worker VM deployment guide
- [ ] Create email template setup guide
- [ ] Create troubleshooting guide
- [ ] Create API reference documentation

## Notes

- Phase 1 MVP is complete and ready for worker VM integration
- Worker VM service is a separate project and should be developed independently
- Email notifications are functional but require email template creation
- All database migrations are handled in `cloudstorage_activate()` and `cloudstorage_upgrade()`

