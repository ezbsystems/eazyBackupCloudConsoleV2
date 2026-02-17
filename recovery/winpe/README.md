# WinPE Recovery Image

Build the WinPE recovery ISO on a Windows build host with ADK + WinPE add‑on.

## Prerequisites
- Windows ADK + WinPE add‑on installed.
- PowerShell (elevated).
- `e3-recovery-agent.exe` built from `e3-backup-agent/cmd/recovery`.

## Build
1. Build the recovery binary (run on Linux or Windows with Go installed):
   ```
   cd /var/www/eazybackup.ca/e3-backup-agent
   GOOS=windows GOARCH=amd64 go build -o e3-recovery-agent.exe ./cmd/recovery
   ```
   Copy `e3-recovery-agent.exe` to the Windows build host.
2. Run the build script:
   - `powershell -ExecutionPolicy Bypass -File .\build.ps1 -RecoveryExe C:\path\to\e3-recovery-agent.exe`

## Build Targets: Development vs Production
The most important setting is `-ApiBase`. This is the backend URL the WinPE recovery agent uses for token exchange, restore start, and status/event updates.

Use a fully-qualified API URL in this format:
`https://<host>/modules/addons/cloudstorage/api`

### Development Build (dev.eazybackup.ca)
```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe C:\path\to\e3-recovery-agent.exe `
  -ApiBase "https://dev.eazybackup.ca/modules/addons/cloudstorage/api"
```

### Production Build (accounts.eazybackup.ca)
```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe C:\path\to\e3-recovery-agent.exe `
  -ApiBase "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
```

### Recommended version naming
Use `-Version` to avoid mixing ISOs between environments:
```powershell
# Example
-Version "dev-2026.02.11"
```

## Launcher UI (WinPE)
The build embeds a small HTA launcher that provides basic controls:
- Open the recovery UI
- Open CMD
- Restart / Shutdown
- PowerShell (button shown even if component is missing)

The build will fail if WinPE-HTA is not installed (mshta.exe missing).

## Script inputs
`build.ps1` supports parameters for:
- `-Arch` (default: `amd64`)
- `-Version` (default: date)
- `-ApiBase` (default: `https://accounts.eazybackup.ca/modules/addons/cloudstorage/api`)
- `-DriversDir` (default root for driver packs: `recovery/winpe/drivers`)
- `-DriverModel` (optional model-specific overlay folder name under `drivers/models/`)
- `-DriverMachine` (optional machine-specific overlay folder name under `drivers/machines/`)
- `-ExtraDriverDirs` (optional comma-separated additional driver folders)
- `-ForceUnsignedDrivers` (optional; pass when using unsigned vendor INF packages)

## Driver injection workflow (common + overlays)
The WinPE build script now supports layered driver injection:

1. **Common NIC pack** (always attempted when present)
   - `recovery/winpe/drivers/common-nic/`
2. **Model overlay** (optional)
   - `recovery/winpe/drivers/models/<model-key>/`
3. **Per-PC overlay** (optional)
   - `recovery/winpe/drivers/machines/<machine-key>/`
4. **Extra ad-hoc folders** (optional)
   - via `-ExtraDriverDirs`

Recommended directory layout:

```text
recovery/winpe/drivers/
  common-nic/
    <vendor INF folders...>
  models/
    hp-elitedesk-800-g6/
    dell-optiplex-7010/
  machines/
    minit-b1rpgg9/
```

### Example: common NIC pack only
```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe C:\path\to\e3-recovery-agent.exe `
  -DriversDir .\drivers
```

### Example: common pack + model overlay
```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe C:\path\to\e3-recovery-agent.exe `
  -DriversDir .\drivers `
  -DriverModel "hp-elitedesk-800-g6"
```

### Example: common pack + model + per-PC overlay
```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe C:\path\to\e3-recovery-agent.exe `
  -DriversDir .\drivers `
  -DriverModel "hp-elitedesk-800-g6" `
  -DriverMachine "minit-b1rpgg9"
```

### Backward compatibility
If no overlay parameters/folders are used, the script keeps legacy behavior and injects `-DriversDir` recursively as a single pack.

## Helper: stage large OEM driver packs
If you download/extract OEM packs under `C:\e3\WinPE Drivers` (dell/hp/intel/lenovo/etc.), use:

```powershell
powershell -ExecutionPolicy Bypass -File .\stage-driver-packs.ps1 `
  -SourceRoot "C:\e3\WinPE Drivers" `
  -OutputRoot "C:\e3\drivers" `
  -CleanOutput
