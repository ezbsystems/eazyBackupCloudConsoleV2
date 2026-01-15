# Windows Installer (Inno Setup)

This folder contains an Inno Setup script for shipping:
- `e3-backup-agent.exe` (Windows service)
- `e3-backup-tray.exe` (tray helper)

## Build the binaries (from Linux build host)

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o bin/e3-backup-agent.exe ./cmd/agent
# Build tray helper as GUI app to avoid leaving a visible cmd window open.
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w -H=windowsgui" -o bin/e3-backup-tray.exe ./cmd/tray
```

## Compile the installer (on Windows)

Open `installer/e3-backup-agent.iss` in Inno Setup Compiler and build.

## Silent install (MSP/RMM)

- `/TOKEN=...` writes `enrollment_token` to `C:\\ProgramData\\E3Backup\\agent.conf`
- `/API=...` overrides `api_base_url`

Example:

```text
e3-backup-agent-setup.exe /VERYSILENT /TOKEN=0123456789abcdef0123456789abcdef01234567 /API=https://accounts.eazybackup.ca/modules/addons/cloudstorage/api
```

## Consumer install

Run the installer normally, then use the tray helper **Enroll / Sign in…** to authenticate and start the service.


