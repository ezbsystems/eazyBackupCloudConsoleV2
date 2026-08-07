# Cloud NAS WinFsp Sidecar — Design

Date: 2026-08-07  
Status: Approved  
Related bug: Mapped Cloud NAS drive mirrors C: capacity/free space; writes fail when C: is low.

## Goal

Deliver correct Windows drive capacity/free space for Cloud NAS (and remove false “disk full” write failures) by replacing the Windows WebDAV Mini-Redirector mount path with a **WinFsp** filesystem mount, without linking GPLv3/WinFsp into the proprietary e3 agent.

## Root cause (confirmed)

Runtime evidence (2026-08-07):

- Explorer shows Cloud NAS **Y:** identical to **C:** (e.g. 7.12 GB free of 63.9 GB).
- `net use` remote is `\\127.0.0.1@<port>\DavWWWRoot` (WebDAV).
- PROPFIND for `DAV:quota-available-bytes` / `quota-used-bytes` returns **HTTP 404** from `golang.org/x/net/webdav`.
- Windows WebDAV Mini-Redirector **by design** mirrors system-drive capacity ([KB2386902](https://support.microsoft.com/kb/2386902)) and does not use remote quota for Explorer or free-space enforcement.

VFS cache on/off is irrelevant: the bug is the transport, not rclone cache mode.

## Non-goals

- Embedding WinFsp / `cgofuse` / rclone `cmount` inside `e3-backup-agent.exe` or `e3-backup-tray.exe`.
- SMB-based mounts.
- Keeping WebDAV as a long-term fallback (removed for Cloud NAS once sidecar ships).
- Implementing Kopia snapshot mounts (still separate / stubbed).
- Changing Ceph/bucket quota product behavior (unlimited buckets remain unlimited from the OS view via large Statfs values).

## Approach (approved)

**Optional GPLv3 sidecar application** (`e3-cloudnas`), agent-driven (no standalone product UI required):

| Piece | License | Responsibility |
|-------|---------|----------------|
| e3-backup-agent | Proprietary | WHMCS poll, temp S3 keys, desired-mount state, localhost calls to sidecar |
| e3-backup-tray | Proprietary | Enrollment/status; Cloud NAS WebDAV mapping removed; optional “sidecar missing” affordances |
| e3-cloudnas | **GPLv3** | WinFsp + rclone VFS/`cmount`; loopback control API; mounts drive letters in **user session** |
| WinFsp Core MSI | Bundled with sidecar | Silent install with Cloud NAS optional component |

Legal note (not legal advice): keep agent and sidecar as **separate processes** communicating only over a documented localhost API. Do not statically/dynamically link WinFsp/GPL code into proprietary binaries. Ship sidecar LICENSE + corresponding source offer. Confirm WinFsp redistribution / commercial terms with counsel before production distribution.

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

### Why user-session mount

Drive letters created in Session 0 (Windows service) are often invisible to interactive Explorer. Today’s design already maps WebDAV from the tray (user session). The sidecar must likewise run in the **logged-on user session** (login startup / tray-started helper).

### Capacity reporting

rclone VFS `Statfs` fills missing backend quota with a large default free size (`unknownFreeBytes` = 1 PiB). For buckets without RGW quota that yields Explorer totals/free in the petabyte range — acceptable for “unlimited.” Optional later: set an explicit `--vfs-disk-space-total-size` / helper flag if product wants a fixed display size.

## Components

### 1. `e3-cloudnas` (new repo or `e3-cloudnas/` tree, GPLv3)

- Windows amd64 binary built **with CGO** against WinFsp / `cgofuse` (build on Windows build host).
- Embeds or vendors rclone S3 + VFS + cmount (same major rclone line as agent where practical).
- Starts at user login (HKCU Run or scheduled task) when installed.
- Listens on `127.0.0.1` only; random high port or fixed port written to discovery file.
- Auth: shared secret token (file under `%ProgramData%\E3Backup\` with restrictive ACLs, or same pattern as tray `X-E3-CloudNAS-Token`).
- No customer-facing window required; optional systray icon “Cloud NAS helper running” is allowed but not required for v1.
- Logs under `%ProgramData%\E3Backup\logs\cloudnas.log`.

### 2. Agent changes (proprietary)

- Stop starting embedded WebDAV for Cloud NAS.
- On prepare/mount: ensure sidecar healthy; `POST /mount` with endpoint, region, keys, bucket, prefix, drive letter, cache mode, read-only, volume label, mount_id.
- On unmount/stop: `POST /unmount`; then revoke temp key (existing).
- If sidecar missing/unreachable: set mount status `error` with stable code/message, e.g. `cloudnas_sidecar_missing` — “Cloud NAS component is not installed or not running.”
- Keep debug instrumentation until post-fix verification of capacity fix; then remove WebDAV-specific debug wrappers.

### 3. Tray changes (proprietary)

- Remove WebDAV `WNetAddConnection2` / `DavWWWRoot` / MountPoints2 WebDAV paths for Cloud NAS.
- Optionally surface sidecar health (installed? running?) for support.
- May launch/ensure sidecar process if installed but not running (same user session).

### 4. WHMCS / UI

- Mount wizard / drive cards: if agent reports `cloudnas_sidecar_missing`, show install guidance (link to agent installer with Cloud NAS component, or download sidecar package).
- Docs: update `CLOUD_NAS.md` — WinFsp sidecar, no WebDAV, optional install.

### 5. Installer

- Main **E3 Backup Agent** Inno Setup: task/checkbox **“Install Cloud NAS (optional)”** (default: checked for new installs that use Cloud NAS product, or unchecked — product decision in implementation plan; recommendation: **checked** when shipping Cloud NAS as a first-class feature).
- If selected:
  - Install/run nested **e3-cloudnas** setup (or extract portable binaries + run WinFsp MSI).
  - Silent WinFsp Core: `msiexec /i winfsp-*.msi /qn` (Core feature only; no Developer).
  - Handle “WinFsp already installed” and “reboot required if FSD loaded” gracefully (message + continue; document rare reboot case).
- Sidecar updates: either bundled in agent installer upgrades or separate signed sidecar package published beside agent builds.
- Client must **not** be told to download WinFsp from the internet manually.

## Local control API (v1)

Base URL: `http://127.0.0.1:<port>/`  
Header: `X-E3-CloudNAS-Token: <token>`

| Method | Path | Body (JSON) | Result |
|--------|------|-------------|--------|
| GET | `/health` | — | `{ "ok": true, "version": "...", "winfsp": true }` |
| GET | `/status` | — | `{ "mounts": [ { "mount_id", "drive_letter", "state", "error" } ] }` |
| POST | `/mount` | mount params (see below) | `{ "ok": true }` or error |
| POST | `/unmount` | `{ "drive_letter" }` or `{ "mount_id" }` | `{ "ok": true }` |

### Mount params

```json
{
  "mount_id": 123,
  "drive_letter": "Y",
  "bucket": "...",
  "prefix": "",
  "endpoint": "https://...",
  "region": "",
  "access_key": "...",
  "secret_key": "...",
  "cache_mode": "writes",
  "read_only": false,
  "volume_label": "Cloud NAS (bucket)"
}
```

Secrets only on loopback; never logged in full. Discovery file example: `%ProgramData%\E3Backup\cloudnas.discovery` → `{ "port", "pid", "version" }` (token not stored world-readable).

## Error handling

| Condition | Behavior |
|-----------|----------|
| Sidecar not installed | Agent error `cloudnas_sidecar_missing`; UI prompts optional install |
| Sidecar installed, not running | Agent/tray attempts start; else `cloudnas_sidecar_not_running` |
| WinFsp missing/broken | Sidecar `/health` `winfsp: false`; error `winfsp_missing` (re-run sidecar installer) |
| Drive letter in use | Mount fails with clear error; agent reports to WHMCS |
| S3 auth/list failure | Mount fails; agent sets mount `error` with sanitized message |
| User logoff | Mounts drop with session (expected); on next login + agent poll, remount desired persistent mounts |
| Agent service restart | Sidecar keeps mounts unless unmount requested; agent reconciles desired set on poll |

## Migration

1. Ship agent that prefers sidecar; refuse WebDAV for new mounts.
2. On upgrade: if old WebDAV mounts active, unmount WebDAV path then remount via sidecar when available.
3. Remove WebDAV NAS server code after sidecar path is verified (or gate behind dead code removal in same release once tests pass).

## Testing

- Fresh install with Cloud NAS component: WinFsp present, sidecar starts at login, no manual WinFsp download.
- Mount RW bucket: Explorer capacity **does not** equal C:; free space is large (or configured total); copy file larger than C: free space **succeeds**.
- Mount RO: writes denied by filesystem.
- Unmount: letter gone; temp key revoked.
- Sidecar absent: UI/agent error is actionable.
- Cache modes off/minimal/writes/full: mount works; capacity still not mirroring C:.
- Upgrade from WebDAV-era agent: remount works; no stuck `DavWWWRoot` mappings.
- Multi-mount: two drive letters concurrently.

## Build / publish

- Agent: keep `CGO_ENABLED=0` Linux cross-compile.
- Sidecar: Windows build host, `CGO_ENABLED=1`, WinFsp dev headers for compile; runtime needs Core only.
- Sign sidecar EXE + its installer; publish via Agent Builds (new artifact) or nested in WindowsStage assets.
- Document GPLv3 source tarball location matching each released sidecar version.

## Success criteria

1. Cloud NAS drive capacity/free space in Explorer is **not** equal to C: for the same machine.  
2. Writes succeed when bucket has space even if C: free space is lower than the write size (within WebClient/OS unrelated limits removed).  
3. Proprietary agent binaries contain **no** WinFsp/cgofuse linkage.  
4. End users who select Cloud NAS at install do not manually install third-party software.

## Product decisions (resolved in implementation plan)

1. Default Inno checkbox: Cloud NAS **checked** by default.  
2. Sidecar source location: monorepo top-level **`e3-cloudnas/`**.  
3. Capacity display: rclone VFS default (~1 PiB free when backend has no About).
