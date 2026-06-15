# Local Backup Agent – Build & Deploy

This guide walks you through building and deploying the E3 Backup Agent for Windows and Linux.

---

## Quick Start (TL;DR)

```bash
# Linux agent
cd /var/www/eazybackup.ca/e3-backup-agent
make build
sudo make install

# Windows agent
make build-windows

# Then:
# - Linux: create /etc/e3-backup-agent/agent.conf and a systemd service
# - Windows: copy to Windows machine and compile installer with Inno Setup
```

---

## Prerequisites


| Requirement    | Details                                                   |
| -------------- | --------------------------------------------------------- |
| Go toolchain   | Version 1.24.x or later                                   |
| Source code    | `/var/www/eazybackup.ca/e3-backup-agent/`                 |
| Linux target   | Linux amd64, native or cross-compiled with `GOOS=linux`   |
| Windows target | Windows amd64, cross-compiled with `CGO_ENABLED=0`        |
| Inno Setup     | Required only on Windows to compile the Windows installer |


Linux runtime notes:

- Run as `root` when backing up protected paths or disk devices.
- Install `lvm2` when using disk-image backups for LVM volumes; the agent will try `lvcreate` snapshots and fall back to direct device reads when snapshots are unavailable.
- The agent stores run state under `/var/lib/e3-backup-agent/runs` by default.

---

## Step 1: Build Linux Agent

Use this section when publishing or testing the Linux local agent.

### Option A: Use Makefile (Recommended)

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
make build
```

This outputs:

```text
bin/e3-backup-agent
```

The Makefile target builds for the current host platform. On the WHMCS development server this is the Linux agent build.

### Option B: Manual Build Command

Use an explicit target when building from another OS or when you want a reproducible Linux amd64 artifact:

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
mkdir -p bin

CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
  go build -trimpath -ldflags="-s -w" \
  -o bin/e3-backup-agent ./cmd/agent
```

### Verify Linux Build Output

```bash
ls -la bin/e3-backup-agent
file bin/e3-backup-agent
```

Expected result: an executable Linux amd64 binary.

### Install on a Linux Machine

For local installs on the build host:

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
sudo make install
```

This copies the binary to:

```text
/usr/local/bin/e3-backup-agent
```

For a remote Linux machine:

```bash
scp bin/e3-backup-agent root@linux-host:/usr/local/bin/e3-backup-agent
ssh root@linux-host 'chown root:root /usr/local/bin/e3-backup-agent && chmod 755 /usr/local/bin/e3-backup-agent'
```

### Publish Linux Download Artifact

To make the Linux binary available from the WHMCS download path:

```bash
mkdir -p /var/www/eazybackup.ca/accounts/client_installer
cp /var/www/eazybackup.ca/e3-backup-agent/bin/e3-backup-agent \
  /var/www/eazybackup.ca/accounts/client_installer/e3-backup-agent-linux
chmod 644 /var/www/eazybackup.ca/accounts/client_installer/e3-backup-agent-linux
```

Download URL:

```text
https://accounts.eazybackup.ca/client_installer/e3-backup-agent-linux
```

---

## Step 2: Configure and Run Linux Agent

### Create Directories

```bash
sudo mkdir -p /etc/e3-backup-agent /var/lib/e3-backup-agent/runs
sudo chmod 700 /etc/e3-backup-agent /var/lib/e3-backup-agent
```

### Create `agent.conf`

Create `/etc/e3-backup-agent/agent.conf` with either an enrollment token or already-issued agent credentials.

Pre-enrollment example:

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
enrollment_token: "0123456789abcdef0123456789abcdef01234567"
device_name: "linux-server-01"
poll_interval_secs: 5
run_dir: "/var/lib/e3-backup-agent/runs"
```

Post-enrollment example:

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
client_id: "42"
agent_uuid: "6f78c615-3d2f-4b7f-8f5b-56dc0a3da781"
agent_token: "deadbeef...40-hex..."
device_name: "linux-server-01"
poll_interval_secs: 5
run_dir: "/var/lib/e3-backup-agent/runs"
```

Protect the config because it contains enrollment material or agent credentials:

```bash
sudo chown root:root /etc/e3-backup-agent/agent.conf
sudo chmod 600 /etc/e3-backup-agent/agent.conf
```

### Run in Foreground for Testing

```bash
sudo /usr/local/bin/e3-backup-agent \
  -config /etc/e3-backup-agent/agent.conf
```

Use foreground mode for first-run validation. Stop it with `Ctrl+C` after confirming the agent starts and reaches the API.

### Install as a systemd Service

Create `/etc/systemd/system/e3-backup-agent.service`:

```ini
[Unit]
Description=E3 Backup Agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/local/bin/e3-backup-agent -config /etc/e3-backup-agent/agent.conf
Restart=always
RestartSec=10
User=root
Group=root

