#!/bin/bash
#
# Safe production deploy: sync tracked WHMCS custom code from git clone → live accounts/.
#
# Can be run from any working directory (paths are absolute).
#
# Usage (on prod as root):
#   bash /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/deploy-production.sh
#   bash /root/deploy-production.sh          # if copied or symlinked to ~/
#   bash .../deploy-production.sh --dry-run
#
# Safety rules:
#   1. Never rsync --delete unless a sentinel file exists in the repo source.
#   2. If repo source is incomplete, keep the existing prod copy (do not delete).
#   3. Exclude prod-only runtime data (ms365 fleet release binaries, build logs).
#   4. Materialize git-tracked vendor/ trees in the repo clone before rsync.
#
set -euo pipefail

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
  echo "[DRY-RUN] No files will be changed."
fi

REPO_ROOT="${REPO_ROOT:-/var/www/eazybackup.ca/repo/eazyBackupCloudConsoleV2}"
REPO_ACCOUNTS="$REPO_ROOT/accounts"
PROD_ROOT="${PROD_ROOT:-/var/www/eazybackup.ca/accounts}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
WORKER_REPO_PATH="${WORKER_REPO_PATH:-/var/www/eazybackup.ca/ms365-backup-worker}"

RSYNC_OPTS=(-av --delete)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_OPTS=(-av --delete --dry-run)
fi

log() { echo "[deploy] $*"; }
fail() { echo "[deploy] ERROR: $*" >&2; exit 1; }

# Require sentinel file in source before rsync --delete (prevents wiping prod from empty clone).
rsync_guarded() {
  local label="$1"
  local sentinel="$2"
  local src="$3"
  local dst="$4"
  shift 4

  if [[ ! -d "$src" ]]; then
    log "SKIP $label — source directory missing: $src"
    return 0
  fi
  if [[ ! -f "$src/$sentinel" ]]; then
    log "SKIP $label — source incomplete (missing $sentinel): $src"
    if [[ -f "$dst/$sentinel" ]]; then
      log "       Keeping existing prod copy at $dst"
      return 0
    fi
    fail "$label — source incomplete and prod copy missing ($dst/$sentinel)"
  fi

  log "SYNC $label"
  rsync "${RSYNC_OPTS[@]}" "$@" "$src" "$dst"
}

rsync_addon() {
  local name="$1"
  local sentinel="$2"
  shift 2
  rsync_guarded "addon/$name" "$sentinel" \
    "$REPO_ACCOUNTS/modules/addons/$name/" \
    "$PROD_ROOT/modules/addons/$name/" \
    "$@"
}

materialize_from_git() {
  local rel="$1"
  local sentinel="$2"
  if [[ -f "$REPO_ACCOUNTS/$rel/$sentinel" ]]; then
    return 0
  fi
  log "Materializing $rel from git (missing $sentinel in repo clone)..."
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "  would run: git checkout HEAD -- $rel"
    return 0
  fi
  git checkout HEAD -- "$rel"
}

preflight() {
  [[ -d "$REPO_ROOT/.git" ]] || fail "Repo not found: $REPO_ROOT"
  [[ -d "$PROD_ROOT" ]] || fail "Prod accounts not found: $PROD_ROOT"
  command -v rsync >/dev/null || fail "rsync not installed"
  command -v git >/dev/null || fail "git not installed"
  command -v php >/dev/null || fail "php not installed"
}

chown_paths() {
  local paths=()
  for p in "$@"; do
    [[ -e "$p" ]] && paths+=("$p")
  done
  [[ "${#paths[@]}" -eq 0 ]] && return 0
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "would chown -R $WEB_USER:$WEB_GROUP ${paths[*]}"
    return 0
  fi
  chown -R "$WEB_USER:$WEB_GROUP" "${paths[@]}"
}

post_deploy_checks() {
  [[ "$DRY_RUN" -eq 1 ]] && return 0

  log "Post-deploy: ensure worker repo path writable by $WEB_USER"
  chown_paths "$WORKER_REPO_PATH"

  log "Post-deploy: browse binary sync"
  if ! php "$PROD_ROOT/modules/addons/ms365backup/bin/ms365_install_browse_binary.php"; then
    fail "Browse binary sync failed — run: php $PROD_ROOT/modules/addons/ms365backup/bin/ms365_browse_binary_diag.php"
  fi

  log "Post-deploy: health check (includes browse binary version)"
  if ! php "$PROD_ROOT/modules/addons/ms365backup/bin/ms365_prod_health_check.php"; then
    fail "Production health check failed — inspect browse_binary diagnostics in output"
  fi

  log "Post-deploy: fleet smoke (non-fatal)"
  php "$PROD_ROOT/modules/addons/ms365backup/bin/ms365_fleet_smoke.php" || true

  if command -v systemctl >/dev/null; then
    if systemctl is-active --quiet php8.2-fpm 2>/dev/null; then
      log "Reloading php8.2-fpm"
      systemctl reload php8.2-fpm
    elif systemctl is-active --quiet php-fpm 2>/dev/null; then
      log "Reloading php-fpm"
      systemctl reload php-fpm
    fi
  fi
}

# --- main ---

preflight

cd "$REPO_ROOT"
log "Fetching origin/main..."
if [[ "$DRY_RUN" -eq 0 ]]; then
  git fetch origin
  git pull origin main
fi

materialize_from_git "modules/addons/ms365backup/vendor" "autoload.php"

[[ -f "$REPO_ACCOUNTS/modules/servers/comet/functions.php" ]] || \
  fail "Repo clone missing modules/servers/comet/functions.php after git pull (comet must be tracked in git)."

# --- rsync (paths must match .gitignore tracked set) ---

rsync_guarded hooks index.php \
  "$REPO_ACCOUNTS/includes/hooks/" \
  "$PROD_ROOT/includes/hooks/"

rsync_guarded crons s3Billing.php \
  "$REPO_ACCOUNTS/crons/" \
  "$PROD_ROOT/crons/"

rsync_addon cloudstorage cloudstorage.php
rsync_addon cometbilling cometbilling.php
rsync_addon eazybackup eazybackup.php
rsync_addon hidepermissions hidepermissions.php

rsync_addon ms365backup ms365backup.php \
  --exclude 'storage/worker-releases/*/' \
  --exclude 'storage/worker-builds/'

rsync_guarded servers/comet functions.php \
  "$REPO_ACCOUNTS/modules/servers/comet/" \
  "$PROD_ROOT/modules/servers/comet/"

rsync_guarded gateways/stripe hooks.php \
  "$REPO_ACCOUNTS/modules/gateways/stripe/" \
  "$PROD_ROOT/modules/gateways/stripe/"

rsync_guarded templates/eazyBackup header.tpl \
  "$REPO_ACCOUNTS/templates/eazyBackup/" \
  "$PROD_ROOT/templates/eazyBackup/"

chown_paths \
  "$WORKER_REPO_PATH" \
  "$PROD_ROOT/modules/addons/cloudstorage" \
  "$PROD_ROOT/modules/addons/cometbilling" \
  "$PROD_ROOT/modules/addons/eazybackup" \
  "$PROD_ROOT/modules/addons/ms365backup" \
  "$PROD_ROOT/modules/addons/hidepermissions" \
  "$PROD_ROOT/modules/servers/comet" \
  "$PROD_ROOT/modules/gateways/stripe" \
  "$PROD_ROOT/includes/hooks" \
  "$PROD_ROOT/crons" \
  "$PROD_ROOT/templates/eazyBackup"

post_deploy_checks

log "Deploy complete."
