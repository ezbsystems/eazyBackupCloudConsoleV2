# Cloud Backup UUIDv7 Job/Run Identity Design

Date: 2026-02-26
Module: `accounts/modules/addons/cloudstorage`
Status: Approved
Owner: Cloudstorage addon team

## Context

The e3 cloud backup platform currently uses auto-increment numeric identifiers for jobs and runs in multiple backend, agent, and UI flows. This design replaces those identifiers with UUID-based identities across the entire cloud backup domain.

This platform is still in development. There is no production-value backup/job/run data that must be preserved.

## Final Decisions

- `job_id`: UUIDv7
- `run_id`: UUIDv7
- Storage format in MySQL 8: `BINARY(16)` (native binary UUID storage)
- No backward compatibility layer
- No conversion migration from old numeric IDs
- Full PK/FK replacement for cloud backup jobs and runs
- Big-bang cutover (single breaking release)
- Strict agent version enforcement (older agent contracts are blocked)
- Optional readability field: `run_seq BIGINT UNSIGNED` per job

## Goals

- Use one identity model across backend, local agent, client pages, and admin pages.
- Remove mixed numeric/UUID handling and all fallback logic.
- Improve index locality and write characteristics using UUIDv7.
- Keep API contracts explicit and deterministic (UUID-only identifiers).

## Non-Goals

- Preserving legacy numeric job/run IDs.
- Data migration of old e3 cloud backup tables.
- Maintaining old agent protocol compatibility.
- Supporting mixed-ID operation windows.

## Architecture

### Identifier Model

- Jobs are identified by `job_id` UUIDv7.
- Runs are identified by `run_id` UUIDv7.
- All API payloads and URLs use canonical UUID string format.
- The application boundary converts UUID strings to/from binary storage.

### Database Model

- `s3_cloudbackup_jobs.job_id` is the primary key (`BINARY(16)`).
- `s3_cloudbackup_runs.run_id` is the primary key (`BINARY(16)`).
- `s3_cloudbackup_runs.job_id` is a `BINARY(16)` FK to jobs.
- All dependent tables referencing jobs/runs use `BINARY(16)` FK columns.
- Optional readability sequence:
  - `run_seq BIGINT UNSIGNED` in runs table
  - unique index on (`job_id`, `run_seq`)

### MySQL 8 Boundary Rules

- Write path: `UUID_TO_BIN(:uuid)` for identity columns.
- Read path: `BIN_TO_UUID(column)` for API/UI output fields.
- UUID values are never exposed as raw binary outside DB access.

## Cloud Backup Table Reset Scope

The cutover will reset and recreate cloud backup domain tables that contain job/run identifiers (directly or indirectly), including:

- `s3_cloudbackup_jobs`
- `s3_cloudbackup_runs`
- `s3_cloudbackup_run_events`
- `s3_cloudbackup_run_logs`
- `s3_cloudbackup_run_commands`
- `s3_cloudbackup_restore_points`
- `s3_hyperv_backup_points` (if linked to runs/jobs)
- Recovery/session token tables that store run/job references (for example `session_run_id`, `backup_run_id`)

If a table references cloud backup run/job identity, it is recreated as UUID-native in this release.

## API Contract

### General Contract

- `job_id` is UUID string only.
- `run_id` is UUID string only.
- Numeric identifier input is invalid.
- Invalid UUID format returns `400`.

### Input/Validation

- Centralized helpers validate UUID shape and perform DB conversion.
- Endpoints stop casting run/job IDs with integer conversion.
- No alternate parameters for numeric identity are accepted.

### Output

- All run/job identifiers in JSON responses are UUID strings.
- No dual fields (`id` + `uuid`) for jobs/runs.

## Agent Contract

- Agent request/response payloads use UUID strings for job/run identities.
- Agent polling, run updates, event push, cancel, and restore paths are UUID-only.
- Local run state/cache naming moves from numeric identity to UUID identity.
- Enrollment/poll path enforces minimum protocol version for UUID-only contract.

Old agent binaries that use numeric run/job contracts are rejected.

## Client and Admin UI

- Routes and query params use UUID strings:
  - `job_id=<uuid>`
  - `run_id=<uuid>`
- UI state treats identifiers as opaque strings, not numbers.
- Remove "Job #123" assumptions in displays and JavaScript.
- Prefer job name + timestamps + optional `run_seq` for readability.

## Recovery and Restore Flows

- Restore run identifiers are UUID-only in token exchange, start, poll, update, cancel, and event APIs.
- Session/token tables store run/job references in `BINARY(16)`.
- Recovery-side consumers use UUID-only parameters.

## Error Handling

- Malformed UUID input: `400 invalid_identifier_format`.
- Well-formed UUID not found: `404` or existing endpoint-specific failure envelope.
- Ownership/authorization checks remain the same but run against UUID keys.
- Agent on unsupported contract version receives explicit protocol/version error.

## Security and Isolation

- Existing auth and tenant scoping rules remain unchanged.
- ID format changes do not relax authorization checks.
- UUID validation occurs before ownership checks to reduce ambiguous error handling.

## Rollout Plan (Big-Bang)

1. Implement UUID-native schema definitions and reset script for cloud backup tables.
2. Update backend data access and endpoint contracts to UUID-only.
3. Update agent protocol and release UUID-only agent build.
4. Update client/admin templates and JavaScript to UUID-only flow.
5. Enable strict agent version enforcement.
6. Run validation suite and smoke tests.
7. Cut release as a single coordinated deployment.

No compatibility window is provided in this plan.

## Validation Plan

### Contract Tests

- All cloud backup endpoints reject numeric `job_id`/`run_id`.
- All cloud backup endpoints accept valid UUIDs and resolve records correctly.
- Recovery endpoints enforce UUID-only run identity.

### End-to-End Tests

- Create job -> start run -> poll/update/events -> live page -> cancel run.
- Restore path from restore point to live progress.
- Admin maintenance/command flows for active runs.

### Schema Assertions

- Verify `BINARY(16)` for all run/job PK/FK identity columns.
- Verify no residual numeric identity columns remain in cloud backup domain.
- Verify index coverage on run/job join and status/time query patterns.

## Risks and Mitigations

- Risk: missed code path still casts IDs to int.
  - Mitigation: grep-based sweep for integer casts on run/job fields and targeted tests.

- Risk: old agent binary in environment after cutover.
  - Mitigation: strict protocol version gate and clear error response.

- Risk: UI page still constructs numeric route links.
  - Mitigation: template and JS identifier audit plus route smoke tests.

## Acceptance Criteria

- Cloud backup jobs/runs use UUIDv7 as sole identifiers across DB/API/agent/UI/admin.
- All cloud backup references use `BINARY(16)` storage in MySQL 8.
- No numeric fallback behavior exists for job/run identity.
- End-to-end backup and restore flows pass using UUID-only identifiers.

