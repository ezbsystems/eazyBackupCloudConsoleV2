#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${ROOT_DIR}/../.." && pwd)"
OUT_DIR="${ROOT_DIR}/out"
WORK_DIR="${ROOT_DIR}/work"
ROOTFS_DIR="${WORK_DIR}/rootfs"
ISO_DIR="${WORK_DIR}/iso"
MOUNT_DIR="${WORK_DIR}/mnt"

VERSION="${VERSION:-$(date +%Y.%m.%d)}"
DEBIAN_SUITE="${DEBIAN_SUITE:-bookworm}"
DEBIAN_MIRROR="${DEBIAN_MIRROR:-http://deb.debian.org/debian}"
ARCH="${ARCH:-amd64}"
IMG_SIZE_GB="${IMG_SIZE_GB:-8}"
KIOSK_URL="${KIOSK_URL:-http://127.0.0.1:8080/}"

IMG_NAME="e3-recovery-linux-${VERSION}.img"
ISO_NAME="e3-recovery-linux-${VERSION}.iso"
IMG_LATEST="e3-recovery-linux.img"
ISO_LATEST="e3-recovery-linux.iso"

RECOVERY_BIN="${RECOVERY_BIN:-${WORK_DIR}/e3-recovery-agent}"

log() {
  echo "[recovery] $*"
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "[recovery] Missing required command: $1" >&2
    exit 1
  }
}

cleanup() {
  set +e
  for m in "$MOUNT_DIR/efi" "$MOUNT_DIR/root" "$ROOTFS_DIR/proc" "$ROOTFS_DIR/sys" "$ROOTFS_DIR/dev"; do
    if mountpoint -q "$m"; then
      umount "$m" 2>/dev/null || umount -l "$m" 2>/dev/null || true
    fi
  done
  if [[ -n "${LOOP_DEV:-}" ]]; then
    losetup -d "${LOOP_DEV}" 2>/dev/null || true
  fi
}

trap cleanup EXIT

if [[ "${EUID}" -ne 0 ]]; then
  echo "[recovery] Please run as root (required for debootstrap/mount)." >&2
  exit 1
fi

require_cmd debootstrap
require_cmd losetup
require_cmd sgdisk
require_cmd mkfs.vfat
require_cmd mkfs.ext4
require_cmd rsync
require_cmd mksquashfs
require_cmd grub-mkrescue
require_cmd xorriso

mkdir -p "${OUT_DIR}" "${WORK_DIR}" "${ROOTFS_DIR}" "${ISO_DIR}" "${MOUNT_DIR}/root" "${MOUNT_DIR}/efi"

log "Building recovery agent binary..."
if [[ ! -x "${RECOVERY_BIN}" ]]; then
  require_cmd go
  (cd "${REPO_ROOT}/e3-backup-agent" && CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -o "${RECOVERY_BIN}" ./cmd/recovery)
fi

log "Creating Debian rootfs (${DEBIAN_SUITE})..."
rm -rf "${ROOTFS_DIR}"
mkdir -p "${ROOTFS_DIR}"
debootstrap --arch="${ARCH}" --variant=minbase "${DEBIAN_SUITE}" "${ROOTFS_DIR}" "${DEBIAN_MIRROR}"

mount --bind /dev "${ROOTFS_DIR}/dev"
mount --bind /proc "${ROOTFS_DIR}/proc"
mount --bind /sys "${ROOTFS_DIR}/sys"

log "Installing packages in chroot..."
chroot "${ROOTFS_DIR}" apt-get update
chroot "${ROOTFS_DIR}" env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
  systemd-sysv linux-image-amd64 initramfs-tools \
  grub-pc-bin grub-efi-amd64-bin shim-signed \
  live-boot live-config \
  ca-certificates curl wget gnupg \
  util-linux e2fsprogs ntfs-3g dosfstools gdisk parted lvm2 mdadm \
  xorg xinit openbox chromium x11-xserver-utils \
  network-manager dbus iproute2
chroot "${ROOTFS_DIR}" apt-get clean

log "Configuring recovery services..."
install -D -m 0755 "${RECOVERY_BIN}" "${ROOTFS_DIR}/usr/local/bin/e3-recovery-agent"

cat > "${ROOTFS_DIR}/etc/default/e3-recovery" <<EOF
E3_RECOVERY_LISTEN=0.0.0.0:8080
E3_RECOVERY_API=https://accounts.eazybackup.ca/modules/addons/cloudstorage/api
E3_KIOSK_URL=${KIOSK_URL}
EOF

cat > "${ROOTFS_DIR}/etc/systemd/system/e3-recovery.service" <<'EOF'
[Unit]
Description=E3 Recovery Agent
After=network-online.target
Wants=network-online.target

[Service]
EnvironmentFile=-/etc/default/e3-recovery
ExecStart=/usr/local/bin/e3-recovery-agent --listen ${E3_RECOVERY_LISTEN} --api ${E3_RECOVERY_API}
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

cat > "${ROOTFS_DIR}/usr/local/bin/e3-kiosk-session.sh" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
xset -dpms
xset s off
xset s noblank
openbox-session &
exec /usr/bin/chromium --kiosk --no-first-run --disable-translate --disable-infobars "${E3_KIOSK_URL:-http://127.0.0.1:8080/}"
EOF
chmod 0755 "${ROOTFS_DIR}/usr/local/bin/e3-kiosk-session.sh"

cat > "${ROOTFS_DIR}/etc/systemd/system/e3-kiosk.service" <<'EOF'
[Unit]
Description=E3 Recovery Kiosk
After=network-online.target
Wants=network-online.target

