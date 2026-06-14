#!/usr/bin/env bash
# Optional first-boot helper: verify worker binary exists before systemd start.
set -euo pipefail
BIN="${INSTALL_PREFIX:-/var/lib/ms365-backup-worker/bin}/ms365-backup-worker"
if [[ -x "$BIN" ]]; then
  exit 0
fi
echo "ms365-backup-worker binary missing at $BIN — start worker service after first deploy from WHMCS fleet UI" >&2
exit 0
