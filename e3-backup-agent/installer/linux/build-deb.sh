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
# Also ship next to install.sh so postinst enrollment can find the unit source.
install -m 644 "${LINUX_DIR}/e3-backup-agent.service" "$STAGING/usr/share/e3-backup-agent/e3-backup-agent.service"
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
Depends: systemd, debconf (>= 0.5) | debconf-2.0
EOF

cat > "$STAGING/DEBIAN/templates" <<'TEMPLATES'
Template: e3-backup-agent/enrollment_token
Type: password
Description: Enrollment token for e3 Backup Agent:
 Enter the one-time enrollment token from the eazyBackup portal
 (Enrollment Tokens). The agent enrolls automatically after install.

Template: e3-backup-agent/enrollment_token/empty
Type: error
Description: Enrollment token required
 An enrollment token is required to register this computer.
 Generate one in the eazyBackup portal under Enrollment Tokens,
 then reinstall or run:
  sudo TOKEN=YOUR_TOKEN dpkg -i e3-backup-agent-linux.deb
TEMPLATES

# Validate templates without touching the system debconf DB (Agent Builds run as www-data).
if command -v debconf-loadtemplate >/dev/null 2>&1; then
  _debconf_tmpdir="$(mktemp -d)"
  _debconf_rc="${_debconf_tmpdir}/debconf.conf"
  cat > "$_debconf_rc" <<EOF
Config: configdb
Templates: templatedb

Name: configdb
Driver: File
Mode: 644
Filename: ${_debconf_tmpdir}/config.dat

Name: templatedb
Driver: File
Mode: 644
Filename: ${_debconf_tmpdir}/templates.dat
EOF
  # #region agent log
  printf '{"sessionId":"bcd995","hypothesisId":"D","location":"build-deb.sh","message":"validating templates with private debconf db","data":{"tmpdir":"%s","user":"%s"},"timestamp":%s}\n' \
    "$_debconf_tmpdir" "$(id -un 2>/dev/null || echo unknown)" "$(date +%s000)" \
    >> /var/www/eazybackup.ca/.cursor/debug-bcd995.log 2>/dev/null || true
  # #endregion
  if ! DEBCONF_SYSTEMRC="$_debconf_rc" debconf-loadtemplate e3-backup-agent "$STAGING/DEBIAN/templates"; then
    rm -rf "$_debconf_tmpdir"
    die "Invalid DEBIAN/templates (debconf-loadtemplate failed)"
  fi
  # #region agent log
  printf '{"sessionId":"bcd995","hypothesisId":"D","location":"build-deb.sh","message":"templates validation ok","data":{"ok":true},"timestamp":%s}\n' \
    "$(date +%s000)" >> /var/www/eazybackup.ca/.cursor/debug-bcd995.log 2>/dev/null || true
  # #endregion
  rm -rf "$_debconf_tmpdir"
fi

cat > "$STAGING/DEBIAN/config" <<'CONFIG'
#!/bin/sh
set -e

. /usr/share/debconf/confmodule

CONF_DIR="/etc/e3-backup-agent"
CONF_FILE="${CONF_DIR}/agent.conf"
TOKEN_QUESTION="e3-backup-agent/enrollment_token"

is_enrolled() {
  [ -f "$CONF_FILE" ] \
    && grep -qE '^[[:space:]]*agent_uuid:[[:space:]]*' "$CONF_FILE" \
    && grep -qE '^[[:space:]]*agent_token:[[:space:]]*' "$CONF_FILE"
}

trim_token() {
  printf '%s' "$1" | tr -d '[:space:]'
}

if is_enrolled; then
  exit 0
fi

TOKEN="$(trim_token "${TOKEN:-}")"
if [ -z "$TOKEN" ] && [ -f "${CONF_DIR}/enrollment.token" ]; then
  TOKEN="$(trim_token "$(cat "${CONF_DIR}/enrollment.token")")"
fi

if [ -n "$TOKEN" ]; then
  # #region agent log
  printf '{"sessionId":"bcd995","hypothesisId":"B","location":"config","message":"token from env or file","data":{"hasToken":true},"timestamp":%s}\n' "$(date +%s000)" >> /tmp/e3-backup-agent-install-debug.ndjson 2>/dev/null || true
  # #endregion
  db_set "$TOKEN_QUESTION" "$TOKEN"
  exit 0
fi