[Service]
EnvironmentFile=-/etc/default/e3-recovery
Environment=DISPLAY=:0
ExecStart=/usr/bin/xinit /usr/local/bin/e3-kiosk-session.sh -- :0 -nolisten tcp
Restart=on-failure
StandardInput=tty
TTYPath=/dev/tty1
TTYReset=yes
TTYVHangup=yes
TTYVTDisallocate=yes

[Install]
WantedBy=multi-user.target
EOF

echo "e3-recovery" > "${ROOTFS_DIR}/etc/hostname"
cat > "${ROOTFS_DIR}/etc/hosts" <<'EOF'
127.0.0.1 localhost
127.0.1.1 e3-recovery
EOF

cat > "${ROOTFS_DIR}/etc/fstab" <<'EOF'
LABEL=E3RECOVERY / ext4 defaults 0 1
LABEL=E3EFI /boot/efi vfat umask=0077 0 1
EOF

chroot "${ROOTFS_DIR}" systemctl enable NetworkManager
chroot "${ROOTFS_DIR}" systemctl enable e3-recovery.service
chroot "${ROOTFS_DIR}" systemctl enable e3-kiosk.service

log "Updating initramfs..."
chroot "${ROOTFS_DIR}" update-initramfs -u

umount -f "${ROOTFS_DIR}/proc"
umount -f "${ROOTFS_DIR}/sys"
umount -f "${ROOTFS_DIR}/dev"

log "Creating disk image..."
IMG_PATH="${OUT_DIR}/${IMG_NAME}"
truncate -s "${IMG_SIZE_GB}G" "${IMG_PATH}"
sgdisk -Z "${IMG_PATH}"
sgdisk -n 1:2048:+2M -t 1:ef02 -c 1:BIOSBOOT "${IMG_PATH}"
sgdisk -n 2:0:+512M -t 2:ef00 -c 2:E3EFI "${IMG_PATH}"
sgdisk -n 3:0:0 -t 3:8300 -c 3:E3RECOVERY "${IMG_PATH}"

LOOP_DEV="$(losetup --find --show --partscan "${IMG_PATH}")"
mkfs.vfat -F32 -n E3EFI "${LOOP_DEV}p2"
mkfs.ext4 -F -L E3RECOVERY "${LOOP_DEV}p3"

mount "${LOOP_DEV}p3" "${MOUNT_DIR}/root"
mkdir -p "${MOUNT_DIR}/root/boot/efi"
mount "${LOOP_DEV}p2" "${MOUNT_DIR}/root/boot/efi"
rsync -aHAX "${ROOTFS_DIR}/" "${MOUNT_DIR}/root/"

mount --bind /dev "${MOUNT_DIR}/root/dev"
mount --bind /proc "${MOUNT_DIR}/root/proc"
mount --bind /sys "${MOUNT_DIR}/root/sys"
chroot "${MOUNT_DIR}/root" grub-install --target=i386-pc --boot-directory=/boot "${LOOP_DEV}"
chroot "${MOUNT_DIR}/root" grub-install --target=x86_64-efi --efi-directory=/boot/efi --bootloader-id=E3Recovery --removable --no-nvram
chroot "${MOUNT_DIR}/root" update-grub
umount "${MOUNT_DIR}/root/proc" 2>/dev/null || umount -l "${MOUNT_DIR}/root/proc" 2>/dev/null || true
umount "${MOUNT_DIR}/root/sys" 2>/dev/null || umount -l "${MOUNT_DIR}/root/sys" 2>/dev/null || true
umount "${MOUNT_DIR}/root/dev" 2>/dev/null || umount -l "${MOUNT_DIR}/root/dev" 2>/dev/null || true
umount "${MOUNT_DIR}/root/boot/efi" 2>/dev/null || umount -l "${MOUNT_DIR}/root/boot/efi" 2>/dev/null || true
umount "${MOUNT_DIR}/root" 2>/dev/null || umount -l "${MOUNT_DIR}/root" 2>/dev/null || true
losetup -d "${LOOP_DEV}"
unset LOOP_DEV

log "Creating ISO (live-boot)..."
rm -rf "${ISO_DIR}"
mkdir -p "${ISO_DIR}/live" "${ISO_DIR}/boot/grub"

VMLINUX_PATH="$(ls -1 "${ROOTFS_DIR}/boot/vmlinuz-"* | head -n 1)"
INITRD_PATH="$(ls -1 "${ROOTFS_DIR}/boot/initrd.img-"* | head -n 1)"
cp "${VMLINUX_PATH}" "${ISO_DIR}/live/vmlinuz"
cp "${INITRD_PATH}" "${ISO_DIR}/live/initrd.img"
mksquashfs "${ROOTFS_DIR}" "${ISO_DIR}/live/filesystem.squashfs" -e boot

cat > "${ISO_DIR}/boot/grub/grub.cfg" <<'EOF'
set default=0
set timeout=5

menuentry "E3 Recovery (Live)" {
  linux /live/vmlinuz boot=live quiet
  initrd /live/initrd.img
}
EOF

grub-mkrescue -o "${OUT_DIR}/${ISO_NAME}" "${ISO_DIR}" >/dev/null 2>&1

cp -f "${OUT_DIR}/${IMG_NAME}" "${OUT_DIR}/${IMG_LATEST}"
cp -f "${OUT_DIR}/${ISO_NAME}" "${OUT_DIR}/${ISO_LATEST}"

log "Linux image build complete:"
log "  ${OUT_DIR}/${IMG_NAME}"
log "  ${OUT_DIR}/${ISO_NAME}"
