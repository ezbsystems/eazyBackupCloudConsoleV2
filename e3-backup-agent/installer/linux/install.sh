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

# Populated by enroll_with_api on success.
ENROLL_AGENT_UUID=""
ENROLL_AGENT_TOKEN=""
ENROLL_CLIENT_ID=""
ENROLL_API_BASE_URL=""

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

load_token_from_conf() {
  if [[ -n "$TOKEN" ]] || [[ ! -f "$CONF_FILE" ]]; then
    return 0
  fi
  if ! conf_has_enrollment_token; then
    return 0
  fi
  TOKEN="$(grep -E '^[[:space:]]*enrollment_token:[[:space:]]*' "$CONF_FILE" \
    | head -1 \
    | sed -E 's/^[[:space:]]*enrollment_token:[[:space:]]*"?([^"#[:space:]]*)"?.*$/\1/' \
    | tr -d '[:space:]')"
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
  load_token_from_conf

  if [[ -n "$TOKEN" ]]; then
    return 0
  fi

  if is_interactive; then
    prompt_enrollment_token
    return 0
  fi

  die_no_token_noninteractive
}

parse_enroll_json() {
  local json_file="$1"
  if command -v python3 >/dev/null 2>&1; then
    python3 - "$json_file" <<'PY'
import json, sys

path = sys.argv[1]
try:
    with open(path, encoding="utf-8") as fh:
        data = json.load(fh)
except (json.JSONDecodeError, OSError):
    print("FAIL\tInvalid enrollment response from server")
    sys.exit(1)

status = data.get("status", "")
if status != "success":
    msg = data.get("message") or "Enrollment failed"
    print(f"FAIL\t{msg}")
    sys.exit(1)

agent_uuid = str(data.get("agent_uuid", "")).strip()
agent_token = str(data.get("agent_token", "")).strip()
if not agent_uuid or not agent_token:
    print("FAIL\tEnrollment response missing agent credentials")
    sys.exit(1)

client_id = str(data.get("client_id", "")).strip()
api_base_url = str(data.get("api_base_url", "")).strip()
print(f"agent_uuid\t{agent_uuid}")
print(f"agent_token\t{agent_token}")
print(f"client_id\t{client_id}")
print(f"api_base_url\t{api_base_url}")
PY
    return $?
  fi

  if command -v jq >/dev/null 2>&1; then
    local status message
    status="$(jq -r '.status // empty' "$json_file")"
    if [[ "$status" != "success" ]]; then
      message="$(jq -r '.message // "Enrollment failed"' "$json_file")"
      printf 'FAIL\t%s\n' "$message"
      return 1
    fi
    ENROLL_AGENT_UUID="$(jq -r '.agent_uuid // empty' "$json_file" | tr -d '[:space:]')"
    ENROLL_AGENT_TOKEN="$(jq -r '.agent_token // empty' "$json_file" | tr -d '[:space:]')"
    ENROLL_CLIENT_ID="$(jq -r '.client_id // empty' "$json_file" | tr -d '[:space:]')"
    ENROLL_API_BASE_URL="$(jq -r '.api_base_url // empty' "$json_file" | tr -d '[:space:]')"
    if [[ -z "$ENROLL_AGENT_UUID" ]] || [[ -z "$ENROLL_AGENT_TOKEN" ]]; then
      printf 'FAIL\t%s\n' "Enrollment response missing agent credentials"
      return 1
    fi
    return 0
  fi

  die "python3 or jq is required to parse enrollment response."
}

enroll_with_api() {
  local token="$1"
  local hostname enroll_url tmp_resp http_code line key value

  [[ -n "$token" ]] || die "Internal error: enroll_with_api called without token."

  if [[ -z "$DEVICE_NAME" ]]; then
    DEVICE_NAME="$(hostname -s 2>/dev/null || hostname)"
  fi
  hostname="$(hostname -f 2>/dev/null || hostname -s 2>/dev/null || hostname)"
  enroll_url="${API_BASE%/}/agent_enroll.php"

  log "Enrolling with ${enroll_url} ..."

  tmp_resp="$(mktemp)"
  trap 'rm -f "$tmp_resp"' RETURN

  if command -v curl >/dev/null 2>&1; then
    http_code="$(
      curl -sS -o "$tmp_resp" -w '%{http_code}' \
        -X POST "$enroll_url" \
        --data-urlencode "token=${token}" \
        --data-urlencode "hostname=${hostname}" \
        --data-urlencode "device_name=${DEVICE_NAME}" \
        --data-urlencode "agent_os=linux" \
        2>/dev/null || echo "000"
    )"
  elif command -v wget >/dev/null 2>&1; then
    local post_data
    if command -v python3 >/dev/null 2>&1; then
      post_data="$(
        TOKEN="$token" HOSTNAME="$hostname" DEVICE="$DEVICE_NAME" python3 - <<'PY'
import os, urllib.parse
print(urllib.parse.urlencode({
    "token": os.environ["TOKEN"],
    "hostname": os.environ["HOSTNAME"],
    "device_name": os.environ["DEVICE"],
    "agent_os": "linux",
}))
PY
      )"
    else
      post_data="token=${token}&hostname=${hostname}&device_name=${DEVICE_NAME}&agent_os=linux"
    fi
    if ! wget -q -O "$tmp_resp" \
      --header="Content-Type: application/x-www-form-urlencoded" \
      --post-data="$post_data" \
      "$enroll_url" 2>/dev/null; then
      http_code="000"
    else
      http_code="200"
    fi
  else
    die "curl or wget is required for enrollment."
  fi

  if [[ ! -s "$tmp_resp" ]]; then
    die "Enrollment request failed (HTTP ${http_code:-unknown}). Check network connectivity and API URL: ${API_BASE}"
  fi

  if command -v python3 >/dev/null 2>&1; then
    local parsed rc=0
    parsed="$(parse_enroll_json "$tmp_resp")" || rc=$?
    if [[ $rc -ne 0 ]] || [[ "$parsed" == FAIL* ]]; then
      local fail_msg="${parsed#FAIL	}"
      [[ "$fail_msg" == "$parsed" ]] && fail_msg="Enrollment failed (HTTP ${http_code})"
      die "Enrollment failed: ${fail_msg}"
    fi
    while IFS=$'\t' read -r key value; do
      case "$key" in
        agent_uuid) ENROLL_AGENT_UUID="$value" ;;
        agent_token) ENROLL_AGENT_TOKEN="$value" ;;
        client_id) ENROLL_CLIENT_ID="$value" ;;
        api_base_url) ENROLL_API_BASE_URL="$value" ;;
      esac
    done <<< "$parsed"
  else
    if ! parse_enroll_json "$tmp_resp"; then
      die "Enrollment failed (HTTP ${http_code})."
    fi
  fi

  [[ -n "$ENROLL_AGENT_UUID" ]] && [[ -n "$ENROLL_AGENT_TOKEN" ]] \
    || die "Enrollment response missing agent credentials."
  log "Enrollment succeeded (agent_uuid=${ENROLL_AGENT_UUID})."
}