[Install]
WantedBy=multi-user.target
```

Enable and start it:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now e3-backup-agent
sudo systemctl status e3-backup-agent
```

View logs:

```bash
sudo journalctl -u e3-backup-agent -f
```

Stop, restart, or remove the service:

```bash
sudo systemctl stop e3-backup-agent
sudo systemctl restart e3-backup-agent
sudo systemctl disable --now e3-backup-agent
sudo rm -f /etc/systemd/system/e3-backup-agent.service
sudo systemctl daemon-reload
```

### Linux Disk Image Runtime Requirements

For disk-image jobs:

- Run the service as `root`; normal users cannot read block devices such as `/dev/sda` or `/dev/mapper/vg-root`.
- Install `lvm2` for LVM snapshot support:

```bash
sudo apt-get update
sudo apt-get install -y lvm2
```

- Ensure the source volume has enough free space in the volume group for snapshots. The current Linux implementation attempts an LVM snapshot when possible and otherwise reads the live device directly.
- Use Linux device paths in disk-image jobs, for example `/dev/sda`, `/dev/nvme0n1`, or `/dev/mapper/vg-root`.

---

## Step 3: Build Windows Binaries (Linux Build Host)

You must build **TWO executables**:


| Binary                | Purpose                                       |
| --------------------- | --------------------------------------------- |
| `e3-backup-agent.exe` | Windows service (runs backups)                |
| `e3-backup-tray.exe`  | Tray helper (enrollment UI, user interaction) |


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

## Step 4: Prepare Windows Installer Assets

The Inno Setup installer requires icon assets from the worker repo.

### Required Files

Copy these to your Windows build machine, preserving the folder structure:

```
your-build-folder/
├── e3-backup-agent/
│   ├── bin/
│   │   ├── e3-backup-agent.exe    ← Built in Step 3
│   │   └── e3-backup-tray.exe     ← Built in Step 3
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

## Step 5: Compile Windows Installer (Inno Setup)

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


| File              | Server Path                                                                  | Download URL                                  |
| ----------------- | ---------------------------------------------------------------------------- | --------------------------------------------- |
| Windows Installer | `/var/www/eazybackup.ca/accounts/client_installer/e3-backup-agent-setup.exe` | `/client_installer/e3-backup-agent-setup.exe` |
| Linux Binary      | `/var/www/eazybackup.ca/accounts/client_installer/e3-backup-agent-linux`     | `/client_installer/e3-backup-agent-linux`     |


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

## Step 6: Install on Target Windows Machine

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

After Windows installation, files are located at:


| Location                             | Contents                                                  |
| ------------------------------------ | --------------------------------------------------------- |
| `C:\Program Files\E3Backup\`         | Executables (`e3-backup-agent.exe`, `e3-backup-tray.exe`) |
| `C:\ProgramData\E3Backup\agent.conf` | Configuration file                                        |
| `C:\ProgramData\E3Backup\runs\`      | Backup run data                                           |
| `C:\ProgramData\E3Backup\logs\`      | Log files (`agent.log`, `tray.log`)                       |


After Linux installation with the commands above, files are located at:


| Location                                      | Contents                                         |
| --------------------------------------------- | ------------------------------------------------ |
| `/usr/local/bin/e3-backup-agent`              | Linux agent binary                               |
| `/etc/e3-backup-agent/agent.conf`             | Configuration file                               |
| `/var/lib/e3-backup-agent/runs/`              | Backup run data                                  |
| `/etc/systemd/system/e3-backup-agent.service` | systemd service unit, if installed manually      |
| `journald`                                    | Service logs via `journalctl -u e3-backup-agent` |


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
agent_uuid: "6f78c615-3d2f-4b7f-8f5b-56dc0a3da781"
agent_token: "deadbeef...40-hex..."
device_id: "9f2c3a6c-7d5d-4c67-9c5a-2c1e9c5d7a11"
install_id: "f1c2d3e4-1111-2222-3333-444455556666"
device_name: "FILE-SERVER-01"
poll_interval_secs: 5
user_agent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 e3-backup-agent/1.0"
run_dir: "C:\\ProgramData\\E3Backup\\runs"
```

---

## Manual Windows Service Commands

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


| Target                     | Description                                |
| -------------------------- | ------------------------------------------ |
| `make build`               | Build Linux agent binary                   |
| `make build-windows`       | Build both Windows binaries (agent + tray) |
| `make build-agent-windows` | Build only Windows agent                   |
| `make build-tray-windows`  | Build only Windows tray                    |
| `make clean`               | Remove build artifacts                     |
| `make deps`                | Download Go dependencies                   |


