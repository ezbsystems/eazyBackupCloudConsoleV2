# Cloud NAS – Developer Guide

## What it is

- Client-area feature that mounts an S3 bucket/prefix to a Windows drive letter via the local backup agent.
- Windows mounts use a separate **e3-cloudnas** sidecar (GPLv3) with WinFsp + rclone VFS/cmount — not WebDAV.
- The proprietary **e3-backup-agent** handles WHMCS polling, temp S3 keys, and localhost HTTP calls to the sidecar.
- Optional at install: the E3 Backup Agent installer includes an **Install Cloud NAS** task (checked by default) that installs the sidecar and WinFsp Core silently.

## Architecture

```text
WHMCS Cloud NAS UI
        │  nas_mount / pending mounts (existing)
        ▼
e3-backup-agent (proprietary, CGO=0)
        │  HTTPS: temp keys, status callbacks
        │  HTTP loopback + shared token: mount/unmount/status
        ▼
e3-cloudnas (GPLv3, user session)
        │  WinFsp cmount → drive letter (e.g. Y:)
        ▼
Explorer / apps  (Statfs from WinFsp, not C:)
```

| Component | License | Role |
|-----------|---------|------|
| e3-backup-agent | Proprietary | WHMCS poll, temp S3 keys, desired-mount state, sidecar client |
| e3-backup-tray | Proprietary | Enrollment/status; ensures sidecar running in user session |
| e3-cloudnas | GPLv3 | WinFsp mount, loopback control API, drive letters in logged-on session |
| WinFsp Core | Bundled with sidecar | Silent install with Cloud NAS optional component |

The agent and sidecar are **separate processes** linked only by a documented localhost API. WinFsp/GPL code is never linked into proprietary binaries.

## Key paths (WHMCS addon)

- `cloudstorage.php` – route: `page=cloudbackup&view=cloudnas` → `templates/e3backup_cloudnas.tpl`.
- UI templates:
  - `templates/e3backup_cloudnas.tpl` (main page + Alpine controller)
  - `templates/partials/cloudnas_drives.tpl` (My Drives)
  - `templates/partials/cloudnas_timemachine.tpl` (Time Machine)
  - `templates/partials/cloudnas_settings.tpl` (Settings)
  - `templates/partials/cloudnas_mount_wizard.tpl` (wizard modal)
- APIs:
  - `api/cloudnas_list_mounts.php`
  - `api/cloudnas_create_mount.php`
  - `api/cloudnas_mount.php`
  - `api/cloudnas_unmount.php`
  - `api/cloudnas_delete_mount.php`
  - `api/cloudnas_settings.php`
  - `api/cloudnas_available_drives.php`
  - `api/cloudnas_mount_snapshot.php`
  - `api/cloudnas_unmount_snapshot.php`
  - (Shared) `api/cloudbackup_list_jobs.php`, `api/cloudbackup_list_snapshots.php`
- DB schema: `sql/cloudnas_schema.sql`
  - `s3_cloudnas_mounts` (mount configs, per client/agent)
  - `s3_cloudnas_settings` (per-client defaults/settings)
- Design spec: `docs/superpowers/specs/2026-08-07-cloudnas-winfsp-sidecar-design.md`

## Agent code (Windows)

- Project: `e3-backup-agent`
- Sidecar client: `internal/agent/nas_sidecar.go`, `nas_sidecar_windows.go`
  - Discovers sidecar via `%ProgramData%\E3Backup\cloudnas.discovery`
  - Auth token from `%ProgramData%\E3Backup\cloudnas.token`
  - Calls `GET /health`, `POST /mount`, `POST /unmount` on loopback
- Mount path: `internal/agent/nas.go`
  - `mountNASDrive`: health-check sidecar, then `POST /mount` with S3 credentials and mount params
  - `unmountNASDrive`: `POST /unmount`, then revoke temp key
  - Stable error prefixes: `cloudnas_sidecar_missing`, `cloudnas_sidecar_not_running`, `winfsp_missing`
- Tray: `cmd/tray/cloudnas_control_windows.go` — starts/ensures sidecar in user session (no WebDAV mapping)

## Sidecar code (Windows)

- Project: `e3-cloudnas` (GPLv3, top-level monorepo tree)
- Entry: `cmd/e3-cloudnas/main.go`
- Mount backend: `internal/mount/winfsp_windows.go` (CGO + WinFsp/cgofuse)
- Control API: `internal/api/server.go`
  - `GET /health` → `{ ok, version, winfsp }`
  - `GET /status` → active mounts
  - `POST /mount` / `POST /unmount`
- Runs in the **logged-on user session** (drive letters visible in Explorer).
- Logs: `%ProgramData%\E3Backup\logs\cloudnas.log`

## Capacity reporting (Statfs)

