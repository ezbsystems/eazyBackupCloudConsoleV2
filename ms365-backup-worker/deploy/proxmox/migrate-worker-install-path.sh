#!/usr/bin/env bash
# One-time migration for workers created before install_path moved under /var/lib.
# Run as root inside each worker LXC (or: pct exec 9011 -- bash /path/to/migrate-worker-install-path.sh)
set -euo pipefail

WORKER_USER="${WORKER_USER:-ms365worker}"
OLD_BIN="/usr/local/bin/ms365-backup-worker"
NEW_DIR="/var/lib/ms365-backup-worker/bin"
NEW_BIN="${NEW_DIR}/ms365-backup-worker"
UNIT="/etc/systemd/system/ms365-backup-worker.service"

echo "==> Preparing ${NEW_DIR}"
systemctl stop ms365-backup-worker 2>/dev/null || true
mkdir -p "$NEW_DIR"
if [[ -x "$OLD_BIN" ]]; then
  cp -a "$OLD_BIN" "$NEW_BIN"
elif [[ ! -x "$NEW_BIN" ]]; then
  echo "No worker binary at $OLD_BIN or $NEW_BIN" >&2
  exit 1
fi
chown -R "$WORKER_USER:$WORKER_USER" /var/lib/ms365-backup-worker
chmod 0755 "$NEW_DIR"
chmod 0755 "$NEW_BIN"

echo "==> Updating systemd unit"
if [[ -f "$UNIT" ]]; then
  sed -i "s|/usr/local/bin/ms365-backup-worker|${NEW_BIN}|g" "$UNIT"
fi

echo "==> Restarting worker"
systemctl daemon-reload
systemctl restart ms365-backup-worker
systemctl --no-pager status ms365-backup-worker || true
echo "Done. Binary: $NEW_BIN"
