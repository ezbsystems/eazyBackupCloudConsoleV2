# Kopia Retention Architecture Changes

## Objective

Move Local Agent Kopia retention from object-prefix deletion to true repository-native retention using a vault-policy hierarchy (Comet-style behavior), while keeping legacy Cloud Backup retention behavior intact where appropriate.

This document defines:

- What must change
- What must be deprecated
- How to ensure `CloudBackupController::applyRetentionPolicy()` is only used for Cloud Backup jobs
- How any eligible agent can execute retention for a specific repository

---

## Current State (Problem)

The current retention cleanup path (`accounts/crons/s3cloudbackup_retention.php`) selects active jobs by retention settings and calls `CloudBackupController::applyRetentionPolicy()`.

That method performs object/prefix deletion under destination prefixes (for example `run_<id>/` and date-based paths). This is compatible with object-layout backup patterns, but is not safe for shared/deduplicated Kopia repositories.

### Why this is a problem for Kopia repos

- Kopia stores data as deduplicated content packs and snapshot metadata.
- Physical reclaim depends on removing snapshot references and then running maintenance.
- Raw S3 prefix deletes can remove data still referenced by other snapshots in the same repo.

---

## Target State

1. **Local Agent Kopia path**
   - Must use repository-native orchestration:
     - Snapshot forget by policy
     - Quick/full maintenance
     - Repo-level locking and scheduling
      - Vault default retention policy with job-level override and fallback

---

## Vault Policy Hierarchy (Required)

For Kopia repositories ("vaults"), retention must follow a strict hierarchy:

1. **Vault default policy** (admin configured)
   - Applied when vault/repo location is created.
   - Example: 30 days default.

2. **Job retention override** (customer configured on active job)
   - While the job is active, this override governs that job/source snapshots.

3. **Fallback to vault default** (on job or agent removal)
   - If job is deleted, paused permanently, or agent is removed, snapshots from that source revert to vault default retention behavior.

This provides predictable lifecycle handling and prevents orphaned data from persisting indefinitely.

---

## Required Architectural Decisions

## 1) Strict retention-path routing

Introduce an explicit retention strategy selector per job:

- `retention_strategy = cloud_object_delete` for Cloud Backup jobs
- `retention_strategy = kopia_repo_native` for Local Agent Kopia jobs

Routing must be based on job identity, not only retention fields:

- Local Agent jobs (`source_type=local_agent`) -> `kopia_repo_native`
- Cloud Backup source types (`s3_compatible`, `aws`, `sftp`, `google_drive`, `dropbox`, `smb`, `nas`) -> `cloud_object_delete`

Do not allow Local Agent jobs to fall through to object-prefix retention.

## 2) Restrict `applyRetentionPolicy()` to Cloud Backup only

`CloudBackupController::applyRetentionPolicy()` must enforce a hard guard:

- If job is Local Agent / Kopia-family engine, return `skipped` (or `fail-fast`) and do nothing.

This turns `applyRetentionPolicy()` into a Cloud Backup-only compatibility path.

## 3) Vault default policy as first-class configuration

Introduce a global admin setting for default vault retention policy:

- Applies to all newly created e3 backup vaults/repositories by default.
- Can be represented in days or by structured retention policy object.
- Stored and versioned as control-plane policy metadata.

When a new vault/repo is created, attach the current default vault policy immediately.

## 4) New Kopia retention orchestrator

Create a dedicated orchestration flow for repo-native retention:

- Evaluate retention policy
- Select snapshots to forget
- Execute forget
- Queue and run maintenance
- Record operation audit/result

This must be separate from `applyRetentionPolicy()`.

## 5) Agent-only cleanup execution

All data cleanup for Kopia vaults must be executed by Kopia-capable agents only.

- Server/control-plane may schedule and enqueue operations.
- Server/control-plane must not directly perform data deletion or retention cleanup against vault objects.
- No system-side cleanup service performs direct data cleanup for Kopia vaults.

---

## Repository-Centric Model

Retention should execute against a **repository identity**, not a run ID.

Recommended repository identity fields:

- `repo_id` (internal identifier)
- `client_id`
- `tenant_id` (nullable for direct/non-tenant customers)
- `engine_family` (`kopia`)
- `dest_bucket_id`
- `dest_prefix` (repo root)
- `vault_default_retention_policy`
- `vault_policy_version`
- `status`

All retention operations target `repo_id`.

---

## Command and Queue Model (Any Agent Can Run Retention)

Use repo-scoped commands with run-less execution context:

- `kopia_retention_apply`
- `kopia_maintenance_quick`
- `kopia_maintenance_full`

Command payload must include:

- `repo_id`
- destination access context (bucket/prefix/endpoint/region/key material source)
- effective policy context (vault default + job override resolution)
- operation idempotency token

### Agent claim model

Any agent may execute retention for a repo if it satisfies:

- Same `client_id`
- Same `tenant_id` scope (for MSP tenant isolation)
- Agent status active/online
- Engine capability supports Kopia retention operations

