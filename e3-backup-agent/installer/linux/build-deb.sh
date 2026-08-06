#!/usr/bin/env bash
# Build e3-backup-agent .deb package (amd64).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
LINUX_DIR="$(cd "$(dirname "$0")" && pwd)"
VERSION="${VERSION:-dev}"
PKG_NAME="e3-backup-agent"
ARCH="amd64"
STAGING="${ROOT}/bin/deb-staging"
OUTPUT="${ROOT}/bin/${PKG_NAME}-linux_${VERSION}_${ARCH}.deb"
OUTPUT_LATEST="${ROOT}/bin/e3-backup-agent-linux.deb"

die() { echo "build-deb: ERROR: $*" >&2; exit 1; }

[[ -f "${ROOT}/bin/e3-backup-agent" ]] || die "Run 'make build' first."

rm -rf "$STAGING"
mkdir -p "$STAGING/DEBIAN"
mkdir -p "$STAGING/usr/local/bin"
mkdir -p "$STAGING/lib/systemd/system"
mkdir -p "$STAGING/etc/e3-backup-agent"
mkdir -p "$STAGING/usr/share/e3-backup-agent"

install -m 755 "${ROOT}/bin/e3-backup-agent" "$STAGING/usr/local/bin/e3-backup-agent"
install -m 644 "${LINUX_DIR}/e3-backup-agent.service" "$STAGING/lib/systemd/system/e3-backup-agent.service"
install -m 644 "${LINUX_DIR}/agent.conf.template" "$STAGING/usr/share/e3-backup-agent/agent.conf.template"
install -m 755 "${LINUX_DIR}/install.sh" "$STAGING/usr/share/e3-backup-agent/install.sh"

cat > "$STAGING/DEBIAN/control" <<EOF
Package: ${PKG_NAME}
Version: ${VERSION}
Section: utils
Priority: optional
Architecture: ${ARCH}
Maintainer: eazyBackup <support@eazybackup.ca>
Description: e3 Cloud Backup local agent
 Installs the e3 backup agent service for Linux endpoints.
Depends: systemd
EOF

cat > "$STAGING/DEBIAN/postinst" <<'POSTINST'
#!/bin/sh
set -e

CONF_DIR="/etc/e3-backup-agent"
CONF_FILE="${CONF_DIR}/agent.conf"
INSTALL_SH="/usr/share/e3-backup-agent/install.sh"
RUN_DIR="/var/lib/e3-backup-agent/runs"

mkdir -p "$CONF_DIR" "$RUN_DIR"
chmod 700 "$CONF_DIR" "$(dirname "$RUN_DIR")" "$RUN_DIR" 2>/dev/null || true

TOKEN="${TOKEN:-}"
if [ -z "$TOKEN" ] && [ -f "${CONF_DIR}/enrollment.token" ]; then
  TOKEN="$(tr -d '[:space:]' < "${CONF_DIR}/enrollment.token")"
fi

if [ -f "$CONF_FILE" ] && grep -qE '^[[:space:]]*agent_uuid:' "$CONF_FILE" \
   && grep -qE '^[[:space:]]*agent_token:' "$CONF_FILE"; then
  echo "e3-backup-agent: existing enrolled config preserved."
elif [ -x "$INSTALL_SH" ]; then
  API_BASE="${API_BASE:-https://accounts.eazybackup.ca/modules/addons/cloudstorage/api}"
  if [ -n "$TOKEN" ]; then
    "$INSTALL_SH" --token "$TOKEN" --api "$API_BASE" --binary /usr/local/bin/e3-backup-agent
  else
    "$INSTALL_SH" --api "$API_BASE" --binary /usr/local/bin/e3-backup-agent
  fi
  exit 0
fi

systemctl daemon-reload
if [ -f "$CONF_FILE" ]; then
  systemctl enable e3-backup-agent 2>/dev/null || true
  systemctl restart e3-backup-agent 2>/dev/null || true
fi
POSTINST

cat > "$STAGING/DEBIAN/prerm" <<'PRERM'
#!/bin/sh
set -e
if [ "$1" = "remove" ] || [ "$1" = "deconfigure" ]; then
  systemctl stop e3-backup-agent 2>/dev/null || true
  systemctl disable e3-backup-agent 2>/dev/null || true
fi
PRERM

chmod 755 "$STAGING/DEBIAN/postinst" "$STAGING/DEBIAN/prerm"

dpkg-deb --build "$STAGING" "$OUTPUT"
cp -f "$OUTPUT" "$OUTPUT_LATEST"
echo "Built: $OUTPUT"
echo "       $OUTPUT_LATEST"
