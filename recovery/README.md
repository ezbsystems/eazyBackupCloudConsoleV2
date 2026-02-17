# Recovery Media Builds

This folder contains build scripts and artifacts for bare‑metal recovery media.

## Output types
- Linux recovery ISO/IMG (primary, includes GUI)
- WinPE recovery ISO (optional, better Windows boot repair)
- PXE/iPXE boot artifacts (kernel + initrd + iPXE script)
- BMC virtual media ISO

## Versioning
Artifacts are versioned and published with checksums:

- `recovery/manifest.json`
- `recovery/e3-recovery-linux-<version>.img`
- `recovery/e3-recovery-winpe-<version>.iso`
- `recovery/sha256sum.txt`

## Build workflow
1. Build recovery agent binaries for Linux and Windows.
2. Build Linux image using `recovery/linux/build.sh`.
3. Build WinPE image using `recovery/winpe/build.ps1` on Windows.
4. Generate checksum file and manifest via `recovery/build_manifest.sh`.
5. Upload to CDN/S3 bucket for on‑demand download by tray wizard.

## Linux build (Debian + GUI)
Example (run as root on a Linux build host):
```
cd recovery/linux
./build.sh
```

Prerequisites (Ubuntu 22.04 / Jammy):
```
apt-get update
apt-get install -y \
  debootstrap qemu-system-x86 xorriso \
  grub-pc-bin grub-efi-amd64-bin grub-common \
  squashfs-tools gdisk dosfstools e2fsprogs rsync
```
Note: `grub-mkrescue` is provided by `grub-common` on Jammy.

Outputs:
- `recovery/linux/out/e3-recovery-linux-<version>.img`
- `recovery/linux/out/e3-recovery-linux-<version>.iso`
- `recovery/linux/out/e3-recovery-linux.img` (latest copy)
- `recovery/linux/out/e3-recovery-linux.iso` (latest copy)

## WinPE build (Windows)
See `recovery/winpe/README.md` for prerequisites and usage.

Driver support for physical hardware is handled via DISM injection in `recovery/winpe/build.ps1`:
- common NIC pack (`recovery/winpe/drivers/common-nic`)
- optional model overlays (`recovery/winpe/drivers/models/<model>`)
- optional per-PC overlays (`recovery/winpe/drivers/machines/<machine>`)

## Checksums + manifest
```
cd recovery
./build_manifest.sh -v <version>
```

## Local smoke test (BIOS/UEFI)
```
cd recovery
./smoke_test.sh -i ./linux/out/e3-recovery-linux.iso -m iso -b both
```