attempt=0
while [ "$attempt" -lt 3 ]; do
  # #region agent log
  printf '{"sessionId":"bcd995","hypothesisId":"A","location":"config","message":"prompting via db_input","data":{"attempt":%s},"timestamp":%s}\n' "$attempt" "$(date +%s000)" >> /tmp/e3-backup-agent-install-debug.ndjson 2>/dev/null || true
  # #endregion
  db_input high "$TOKEN_QUESTION" || exit 1
  if ! db_go; then
    exit 1
  fi

  db_get "$TOKEN_QUESTION"
  TOKEN="$(trim_token "$RET")"
  if [ -n "$TOKEN" ]; then
    db_set "$TOKEN_QUESTION" "$TOKEN"
    exit 0
  fi

  db_input high e3-backup-agent/enrollment_token/empty || true
  db_go || true
  attempt=$((attempt + 1))
done

exit 1
CONFIG

cat > "$STAGING/DEBIAN/postinst" <<'POSTINST'
#!/bin/sh
set -e

. /usr/share/debconf/confmodule

CONF_DIR="/etc/e3-backup-agent"
CONF_FILE="${CONF_DIR}/agent.conf"
INSTALL_SH="/usr/share/e3-backup-agent/install.sh"
RUN_DIR="/var/lib/e3-backup-agent/runs"
TOKEN_QUESTION="e3-backup-agent/enrollment_token"

trim_token() {
  printf '%s' "$1" | tr -d '[:space:]'
}

clear_debconf_token() {
  db_set "$TOKEN_QUESTION" ''
  db_fset "$TOKEN_QUESTION" seen false 2>/dev/null || true
}

mkdir -p "$CONF_DIR" "$RUN_DIR"
chmod 700 "$CONF_DIR" "$(dirname "$RUN_DIR")" "$RUN_DIR" 2>/dev/null || true

if [ -f "$CONF_FILE" ] && grep -qE '^[[:space:]]*agent_uuid:' "$CONF_FILE" \
   && grep -qE '^[[:space:]]*agent_token:' "$CONF_FILE"; then
  echo "e3-backup-agent: existing enrolled config preserved."
  clear_debconf_token
  systemctl daemon-reload
  systemctl enable e3-backup-agent 2>/dev/null || true
  systemctl restart e3-backup-agent 2>/dev/null || true
  exit 0
fi

TOKEN="$(trim_token "${TOKEN:-}")"
if [ -z "$TOKEN" ] && [ -f "${CONF_DIR}/enrollment.token" ]; then
  TOKEN="$(trim_token "$(cat "${CONF_DIR}/enrollment.token")")"
fi
if [ -z "$TOKEN" ]; then
  db_get "$TOKEN_QUESTION"
  TOKEN="$(trim_token "$RET")"
fi

# #region agent log
printf '{"sessionId":"bcd995","hypothesisId":"C","location":"postinst","message":"token resolved","data":{"hasToken":%s},"timestamp":%s}\n' \
  "$([ -n "$TOKEN" ] && echo true || echo false)" "$(date +%s000)" \
  >> /tmp/e3-backup-agent-install-debug.ndjson 2>/dev/null || true
# #endregion

if [ -z "$TOKEN" ]; then
  echo "e3-backup-agent: ERROR: No enrollment token provided." >&2
  echo "Generate a token in the eazyBackup portal (Enrollment Tokens) and reinstall, or run:" >&2
  echo "  sudo TOKEN=YOUR_TOKEN dpkg -i e3-backup-agent-linux.deb" >&2
  clear_debconf_token
  exit 1
fi

if [ ! -x "$INSTALL_SH" ]; then
  echo "e3-backup-agent: ERROR: installer script missing at ${INSTALL_SH}" >&2
  clear_debconf_token
  exit 1
fi

API_BASE="${API_BASE:-https://accounts.eazybackup.ca/modules/addons/cloudstorage/api}"
"$INSTALL_SH" --token "$TOKEN" --api "$API_BASE" --binary /usr/local/bin/e3-backup-agent --noninteractive

clear_debconf_token
exit 0
POSTINST

cat > "$STAGING/DEBIAN/prerm" <<'PRERM'
#!/bin/sh
set -e
if [ "$1" = "remove" ] || [ "$1" = "deconfigure" ]; then
  systemctl stop e3-backup-agent 2>/dev/null || true
  systemctl disable e3-backup-agent 2>/dev/null || true
fi
PRERM

cat > "$STAGING/DEBIAN/postrm" <<'POSTRM'
#!/bin/sh
set -e

if [ "$1" = "purge" ]; then
  . /usr/share/debconf/confmodule
  db_purge
fi
POSTRM

chmod 755 "$STAGING/DEBIAN/config" "$STAGING/DEBIAN/postinst" "$STAGING/DEBIAN/prerm" "$STAGING/DEBIAN/postrm"

dpkg-deb --build "$STAGING" "$OUTPUT"
cp -f "$OUTPUT" "$OUTPUT_LATEST"
echo "Built: $OUTPUT"
echo "       $OUTPUT_LATEST"
