# AGENTS.md

## Cursor Cloud specific instructions

This repo is a monorepo for the eazyBackup / "e3" backup platform:

- `accounts/` — WHMCS PHP control plane (billing, portal, orchestration APIs, cron jobs) delivered as custom WHMCS modules.
- `e3-backup-agent/` — Go Windows endpoint backup agent (service + tray + recovery).
- `e3-cloudbackup-worker/` — Go Linux worker for cloud-to-cloud (S3/SFTP → S3) backups. Polls the WHMCS DB for queued runs and executes them with `rclone`.
- `ms365-backup-worker/` — Go Linux worker for Microsoft 365 backups (Graph ingest + Kopia).
- `recovery/` — bare-metal recovery media build scripts (build-time tooling, not a runtime service).
- `docs/` — design/spec docs only.

### Toolchain (already installed in the snapshot)

- Go 1.25 lives at `/usr/local/go` and is prepended to `PATH` via `~/.bashrc`. The system also has an older `/usr/bin/go` (1.22); make sure `/usr/local/go/bin` wins. `ms365-backup-worker` requires Go 1.25 (`go.mod`), the other two require 1.24.
- `golangci-lint` (v2.x) is installed at `~/go/bin/golangci-lint`.
- `rclone` **v1.75** is at `/usr/local/bin/rclone`. Do NOT rely on the Ubuntu apt `rclone` (v1.60 at `/usr/bin/rclone`): `e3-cloudbackup-worker` passes a lowercase `--log-level` (e.g. `info`), which older rclone rejects with `unknown log level "info"`. Use rclone ≥ ~1.63.
- `mysql-server` **8.0** is installed (NOT MariaDB). The workers' SQL uses `UUID_TO_BIN` / `BIN_TO_UUID`, which MariaDB 10.11 does not provide. Start it with `sudo service mysql start` (systemd is not the init here; use the `service` command).
- `minio` + `mc` (MinIO server/client) are at `/usr/local/bin` for a local S3-compatible endpoint when exercising the cloud-backup worker.

### Build / test / lint (Go services)

Run these from each service directory. Standard targets live in each `Makefile`.

| Service | Build | Test | Lint |
| --- | --- | --- | --- |
| `e3-cloudbackup-worker` | `make build` | `make test` (`go test ./...`) | `make lint` |
| `e3-backup-agent` | `make build` | `make test` | `make lint` |
| `ms365-backup-worker` | `make build` | `make test` | no `lint` target (run `golangci-lint run` manually) |

Notes:

- `make lint` currently reports many pre-existing findings in `e3-cloudbackup-worker` and `e3-backup-agent` and exits non-zero; that is the baseline, not something you introduced.
- `ms365-backup-worker` tests: `TestApplyWithReadOnlyConfigDir` (in `internal/configapply`) only passes as **root** (it relies on root bypassing a `0555` dir). As the normal `ubuntu` user it fails with `permission denied`; everything else passes. Run the full suite with `sudo -E env PATH="$PATH" go test ./...` if you need that test green.

### Running `e3-cloudbackup-worker` end-to-end (the runnable flagship service)

The worker has no external SaaS dependency, so it can run fully locally:

1. Start MySQL 8 (`sudo service mysql start`) and create a DB with the WHMCS `s3_cloudbackup_*` / `s3_buckets` / `s3_user_access_keys` / `tbladdonmodules` / `tblconfiguration` tables. It claims rows in `s3_cloudbackup_runs` where `status='queued'`.
2. Provide an S3 endpoint (MinIO works) for both source and destination.
3. Copy `config/config.yaml.example` → a real `config.yaml`, set `rclone.binary_path` to `/usr/local/bin/rclone` and `destination.endpoint` to your S3 endpoint.
4. Export `CLOUD_BACKUP_ENCRYPTION_KEY` (used to decrypt destination S3 keys via AES-256-CBC, PHP-compatible format `base64(IV[16] + ciphertext)`; see `internal/crypto`). Source config that is plaintext JSON without an `=` character is accepted as-is (dev bypass).
5. Run `./bin/e3-cloudbackup-worker -config config.yaml`. It polls, generates an `rclone.conf`, runs `rclone sync source → dest`, records events in `s3_cloudbackup_run_events`, and sets the run to `success`. The post-run notify POST to WHMCS `SystemURL` is best-effort and safe to fail locally.

### Services that need external infrastructure (not runnable to completion here)

- `ms365-backup-worker` — needs the WHMCS cloudstorage HTTP API + a worker token (`MS365_WORKER_TOKEN`) and Microsoft Graph / Entra credentials. Verified here via build, unit tests, and CLI (`-config`).
- `e3-backup-agent` — a Windows service/agent that talks to the WHMCS `cloudstorage` API and stores to S3. Cross-compile Windows binaries with `make build-windows`. Verified here via build, unit tests, and CLI.
- `accounts/` (WHMCS PHP app) — cannot run in this VM. It requires the proprietary WHMCS core (`accounts/init.php`) plus the ionCube Loader, MySQL, Ceph RGW, Comet, and Microsoft Graph — none of which are in the repo. The addon PHPUnit suite (`accounts/modules/addons/eazybackup`) bootstraps `accounts/init.php`, so it also cannot run without a full WHMCS install.
