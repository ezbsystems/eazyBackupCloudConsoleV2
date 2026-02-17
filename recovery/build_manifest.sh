#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LINUX_OUT="${ROOT_DIR}/linux/out"
WINPE_OUT="${ROOT_DIR}/winpe/out"

VERSION=""
LINUX_IMG="${LINUX_IMG_PATH:-}"
WINPE_ISO="${WINPE_ISO_PATH:-}"

usage() {
  echo "Usage: $0 -v <version> [-l linux_img] [-w winpe_iso]" >&2
}

while getopts ":v:l:w:h" opt; do
  case "${opt}" in
    v) VERSION="${OPTARG}" ;;
    l) LINUX_IMG="${OPTARG}" ;;
    w) WINPE_ISO="${OPTARG}" ;;
    h)
      usage
      exit 0
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

if [[ -z "${LINUX_IMG}" ]]; then
  if [[ -n "${VERSION}" ]]; then
    LINUX_IMG="${LINUX_OUT}/e3-recovery-linux-${VERSION}.img"
  else
    mapfile -t candidates < <(ls -1 "${LINUX_OUT}"/e3-recovery-linux-*.img 2>/dev/null || true)
    if [[ "${#candidates[@]}" -eq 1 ]]; then
      LINUX_IMG="${candidates[0]}"
      VERSION="$(basename "${LINUX_IMG}" | sed -e 's/^e3-recovery-linux-//' -e 's/\.img$//')"
    fi
  fi
fi

if [[ -z "${WINPE_ISO}" ]]; then
  if [[ -n "${VERSION}" ]]; then
    WINPE_ISO="${WINPE_OUT}/e3-recovery-winpe-${VERSION}.iso"
  else
    mapfile -t candidates < <(ls -1 "${WINPE_OUT}"/e3-recovery-winpe-*.iso 2>/dev/null || true)
    if [[ "${#candidates[@]}" -eq 1 ]]; then
      WINPE_ISO="${candidates[0]}"
      if [[ -z "${VERSION}" ]]; then
        VERSION="$(basename "${WINPE_ISO}" | sed -e 's/^e3-recovery-winpe-//' -e 's/\.iso$//')"
      fi
    fi
  fi
fi

if [[ -z "${VERSION}" ]]; then
  echo "[recovery] Version not specified and could not be inferred." >&2
  exit 1
fi

if [[ ! -f "${LINUX_IMG}" ]]; then
  echo "[recovery] Linux image not found: ${LINUX_IMG}" >&2
  exit 1
fi

if [[ ! -f "${WINPE_ISO}" ]]; then
  echo "[recovery] WinPE ISO not found: ${WINPE_ISO}" >&2
  exit 1
fi

sha_linux="$(sha256sum "${LINUX_IMG}" | awk '{print $1}')"
sha_winpe="$(sha256sum "${WINPE_ISO}" | awk '{print $1}')"
size_linux="$(stat -c%s "${LINUX_IMG}")"
size_winpe="$(stat -c%s "${WINPE_ISO}")"

python3 - <<PY
import json
import os
from datetime import datetime, timezone

manifest_path = os.path.join("${ROOT_DIR}", "manifest.json")
with open(manifest_path, "r", encoding="utf-8") as f:
    data = json.load(f)

data["version"] = "${VERSION}"
data["generated_at"] = datetime.now(timezone.utc).isoformat()

def upsert(name, filename, sha256, size_bytes):
    for item in data.get("images", []):
        if item.get("name") == name:
            item["filename"] = filename
            item["sha256"] = sha256
            item["size_bytes"] = size_bytes
            return
    data.setdefault("images", []).append({
        "name": name,
        "filename": filename,
        "sha256": sha256,
        "size_bytes": size_bytes,
    })

upsert("linux", os.path.basename("${LINUX_IMG}"), "${sha_linux}", int("${size_linux}"))
upsert("winpe", os.path.basename("${WINPE_ISO}"), "${sha_winpe}", int("${size_winpe}"))

with open(manifest_path, "w", encoding="utf-8") as f:
    json.dump(data, f, indent=2)
    f.write("\\n")
PY

SHA_FILE="${ROOT_DIR}/sha256sum.txt"
{
  sha256sum "${LINUX_IMG}"
  if [[ -f "${LINUX_OUT}/e3-recovery-linux-${VERSION}.iso" ]]; then
    sha256sum "${LINUX_OUT}/e3-recovery-linux-${VERSION}.iso"
  fi
  sha256sum "${WINPE_ISO}"
} > "${SHA_FILE}"

echo "[recovery] Updated ${ROOT_DIR}/manifest.json"
echo "[recovery] Wrote ${SHA_FILE}"
