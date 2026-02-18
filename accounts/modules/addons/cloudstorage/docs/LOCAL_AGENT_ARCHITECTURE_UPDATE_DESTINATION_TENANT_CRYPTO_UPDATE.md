# e3 Cloud Backup - Phase 1 and Phase 2 Implementation Summary

## Purpose

This document summarizes what has been implemented for the encryption and destination-isolation overhaul across:

- Phase 1: destination policy and tenant-ownership hardening
- Phase 2: repository-based crypto identity and canonical snapshot propagation

It includes schema/migration changes, new files, updated API routes, and remaining work.

---

## Phase 1 - Completed Updates

### 1) Schema and Migration Guardrails

Implemented in `cloudstorage.php` (activate + upgrade paths), including backfills:

- `s3_cloudbackup_jobs`
  - Added `tenant_id` (indexed)
- `s3_cloudbackup_runs`
  - Added `tenant_id` (indexed)
- `s3_users`
  - Added system-management flags:
    - `is_system_managed`
    - `system_key`
    - `manage_locked`
  - Added indexes for management/lookup guardrails
- New table: `s3_cloudbackup_agent_destinations`
  - Key columns: `agent_id`, `client_id`, `tenant_id`, `s3_user_id`, `dest_bucket_id`, `root_prefix`, `is_locked`
  - Constraints:
    - unique `agent_id`
    - unique `(dest_bucket_id, root_prefix)` to prevent destination collisions
- Backfills:
  - `jobs.tenant_id` from agent mapping (where available)
  - `runs.tenant_id` from jobs snapshot

### 2) Product-Activation Hardening

- `lib/Client/DBController.php`
  - Added strict `getActiveProduct(...)` (checks active WHMCS service status)
- `lib/Provision/Provisioner.php`
  - Added `ensureCloudStorageProductActive(...)` with idempotent activation and verification

### 3) Backup Owner and Destination Bootstrap

- New file: `lib/Client/CloudBackupBootstrapService.php`
  - `ensureBackupOwnerUser(clientId)`
  - `ensureDirectBucket(clientId)`
  - `ensureTenantBucket(clientId, tenantId)`
  - `ensureAgentDestination(agentId)`
- Enforced system-managed owner model for cloud backup buckets/user ownership.

### 4) Enrollment and Tenant Provisioning Automation

Updated routes:

- `api/e3backup_token_create.php`
- `api/e3backup_tenant_create.php`
- `api/agent_enroll.php`

Behavior:

- Ensure product is active before token/tenant operations
- Ensure backup owner user and policy bucket(s) exist
- Ensure per-agent destination mapping on enrollment

### 5) Destination Lock Enforcement (Backend + UI)

Updated routes/controllers:

- `api/cloudbackup_create_job.php`
- `api/cloudbackup_update_job.php`
- `lib/Client/CloudBackupController.php`

For `local_agent` jobs:

- Destination is derived from policy (`s3_cloudbackup_agent_destinations`)
- Manual `dest_bucket_id` / `dest_prefix` edits are blocked or ignored
- `jobs.tenant_id` is set from destination/agent scope

Updated templates/pages:

- `templates/partials/job_create_wizard.tpl`
- `templates/e3backup_jobs.tpl`
- `pages/users.php`
- `templates/users_v2.tpl`

### 6) Tenant Ownership Source of Truth

Updated:

- `lib/Client/MspController.php` (`validateJobAccess`)
- `lib/Client/CloudBackupController.php` (`startRun`, restore-point recording paths)
- `api/e3backup_job_list.php`

Ownership now prefers job/run snapshots over mutable live-agent tenant state.

### 7) System User Locking and Protection

Updated:

- `api/managedusers.php`
- `api/tenant_access_keys.php`
- `lib/Admin/Tenant.php`

Server-side enforcement now blocks management/key operations for locked/system-managed users.

---

## Phase 2 - Completed Updates

### 1) Repository Model Schema and Migrations

Implemented in `cloudstorage.php` (activate + upgrade paths):

- New table: `s3_cloudbackup_repositories`
  - `repository_id`, `client_id`, `tenant_id`, `tenant_user_id`, `bucket_id`, `root_prefix`, `engine`, `status`, timestamps
- New table: `s3_cloudbackup_repository_keys`
  - `repository_ref`, `key_version`, `wrap_alg`, `wrapped_repo_secret`, `kek_ref`, `mode`, `created_by`, timestamps
- Added `repository_id` to:
  - `s3_cloudbackup_jobs` (indexed)
  - `s3_cloudbackup_runs` (indexed)
  - `s3_cloudbackup_restore_points` (indexed)
