# Local Backup Agent – Build & Deploy

This guide walks you through building and deploying the E3 Backup Agent for Windows.

---

## Quick Start (TL;DR)

```bash
# 1. Build both Windows binaries
cd /var/www/eazybackup.ca/e3-backup-agent
make build-windows

# 2. Copy to Windows machine and compile installer with Inno Setup
# 3. Run installer on target Windows machine
```

---

## Prerequisites

| Requirement | Details |
|-------------|---------|
| Go toolchain | Version 1.24.x or later |
| Source code | `/var/www/eazybackup.ca/e3-backup-agent/` |
| Target platform | Windows amd64 (CGO_ENABLED=0) |
| Inno Setup | Required on Windows to compile the installer |

---

## Step 1: Build Windows Binaries (Linux Build Host)

You must build **TWO executables**:

| Binary | Purpose |
|--------|---------|
| `e3-backup-agent.exe` | Windows service (runs backups) |
| `e3-backup-tray.exe` | Tray helper (enrollment UI, user interaction) |

### Option A: Use Makefile (Recommended)

```bash
cd /var/www/eazybackup.ca/e3-backup-agent

# Build BOTH binaries at once
make build-windows
```

This will output:
```
bin/e3-backup-agent.exe
bin/e3-backup-tray.exe
```

### Option B: Manual Build Commands

If you prefer to run the commands directly:

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
mkdir -p bin

# 1. Build the service binary
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
  go build -trimpath -ldflags="-s -w" \
  -o bin/e3-backup-agent.exe ./cmd/agent

# 2. Build the tray helper (must use -H=windowsgui to hide console window)
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
  go build -trimpath -ldflags="-s -w -H=windowsgui" \
  -o bin/e3-backup-tray.exe ./cmd/tray
```

### Verify Build Output

```bash
ls -la bin/
# Should show:
#   e3-backup-agent.exe
#   e3-backup-tray.exe
```

---

## Step 2: Prepare Installer Assets

The Inno Setup installer requires icon assets from the worker repo.

### Required Files

Copy these to your Windows build machine, preserving the folder structure:

```
your-build-folder/
├── e3-backup-agent/
│   ├── bin/
│   │   ├── e3-backup-agent.exe    ← Built in Step 1
│   │   └── e3-backup-tray.exe     ← Built in Step 1
│   └── installer/
│       └── e3-backup-agent.iss    ← Installer script
└── e3-cloudbackup-worker/
    └── assets/
        ├── tray_logo-drk-orange120x120.png
        └── tray_logo-drk-orange.svg
```

### Copy Command Example

```bash
# From the Linux server, copy to Windows via scp/rsync/network share:
scp -r /var/www/eazybackup.ca/e3-backup-agent user@windows-pc:C:/src/eazybackup/
scp -r /var/www/eazybackup.ca/e3-cloudbackup-worker user@windows-pc:C:/src/eazybackup/
```

---

## Step 3: Compile Windows Installer (Inno Setup)

### Install Inno Setup

Download and install [Inno Setup](https://jrsoftware.org/isinfo.php) on your Windows machine.

### Compile the Installer

1. Open **Inno Setup Compiler**
2. File → Open → select: `e3-backup-agent\installer\e3-backup-agent.iss`
3. Build → Compile (or press **F9**)

### Output

The installer will be created in the `installer\Output\` folder:
```
e3-backup-agent-setup.exe
```

### Deploy to Web Server

Copy the installer to the public download location on your server:

```bash
# The public web root is /var/www/eazybackup.ca/accounts/
# Create the client_installer directory if it doesn't exist
mkdir -p /var/www/eazybackup.ca/accounts/client_installer

# Copy the installer (from Windows build machine)
scp e3-backup-agent-setup.exe user@server:/var/www/eazybackup.ca/accounts/client_installer/

# The installer will be accessible at:
# https://accounts.eazybackup.ca/client_installer/e3-backup-agent-setup.exe
```

| File | Server Path | Download URL |
|------|-------------|--------------|
| Windows Installer | `/var/www/eazybackup.ca/accounts/client_installer/e3-backup-agent-setup.exe` | `/client_installer/e3-backup-agent-setup.exe` |
| Linux Binary | `/var/www/eazybackup.ca/accounts/client_installer/e3-backup-agent-linux` | `/client_installer/e3-backup-agent-linux` |

---

## Optional: Build WinPE Recovery ISO (Development and Production)

Use this section when you need to build/update the WinPE recovery media ISO used by the tray recovery-media builder.

### Prerequisites (Windows build host)

- Windows ADK + WinPE add-on installed
- Elevated PowerShell
- WinPE build script available at: `/var/www/eazybackup.ca/recovery/winpe/build.ps1`
- Recovery binary: `e3-recovery-agent.exe` built from `e3-backup-agent/cmd/recovery`

### Step A: Build the WinPE recovery binary

Run on Linux (or any Go build host):

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
  go build -trimpath -ldflags="-s -w" \
  -o e3-recovery-agent.exe ./cmd/recovery
```

