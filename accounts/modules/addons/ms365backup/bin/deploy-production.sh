#!/bin/bash
# Production deploy: sync WHMCS custom code from repo clone to live accounts/.
# Run on prod as root:
#   bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh
set -euo pipefail

REPO_ROOT="${REPO_ROOT:-/var/www/eazybackup.ca/repo/eazyBackupCloudConsoleV2}"
REPO_ACCOUNTS="$REPO_ROOT/accounts"
PROD_ROOT="${PROD_ROOT:-/var/www/eazybackup.ca/accounts}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

cd "$REPO_ROOT"
git fetch origin
git pull origin main

if [[ ! -f "$REPO_ACCOUNTS/modules/addons/ms365backup/vendor/autoload.php" ]]; then
  echo "[INFO] Restoring ms365backup/vendor from git into repo clone..."
  git checkout HEAD -- accounts/modules/addons/ms365backup/vendor/
fi

rsync_safe() {
  local label="$1"
  local src="$2"
  local dst="$3"
  shift 3
  if [[ ! -d "$src" ]]; then
    echo "[SKIP] $label — source missing: $src"
    return 0
  fi
  echo "[SYNC] $label"
  rsync -av --delete "$@" "$src" "$dst"
}

rsync_addon() {
  local name="$1"
  shift
  local src="$REPO_ACCOUNTS/modules/addons/$name/"
  local dst="$PROD_ROOT/modules/addons/$name/"
  if [[ ! -d "$src" ]]; then
    echo "[SKIP] addon $name — not in repo clone ($src)"
    return 0
  fi
  echo "[SYNC] addon $name"
  rsync -av --delete "$@" "$src" "$dst"
}

rsync_safe hooks "$REPO_ACCOUNTS/includes/hooks/" "$PROD_ROOT/includes/hooks/"
rsync_safe crons "$REPO_ACCOUNTS/crons/" "$PROD_ROOT/crons/"

rsync_addon cloudstorage
rsync_addon cometbilling
rsync_addon eazybackup
rsync_addon hidepermissions
rsync_addon mspconnect
rsync_addon ms365backup \
  --exclude 'storage/worker-releases/*/' \
  --exclude 'storage/worker-builds/'

if [[ -f "$REPO_ACCOUNTS/modules/servers/comet/functions.php" ]]; then
  rsync_safe comet "$REPO_ACCOUNTS/modules/servers/comet/" "$PROD_ROOT/modules/servers/comet/"
else
  echo "[SKIP] comet server — repo clone has no functions.php (submodule not checked out)."
  if [[ ! -f "$PROD_ROOT/modules/servers/comet/functions.php" ]]; then
    echo "[FAIL] Prod comet module missing. Copy from dev, e.g.:"
    echo "  scp -r DEV_HOST:$PROD_ROOT/modules/servers/comet/ $PROD_ROOT/modules/servers/comet/"
    exit 1
  fi
fi

rsync_safe stripe_gateway "$REPO_ACCOUNTS/modules/gateways/stripe/" "$PROD_ROOT/modules/gateways/stripe/"
rsync_safe eazyBackup_template "$REPO_ACCOUNTS/templates/eazyBackup/" "$PROD_ROOT/templates/eazyBackup/"

chown -R "$WEB_USER:$WEB_GROUP" \
  "$PROD_ROOT/modules/addons/cloudstorage" \
  "$PROD_ROOT/modules/addons/cometbilling" \
  "$PROD_ROOT/modules/addons/eazybackup" \
  "$PROD_ROOT/modules/addons/ms365backup" \
  "$PROD_ROOT/modules/addons/hidepermissions" \
  "$PROD_ROOT/includes/hooks" \
  "$PROD_ROOT/crons" \
  "$PROD_ROOT/templates/eazyBackup"
[[ -d "$PROD_ROOT/modules/servers/comet" ]] && chown -R "$WEB_USER:$WEB_GROUP" "$PROD_ROOT/modules/servers/comet"
[[ -d "$PROD_ROOT/modules/gateways/stripe" ]] && chown -R "$WEB_USER:$WEB_GROUP" "$PROD_ROOT/modules/gateways/stripe"
[[ -d "$PROD_ROOT/modules/addons/mspconnect" ]] && chown -R "$WEB_USER:$WEB_GROUP" "$PROD_ROOT/modules/addons/mspconnect"

php "$PROD_ROOT/modules/addons/ms365backup/bin/ms365_install_browse_binary.php"
php "$PROD_ROOT/modules/addons/ms365backup/bin/ms365_prod_health_check.php" || true

echo "Deploy complete."