- Backfills:
  - `runs.repository_id` from jobs
  - `restore_points.repository_id` from runs

### 2) Repository and Secret Services

New files in `lib/Client/`:

- `RepositoryService.php`
  - create/attach repository by policy destination scope
  - repository password retrieval via wrapped secret
  - feature-readiness guard for partial-upgrade compatibility
- `RepoSecretService.php`
  - secure random repository secret generation
- `KeyWrapService.php`
  - wrap/unwrap repository secrets
  - module key fallback (`cloudbackup_encryption_key` then `encryption_key`)

Audit logging is included for repository and key-version creation.

### 3) Local-Agent Job Binding to Repository Identity

Updated:

- `api/cloudbackup_create_job.php`
- `api/cloudbackup_update_job.php`
- `lib/Client/CloudBackupController.php`

Behavior:

- Local-agent job create attaches/creates repository identity when feature is available
- `jobs.repository_id` persisted when schema supports it
- destination root mirrored from repository identity
- repository root immutability enforced for repository-bound jobs

Compatibility:

- If repository schema is not yet available, create/update fall back safely to legacy behavior (no hard failure).

### 4) Canonical Snapshot Propagation

Updated in `CloudBackupController`:

- `startRun()` now copies both `tenant_id` and `repository_id` from job snapshot
- `recordRestorePointsForRun()` now snapshots `tenant_id` and `repository_id` from run/job snapshot chain

### 5) Dispatch Payload v2 (Agent APIs)

Updated routes:

- `api/agent_next_run.php`
- `api/agent_poll_pending_commands.php`

Added fields:

- `repository_id`
- `repository_password` (unwrapped repo secret)
- `repo_password_mode` (`v2` or legacy)
- `payload_version` (`v2`)

Legacy destination auth transport remains unchanged (`dest_access_key`, `dest_secret_key`).

### 6) Canonical Read/Restore API Updates

Updated routes:

- `api/e3backup_job_list.php`
  - returns `repository_id`
- `api/e3backup_restore_points_list.php`
  - includes `repository_id`
- `api/cloudbackup_list_runs.php`
  - returns canonical `tenant_id` + `repository_id` (run snapshot first, job fallback)
- `api/cloudbackup_start_restore.php`
  - restore run stores snapshot tenant/repository identity where columns exist

### 7) Runtime Reliability Improvement for System-Managed Owner Buckets

To prevent runtime failures when owner keys are missing:

- `CloudBackupBootstrapService::ensureBackupOwnerUser(...)` now also ensures a persisted runtime key exists for the backup-owner user.
- `agent_next_run.php` now attempts owner-key bootstrap and key reload when dispatch detects missing key rows for system-managed `cloudbackup_owner`.

This addresses backup runtime failures caused by missing dispatch credentials against existing buckets.

---

## New Files Added (Phase 1 + Phase 2)

- `lib/Client/CloudBackupBootstrapService.php`
- `lib/Client/RepositoryService.php`
- `lib/Client/RepoSecretService.php`
- `lib/Client/KeyWrapService.php`

---

## API Routes Added/Updated

Primary routes touched by these phases:

- `api/e3backup_token_create.php`
- `api/e3backup_tenant_create.php`
- `api/agent_enroll.php`
- `api/cloudbackup_create_job.php`
- `api/cloudbackup_update_job.php`
- `api/agent_next_run.php`
- `api/agent_poll_pending_commands.php`
- `api/e3backup_job_list.php`
- `api/e3backup_restore_points_list.php`
- `api/cloudbackup_list_runs.php`
- `api/cloudbackup_start_restore.php`
- `api/managedusers.php`
- `api/tenant_access_keys.php`

---

## Remaining / Pending Development Tasks

1. Agent repo contract completion (`e3-backup-agent`)
   - Fully adopt `repository_password` + `repository_id` as primary Kopia password source.
   - Keep legacy derived-password fallback only for old payloads during transition.

2. Phase 2 verification/cutover checklist execution
   - End-to-end validation evidence for:
     - repository persistence on new jobs
     - run/restore snapshot propagation
     - dispatch payload behavior for new and legacy jobs
     - credential rotation not breaking repository decrypt/open

3. Compatibility fallback retirement
   - After all environments finish schema upgrade, optionally remove legacy fallback paths (`status=skip` compatibility mode) and enforce repository mode uniformly.

4. Optional resiliency parity
   - Add the same owner-key self-heal fallback to other dispatch contexts if required (for example restore/maintenance command contexts) to match `agent_next_run` resiliency.