write_enrolled_conf() {
  local api_quoted device_quoted client_quoted uuid_quoted token_quoted enroll_block api_url

  if [[ -z "$DEVICE_NAME" ]]; then
    DEVICE_NAME="$(hostname -s 2>/dev/null || hostname)"
  fi

  api_url="$API_BASE"
  if [[ -n "$ENROLL_API_BASE_URL" ]]; then
    api_url="$ENROLL_API_BASE_URL"
  fi

  api_quoted="$(yaml_quote "$api_url")"
  device_quoted="$(yaml_quote "$DEVICE_NAME")"
  uuid_quoted="$(yaml_quote "$ENROLL_AGENT_UUID")"
  token_quoted="$(yaml_quote "$ENROLL_AGENT_TOKEN")"
  enroll_block="agent_uuid: ${uuid_quoted}
agent_token: ${token_quoted}"
  if [[ -n "$ENROLL_CLIENT_ID" ]]; then
    client_quoted="$(yaml_quote "$ENROLL_CLIENT_ID")"
    enroll_block="${enroll_block}
client_id: ${client_quoted}"
  fi

  mkdir -p "$CONF_DIR"
  chmod 700 "$CONF_DIR"

  local template
  template="$(script_dir)/agent.conf.template"
  [[ -f "$template" ]] || die "Missing template: ${template}"

  sed \
    -e "s|__API_BASE_URL__|${api_url//|/\\|}|g" \
    -e "s|__DEVICE_NAME__|${DEVICE_NAME//|/\\|}|g" \
    -e "s|__ENROLLMENT_BLOCK__|${enroll_block}|g" \
    "$template" > "$CONF_FILE"

  chmod 600 "$CONF_FILE"
  chown root:root "$CONF_FILE"
  log "Wrote enrolled ${CONF_FILE}"
}