```

### Driver profiles (recommended)

`stage-driver-packs.ps1` supports:

- `-Profile critical` -> Net + Storage + RAID focused pack (fastest WinPE boot/init)
- `-Profile full` -> broad all-class pack (largest compatibility, slower boot/init)

Build a critical pack:

```powershell
powershell -ExecutionPolicy Bypass -File .\stage-driver-packs.ps1 `
  -SourceRoot "C:\e3\WinPE Drivers" `
  -OutputRoot "C:\e3\drivers-critical" `
  -Profile critical `
  -CleanOutput
```

Build a broad pack:

```powershell
powershell -ExecutionPolicy Bypass -File .\stage-driver-packs.ps1 `
  -SourceRoot "C:\e3\WinPE Drivers" `
  -OutputRoot "C:\e3\drivers-full" `
  -Profile full `
  -CleanOutput
```

Then build WinPE using staged drivers:

```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe .\e3-recovery-agent.exe `
  -ApiBase "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api" `
  -Version "prod-YYYY.MM.DD" `
  -DriversDir "C:\e3\drivers"
```

Notes:
- The helper stages x64 INF packages by default.
- `critical` profile keeps class/path-matched Net + Storage + RAID families.
- Tune the filter with `-CriticalClasses` and `-CriticalPathKeywords` if you need stricter or broader matching.
- It keeps required companion files (`.sys`, `.cat`, `.dll`, etc.) so DISM injection works.
- Use `-IncludeX86` only if you intentionally build x86 WinPE.
- `build.ps1` now writes a per-build driver injection log to `out/driver-injection-<version>.log`.
- If you want the build to fail on any driver install error, add `-StrictDriverInjection`.
- To prevent a single bad INF from hanging the build, adjust `-DriverInstallTimeoutSeconds` (default: `120`).
- To skip known-problematic driver paths, use `-ExcludeDriverPathPatterns`, for example:
  - `-ExcludeDriverPathPatterns "*\\x86\\*", "*\\Thunderbolt\\*"`

## Runtime verification in WinPE
After boot, confirm the agent is running with the expected API base:

```cmd
wmic process where "name='e3-recovery-agent.exe'" get CommandLine
```

Expected output includes one of:
- `--api "https://dev.eazybackup.ca/modules/addons/cloudstorage/api"`
- `--api "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"`

If it does not, restart the agent manually with the correct URL.

## Outputs
- `recovery/winpe/out/e3-recovery-winpe-<version>.iso`
- `recovery/winpe/out/e3-recovery-winpe.iso` (latest copy)

## Troubleshooting

### HTA shows blank or doesn't open
The HTA requires language packs. The build script auto-installs `WinPE-HTA_<lang>.cab` and `WinPE-Scripting_<lang>.cab`. If these are missing from your ADK installation, the HTA will render blank.

To verify language packs are installed in the image:
```powershell
Mount-WindowsImage -ImagePath .\work\media\sources\boot.wim -Index 1 -Path .\mount
Get-WindowsPackage -Path .\mount | Where-Object { $_.PackageName -like "*HTA*" -or $_.PackageName -like "*Scripting*" }
Dismount-WindowsImage -Path .\mount -Discard
```

### Only cmd.exe appears on boot
The startup flow is: `loader.hta + console heartbeat` -> `wpeinit` -> `e3-recovery-shell.cmd` -> HTA launcher.
If only cmd.exe appears, manually run:
```
mshta %SystemRoot%\System32\e3-launcher.hta
```
Or access the web UI directly at `http://127.0.0.1:8080/`

During startup, `wpeinit` output is captured to:
```
%SystemRoot%\Temp\e3-wpeinit.log
```

### USB-staged driver loading (source first, then broad)

The startup shell now loads additional drivers from the recovery USB before launching the UI:

1. `\e3\drivers\source\`
2. `\e3\drivers\broad\`

Driver load activity is logged to:
```
%SystemRoot%\Temp\e3-driver-load.log
```

Use this to confirm source bundle and broad extras packs were detected and staged correctly.

### Recovery agent not responding
Check if the agent is running:
```
tasklist | find "e3-recovery"
netstat -an | find ":8080"
```
Restart manually:
```
start /min "%ProgramFiles%\E3Recovery\e3-recovery-agent.exe" --listen 0.0.0.0:8080 --api "https://dev.eazybackup.ca/modules/addons/cloudstorage/api"
```

Production restart command:
```
start /min "%ProgramFiles%\E3Recovery\e3-recovery-agent.exe" --listen 0.0.0.0:8080 --api "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
```
