# PXE / iPXE Recovery Boot

Use these artifacts to boot the Linux recovery environment via PXE/iPXE.

## Files
- `ipxe-boot.ipxe` — iPXE script to chain‑load kernel + initrd

## Notes
Publish kernel, initrd, and `ipxe-boot.ipxe` to the same CDN bucket used for recovery artifacts.
