#!/usr/bin/env bash
# e3-backup-agent Linux installer
# Installs the agent binary, systemd unit, and agent.conf; optionally enrolls via token.
set -euo pipefail

VERSION="dev"
BINARY_URL_DEFAULT="https://accounts.eazybackup.ca/client_installer/e3-backup-agent-linux"
API_BASE_DEFAULT="https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"

BIN_DEST="/usr/local/bin/e3-backup-agent"
CONF_DIR="/etc/e3-backup-agent"
CONF_FILE="${CONF_DIR}/agent.conf"
RUN_DIR="/var/lib/e3-backup-agent/runs"
UNIT_SRC_NAME="e3-backup-agent.service"
UNIT_DEST="/etc/systemd/system/e3-backup-agent.service"
SERVICE_NAME="e3-backup-agent"

TOKEN=""
API_BASE="${API_BASE_DEFAULT}"
DEVICE_NAME=""
BINARY_PATH=""
BINARY_URL="${BINARY_URL_DEFAULT}"
UNINSTALL=0
SHOW_VERSION=0
NONINTERACTIVE=0

log() { printf 'e3-backup-agent: %s\n' "$*"; }
die() { log "ERROR: $*" >&2; exit 1; }

usage() {
  cat <<'EOF'
Usage: install.sh [options]

Install the e3 Backup Agent on Linux (systemd required).

Options:
  --token TOKEN         One-time enrollment token from the eazyBackup portal
  --api URL             API base URL (default: accounts.eazybackup.ca)
  --device-name NAME    Friendly device name (default: hostname)
  --binary PATH         Use a local agent binary instead of downloading
  --binary-url URL      Download URL for the agent binary
  --noninteractive      Never prompt; require --token or enrollment.token
  --version             Show installer version and exit
  --uninstall           Stop service, remove unit and binary (keeps agent.conf)
  -h, --help            Show this help

Examples:
  sudo bash install.sh --token YOUR_TOKEN
  curl -fsSL .../e3-backup-agent-linux-install.sh | sudo bash -s -- --token YOUR_TOKEN
  sudo dpkg -i e3-backup-agent-linux.deb
EOF
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --token) TOKEN="${2:-}"; shift 2 ;;
      --api) API_BASE="${2:-}"; shift 2 ;;
      --device-name) DEVICE_NAME="${2:-}"; shift 2 ;;
      --binary) BINARY_PATH="${2:-}"; shift 2 ;;
      --binary-url) BINARY_URL="${2:-}"; shift 2 ;;
      --version) SHOW_VERSION=1; shift ;;
      --uninstall) UNINSTALL=1; shift ;;
      --noninteractive) NONINTERACTIVE=1; shift ;;
      -h|--help) usage; exit 0 ;;
      *) die "Unknown option: $1 (use --help)" ;;
    esac
  done
}

require_root() {
  [[ "$(id -u)" -eq 0 ]] || die "Run as root (sudo)."
}

require_systemd() {
  command -v systemctl >/dev/null 2>&1 || die "systemd is required."
  [[ -d /run/systemd/system || -L /run/systemd/system ]] || die "systemd does not appear to be running."
}

