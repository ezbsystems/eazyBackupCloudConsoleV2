#!/usr/bin/env bash
set -euo pipefail

export PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin

LOOKBACK_MIN="${LOOKBACK_MIN:-6}"
ALERT_COOLDOWN_MIN="${ALERT_COOLDOWN_MIN:-30}"
ALERT_TO="${ALERT_TO:-root}"

# AWS SES defaults aligned with /var/www/eazybackup.ca/backup_whmcs_db.sh
SES_ENABLED="${SES_ENABLED:-1}"
SES_REGION="${SES_REGION:-us-west-2}"
SES_SENDER="${SES_SENDER:-postmaster@eazybackup.ca}"
SES_RECIPIENT="${SES_RECIPIENT:-services@eazybackup.ca}"

# Patterns that indicate worker health issues likely to impact notifications.
PATTERN='Could not resolve host|Heartbeat ERROR|WS error|Auth failed|No auth response|Non-JSON frame'

STATE_DIR="/tmp/eazybackup-ws-health"
mkdir -p "$STATE_DIR"

now_epoch="$(date +%s)"
hostname_short="$(hostname -s 2>/dev/null || hostname)"

units=(
  "eazybackup-comet-ws@cometbackup.service"
  "eazybackup-comet-ws@obc.service"
)

filter_hits() {
  local input="$1"
  if command -v rg >/dev/null 2>&1; then
    printf '%s\n' "$input" | rg -i "$PATTERN" || true
  else
    printf '%s\n' "$input" | grep -E -i "$PATTERN" || true
  fi
}

send_alert_via_ses() {
  local subject="$1"
  local body="$2"

  [[ "$SES_ENABLED" == "1" ]] || return 1
  command -v aws >/dev/null 2>&1 || return 1
  [[ -n "$SES_SENDER" && -n "$SES_RECIPIENT" ]] || return 1

  local aws_out
  if ! aws_out=$(aws ses send-email \
      --region "$SES_REGION" \
      --from "$SES_SENDER" \
      --destination "ToAddresses=$SES_RECIPIENT" \
      --message "Subject={Data=\"$subject\"},Body={Text={Data=\"$body\"}}" \
      2>&1); then
    logger -p daemon.err -t eb-ws-health-alert -- "SES send failed: ${aws_out}"
    return 1
  fi

  logger -p daemon.info -t eb-ws-health-alert -- "SES alert sent to ${SES_RECIPIENT}"
  return 0
}

send_alert() {
  local unit="$1"
  local hit_lines="$2"

  local subject="[ALERT] WS worker health issue: ${unit} on ${hostname_short}"
  local body
  body=$(cat <<EOM
Time: $(date -u '+%Y-%m-%d %H:%M:%S UTC')
Host: ${hostname_short}
Unit: ${unit}
Pattern: ${PATTERN}
Window: last ${LOOKBACK_MIN} minutes

Recent matching journal lines:
${hit_lines}
EOM
)

  # Always emit alert to journal/syslog for monitoring.
  logger -p daemon.err -t eb-ws-health-alert -- "${subject}"

  # Primary route: AWS SES (same mechanism used by backup script).
  if send_alert_via_ses "$subject" "$body"; then
    return 0
  fi

  # Fallback: local mail if available.
  if command -v mail >/dev/null 2>&1; then
    if ! printf '%s\n' "$body" | mail -s "$subject" "$ALERT_TO"; then
      logger -p daemon.err -t eb-ws-health-alert -- "fallback mail send failed for ${unit} (to=${ALERT_TO}); alert logged to journal"
    else
      logger -p daemon.info -t eb-ws-health-alert -- "fallback mail alert sent to ${ALERT_TO}"
    fi
  else
    logger -p daemon.err -t eb-ws-health-alert -- "mail command not found; alert logged only for ${unit}"
  fi

  return 0
}

for unit in "${units[@]}"; do
  logs="$(journalctl -u "$unit" --since "-${LOOKBACK_MIN} min" --no-pager 2>/dev/null || true)"
  hits="$(filter_hits "$logs")"
  [[ -n "$hits" ]] || continue

  state_file="${STATE_DIR}/${unit//[^a-zA-Z0-9_.-]/_}.state"
  hash_now="$(printf '%s' "$hits" | sha1sum | awk '{print $1}')"

  prev_hash=""
  prev_ts="0"
  if [[ -f "$state_file" ]]; then
    prev_hash="$(awk -F= '/^hash=/{print $2}' "$state_file" 2>/dev/null || true)"
    prev_ts="$(awk -F= '/^ts=/{print $2}' "$state_file" 2>/dev/null || echo 0)"
  fi

  cooldown_sec=$((ALERT_COOLDOWN_MIN * 60))
  elapsed=$((now_epoch - prev_ts))

  if [[ "$hash_now" == "$prev_hash" && "$elapsed" -lt "$cooldown_sec" ]]; then
    continue
  fi

  hit_excerpt="$(printf '%s\n' "$hits" | tail -n 40)"
  send_alert "$unit" "$hit_excerpt"

  {
    echo "hash=${hash_now}"
    echo "ts=${now_epoch}"
  } > "$state_file"
done