The legacy WebDAV Mini-Redirector path mirrored C: free space in Explorer ([KB2386902](https://support.microsoft.com/kb/2386902)), causing false “disk full” write failures when C: was low.

The WinFsp sidecar uses rclone VFS `Statfs`. When the S3 backend has no quota, rclone reports a large default free size (~1 PiB). Explorer shows petabyte-scale totals/free for unlimited buckets — acceptable for “unlimited” product behavior and **does not** mirror C:.

## Data model

`s3_cloudnas_mounts`
- id (PK), client_id, agent_id
- bucket_name, prefix
- drive_letter
- read_only (bool), persistent (bool), cache_mode
- status (mounted, unmounted, mounting, unmounting, error)
- error (text) — stable code prefix when sidecar/WinFsp missing, e.g. `cloudnas_sidecar_missing: …`
- temp_access_key (nullable) — AdminOps temp key issued for the active mount; revoked on unmount/delete
- temp_key_ceph_uid (nullable) — Ceph UID the temp key belongs to (needed for revocation)
- last_mounted_at, created_at, updated_at

`s3_cloudnas_settings`
- id (PK), client_id (unique)
- settings_json (cache_mode, cache_size_gb, bandwidth limits, auto_mount, default_read_only), timestamps

## UI behavior (client)

- `templates/e3backup_cloudnas.tpl` loads tabs (My Drives / Time Machine / Settings) via Alpine.
- Mount wizard: select agent, bucket, prefix, drive letter; options for read-only, cache mode, auto-mount.
- On submit: `cloudnas_create_mount.php` then auto `cloudnas_mount.php`.
- Drives tab shows cards with status, pills (RO, cache, auto-mount), actions (mount/unmount/edit/delete).
- When mount `error` contains `cloudnas_sidecar_missing` or `winfsp_missing`, My Drives and the mount wizard show a banner: re-run the agent installer with **Install Cloud NAS** checked.
- Time Machine tab: Kopia jobs/snapshots; snapshot mount still stubbed on the agent.

## Server–agent flow (mount)

1) Client creates mount config → `cloudnas_create_mount.php` stores row in `s3_cloudnas_mounts`.
2) Client requests mount → `cloudnas_mount.php`:
   - Resolves the Ceph UID for the bucket owner.
   - Creates a temporary S3 key via `AdminOps::createTempKey()`.
   - Stores the temp key ID in `s3_cloudnas_mounts.temp_access_key` for later revocation.
   - Enqueues `nas_mount` command with payload (bucket/prefix/drive_letter/endpoint/temp-keys/cache_mode/read_only).
   - On any error, updates mount status to `error` with a descriptive message for the UI.
3) Agent polls pending commands and calls the sidecar to mount via WinFsp.
4) Agent reports status via `cloudnas_update_status.php`; drive appears as "mounted" or "error".
5) Unmount → `cloudnas_unmount.php` revokes temp key, enqueues `nas_unmount`; agent calls sidecar unmount.
6) Delete → `cloudnas_delete_mount.php` revokes temp key and sends unmount if mounted.

## Installer

- E3 Backup Agent Inno Setup: optional task **Install Cloud NAS** (default checked).
- When selected: installs `e3-cloudnas` and WinFsp Core silently (`msiexec /i winfsp-*.msi /qn`).
- Customers are not asked to download WinFsp manually.
- Re-run the agent installer with **Install Cloud NAS** checked to repair missing sidecar or WinFsp.

## Error codes (agent → WHMCS)

| Code | Meaning | UI action |
|------|---------|-----------|
| `cloudnas_sidecar_missing` | Sidecar not installed | Banner: re-run installer with Cloud NAS checked |
| `cloudnas_sidecar_not_running` | Installed but not reachable | Tray/agent may restart sidecar; else support |
| `winfsp_missing` | WinFsp not installed or broken | Re-run installer with Cloud NAS checked |

## Testing checklist

- Fresh install with Cloud NAS component: WinFsp present, sidecar starts at login.
- Mount RW bucket: Explorer capacity **does not** equal C:; copy larger than C: free space succeeds.
- Mount RO: writes denied.
- Unmount: letter gone; temp key revoked.
- Sidecar absent: UI banner and mount card show actionable error.
- Cache modes off/minimal/writes/full: mount works; capacity still not mirroring C:.
- Upgrade from WebDAV-era agent: remount via sidecar; no stuck `DavWWWRoot` mappings.

## Build / deploy notes

- Agent: `CGO_ENABLED=0` cross-compile (Windows amd64).
- Sidecar: Windows build host, `CGO_ENABLED=1`, WinFsp dev headers; runtime needs WinFsp Core only.
- Publish sidecar via Agent Builds (`WindowsStage.php` stages sidecar binary + WinFsp MSI).
- Ship GPLv3 source offer matching each released sidecar version.

## Future work

- Implement Kopia snapshot mount browsing.
- Orphan temp key cleanup for mounts stuck in `error`/`unmounted`.
- Optional fixed `--vfs-disk-space-total-size` for Explorer display sizing.
