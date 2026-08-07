# e3-cloudnas

Cloud NAS sidecar for E3 Backup. Exposes a loopback-only HTTP control API used by the proprietary E3 Backup Agent to mount S3-backed cloud storage as Windows drive letters via WinFsp.

## License

This project is licensed under the **GNU General Public License v3.0 (GPLv3)**. See [LICENSE](LICENSE).

`e3-cloudnas` is a separate GPLv3 component. It is **not** part of the proprietary `e3-backup-agent` codebase. The agent communicates with this sidecar over `127.0.0.1` using a shared token.

## Corresponding source

This repository contains the complete corresponding source for `e3-cloudnas`. For GPLv3 compliance questions or source requests, contact EZB Systems.

## Development

```bash
go test ./... -count=1
go run ./cmd/e3-cloudnas -program-data /tmp/e3backup-test
make build
```

The default Linux build uses the unsupported-platform mount stub so API and
control-plane development can run without WinFsp.

## Windows build

Install both the WinFsp Core and Developer packages, and install a CGO-capable
C compiler. Then run the following from a Windows shell:

```bat
set CGO_ENABLED=1
go build -trimpath -ldflags="-s -w" -o bin/e3-cloudnas.exe ./cmd/e3-cloudnas
```

`make build-windows` runs the same build when `make` is available. The
resulting sidecar requires the WinFsp runtime at startup. Its authenticated
`/health` response reports `"winfsp": true` when the WinFsp DLL or installation
registry key is detectable.

### Windows smoke checklist

- Install WinFsp Core and Developer on the build VM.
- Build `bin/e3-cloudnas.exe` with CGO enabled.
- Start the executable and confirm `cloudnas.discovery` is written.
- Call `/health` with `X-E3-CloudNAS-Token` and confirm `winfsp` is `true`.
- Mount a test bucket to `Z:`, verify its free space differs from `C:`, and
  copy a test file through Explorer.

This checklist must be completed on a Windows host; it cannot be validated by
the Linux stub build.
