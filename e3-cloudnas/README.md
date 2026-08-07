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
```