---

## Notes

- The agent uses embedded rclone (no external binary required)
- Supported backends: local, S3
- Poll interval configurable via `poll_interval_secs` (default: 5 seconds)
- The tray helper auto-opens the enrollment page if the device is not enrolled
- Driver bundles for recovery media are stored in customer destination buckets using:
  - `dest_prefix/driver-bundles/<agentid>/<profile>.zip`
- Driver bundle downloads in media manifests are pre-signed (12-hour TTL), so generated URLs are intentionally temporary.

---

## Automated Builds from the WHMCS Admin Area

The cloudstorage addon now ships an "Agent Builds" admin page that drives the
entire pipeline (cross-compile, Inno Setup, Azure code signing, publish) from
WHMCS. The runner uses the local Linux build host plus SSH to a Windows build
host (default: lab Server 2025 at `192.168.92.210`).

### Admin URL

```
WHMCS Admin -> Addons -> Cloud Storage -> Agent Builds
```

Tabs: Dashboard, New Build, Build History, Build Detail, Releases, Deployment, Settings.

### One-time Windows build host prerequisites (Server 2025, 192.168.92.210)

1. **OpenSSH Server** enabled. Verify from the WHMCS host:
   ```bash
   ssh -i /root/.ssh/windows_server_ed25519 Administrator@192.168.92.210 powershell -Command "Write-Output ok"
   ```
   The lab's existing `~/.ssh/windows_server_ed25519` key already authorizes this user; reuse it for the runner.

2. **Inno Setup 6** installed at the default path `C:\Program Files (x86)\Inno Setup 6\ISCC.exe`. Override the path in Agent Builds -> Settings if needed.

3. **AzureSignTool** placed at `C:\Tools\AzureSignTool\AzureSignTool.exe`:
   ```powershell
   New-Item -ItemType Directory -Force C:\Tools\AzureSignTool | Out-Null
   # Download a self-contained AzureSignTool release (.NET 8 runtime included) from
   # https://github.com/vcsjones/AzureSignTool/releases  and place it at the path above.
   ```

4. **Azure setup (one-time):**
   - In the Azure portal create (or reuse) an App Registration and add a client secret.
   - On the Key Vault that holds the code-signing certificate, grant the App registration `Get` and `Sign` permissions on certificates and keys (access policy or RBAC role `Key Vault Crypto User` + `Key Vault Certificate User`).
   - Note the tenant ID, client ID, client secret, vault URL, and certificate name.

5. **Configure WHMCS Agent Builds -> Settings** with all of the above. The client secret is stored encrypted by WHMCS.

6. **Click "Test Connection"** on the Settings tab to verify SSH reachability, ISCC presence, AzureSignTool presence, and config completeness.

### Schedule the runner

The runner is a CLI script with `flock` single-instance protection:

```text
accounts/modules/addons/cloudstorage/crons/agent_build_runner.php
```

Recommended systemd schedule (preferred over WHMCS cron because it gives you live logs and fine-grained timing):

```ini
# /etc/systemd/system/e3-agent-build-runner.service
[Unit]
Description=e3 Agent Build Runner (one-shot)
After=network-online.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/eazybackup.ca/accounts
ExecStart=/usr/bin/php /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/agent_build_runner.php
```

```ini
# /etc/systemd/system/e3-agent-build-runner.timer
[Unit]
Description=Tick the e3 Agent Build Runner every minute

[Timer]
OnBootSec=2min
OnUnitActiveSec=60s
AccuracySec=5s
Unit=e3-agent-build-runner.service

[Install]
WantedBy=timers.target
```

