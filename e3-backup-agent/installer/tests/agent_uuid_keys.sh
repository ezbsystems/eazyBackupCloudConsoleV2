#!/usr/bin/env bash
set -euo pipefail
content="$(<e3-backup-agent/installer/e3-backup-agent.iss)"
if grep -q "agent_id" <<<"$content"; then
  echo "legacy agent_id key still present in installer script"
  exit 1
fi
if ! grep -q "agent_uuid" <<<"$content"; then
  echo "agent_uuid key not found in installer script"
  exit 1
fi
echo "installer-keys-ok"