No requirement that retention be run by the same agent that created snapshots.
Requirement: retention execution must be performed by an eligible agent, never by server-side direct cleanup.

---

## Concurrency and Locking

Retention and maintenance require per-repo exclusivity.

Implement a repo operation lock with lease timeout:

- Only one maintenance operation per `repo_id` at a time
- Forget + maintenance serialized for same `repo_id`
- Safe lock recovery when worker/agent dies

Expected behavior:

- Backups may continue on other repos
- Same-repo operations are coordinated to avoid corruption/race conditions

---

## Retention Policy Semantics for Kopia

Policy resolution should be source-aware and lifecycle-aware:

- Active source retention:
  - Use job retention override when present.
  - If no override exists, use vault default policy.

- Retired source retention:
  - Triggered when job is deleted or agent is removed.
  - Job override no longer applies.
  - Source falls back to vault default policy.
  - Optional grace window may still be supported before forget.

Typical lifecycle:

1. Vault/repo created -> vault default policy attached
2. Job created -> optional job override attached
3. Job active -> effective policy = job override (or vault default if none)
4. Job deleted or agent removed -> source retired and reverts to vault default
5. Eligible snapshots forgotten by agent
6. Maintenance run by agent reclaims unreferenced data

This ensures predictable reclaim while preserving safe fallback behavior.

---

## Data Model Additions (Recommended)

## A) Repo operations table

`s3_kopia_repo_operations` (new):

- `id`
- `repo_id`
- `op_type` (`retention_apply`, `maintenance_quick`, `maintenance_full`)
- `status` (`queued`, `running`, `success`, `failed`)
- `claimed_by_agent_id`
- `attempt_count`
- `payload_json`
- `result_json`
- timestamps

## B) Optional snapshot/source index table

`s3_kopia_snapshot_index` (new or derived cache) to map:

- `repo_id`
- source identity
- snapshot id
- timestamp

Used to efficiently compute forget sets by policy.

---

## Cron and Scheduler Changes

Split scheduling behavior:

1. Cloud Backup scheduler path:
   - Continue Cloud Backup retention scheduling and `applyRetentionPolicy()` usage for Cloud Backup jobs only.

2. Kopia scheduler/orchestration path:
   - Enqueue repo operations (`kopia_retention_apply`, maintenance) with effective policy metadata.
   - Cleanup execution is agent-only.
   - No object-prefix deletion for Kopia repos.
   - No server-side direct data cleanup service for Kopia vaults.

---

## Migration Plan

## Phase 1: Safe guardrails

- Add job-type guard in `applyRetentionPolicy()`
- Prevent Local Agent jobs from being processed by `s3cloudbackup_retention.php`
- Emit logs when Local Agent retention would previously have run
- Add vault default retention setting and attach to new vault/repo records

## Phase 2: Introduce Kopia orchestrator

- Add repo operation table and command types
- Implement scheduling + claim + lock flow
- Implement forget + maintenance execution path
- Ensure execution path is agent-only (no direct server-side cleanup)

## Phase 3: UI and policy wiring

- Expose vault default policy and effective policy resolution for Local Agent repos
- Show operation history and last reclaim status
- Show fallback-to-vault behavior for retired sources

## Phase 4: tighten compatibility path

- Keep `applyRetentionPolicy()` for Cloud Backup only
- Update docs to explicitly state split retention architecture
- Remove/disable any Kopia cleanup path that performs server-side direct object deletion

---

## Acceptance Criteria

1. Local Agent Kopia jobs never invoke object-prefix retention deletion.
2. `applyRetentionPolicy()` executes only for Cloud Backup source types.
3. Vault default retention policy is attached when vault/repo is created.
4. Active jobs can apply a job-level retention override.
5. On job delete or agent delete, source snapshots fall back to vault default policy.
6. Repo-native retention uses snapshot forget + maintenance.
7. Any eligible scoped agent can execute retention for a repo.
8. Repo-level locking prevents concurrent conflicting maintenance operations.
9. Kopia vault cleanup is executed only by agents, never by server-side direct cleanup.

---

## Documentation Updates Required

Update the following docs to reflect this architecture:

- `accounts/modules/addons/cloudstorage/docs/CLOUD_BACKUP.md`
  - Clarify that `applyRetentionPolicy()` is Cloud Backup-only
- `accounts/modules/addons/cloudstorage/docs/LOCAL_AGENT_OVERVIEW.md`
  - Document repo-native retention orchestration for Kopia
- `accounts/modules/addons/cloudstorage/docs/CLOUD_BACKUP_TASKS.md`
  - Add tasks for split routing, guards, orchestrator tables, and command types

---

## Explicit Policy Statement

`CloudBackupController::applyRetentionPolicy()` must not be used for Local Agent Kopia repositories.

It remains a Cloud Backup retention path only, while Kopia retention is handled by repository-native snapshot-forget/maintenance orchestration with vault-default/job-override/fallback semantics and agent-only cleanup execution.

