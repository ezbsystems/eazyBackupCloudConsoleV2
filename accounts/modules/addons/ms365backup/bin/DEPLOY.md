# Production deploy

Safe script to sync tracked WHMCS custom code from the git clone to live `accounts/` on production.

## Run

```bash
# Preview changes (no writes)
bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh --dry-run

# Deploy
sudo bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh
```

### Run from `/root` (or any directory)

The script uses **absolute paths** (`REPO_ROOT`, `PROD_ROOT`). It does not depend on the current working directory.

Recommended: symlink so updates from git deploy the latest script automatically:

```bash
ln -sf /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh /root/deploy.sh
sudo /root/deploy.sh
```

If you **copy** the script to `/root/deploy.sh` instead, re-copy (or pull + deploy ms365backup) when the script changes in git.

## What it syncs

| Path | Notes |
|------|--------|
| `includes/hooks/` | Full mirror from git |
| `crons/` | Custom crons only |
| `modules/addons/cloudstorage` | |
| `modules/addons/cometbilling` | |
| `modules/addons/eazybackup` | |
| `modules/addons/hidepermissions` | |
| `modules/addons/ms365backup` | Excludes `storage/worker-releases/*/` and `storage/worker-builds/` |
| `modules/servers/comet` | Git-tracked; synced when `functions.php` exists in repo clone |
| `modules/gateways/stripe` | |
| `templates/eazyBackup` | |

`mspconnect` is intentionally **not** deployed (retired).

## Safety rules

1. **Sentinel check** — `rsync --delete` only runs when a key file exists in the repo source (e.g. `comet/functions.php`).
2. **ms365 storage** — Fleet release binaries and build logs on prod are never deleted by rsync.
3. **Vendor materialization** — `ms365backup/vendor/` is checked out from git into the repo clone before rsync.
4. **Comet in git** — Aborts if `git pull` did not populate `modules/servers/comet/functions.php` in the repo clone.
5. **Post-deploy** — Browse binary sync, health check, optional PHP-FPM reload.

## Environment overrides

```bash
REPO_ROOT=/path/to/clone PROD_ROOT=/path/to/live/accounts bash /root/deploy.sh
```