Enable:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now e3-agent-build-runner.timer
```

WHMCS cron alternative (if systemd is undesired):

```cron
* * * * * www-data /usr/bin/php /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/agent_build_runner.php
```

### What gets published

On a successful build with **Publish** checked, the runner writes versioned and "latest" filenames into the configured publish directory (default `/var/www/eazybackup.ca/accounts/client_installer/`):

| Platform | Versioned | Latest alias |
| -------- | --------- | ------------ |
| Linux    | `e3-backup-agent-linux-<version>` | `e3-backup-agent-linux` |
| Windows  | `e3-backup-agent-setup-<version>.exe` | `e3-backup-agent-setup.exe` |
| Recovery | `e3-recovery-agent-<version>.exe` | `e3-recovery-agent.exe` |
| Recovery media creator | `e3-recovery-media-creator-<version>.exe` | `e3-recovery-media-creator.exe` |

A row is inserted in `s3_agent_releases` for each artifact (sha256, size, signed metadata, version, commit, download URL). The Releases tab lets admins promote any prior versioned file back to "latest".

When **Also build recovery agent** is checked, the pipeline also builds and publishes `e3-recovery-media-creator.exe`.

### Troubleshooting

- **Build stays "queued":** the systemd timer/cron is not running. Run the script manually as `www-data` and watch stderr.
- **`windows_stage` fails:** SSH key likely belongs to `root` only; copy it to the runner user's home or override the path in Settings.
- **`windows_inno` fails with "missing AssetsDir":** the staged assets directory is missing or the `.iss` references unstaged paths. The runner stages `tray_logo-drk-orange120x120.png` and `.svg` from `/var/www/eazybackup.ca/e3-cloudbackup-worker/assets/`; confirm these exist.
- **`windows_sign` fails with HTTP 401/403 from Key Vault:** the App registration is missing Key Vault permissions, or the client secret expired; rotate via Settings.
- **`publish` fails with "permission denied":** the runner user (`www-data` by default) cannot write to `/accounts/client_installer/`. Either chown the directory to `www-data` or run the runner as a user that can.

---

## Production Deployment (dev → accounts.eazybackup.ca)

Builds run on the **dev** WHMCS server (`dev.eazybackup.ca`). Customer downloads
live on **production** (`accounts.eazybackup.ca/client_installer/`). The
deployment subsystem uses an explicit **publish on dev** + **pull on prod**
model.

### Architecture

1. Dev admin builds and publishes artifacts to dev `client_installer/`.
2. Dev admin opens **Agent Builds → Deployment** and clicks **Deploy to production** (or checks **Deploy to production after publish** on the New Build form).
3. Dev records an active deployment manifest at:
   ```
   https://dev.eazybackup.ca/modules/addons/cloudstorage/api/agent_deploy_manifest.php
   ```
4. Production runs `crons/agent_deploy_sync.php` every ~5 minutes. It fetches the manifest (Bearer token), downloads artifacts via signed nonce URLs, verifies SHA-256, and installs into prod `client_installer/` plus `s3_agent_releases`.

### One-time setup

**On dev (publisher):**

1. Agent Builds → Settings → Production Deployment
2. Set **Server role** to `Publisher (dev)`
3. Generate a long random shared secret; paste into **Shared deploy secret** and save
4. Note the **Publisher manifest URL** shown on the settings page

**On production (consumer):**

1. Deploy the cloudstorage addon code (same version as dev)
2. Agent Builds → Settings → Production Deployment
3. Set **Server role** to `Consumer (production)`
4. Paste the **same shared secret**
5. Set **Manifest URL** to the dev manifest endpoint (see above)
6. Set **Production publish directory** (e.g. `/var/www/eazybackup.ca/accounts/client_installer`)
7. Enable **Enable deployment sync cron**
8. Schedule the sync runner (systemd recommended):

```ini
# /etc/systemd/system/e3-agent-deploy-sync.service
[Unit]
Description=e3 Agent Deploy Sync (pull from dev)
After=network-online.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/eazybackup.ca/accounts
ExecStart=/usr/bin/php /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/agent_deploy_sync.php
```

```ini
# /etc/systemd/system/e3-agent-deploy-sync.timer
[Unit]
Description=Poll dev for agent installer deployments every 5 minutes

[Timer]
OnBootSec=3min
OnUnitActiveSec=5min
AccuracySec=30s
Unit=e3-agent-deploy-sync.service

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now e3-agent-deploy-sync.timer
```

### Deploy workflow

1. Build with **Publish** checked (and optionally **Deploy to production after publish**)
2. Or, after a successful build: **Deployment** tab → **Deploy latest releases to production**
3. Or, from **Build Detail**: **Deploy to Production** for that specific job
4. Production picks up the new `deployment_id` within ~5 minutes
5. Verify prod downloads:
   - `https://accounts.eazybackup.ca/client_installer/e3-backup-agent-setup.exe`
   - `https://accounts.eazybackup.ca/client_installer/e3-backup-agent-linux`

### Deployment troubleshooting

- **Manifest returns 401:** shared secret mismatch between dev and prod
- **SHA-256 mismatch on prod:** re-deploy from dev; check for partial downloads
- **Prod never updates:** confirm `e3-agent-deploy-sync.timer` is active; run the sync script manually as `www-data`
- **Prod cannot reach dev:** production must have outbound HTTPS to `dev.eazybackup.ca`
- **Deploy button fails on dev:** ensure artifacts exist in dev `client_installer/` and `s3_agent_releases` has rows for them