write_conf() {
  mkdir -p "$CONF_DIR"
  chmod 700 "$CONF_DIR"

  if conf_has_enrollment; then
    log "Existing enrolled agent.conf found — preserving credentials."
    if [[ -n "$TOKEN" ]]; then
      log "Note: --token ignored because agent is already enrolled."
    fi
    return 0
  fi

  [[ -n "$TOKEN" ]] || die "No enrollment token available after resolution."

  log "Enrollment API base: ${API_BASE}"
  enroll_with_api "$TOKEN"
  write_enrolled_conf
}

install_binary() {
  mkdir -p "$(dirname "$BIN_DEST")"
  if [[ -n "$BINARY_PATH" ]]; then
    [[ -f "$BINARY_PATH" ]] || die "Binary not found: ${BINARY_PATH}"
    # .deb postinst passes --binary pointing at the already-unpacked destination.
    if [[ "$BINARY_PATH" -ef "$BIN_DEST" ]] || [[ "$BINARY_PATH" == "$BIN_DEST" ]]; then
      chmod 755 "$BIN_DEST"
      chown root:root "$BIN_DEST"
      log "Binary already at ${BIN_DEST} — skipping copy"
      return
    fi
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
  local unit_src packaged="/lib/systemd/system/${UNIT_SRC_NAME}"
  unit_src="$(script_dir)/${UNIT_SRC_NAME}"

  # .deb already installs the unit under /lib/systemd/system; do not fail if the
  # share-directory copy is absent.
  if [[ ! -f "$unit_src" ]] && [[ -f "$packaged" ]]; then
    log "Using packaged systemd unit ${packaged}"
    return 0
  fi
  [[ -f "$unit_src" ]] || die "Missing unit file: ${unit_src}"
  if [[ -f "$UNIT_DEST" ]] && { [[ "$unit_src" -ef "$UNIT_DEST" ]] || [[ "$unit_src" == "$UNIT_DEST" ]]; }; then
    log "Systemd unit already at ${UNIT_DEST} — skipping copy"
    return 0
  fi
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
}

verify_install_success() {
  local waited=0
  local max_wait=15

  while [[ "$waited" -lt "$max_wait" ]]; do
    if systemctl is-active --quiet "$SERVICE_NAME" 2>/dev/null && conf_has_enrollment; then
      log "Service ${SERVICE_NAME} is active."
      log "Enrollment confirmed. The agent should appear in the portal within ~10 seconds."
      return 0
    fi
    sleep 1
    waited=$((waited + 1))
  done

  if ! conf_has_enrollment; then
    die "Enrollment credentials missing from ${CONF_FILE} after install."
  fi
  die "Service ${SERVICE_NAME} failed to start after enrollment. Check: journalctl -u ${SERVICE_NAME} -n 50"
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
  verify_install_success
}

main "$@"
