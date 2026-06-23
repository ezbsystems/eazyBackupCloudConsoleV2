#!/usr/bin/env bash
# Build golden LXC template for ms365-backup-worker on Proxmox.
# Run inside a Debian 12 LXC as root (VMID e.g. 9010) before: pct template <vmid>
#
# Default fleet CT sizing (create on Proxmox before running this script):
#   8192 MB RAM, 4 cores, 32 GB rootfs — see deploy/proxmox/README.md
#
# Prerequisites on the container:
#   - Repo at /opt/ms365-backup-worker (temporary; removed at finalize)
#   - Compiled binary at /tmp/ms365-backup-worker (from Worker Fleet build or make build)
#
# Usage:
#   MS365_WORKER_TOKEN=... MS365_WORKER_API_BASE=... bash deploy/proxmox/template-setup.sh
#   STRIP_SOURCE=1 MS365_WORKER_TOKEN=... bash deploy/proxmox/template-setup.sh
set -euo pipefail

REPO_ROOT="${REPO_ROOT:-/opt/ms365-backup-worker}"
WORKER_USER="${WORKER_USER:-ms365worker}"
INSTALL_PREFIX="${INSTALL_PREFIX:-/var/lib/ms365-backup-worker/bin}"
DATA_ROOT="${DATA_ROOT:-/var/lib/ms365-backup-worker}"
CONFIG_DIR="${CONFIG_DIR:-/etc/ms365-backup-worker}"
SYSTEMD_UNIT="${SYSTEMD_UNIT:-/etc/systemd/system/ms365-backup-worker.service}"
SYSTEMD_DROPIN_DIR="/etc/systemd/system/ms365-backup-worker.service.d"
BINARY_SRC="${BINARY_SRC:-/tmp/ms365-backup-worker}"

die() { echo "ERROR: $*" >&2; exit 1; }

echo "==> Installing base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl systemd >/dev/null

echo "==> Creating service user and data directories"
id "$WORKER_USER" &>/dev/null || useradd --system --home "$DATA_ROOT" --shell /usr/sbin/nologin "$WORKER_USER"
mkdir -p \
  "$DATA_ROOT/runs" \
  "$DATA_ROOT/kopia" \
  "$INSTALL_PREFIX" \
  "$CONFIG_DIR" \
  "$SYSTEMD_DROPIN_DIR"
chown -R "$WORKER_USER:$WORKER_USER" "$DATA_ROOT"
chmod 0755 "$INSTALL_PREFIX" "$DATA_ROOT/runs" "$DATA_ROOT/kopia"

echo "==> Installing worker binary"
[[ -f "$BINARY_SRC" ]] || die "Place compiled binary at $BINARY_SRC first"
systemctl stop ms365-backup-worker 2>/dev/null || true
install -m 0755 "$BINARY_SRC" "$INSTALL_PREFIX/ms365-backup-worker"
chown "$WORKER_USER:$WORKER_USER" "$INSTALL_PREFIX/ms365-backup-worker"

LICENSE_SRC="$REPO_ROOT/THIRD_PARTY_LICENSES.txt"
[[ -f "$LICENSE_SRC" ]] || die "Missing $LICENSE_SRC — run: make licenses"
install -m 0644 "$LICENSE_SRC" "$INSTALL_PREFIX/THIRD_PARTY_LICENSES.txt"

echo "==> Installing config (non-secret template; 8G/32G budget defaults)"
CONFIG_TEMPLATE="$REPO_ROOT/deploy/proxmox/config.yaml.template"
[[ -f "$CONFIG_TEMPLATE" ]] || CONFIG_TEMPLATE="$REPO_ROOT/config/config.yaml.example"
install -m 0640 -o root -g "$WORKER_USER" "$CONFIG_TEMPLATE" "$CONFIG_DIR/config.yaml"

echo "==> Installing systemd unit"
[[ -f "$REPO_ROOT/ms365-backup-worker.service" ]] || die "Missing $REPO_ROOT/ms365-backup-worker.service"
install -m 0644 "$REPO_ROOT/ms365-backup-worker.service" "$SYSTEMD_UNIT"

ENV_EXAMPLE="$REPO_ROOT/deploy/proxmox/environment.conf.example"
[[ -f "$ENV_EXAMPLE" ]] || die "Missing $ENV_EXAMPLE"
if [[ ! -f "$SYSTEMD_DROPIN_DIR/environment.conf" ]]; then
  install -m 0600 "$ENV_EXAMPLE" "$SYSTEMD_DROPIN_DIR/environment.conf"
fi

TOKEN="${MS365_WORKER_TOKEN:-}"
API_BASE="${MS365_WORKER_API_BASE:-https://accounts.eazybackup.ca/modules/addons/cloudstorage/api}"
if [[ "$API_BASE" != */modules/addons/cloudstorage/api ]]; then
  die "MS365_WORKER_API_BASE must end with /modules/addons/cloudstorage/api (got: ${API_BASE})"
fi
if [[ -n "$TOKEN" && "$TOKEN" != "CHANGE_ME" ]]; then
  sed -i "s|^Environment=MS365_WORKER_TOKEN=.*|Environment=MS365_WORKER_TOKEN=${TOKEN}|" "$SYSTEMD_DROPIN_DIR/environment.conf"
fi
if [[ -n "$API_BASE" ]]; then
  sed -i "s|^Environment=MS365_WORKER_API_BASE=.*|Environment=MS365_WORKER_API_BASE=${API_BASE}|" "$SYSTEMD_DROPIN_DIR/environment.conf"
fi
if grep -q 'CHANGE_ME' "$SYSTEMD_DROPIN_DIR/environment.conf" 2>/dev/null; then
  echo "WARN: Set MS365_WORKER_TOKEN in $SYSTEMD_DROPIN_DIR/environment.conf before templating"
fi

systemctl daemon-reload
systemctl enable ms365-backup-worker
systemctl stop ms365-backup-worker 2>/dev/null || true

if [[ "${STRIP_SOURCE:-0}" == "1" && -d "$REPO_ROOT" ]]; then
  echo "==> Removing build source tree $REPO_ROOT"
  rm -rf "$REPO_ROOT"
fi

rm -f "$BINARY_SRC"

cat <<EOF

==> Template ready

Before: pct template <vmid>
  1. Verify secrets in $SYSTEMD_DROPIN_DIR/environment.conf (mode 0600)
  2. Service is enabled but stopped; first clone start brings the worker online

Proxmox CT sizing for clones from this template:
  8192 MB RAM, 4 cores, 32 GB rootfs (local-lvm)
  config.yaml budgets match this size — raise both if you use larger CTs

Workers stream Graph → Kopia (no full-resource disk staging). TB tenants use sharding
across many modest workers, not larger containers.

Autoscale clones: ProxmoxProvisioner sets hostname + WHMCS registerProvisioning row (no API env=; worker adopts by hostname).
Routine binary updates: WHMCS Worker Fleet → Deployments (heartbeat pull).
EOF