Copy `e3-recovery-agent.exe` to your Windows WinPE build folder (for example, `recovery\winpe\`).

### Step B: Prepare WinPE driver packs (critical + full profiles)

The WinPE build supports layered driver injection:

```text
recovery/winpe/drivers/
  common-nic/                  # large generic NIC pack
  models/<model-key>/          # optional per-model overlay
  machines/<machine-key>/      # optional per-device overlay
```

If no drivers are present, WinPE is built with inbox drivers only.

For large OEM driver trees (for example `C:\e3\WinPE Drivers`), use the staging helper to auto-build two packs:

```powershell
# Fast boot profile (Net + Storage + RAID focused)
powershell -ExecutionPolicy Bypass -File .\stage-driver-packs.ps1 `
  -SourceRoot "C:\e3\WinPE Drivers" `
  -OutputRoot "C:\e3\drivers-critical" `
  -Profile critical `
  -CleanOutput

# Broad compatibility profile (all discovered classes)
powershell -ExecutionPolicy Bypass -File .\stage-driver-packs.ps1 `
  -SourceRoot "C:\e3\WinPE Drivers" `
  -OutputRoot "C:\e3\drivers-full" `
  -Profile full `
  -CleanOutput
```

Recommended operational model:
- daily/default recovery ISO -> `drivers-critical` (faster startup)
- fallback extended ISO -> `drivers-full` (maximum hardware coverage)

### Step C: Build Development ISO

Run on Windows from the `recovery\winpe` folder:

```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe .\e3-recovery-agent.exe `
  -ApiBase "https://dev.eazybackup.ca/modules/addons/cloudstorage/api" `
  -Version "dev-2026.02.14" `
  -DriversDir "C:\e3\drivers-critical"
```

### Step D: Build Production ISO

```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe .\e3-recovery-agent.exe `
  -ApiBase "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api" `
  -Version "prod-2026.02.14" `
  -DriversDir "C:\e3\drivers-critical"
```

### Optional: Build extended full-driver ISO

```powershell
powershell -ExecutionPolicy Bypass -File .\build.ps1 `
  -RecoveryExe .\e3-recovery-agent.exe `
  -ApiBase "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api" `
  -Version "prod-2026.02.14-full" `
  -DriversDir "C:\e3\drivers-full"
```

### Optional flags for hardware-specific overlays

Add these when needed:

```powershell
-DriverModel "hp-elitedesk-800-g6" `
-DriverMachine "minit-b1rpgg9"
```

If vendor INF packages are unsigned, also add:

```powershell
-ForceUnsignedDrivers
```

### Build outputs

`build.ps1` writes:

- `recovery/winpe/out/e3-recovery-winpe-<version>.iso`
- `recovery/winpe/out/e3-recovery-winpe.iso` (latest copy)

Use `dev-*` versions for development builds and `prod-*` versions for production builds so published artifacts are easy to identify.

### Runtime driver layering model (current)

The base ISO stays lean. Driver packs are layered onto USB media at creation time:

- **Fast / Same Hardware**: base ISO + latest source bundle (`essential`, fallback `full`, then broad extras)
- **Dissimilar Hardware**: base ISO + broad extras (and source bundle when available)
- WinPE startup loads drivers in this order:
  1. `\e3\drivers\source\`
  2. `\e3\drivers\broad\`

Driver load log is written to:

```text
%SystemRoot%\Temp\e3-driver-load.log
```

### Build the portable Recovery Media Creator (Windows)

Use this for the dead-source-PC flow (no tray/agent install required on the machine writing USB):

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
  go build -trimpath -ldflags="-s -w" \
  -o bin/e3-recovery-media-creator.exe ./cmd/recovery-media-creator
```

Suggested publish location:

```text
/var/www/eazybackup.ca/accounts/client_installer/e3-recovery-media-creator.exe
```

### Required addon settings for media flow

Set these in `tbladdonmodules` for module `cloudstorage`:

- `recovery_media_base_iso_url` (default: `https://accounts.eazybackup.ca/recovery_media/e3-recovery-winpe-prod.iso`)
- `recovery_media_base_iso_sha256` (optional but recommended)
- `recovery_media_broad_bundle_url` (optional broad fallback pack zip)
- `recovery_media_broad_bundle_sha256` (optional)
- `recovery_media_creator_download_url` (client-area download link target)
- `recovery_media_bundle_base_url` (base URL used when agents upload source bundles)

### QA matrix (minimum)

Validate these before release:

- Tray flow (healthy source): `fast` mode writes USB and stages source drivers.
- Tray flow fallback: no source bundle available -> warning shown, broad pack staged.
- Client area flow (dead source): token creation + portable tool token exchange works.
- Portable tool flow: USB write + source and/or broad bundle layering.
- WinPE runtime: `%SystemRoot%\Temp\e3-driver-load.log` confirms source-first load order.
- Invalid token scenarios: expired/invalid token rejected by exchange API.

---

## Step 4: Install on Target Windows Machine

### Interactive Install (End Users)

1. Run `e3-backup-agent-setup.exe`
2. Follow the wizard
3. After install, the tray helper will launch automatically
4. If the device is not enrolled, the browser will open to the enrollment page
5. Or click the tray icon → **Enroll / Sign in…**

### Silent Install (MSP/RMM Deployment)

```cmd
e3-backup-agent-setup.exe /VERYSILENT /TOKEN=your-enrollment-token-here
```

With custom API endpoint:
```cmd
e3-backup-agent-setup.exe /VERYSILENT /TOKEN=abc123... /API=https://your-server.com/modules/addons/cloudstorage/api
```

---

## Installed Files

After installation, files are located at:

| Location | Contents |
|----------|----------|
| `C:\Program Files\E3Backup\` | Executables (`e3-backup-agent.exe`, `e3-backup-tray.exe`) |
| `C:\ProgramData\E3Backup\agent.conf` | Configuration file |
| `C:\ProgramData\E3Backup\runs\` | Backup run data |
| `C:\ProgramData\E3Backup\logs\` | Log files (`agent.log`, `tray.log`) |

---

## Configuration (agent.conf)

### Before Enrollment (MSP Token)

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
enrollment_token: "0123456789abcdef0123456789abcdef01234567"
device_id: "9f2c3a6c-7d5d-4c67-9c5a-2c1e9c5d7a11"
install_id: "f1c2d3e4-1111-2222-3333-444455556666"
device_name: "FILE-SERVER-01"
poll_interval_secs: 5
user_agent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 e3-backup-agent/1.0"
run_dir: "C:\\ProgramData\\E3Backup\\runs"
```

### After Enrollment (Credentials Saved)

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
client_id: "42"
agent_id: "123"
agent_token: "deadbeef...40-hex..."
device_id: "9f2c3a6c-7d5d-4c67-9c5a-2c1e9c5d7a11"
install_id: "f1c2d3e4-1111-2222-3333-444455556666"
device_name: "FILE-SERVER-01"
poll_interval_secs: 5
user_agent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 e3-backup-agent/1.0"
run_dir: "C:\\ProgramData\\E3Backup\\runs"
```

---

## Manual Service Commands

```powershell
# Install service
.\e3-backup-agent.exe -service install -config C:\ProgramData\E3Backup\agent.conf

# Start service
.\e3-backup-agent.exe -service start -config C:\ProgramData\E3Backup\agent.conf

# Stop service
.\e3-backup-agent.exe -service stop -config C:\ProgramData\E3Backup\agent.conf

# Uninstall service
.\e3-backup-agent.exe -service uninstall -config C:\ProgramData\E3Backup\agent.conf
```

---

## Manual Tray Helper

```powershell
.\e3-backup-tray.exe -config C:\ProgramData\E3Backup\agent.conf
```

---

## Troubleshooting

### Tray app not included in installer
**Symptom:** Only `e3-backup-agent.exe` exists in `C:\Program Files\E3Backup\`

**Cause:** The tray binary wasn't built before compiling the installer.

**Fix:** Run `make build-windows` to build both binaries, then recompile the installer.

### YAML parse error (unknown escape character)
**Symptom:** Service fails with `found unknown escape character`

**Cause:** Windows paths with backslashes aren't escaped in double-quoted YAML strings.

**Fix:** Use escaped backslashes in the config:
```yaml
run_dir: "C:\\ProgramData\\E3Backup\\runs"
```

### Browser doesn't open on first run
**Symptom:** Tray shows "Not enrolled" but browser doesn't open automatically.

**Cause:** The tray binary wasn't rebuilt with the latest code.

**Fix:** Rebuild with `make build-windows`, recompile installer, reinstall.

**Debug:** Check `C:\ProgramData\E3Backup\logs\tray.log` for debug messages.

---

## Makefile Targets

| Target | Description |
|--------|-------------|
| `make build` | Build Linux agent binary |
| `make build-windows` | Build both Windows binaries (agent + tray) |
| `make build-agent-windows` | Build only Windows agent |
| `make build-tray-windows` | Build only Windows tray |
| `make clean` | Remove build artifacts |
| `make deps` | Download Go dependencies |

---

## Notes

- The agent uses embedded rclone (no external binary required)
- Supported backends: local, S3
- Poll interval configurable via `poll_interval_secs` (default: 5 seconds)
- The tray helper auto-opens the enrollment page if the device is not enrolled
- Driver bundles for recovery media are stored in customer destination buckets using:
  - `dest_prefix/driver-bundles/<agentid>/<profile>.zip`
- Driver bundle downloads in media manifests are pre-signed (12-hour TTL), so generated URLs are intentionally temporary.
