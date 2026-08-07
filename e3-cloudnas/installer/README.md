# E3 Cloud NAS Windows installer

This Inno Setup package installs the GPLv3 `e3-cloudnas.exe` sidecar under
`C:\Program Files\E3Backup\CloudNAS`, registers `E3CloudNAS` in the current
user's `HKCU\...\Run` key, and silently installs only the WinFsp Core feature.

## Prepare the Windows build host

The sidecar uses WinFsp through CGO and cannot be cross-compiled by the Linux
agent build step. On the Windows host, install the WinFsp Core and Developer
features plus a CGO-capable C compiler, then run:

```bat
cd e3-cloudnas
set CGO_ENABLED=1
go build -trimpath -ldflags="-s -w" -o bin\e3-cloudnas.exe .\cmd\e3-cloudnas
```

`make build-windows` is equivalent when GNU Make is available.

## Supply WinFsp

The MSI is intentionally not stored in Git. Download the approved, pinned
WinFsp release into `installer\redist\winfsp.msi` and verify its SHA-256 before
compiling:

- Release page: <https://github.com/winfsp/winfsp/releases>
- Pinned asset URL: replace with the release approved for production
- Expected SHA-256: `<REPLACE_WITH_APPROVED_RELEASE_SHA256>`

The release pipeline may provide this file through Git LFS or a trusted CI
cache. Never compile an installer from an unverified download.

## Compile and operate

Open `e3-cloudnas.iss` in Inno Setup 6 and compile it after the executable and
MSI are present. WinFsp MSI exit codes are logged and do not abort sidecar file
installation, which permits repair/upgrade when WinFsp is already installed.
If Windows reports MSI code `1641` or `3010`, reboot before mounting Cloud NAS
drives. Other non-zero MSI codes require investigation in the installer log.

The sidecar is not launched by the elevated installer. It starts in the
interactive user's session at the next sign-in, or when the E3 Backup tray
helper starts it.
