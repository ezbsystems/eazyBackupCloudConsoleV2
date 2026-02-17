WinPE driver packs live in this directory.

Suggested layout:

- `common-nic/` - shared NIC driver pack used for most hardware
- `models/<model-key>/` - model specific overlays (optional)
- `machines/<machine-key>/` - per-device overlays (optional)

Example keys:

- Model: `hp-elitedesk-800-g6`
- Machine: `minit-b1rpgg9`

The WinPE build script (`../build.ps1`) injects drivers from:

1. `common-nic/` (if present)
2. `models/<DriverModel>/` (if `-DriverModel` is supplied)
3. `machines/<DriverMachine>/` (if `-DriverMachine` is supplied)
4. Any folders passed with `-ExtraDriverDirs`

Driver packages should be extracted `.inf` trees (not raw installer `.exe` files).
