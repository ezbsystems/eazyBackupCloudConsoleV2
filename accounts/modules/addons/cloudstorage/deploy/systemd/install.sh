#!/bin/bash
# Install or update the e3 agent deploy sync systemd timer on production.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="e3-agent-deploy-sync"

install -m 0644 "$SCRIPT_DIR/${SERVICE_NAME}.service" "/etc/systemd/system/${SERVICE_NAME}.service"
install -m 0644 "$SCRIPT_DIR/${SERVICE_NAME}.timer" "/etc/systemd/system/${SERVICE_NAME}.timer"

systemctl daemon-reload
systemctl enable "${SERVICE_NAME}.timer"
systemctl restart "${SERVICE_NAME}.timer"

echo "Timer status:"
systemctl status "${SERVICE_NAME}.timer" --no-pager || true
