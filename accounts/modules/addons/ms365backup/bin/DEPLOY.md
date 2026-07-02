# Production deploy

Safe script to sync tracked WHMCS custom code from the git clone to live `accounts/` on production.

## Run

```bash
# Preview changes (no writes)
bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh --dry-run

# Deploy
sudo bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh
```

After pulling updates to the script from git, copy or re-run from the live path (the script lives inside `ms365backup` and deploys itself).

## What it syncs

| Path | Notes |
|------|--------|
| `includes/hooks/` | Full mirror from git |
| `crons/` | Custom crons only |
| `modules/addons/cloudstorage` | |
| `modules/addons/cometbilling` | |
| `modules/addons/eazybackup` | |
| `modules/addons/hidepermissions` | |
| `modules/addons/mspconnect` | Skipped if not in repo clone |
| `modules/addons/ms365backup` | Excludes `storage/worker-releases/*/` and `storage/worker-builds/` |
| `modules/servers/comet` | **Skipped** if repo clone has no `functions.php` (submodule); prod copy kept |
| `modules/gateways/stripe` | |
| `templates/eazyBackup` | |

## Safety rules

1. **Sentinel check** — `rsync --delete` only runs when a key file exists in the repo source (e.g. `comet/functions.php`). Empty repo checkouts cannot wipe prod.
2. **ms365 storage** — Fleet release binaries and build logs on prod are never deleted by rsync.
3. **Vendor materialization** — `ms365backup/vendor/` is checked out from git into the repo clone before rsync.
4. **Preflight** — Aborts if prod `comet/functions.php` is missing.
5. **Post-deploy** — Browse binary sync, health check, optional PHP-FPM reload.

## Comet server module

`modules/servers/comet` is a git submodule pointer in the main repo; the clone on prod often has **no files**. The deploy script **never deletes** the live comet module in that case. Restore comet manually from dev if prod is missing:

```bash
scp -r DEV_HOST:/var/www/eazybackup.ca/accounts/modules/servers/comet/ \
  /var/www/eazybackup.ca/accounts/modules/servers/comet/
```

## Environment overrides

```bash
REPO_ROOT=/path/to/clone PROD_ROOT=/path/to/live/accounts ./deploy-production.sh
```
