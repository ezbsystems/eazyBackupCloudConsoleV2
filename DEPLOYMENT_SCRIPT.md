## Deployment Script - Tenant v2 Canonical Rollout

Use this runbook when releasing Tenant v2 canonical ownership changes in environments that run Partner Hub + Cloud Storage.

### Deployment Sync Script (existing behavior)

```bash
#!/bin/bash
set -e

REPO_ROOT="/var/www/eazybackup.ca/repo/eazyBackupCloudConsoleV2"
REPO_ACCOUNTS="$REPO_ROOT/accounts"
PROD_ROOT="/var/www/eazybackup.ca/accounts"
WEB_USER="www-data"
WEB_GROUP="www-data"

cd "$REPO_ROOT"
git fetch origin
git pull origin main

# Hooks
rsync -av --delete "$REPO_ACCOUNTS/includes/hooks/" "$PROD_ROOT/includes/hooks/"

# Crons
rsync -av --delete "$REPO_ACCOUNTS/crons/" "$PROD_ROOT/crons/"

# Addons
rsync -av --delete "$REPO_ACCOUNTS/modules/addons/cloudstorage/"   "$PROD_ROOT/modules/addons/cloudstorage/"
rsync -av --delete "$REPO_ACCOUNTS/modules/addons/cometbilling/"   "$PROD_ROOT/modules/addons/cometbilling/"
rsync -av --delete "$REPO_ACCOUNTS/modules/addons/eazybackup/"     "$PROD_ROOT/modules/addons/eazybackup/"
rsync -av --delete "$REPO_ACCOUNTS/modules/addons/hidepermissions/" "$PROD_ROOT/modules/addons/hidepermissions/"
rsync -av --delete "$REPO_ACCOUNTS/modules/addons/mspconnect/"     "$PROD_ROOT/modules/addons/mspconnect/"

# Server + gateway modules
rsync -av --delete "$REPO_ACCOUNTS/modules/servers/comet/" "$PROD_ROOT/modules/servers/comet/"
rsync -av --delete "$REPO_ACCOUNTS/modules/gateways/stripe/" "$PROD_ROOT/modules/gateways/stripe/"

# Template
rsync -av --delete "$REPO_ACCOUNTS/templates/eazyBackup/" "$PROD_ROOT/templates/eazyBackup/"

# Fix permissions
chown -R "$WEB_USER:$WEB_GROUP" \
  "$PROD_ROOT/modules/addons/cloudstorage" \
  "$PROD_ROOT/modules/addons/cometbilling" \
  "$PROD_ROOT/modules/addons/eazybackup" \
  "$PROD_ROOT/modules/addons/hidepermissions" \
  "$PROD_ROOT/modules/addons/mspconnect" \
  "$PROD_ROOT/modules/servers/comet" \
  "$PROD_ROOT/modules/gateways/stripe" \
  "$PROD_ROOT/includes/hooks" \
  "$PROD_ROOT/crons" \
  "$PROD_ROOT/templates/eazyBackup"
```

## Tenant v2 Pre-release Checklist

- [ ] Confirm release branch/worktree is `tenant-v2-canonical` and includes Tenant v2 canonical migration changes.
- [ ] Capture a database snapshot/backup before rollout.
- [ ] Run release gate:
  - `php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php`
  - Expected result: prints `MSP_BILLING_RELEASE_GATE_PASS` and exits `0`.
- [ ] Run dry-run canonical migration:
  - `php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --dry-run`
  - Expected result: `TENANT_V2_CANONICAL_MIGRATION_REPORT` output and exit `0`.
  - Release criteria before `--apply`:
    - `ambiguous=0`
    - `manual_invalid=0`
    - `unmapped=0` OR an approved manual mapping plan is documented in the change ticket.
- [ ] Run canonical migration apply in approved change window:
  - `php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --apply`
  - Expected result: `TENANT_V2_CANONICAL_MIGRATION_REPORT` with `mode=APPLY`, `manual_invalid=0`, and `applied_updates=<n>` matching expected mapped rows for the approved plan.
- [ ] Confirm deployment user has DB privileges required by canonical tenant updates (`SELECT`, `INSERT`, `UPDATE`, `ALTER` where applicable).

## Tenant v2 Rollback Notes

- If rollout validation fails before live migration:
  - Stop release, do not run non-dry migration.
  - Fix schema/environment issue and re-run gate + dry-run.
- If live migration already executed and behavior regresses:
  - Restore from pre-release DB snapshot.
  - Re-deploy previous known-good application revision.
  - Re-run release gate to confirm baseline restored.
- If only Partner Hub UI paths regress while DB is healthy:
  - Keep DB state, roll back app code only, then rerun release gate and smoke-test key routes.
- Route fallback for rollback:
  - Disable/revert newly introduced Partner Hub tenant endpoints if needed.
  - Keep legacy cloudstorage tenant wrapper routes active so bookmarked URLs continue to resolve.

## Post-deploy Verification Queries and Checks

Run after deployment and after any rollback/recovery:

1) Release gate
- Command: `php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php`
- Expected: `MSP_BILLING_RELEASE_GATE_PASS`, exit `0`.

2) Canonical dry-run sanity check
- Command: `php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --dry-run`
- Expected: `TENANT_V2_CANONICAL_MIGRATION_REPORT` output, exit `0`.
- Gate criteria: `ambiguous=0`, `manual_invalid=0`, and `unmapped=0` unless a documented manual-mapping exception exists.

3) Canonical apply execution check (during rollout)
- Command: `php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --apply`
- Expected: `TENANT_V2_CANONICAL_MIGRATION_REPORT` output with `mode=APPLY` and non-negative `applied_updates`.

4) DB verification queries (example)
- Confirm canonical table exists:
  - `SHOW TABLES LIKE 's3_backup_tenants';`
  - Expected: one row.
- Confirm canonical link column required by migration exists in target environment before live run:
  - `SHOW COLUMNS FROM eb_whitelabel_tenants LIKE 'canonical_tenant_id';`
  - Expected: one row once prerequisite schema migration has run.
- Spot-check linkage:
  - `SELECT id, canonical_tenant_id FROM eb_whitelabel_tenants ORDER BY id DESC LIMIT 20;`
  - Expected: canonical tenant linkage populated for migrated tenants.
- Full-linkage check:
  - `SELECT COUNT(*) AS missing_links FROM eb_whitelabel_tenants WHERE canonical_tenant_id IS NULL OR canonical_tenant_id = 0;`
  - Expected: `0` after completed migration (or value matches pre-approved exception list).

## Operational Note - Dry-run Failures on Missing DB Columns

If `--dry-run` fails due to missing column errors (for example, unknown column such as `canonical_tenant_id`), this typically means prerequisite schema migrations have not been applied in that environment yet.

Operational response:
- Treat as an environment readiness failure, not an application logic failure.
- Run/complete schema migration step for the missing columns first.
- Re-run:
  1. `php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php`
  2. `php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --dry-run`
- Proceed to live rollout only after both commands exit `0` with expected pass output.

## Task 8 Verification Log (current environment)

- `php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php`
  - Output: `MSP_BILLING_RELEASE_GATE_PASS`
  - Exit: `0`
- `php accounts/modules/addons/eazybackup/bin/dev/migrate_tenant_v2_canonical.php --dry-run`
  - Output: `ERROR: eb_whitelabel_tenants.canonical_tenant_id is missing. Run addon schema migration first.`
  - Exit: `1`
- Status: schema prerequisite missing in this environment; complete addon schema migration before running `--apply`.
