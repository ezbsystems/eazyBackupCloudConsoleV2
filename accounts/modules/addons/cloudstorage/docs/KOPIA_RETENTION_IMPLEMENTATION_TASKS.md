# Kopia Retention Implementation Tasks

Short companion checklist for implementing the retention architecture described in:

- `accounts/modules/addons/cloudstorage/docs/KOPIA_RETENTION_ARCHITECTURE.md`

---

## Phase 1 - Safety Guards (Immediate)

- [ ] Add hard guard in `CloudBackupController::applyRetentionPolicy()`:
  - [ ] Skip/fail for `source_type=local_agent`
  - [ ] Skip/fail for Kopia-family engines (`kopia`, `disk_image`, `hyperv`)
- [ ] Update `accounts/crons/s3cloudbackup_retention.php` query to process **Cloud Backup source types only**
- [ ] Add log warnings for skipped Local Agent retention attempts
- [ ] Add unit/integration tests for guard behavior

## Phase 2 - Vault Default Policy (Comet-style baseline)

- [ ] Add admin-configurable default vault retention policy setting
- [ ] Apply default vault policy automatically when vault/repo is created
- [ ] Persist vault policy version with repo metadata
- [ ] Add validation for policy format/range
- [ ] Add migration/backfill strategy for existing repos without default vault policy

## Phase 3 - Data Model and Queue

- [ ] Create repo-operations table (for repo-scoped retention/maintenance orchestration)
  - [ ] `repo_id`, `op_type`, `status`, `claimed_by_agent_id`, `attempt_count`, `payload_json`, `result_json`, timestamps
- [ ] Add indexes for queue performance (`status+created_at`, `repo_id+status`)
- [ ] Add migration with backward-safe guards for existing installs
- [ ] Add repository lock/lease fields or lock table for per-repo exclusivity

## Phase 4 - Command Types and API Wiring

- [ ] Add repo-scoped command types:
  - [ ] `kopia_retention_apply`
  - [ ] `kopia_maintenance_quick`
  - [ ] `kopia_maintenance_full`
- [ ] Extend command enqueue APIs for repo-scoped operations
- [ ] Add `agent_poll_repo_operations.php` to deliver repo-scoped operations (separate from `agent_poll_pending_commands.php` run commands)
- [ ] Implement claim rules so **any eligible scoped agent** can claim repo operations
- [ ] Enforce scope checks (`client_id`, `tenant_id`, capability checks)

## Phase 5 - Agent Execution Path (Agent-Only Cleanup)

- [ ] Implement retention executor in agent:
  - [ ] Resolve repository context from command payload
  - [ ] Resolve effective policy (job override vs vault default)
  - [ ] Compute forget set from effective policy/source lifecycle
  - [ ] Execute snapshot forget
  - [ ] Execute maintenance quick/full
- [ ] Add retry/backoff behavior for transient lock/contention failures
- [ ] Add structured events/logs for operation status and reclaim metrics
- [ ] Ensure idempotency via operation token/version in payload
- [ ] Ensure all Kopia data cleanup is executed by agent only (no server-side direct cleanup path)

## Phase 6 - Scheduling and Control Plane Split

- [ ] Split retention scheduling into two paths:
  - [ ] Cloud Backup object-delete scheduler (legacy path)
  - [ ] Kopia repo-native operation enqueue (new path)
- [ ] Ensure scheduler never routes Local Agent jobs into object-delete retention
- [ ] Ensure control plane only enqueues operations and never performs direct Kopia cleanup
- [ ] Add health metrics: queued, running, failed, stale operations

## Phase 7 - Lifecycle Rules for Job/Agent Removal

- [ ] Add source lifecycle state (`active`, `retired`, `expired`) tracking
- [ ] On job deletion: mark source retired and remove job override from effective policy
- [ ] On agent removal: retire associated sources and remove job override from effective policy
- [ ] Ensure retired sources fall back to vault default retention policy
- [ ] Apply grace-period policy before forget/purge (if configured)
- [ ] Run maintenance after forget to reclaim storage

## Phase 8 - UI and Operational Visibility

- [ ] Add admin/client visibility for repo retention status:
  - [ ] Last retention run
  - [ ] Last maintenance run
  - [ ] Pending/failed operations
- [ ] Show vault default policy and effective per-job override status
- [ ] Show fallback-to-vault status for retired sources
- [ ] Add manual trigger controls for repo retention/maintenance
- [ ] Add clear status messaging for lock/contention and retry states

## Phase 9 - Documentation and Cleanup

- [x] Update `CLOUD_BACKUP.md` to explicitly scope `applyRetentionPolicy()` to Cloud Backup only
- [x] Update `LOCAL_AGENT_OVERVIEW.md` with repo-native retention flow
- [x] Update docs to include vault default + job override + fallback model
- [x] Update task docs to remove Local Agent reliance on object-prefix deletion and server-side cleanup
- [ ] Add runbook for failure recovery (stale lock, failed maintenance, replay)

---

## Release Gate Script

Run to verify required retention classes exist before release (can be run from any directory; paths resolve from script `__DIR__`):

```bash
php accounts/modules/addons/cloudstorage/bin/dev/kopia_retention_release_gate_test.php
```

Checks: `KopiaRetentionRoutingService`, `KopiaRetentionPolicyService`, `KopiaRetentionOperationService` must be loadable.

---

## Test Checklist (Release Gate)

- [ ] Local Agent Kopia job with retention policy does **not** invoke object-prefix delete path
- [ ] Cloud Backup jobs continue to use `applyRetentionPolicy()` as expected
- [ ] New vault/repo gets default vault retention policy on creation
- [ ] Active job override takes precedence over vault default
- [ ] Deleting job/agent causes source retention to fall back to vault default
- [ ] Repo operation lock prevents concurrent maintenance on same repo
- [ ] Two different agents can claim and execute repo retention (same scope) successfully
- [ ] Job deletion retires source and reclaims space only via forget+maintenance
- [ ] Agent deletion retires source and preserves shared snapshot safety
- [ ] No server-side direct cleanup path mutates Kopia vault data
- [ ] Failed operations retry and surface clear diagnostics

---

## Explicit Constraint

- [ ] `CloudBackupController::applyRetentionPolicy()` must remain Cloud Backup-only and must not run against Local Agent Kopia repositories.
- [ ] Kopia vault data cleanup must be performed by eligible agents only (never by system-side direct cleanup services).