script_dir() {
  local src="${BASH_SOURCE[0]}"
  while [[ -L "$src" ]]; do
    local dir
    dir="$(cd -P "$(dirname "$src")" && pwd)"
    src="$(readlink "$src")"
    [[ "$src" != /* ]] && src="${dir}/${src}"
  done
  cd -P "$(dirname "$src")" && pwd
}

yaml_quote() {
  local s="$1"
  s="${s//\\/\\\\}"
  s="${s//\"/\\\"}"
  printf '"%s"' "$s"
}

conf_has_enrollment() {
  [[ -f "$CONF_FILE" ]] || return 1
  grep -qE '^[[:space:]]*agent_uuid:[[:space:]]*' "$CONF_FILE" \
    && grep -qE '^[[:space:]]*agent_token:[[:space:]]*' "$CONF_FILE"
}

conf_has_enrollment_token() {
  [[ -f "$CONF_FILE" ]] || return 1
  grep -qE '^[[:space:]]*enrollment_token:[[:space:]]*' "$CONF_FILE"
}

is_interactive() {
  [[ "$NONINTERACTIVE" -eq 0 ]] || return 1
  [[ "${DEBIAN_FRONTEND:-}" != "noninteractive" ]] || return 1
  [[ -r /dev/tty && -w /dev/tty ]] || return 1
  stty -F /dev/tty size >/dev/null 2>&1
}

load_enrollment_token_file() {
  local token_file="${CONF_DIR}/enrollment.token"
  if [[ -z "$TOKEN" ]] && [[ -f "$token_file" ]]; then
    TOKEN="$(tr -d '[:space:]' < "$token_file")"
  fi
}

die_no_token_noninteractive() {
  die "No enrollment token provided.

For silent install, pass a token from the eazyBackup portal (Enrollment Tokens):
  sudo bash install.sh --token YOUR_TOKEN
  sudo TOKEN=YOUR_TOKEN dpkg -i e3-backup-agent-linux.deb

Or run the installer in an interactive terminal to be prompted for your token."
}

prompt_enrollment_token() {
  local attempt=0
  local max_attempts=3
  local input=""

  exec 3<>/dev/tty || die "Cannot open /dev/tty for enrollment prompt."

  while [[ "$attempt" -lt "$max_attempts" ]]; do
    {
      echo ""
      echo "========================================"
      echo "  E3 Backup Agent — Enrollment"
      echo "========================================"
      echo "  Paste the enrollment token from the"
      echo "  eazyBackup portal (Enrollment Tokens)."
      echo ""
      echo -n "  Token: "
    } >&3

    if ! IFS= read -r input <&3; then
      exec 3<&-
      exec 3>&-
      die "Failed to read enrollment token."
    fi

    input="$(printf '%s' "$input" | tr -d '[:space:]')"
    if [[ -z "$input" ]]; then
      {
        echo ""
        echo "  Token cannot be empty."
      } >&3
      attempt=$((attempt + 1))
      continue
    fi

    if [[ ! "$input" =~ ^[0-9a-fA-F]+$ ]] || [[ ${#input} -lt 16 ]]; then
      {
        echo ""
        echo "  Note: token format looks unusual; continuing anyway."
      } >&3
    fi

    TOKEN="$input"
    exec 3<&-
    exec 3>&-
    return 0
  done

  exec 3<&- 2>/dev/null || true
  exec 3>&- 2>/dev/null || true
  die "No enrollment token entered after ${max_attempts} attempts. Generate one in the eazyBackup portal under Enrollment Tokens."
}

resolve_enrollment_token() {
  if conf_has_enrollment; then
    return 0
  fi

  load_enrollment_token_file

  if [[ -n "$TOKEN" ]]; then
    return 0
  fi

  if conf_has_enrollment_token; then
    return 0
  fi

  if is_interactive; then
    prompt_enrollment_token
    return 0
  fi

  die_no_token_noninteractive
}

write_conf() {
  local api_quoted device_quoted enroll_block=""
  api_quoted="$(yaml_quote "$API_BASE")"
  if [[ -z "$DEVICE_NAME" ]]; then
    DEVICE_NAME="$(hostname -s 2>/dev/null || hostname)"
  fi
  device_quoted="$(yaml_quote "$DEVICE_NAME")"

  if [[ -n "$TOKEN" ]]; then
    enroll_block="enrollment_token: $(yaml_quote "$TOKEN")"
  fi

  mkdir -p "$CONF_DIR"
  chmod 700 "$CONF_DIR"

  if conf_has_enrollment; then
    log "Existing enrolled agent.conf found — preserving credentials."
    if [[ -n "$TOKEN" ]]; then
      log "Note: --token ignored because agent is already enrolled."
    fi
    return 0
  fi

  if [[ -z "$TOKEN" ]] && [[ ! -f "$CONF_FILE" ]]; then
    die "No enrollment token available after resolution."
  fi

  if [[ -f "$CONF_FILE" ]] && [[ -z "$TOKEN" ]]; then
    if conf_has_enrollment_token; then
      log "Using existing agent.conf with enrollment_token."
      chmod 600 "$CONF_FILE"
      chown root:root "$CONF_FILE"
      return 0
    fi
    die "agent.conf exists but has no enrollment credentials. Add enrollment_token or use --token."
  fi

  local template
  template="$(script_dir)/agent.conf.template"
  [[ -f "$template" ]] || die "Missing template: ${template}"

  sed \
    -e "s|__API_BASE_URL__|${API_BASE//|/\\|}|g" \
    -e "s|__DEVICE_NAME__|${DEVICE_NAME//|/\\|}|g" \
    -e "s|__ENROLLMENT_BLOCK__|${enroll_block}|g" \
    "$template" > "$CONF_FILE"

  chmod 600 "$CONF_FILE"
  chown root:root "$CONF_FILE"
  log "Wrote ${CONF_FILE}"
}

install_binary() {
  mkdir -p "$(dirname "$BIN_DEST")"
  if [[ -n "$BINARY_PATH" ]]; then
    [[ -f "$BINARY_PATH" ]] || die "Binary not found: ${BINARY_PATH}"
    install -m 755 -o root -g root "$BINARY_PATH" "$BIN_DEST"
    log "Installed binary from ${BINARY_PATH}"
    return
  fi

  local tmp
  tmp="$(mktemp)"
  trap 'rm -f "$tmp"' RETURN
  log "Downloading agent from ${BINARY_URL} ..."
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL -o "$tmp" "$BINARY_URL"
  elif command -v wget >/dev/null 2>&1; then
    wget -q -O "$tmp" "$BINARY_URL"
  else
    die "curl or wget required to download the agent binary."
  fi
  install -m 755 -o root -g root "$tmp" "$BIN_DEST"
  log "Installed binary to ${BIN_DEST}"
}

install_unit() {
  local unit_src
  unit_src="$(script_dir)/${UNIT_SRC_NAME}"
  [[ -f "$unit_src" ]] || die "Missing unit file: ${unit_src}"
  install -m 644 -o root -g root "$unit_src" "$UNIT_DEST"
  log "Installed systemd unit ${UNIT_DEST}"
}

prepare_dirs() {
  mkdir -p "$RUN_DIR"
  chmod 700 "$(dirname "$RUN_DIR")" "$RUN_DIR" 2>/dev/null || true
  chown root:root "$(dirname "$RUN_DIR")" "$RUN_DIR" 2>/dev/null || true
}

start_service() {
  systemctl daemon-reload
  systemctl enable "$SERVICE_NAME"
  systemctl restart "$SERVICE_NAME"
  log "Service ${SERVICE_NAME} enabled and started."
  log "The agent should appear in the portal within ~10 seconds."
}

do_uninstall() {
  if systemctl is-active --quiet "$SERVICE_NAME" 2>/dev/null; then
    systemctl stop "$SERVICE_NAME" || true
  fi
  systemctl disable "$SERVICE_NAME" 2>/dev/null || true
  rm -f "$UNIT_DEST"
  rm -f "$BIN_DEST"
  systemctl daemon-reload 2>/dev/null || true
  log "Uninstalled ${SERVICE_NAME} (agent.conf preserved at ${CONF_FILE})."
}

main() {
  parse_args "$@"
  if [[ "$SHOW_VERSION" -eq 1 ]]; then
    echo "e3-backup-agent-installer ${VERSION}"
    exit 0
  fi
  require_root
  require_systemd

  if [[ "$UNINSTALL" -eq 1 ]]; then
    do_uninstall
    exit 0
  fi

  install_binary
  resolve_enrollment_token
  write_conf
  install_unit
  prepare_dirs
  start_service
}

main "$@"
