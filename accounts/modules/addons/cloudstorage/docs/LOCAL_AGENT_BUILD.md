# Local Backup Agent – Build & Deploy

## Prereqs
- Go toolchain (1.24.x) on the build host.
- Source: `/var/www/eazybackup.ca/e3-backup-agent/`
- Windows target: GOOS=windows, GOARCH=amd64, CGO_ENABLED=0.

## Build (Windows binaries) – Linux build host

These steps produce **two executables**:
- `e3-backup-agent.exe` (Windows service)
- `e3-backup-tray.exe` (tray helper + end-user enrollment UI)

### 1) Build the service binary
From the agent repo on the Linux build host:

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o bin/e3-backup-agent.exe ./cmd/agent
```

### 2) Build the tray helper binary (GUI subsystem)

The tray helper must be built as a **GUI app** to avoid leaving a visible cmd window open.

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w -H=windowsgui" -o bin/e3-backup-tray.exe ./cmd/tray
```

### 3) Collect installer assets

The Inno installer bundles icon assets from:
- `/var/www/eazybackup.ca/e3-cloudbackup-worker/assets/`
  - `tray_logo-drk-orange120x120.png`
  - `tray_logo-drk-orange.svg` (source-of-truth)

## Compile the Windows installer (Inno Setup) – Windows machine

The installer script is:
- `/var/www/eazybackup.ca/e3-backup-agent/installer/e3-backup-agent.iss`

### 1) Copy build outputs to Windows (preserve folder layout)

On Windows, you need these relative paths to exist (exactly) for the `.iss` script:

- `e3-backup-agent\bin\e3-backup-agent.exe`
- `e3-backup-agent\bin\e3-backup-tray.exe`
- `e3-backup-agent\installer\e3-backup-agent.iss`
- `e3-cloudbackup-worker\assets\tray_logo-drk-orange120x120.png`
- `e3-cloudbackup-worker\assets\tray_logo-drk-orange.svg`

Easiest: copy the whole repo to something like:
- `C:\src\eazybackup\e3-backup-agent\...`
- `C:\src\eazybackup\e3-cloudbackup-worker\...`

### 2) Install Inno Setup Compiler

Install the Inno Setup Compiler on Windows (GUI app).

### 3) Open and compile the installer

1. Open **Inno Setup Compiler**
2. File → Open → select: `e3-backup-agent\installer\e3-backup-agent.iss`
3. Build → Compile (or press **F9**)

This produces an installer EXE (default name from the script):
- `e3-backup-agent-setup.exe`

### 4) Silent install (MSP/RMM)

The installer supports:
- `/TOKEN=...` to write `enrollment_token` to `C:\ProgramData\E3Backup\agent.conf`
- `/API=...` to override `api_base_url`

Example:

```text
e3-backup-agent-setup.exe /VERYSILENT /TOKEN=0123456789abcdef0123456789abcdef01234567 /API=https://accounts.eazybackup.ca/modules/addons/cloudstorage/api
```

### 5) Consumer install (interactive)

1. Run the installer normally
2. Launch the tray helper (or let it auto-run at login)
3. Click **Enroll / Sign in…** and enter email/password

The tray helper will:
- enroll the device
- write `agent_id`/`agent_token` into `agent.conf`
- start the Windows service


## Files of interest
- Output: `bin/e3-backup-agent.exe`
- Output: `bin/e3-backup-tray.exe`
- Config (on Windows): `%PROGRAMDATA%\E3Backup\agent.conf`
- Run dir (default): `%PROGRAMDATA%\E3Backup\runs`

## agent.conf (enrollment + identity)

The agent supports **two enrollment methods**:
- **MSP/RMM token enrollment** (`enrollment_token`)
- **End-user login enrollment** (`enroll_email` + `enroll_password`)

The installer (or tray helper) should write a minimal config for first run. After successful enrollment, the agent rewrites the config to store `agent_id` + `agent_token` and clears enrollment inputs.

### Example: MSP silent rollout (token)

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
enrollment_token: "0123456789abcdef0123456789abcdef01234567"

# Stable device identity (generated if missing)
device_id: "9f2c3a6c-7d5d-4c67-9c5a-2c1e9c5d7a11"
install_id: "f1c2d3e4-1111-2222-3333-444455556666"
device_name: "FILE-SERVER-01"

poll_interval_secs: 5
run_dir: "C:\\ProgramData\\E3Backup\\runs"
log_level: "info"
```

### Example: End-user enrollment (email/password)

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
enroll_email: "user@example.com"
enroll_password: "example-password"
enroll_remember_me: true

# Stable device identity (generated if missing)
device_id: "9f2c3a6c-7d5d-4c67-9c5a-2c1e9c5d7a11"
install_id: "f1c2d3e4-1111-2222-3333-444455556666"
device_name: "HOME-PC"
```

### After enrollment (persisted credentials)

```yaml
api_base_url: "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
client_id: "42"
agent_id: "123"
agent_token: "deadbeef...40-hex..."
device_id: "9f2c3a6c-7d5d-4c67-9c5a-2c1e9c5d7a11"
install_id: "f1c2d3e4-1111-2222-3333-444455556666"
device_name: "FILE-SERVER-01"
poll_interval_secs: 5
run_dir: "C:\\ProgramData\\E3Backup\\runs"
```

## Running (debug/foreground)
```powershell
.\e3-backup-agent.exe -config C:\ProgramData\E3Backup\agent.conf
```

## Service install (kardianos/service)
- `.\e3-backup-agent.exe -service install -config C:\ProgramData\E3Backup\agent.conf`
- `.\e3-backup-agent.exe -service start -config C:\ProgramData\E3Backup\agent.conf`
- `.\e3-backup-agent.exe -service stop -config C:\ProgramData\E3Backup\agent.conf`
- `.\e3-backup-agent.exe -service uninstall -config C:\ProgramData\E3Backup\agent.conf`

## Tray helper (manual)

Run the tray helper manually (foreground) with:

```powershell
.\e3-backup-tray.exe -config C:\ProgramData\E3Backup\agent.conf
```

## Notes
- Embedded rclone (no external binary).
- Backends registered: local, s3.
- S3 config uses path-style and location_constraint (region) when provided.
- Poll interval set via `poll_interval_secs` in config (default 5s).

## Current blocking issue (handoff)
- Agent sees queued runs in DB, but `agent_next_run.php` returns `no_run` even with `base_count=2` / `filtered_count=2`; claim may be failing.
- Investigate `agent_next_run.php` claim update and DB transaction; see `LOCAL_AGENT_OVERVIEW.md` for context.

