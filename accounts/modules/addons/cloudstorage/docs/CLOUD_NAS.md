# Cloud NAS – Developer Guide

## What it is
- Client-area feature that mounts an S3 bucket/prefix to a Windows drive letter via the local backup agent.
- Uses embedded rclone library: S3 backend + VFS + embedded WebDAV server; Windows maps drive with `net use`.
- Single-agent binary; no external rclone.exe or WinFSP needed.

## Key paths (WHMCS addon)
- `cloudstorage.php` – route: `page=cloudbackup&view=cloudnas` → `templates/cloudnas.tpl`.
- UI templates:
  - `templates/cloudnas.tpl` (main page + tabs)
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
  - `api/cloudnas_mount_snapshot.php`
  - `api/cloudnas_unmount_snapshot.php`
  - (Shared) `api/cloudbackup_list_jobs.php`, `api/cloudbackup_list_snapshots.php`
- DB schema: `sql/cloudnas_schema.sql`
  - `s3_cloudnas_mounts` (mount configs, per client/agent)
  - `s3_cloudnas_settings` (per-client defaults/settings)
- Navigation:
  - Cloud Backup tab `view=cloudnas` (in `cloudbackup_nav.tpl`)
  - Sidebar link in `templates/eazyBackup/header.tpl`

## Agent code (Windows)
- Project: `e3-backup-agent`
- File: `internal/agent/nas.go`
  - `mountNASDrive`:
    - Build S3 fs via rclone `s3.NewFs` with endpoint/region/keys from payload.
    - Create VFS with cache mode (off/minimal/writes/full) and optional read-only.
    - Start embedded WebDAV server on `127.0.0.1:0` backed by the VFS.
    - Map drive with Windows WebDAV client: `net use <drive>: \\127.0.0.1@<port>\DavWWWRoot /persistent:no`.
    - Track mount in `NASMount` (WebDAV server, VFS, port, metadata).
  - `unmountNASDrive`:
    - `net use <drive>: /delete /y`
    - Graceful shutdown of WebDAV server + VFS.
  - `UnmountAll` shuts down all mounts on agent exit.
  - Snapshot mount placeholders remain unimplemented (returns informative error).
- Dependencies used: rclone `s3` backend, `vfs`, `golang.org/x/net/webdav`; no external binaries.

## Data model
`s3_cloudnas_mounts`
- id (PK), client_id, agent_id
- bucket_name, prefix
- drive_letter
- read_only (bool), persistent (bool), cache_mode
- status (mounted, unmounted, mounting, unmounting, error)
- error (text), last_mounted_at, created_at, updated_at

`s3_cloudnas_settings`
- id (PK), client_id (unique)
- settings_json (cache_mode, cache_size_gb, bandwidth limits, auto_mount, default_read_only), timestamps

## UI behavior (client)
- `templates/cloudnas.tpl` loads tabs (My Drives / Time Machine / Settings) via Alpine.
- Mount wizard:
  - Select agent, bucket, prefix, drive letter
  - Options: read-only, cache mode, auto-mount (persistent)
  - On submit: `cloudnas_create_mount.php` then auto `cloudnas_mount.php`.
- Drives tab shows cards with status, pills (RO, cache, auto-mount), actions (mount/unmount/edit/delete).
- Time Machine tab:
  - Lists Kopia jobs (engine=kopia) via `cloudbackup_list_jobs.php`
  - Snapshots via `cloudbackup_list_snapshots.php`
  - Mount snapshot → `cloudnas_mount_snapshot.php` (agent placeholder until implemented)

## Server–agent flow (mount)
1) Client creates mount config → `cloudnas_create_mount.php` stores row in `s3_cloudnas_mounts`.
2) Client requests mount → `cloudnas_mount.php` enqueues `nas_mount` command in `s3_cloudbackup_run_commands` with payload (bucket/prefix/drive_letter/endpoint/keys/cache_mode/read_only).
3) Agent polls `agent_poll_pending_commands.php` and executes `mountNASDrive`.
4) Agent reports status via `cloudnas_update_status.php` (optional) and drive appears in UI as “mounted”.
5) Unmount → `cloudnas_unmount.php` enqueues `nas_unmount`; agent runs `unmountNASDrive`.

## Important considerations
- Windows WebDAV client:
  - WebClient service must be running.
  - For non-SSL basic auth, registry may need `HKLM\SYSTEM\CurrentControlSet\Services\WebClient\Parameters\BasicAuthLevel=2`, then restart WebClient service. Localhost often works without change, but document this for support.
  - Default upload limit (50MB) is a WebClient setting; can be raised in the same registry key (`FileSizeLimitInBytes`).
- Performance: WebDAV overhead is slightly higher than FUSE; VFS cache helps. Cache mode is configurable.
- Security: WebDAV served on 127.0.0.1 only; no auth since it is loopback + agent-controlled.
- Snapshot mount: currently stubbed; Time Machine UI calls the endpoint, but agent returns “not implemented” error—needs future work if snapshot browsing is required.

## Testing checklist
- Create mount (RO and RW) and verify:
  - `net use` shows mapping to `\\127.0.0.1@<port>\DavWWWRoot`
  - Browse via Explorer; reads succeed; writes only in RW mode
  - Unmount removes mapping and server stops
- Settings: cache modes apply; read-only enforced.
- Error paths: invalid drive letter, missing bucket/keys, WebClient stopped.

## Build / deploy notes
- Agent builds as single binary (Windows):
  - `GOOS=windows GOARCH=amd64 go build -o bin/e3-backup-agent.exe ./cmd/agent`
- No external rclone.exe or WinFSP required.

## Future work
- Implement Kopia snapshot mount browsing.
- Add bandwidth enforcement for WebDAV (currently relies on VFS caching and general network limits).
- Better status callbacks: agent → `cloudnas_update_status.php` to reflect errors in UI promptly.

