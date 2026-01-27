# Bare‑Metal Recovery QA Matrix

## Boot modes & partition tables
- UEFI + GPT (Windows)
- Legacy BIOS + MBR (Windows)
- UEFI + GPT (Linux)
- Legacy BIOS + MBR (Linux)

## Filesystem coverage (shrink support)
- NTFS: shrink restore to smaller disk (used bytes < target)
- ext4: shrink restore to smaller disk (used bytes < target)
- Other FS: block same‑size or larger only

## Disk size scenarios
- Target disk = same size as source
- Target disk larger than source
- Target disk smaller but >= used bytes (NTFS/ext4)
- Target disk smaller than used bytes (block)

## Dissimilar hardware
- Windows: new storage controller (NVMe/SATA)
- Linux: different disk controller

## Recovery media
- USB boot (Linux)
- USB boot (WinPE)
- PXE/iPXE boot
- BMC virtual media ISO (iLO/iDRAC)

## Token flow
- Valid token exchange
- Expired token
- Reused token (blocked)
- Session expiry during restore

## Failure modes
- Missing disk layout metadata
- Layout mismatch (GPT->MBR)
- Missing storage credentials
- Restore interrupted (power loss)

## Smoke tests
- Generate recovery token in WHMCS
- Boot recovery media and list restore points
- Select target disk and start restore
- Boot repaired system successfully
