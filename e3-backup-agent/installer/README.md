# Windows Installer (Inno Setup)

This folder contains an Inno Setup script for shipping:
- `e3-backup-agent.exe` (Windows service)
- `e3-backup-tray.exe` (tray helper)
- Optional `e3-cloudnas.exe` sidecar and WinFsp Core runtime

## Build the binaries (from Linux build host)

```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o bin/e3-backup-agent.exe ./cmd/agent
# Build tray helper as GUI app to avoid leaving a visible cmd window open.
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w -H=windowsgui" -o bin/e3-backup-tray.exe ./cmd/tray
```

## Stage the optional Cloud NAS component

Cloud NAS is checked by default on first install when both of these files are
present beside the staged agent installer:

```text
CloudNAS\bin\e3-cloudnas.exe
CloudNAS\installer\redist\winfsp.msi
```

The sidecar must be built on a Windows host with `CGO_ENABLED=1`, the WinFsp
Core and Developer features, and a CGO-capable C compiler. It cannot be
cross-compiled by the Linux `build-windows` target. See
`e3-cloudnas\installer\README.md` for build and MSI-fetch instructions.

If either artifact is absent, Inno omits the Cloud NAS task and sources instead
of failing the agent installer build.

## Compile the installer (on Windows)

Open `installer/e3-backup-agent.iss` in Inno Setup Compiler and build.

Selecting Cloud NAS installs the sidecar in
`C:\Program Files\E3Backup\CloudNAS`, adds the current-user `E3CloudNAS`
startup value, and invokes WinFsp MSI with `ADDLOCAL=Core`. WinFsp exit codes
are logged but do not abort the agent install, allowing an existing WinFsp
installation to remain in place. MSI code `1641` or `3010` means Windows must
be rebooted before mounting drives; investigate any other non-zero code.

## Silent install (MSP/RMM)

- `/TOKEN=...` writes `enrollment_token` to `C:\\ProgramData\\E3Backup\\agent.conf`
- `/API=...` overrides `api_base_url`

Example:

```text
e3-backup-agent-setup.exe /VERYSILENT /TOKEN=0123456789abcdef0123456789abcdef01234567 /API=https://accounts.eazybackup.ca/modules/addons/cloudstorage/api
```

## Consumer install

Run the installer normally, then use the tray helper **Enroll / Sign in…** to authenticate and start the service.


