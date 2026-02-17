#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./smoke_test.sh -i <image_path> [-m iso|img] [-b bios|uefi|both] [-t seconds] [-p base_port]

Examples:
  ./smoke_test.sh -i ./linux/out/e3-recovery-linux.iso -m iso
  ./smoke_test.sh -i ./linux/out/e3-recovery-linux.img -m img -b both

Notes:
  - BIOS test uses QEMU legacy firmware.
  - UEFI test uses OVMF. Set OVMF_CODE and OVMF_VARS if not in default paths.
  - The script forwards guest port 8080 to host ports (base_port, base_port+1).
EOF
}

IMAGE=""
IMAGE_MODE="iso"
BOOT_MODE="both"
TIMEOUT_SECONDS=180
BASE_PORT=8081

while getopts ":i:m:b:t:p:h" opt; do
  case "${opt}" in
    i) IMAGE="${OPTARG}" ;;
    m) IMAGE_MODE="${OPTARG}" ;;
    b) BOOT_MODE="${OPTARG}" ;;
    t) TIMEOUT_SECONDS="${OPTARG}" ;;
    p) BASE_PORT="${OPTARG}" ;;
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

if [[ -z "${IMAGE}" ]]; then
  usage
  exit 1
fi

if [[ ! -f "${IMAGE}" ]]; then
  echo "[smoke] Image not found: ${IMAGE}" >&2
  exit 1
fi

if [[ "${IMAGE_MODE}" != "iso" && "${IMAGE_MODE}" != "img" ]]; then
  echo "[smoke] Invalid image mode: ${IMAGE_MODE}" >&2
  exit 1
fi

if [[ "${BOOT_MODE}" != "bios" && "${BOOT_MODE}" != "uefi" && "${BOOT_MODE}" != "both" ]]; then
  echo "[smoke] Invalid boot mode: ${BOOT_MODE}" >&2
  exit 1
fi

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "[smoke] Missing required command: $1" >&2
    exit 1
  }
}

require_cmd qemu-system-x86_64
require_cmd curl

OVMF_CODE="${OVMF_CODE:-/usr/share/OVMF/OVMF_CODE.fd}"
OVMF_VARS="${OVMF_VARS:-/usr/share/OVMF/OVMF_VARS.fd}"

log() {
  echo "[smoke] $*"
}

wait_for_http() {
  local port="$1"
  local deadline="$2"
  local url="http://127.0.0.1:${port}/"
  local elapsed=0
  while [[ "${elapsed}" -lt "${deadline}" ]]; do
    if curl -fsS --max-time 2 "${url}" | grep -q "Recovery Console"; then
      return 0
    fi
    sleep 3
    elapsed=$((elapsed + 3))
  done
  return 1
}

run_boot_test() {
  local mode="$1"
  local host_port="$2"
  local tmpdir
  tmpdir="$(mktemp -d)"
  local pidfile="${tmpdir}/qemu.pid"
  local vars_copy=""

  local args=(
    -m 2048
    -smp 2
    -netdev "user,id=net0,hostfwd=tcp::${host_port}-:8080"
    -device "virtio-net-pci,netdev=net0"
    -display none
    -serial "file:${tmpdir}/serial.log"
    -pidfile "${pidfile}"
    -daemonize
  )

  if [[ "${IMAGE_MODE}" == "iso" ]]; then
    args+=(-cdrom "${IMAGE}" -boot d)
  else
    args+=(-drive "file=${IMAGE},format=raw,if=virtio" -boot c)
  fi

  if [[ "${mode}" == "uefi" ]]; then
    if [[ ! -f "${OVMF_CODE}" || ! -f "${OVMF_VARS}" ]]; then
      echo "[smoke] OVMF firmware not found. Set OVMF_CODE/OVMF_VARS." >&2
      rm -rf "${tmpdir}"
      return 1
    fi
    vars_copy="${tmpdir}/OVMF_VARS.fd"
    cp "${OVMF_VARS}" "${vars_copy}"
    args+=(
      -machine q35
      -drive "if=pflash,format=raw,readonly=on,file=${OVMF_CODE}"
      -drive "if=pflash,format=raw,file=${vars_copy}"
    )
  fi

  log "Starting ${mode} boot (host port ${host_port})..."
  qemu-system-x86_64 "${args[@]}"

  if [[ ! -s "${pidfile}" ]]; then
    echo "[smoke] Failed to start QEMU for ${mode}." >&2
    rm -rf "${tmpdir}"
    return 1
  fi

  local qemu_pid
  qemu_pid="$(cat "${pidfile}")"

  if wait_for_http "${host_port}" "${TIMEOUT_SECONDS}"; then
    log "${mode} boot OK (recovery UI reachable)."
    kill "${qemu_pid}" >/dev/null 2>&1 || true
    rm -rf "${tmpdir}"
    return 0
  fi

  echo "[smoke] ${mode} boot failed: UI not reachable within ${TIMEOUT_SECONDS}s." >&2
  kill "${qemu_pid}" >/dev/null 2>&1 || true
  rm -rf "${tmpdir}"
  return 1
}

status=0
if [[ "${BOOT_MODE}" == "bios" || "${BOOT_MODE}" == "both" ]]; then
  run_boot_test "bios" "${BASE_PORT}" || status=1
fi

if [[ "${BOOT_MODE}" == "uefi" || "${BOOT_MODE}" == "both" ]]; then
  run_boot_test "uefi" "$((BASE_PORT + 1))" || status=1
fi

exit "${status}"
