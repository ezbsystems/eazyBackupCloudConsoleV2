# Agent UUID Cutover Runbook

## Purpose
Use this runbook to perform the UUID big-bang cutover for local agents during a maintenance window.

## Scope
- Convert runtime agent identity from numeric `agent_id` to immutable `agent_uuid`.
- Rebuild Cloud Backup schema through module activation logic.
- Verify enrollment/auth, route contracts, and UI contract behavior after cutover.

## Preconditions
- Maintenance window approved and announced.
- Full database backup captured and validated.
- No active Cloud Backup jobs/runs expected (development reset model).
- Operator has shell and DB access to the WHMCS host.

## Pre-Cutover Checks
1. Ensure repository is on the cutover branch/commit.
2. Run source-level contract tests:
   - `php accounts/modules/addons/cloudstorage/tests/agent_uuid_schema_contract_test.php`
   - `php accounts/modules/addons/cloudstorage/tests/run_agent_uuid_tests.php`
   - `php accounts/modules/addons/cloudstorage/tests/agent_uuid_route_smoke.php`
   - `php accounts/modules/addons/cloudstorage/tests/agent_uuid_ui_smoke.php`
3. Confirm app is in maintenance mode (or traffic blocked for Cloud Backup routes).

## Cutover Procedure
1. Execute the cutover script:
   - `php accounts/modules/addons/cloudstorage/scripts/agent_uuid_bigbang_cutover.php`
2. Confirm script reports successful schema recreation and smoke checks.
3. Restart PHP-FPM/web worker processes if your environment requires cache refresh.

## Mandatory Manual DB Validation Gate (Real WHMCS Runtime)
Run these checks directly against the runtime database before merge/deploy:
- Verify UUID columns exist and are indexed on:
  - `s3_cloudbackup_agents.agent_uuid` (PK/unique)
  - `s3_cloudbackup_jobs.agent_uuid`
  - `s3_cloudbackup_runs.agent_uuid`
  - `s3_cloudbackup_run_commands.agent_uuid`
  - `s3_cloudbackup_restore_points.agent_uuid`
  - `s3_cloudbackup_agent_destinations.agent_uuid`
- Verify core tables required by production paths exist after cutover (settings, sources, recovery token/exchange limit tables, jobs/runs/events tables).
- Verify latest API write-path columns for jobs/runs are present (schedule, retention, encryption/compression, stats/progress/log refs).

## Post-Cutover Verification
1. Go agent tests:
   - `cd e3-backup-agent && go test ./...`
2. PHP tests:
   - `php accounts/modules/addons/cloudstorage/tests/run_agent_uuid_tests.php`
   - `php accounts/modules/addons/cloudstorage/tests/agent_uuid_schema_contract_test.php`
   - `php accounts/modules/addons/cloudstorage/tests/agent_uuid_route_smoke.php`
   - `php accounts/modules/addons/cloudstorage/tests/agent_uuid_ui_smoke.php`
3. Legacy token scan:
   - `rg "X-Agent-UUID|agent_uuid" accounts/modules/addons/cloudstorage e3-backup-agent`
4. Functional smoke:
   - Enroll/login a test agent and verify destination bootstrap.
   - Start a local-agent backup and verify run association by `agent_uuid`.

## Rollback
If any validation fails:
1. Keep maintenance mode enabled.
2. Restore database from pre-cutover backup.
3. Revert application code to pre-cutover release.
4. Restart services and run baseline health checks.
5. Re-schedule cutover after root-cause fixes.

## Notes
- This runbook assumes development-data destructive reset semantics.
- If historical data retention becomes mandatory, stop and use a dual-write migration strategy instead of this runbook.
