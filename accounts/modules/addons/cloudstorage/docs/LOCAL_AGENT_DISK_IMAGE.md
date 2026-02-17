# Local Agent – Disk Image Backup (Windows VSS + Linux LVM)

This document explains the disk image backup path added to the local agent (Kopia engine), the files involved, schema fields, and how it works end to end so another developer can continue the work.

---

## Tech Stack
- Agent: Go 1.24, single binary.
- Backup engines: Kopia (snapshots/dedup), rclone (legacy sync).
- Disk image support:
  - Windows: VSS via `github.com/mxk/go-vss` (v1.2.0).
  - Linux: LVM snapshots (shelling to `lvcreate`/`lvremove` when available).
  - Image formats: raw sparse (all platforms), VHDX placeholder writer (pure Go stub; no real VHDX metadata yet).
- Storage: S3-compatible (MinIO SDK) via Kopia.
- UI/Server: WHMCS addon PHP endpoints + Smarty templates.

---

## Agent Code Overview (disk image path)
- `internal/agent/disk_image.go`
  - Orchestration for disk image runs (`runDiskImage` via `runRun` engine `disk_image`).
  - Normalizes options (source volume/device, format, temp dir, block size, cache).
  - Captures disk layout metadata and passes it into streaming read-plan selection.
  - Implements end-of-backup restore-readiness verification and metadata-only retries.
  - Uses per-OS `createDiskImageStream` for direct device streaming into Kopia.
  - Sparse writer helper (`openSparseWriter`) with VHDX stub + raw writer.
  - Block cache scaffold (`BlockCache`) for future change-detection.
- `internal/agent/disk_image_windows.go`
  - Uses VSS for volume sources: `vss.Create(volume)` → shadow copy device path via `vss.Get`.
  - For physical-disk sources, streams directly from `\\.\PhysicalDriveN`.
  - Builds stream entries with warning callbacks for recovered read errors.
- `internal/agent/disk_image_cbt_windows.go`
  - Windows read-plan builder for CBT/bitmap/physical-layout modes.
  - Physical-disk mode now prefers disk-layout extents + metadata extents (header/GPT tail) over full-disk reads.
- `internal/agent/disk_image_linux.go`
  - Tries to create LVM snapshot (`lvcreate -s -L 5%ORIGIN`) when the source is an LVM device; falls back to direct device.
  - Streams snapshot/device directly to Kopia.
- `internal/agent/stream_entry.go`, `internal/agent/stream_entry_parallel.go`, `internal/agent/range_reader.go`
  - Device reader implementations (sequential/parallel/range-aware).
  - Handle Windows `ERROR_SECTOR_NOT_FOUND` in best-effort mode with tail-EOF conversion or zero-fill recovery.
  - Support strict fail-fast mode via policy/env override.
- `internal/agent/disk_image_stub.go`
  - Stub for unsupported platforms.
- `internal/agent/block_cache.go`
  - JSON-serialized cache scaffolding (offset → hash). Not yet used for skipping writes; groundwork for Phase 2.
- `internal/agent/vhdx/writer.go`
  - Minimal placeholder writer that currently behaves like raw sparse writes. TODO: real VHDX dynamic metadata/BAT.
- `internal/agent/runner.go`
  - Engine switch includes `disk_image` → `runDiskImage`.
- `internal/agent/api_client.go`
  - `NextRunResponse` / `JobContext` carry disk image fields: `disk_source_volume`, `disk_image_format`, `disk_temp_dir`.
- Placeholders:
  - `internal/agent/kopia_mount.go` (not implemented; future FUSE/WinFsp mounts).
  - `internal/agent/synthetic.go` (not implemented; future synthetic fulls).

---

## Server/API Changes
- `accounts/modules/addons/cloudstorage/api/agent_next_run.php`
  - Returns disk image fields when present in `s3_cloudbackup_jobs`.
- `accounts/modules/addons/cloudstorage/api/cloudbackup_create_job.php`
  - Validates `disk_image` engine requires agent + `disk_source_volume`; stores `disk_image_format` (default vhdx) and optional `disk_temp_dir`.
- `accounts/modules/addons/cloudstorage/api/cloudbackup_update_job.php`
  - Same validations on update; persists disk image fields.
- `accounts/modules/addons/cloudstorage/lib/Client/CloudBackupController.php`
  - `recordRestorePointsForRun(...)` classifies disk-image restore points as `metadata_incomplete` when restore-readiness metadata indicates failure (or when disk layout metadata is missing).
- `accounts/modules/addons/cloudstorage/api/agent_update_run.php`
  - Merges incremental `stats_json` with `array_replace_recursive(...)` so previously posted metadata (including `restore_readiness` and `disk_layout`) is preserved when later progress updates arrive.
- `accounts/modules/addons/cloudstorage/api/e3backup_restore_points_list.php`
  - Adds `is_restorable` and `non_restorable_reason` fields per restore point.
- `accounts/modules/addons/cloudstorage/api/cloudbackup_disk_image_start_restore.php`
- `accounts/modules/addons/cloudstorage/api/cloudbackup_recovery_start_restore.php`
- `accounts/modules/addons/cloudstorage/api/cloudbackup_recovery_token_create.php`
- `accounts/modules/addons/cloudstorage/api/cloudbackup_recovery_token_exchange.php`
  - All four endpoints explicitly block `metadata_incomplete` restore points and return a clear, user-facing reason.

---

## Recovery Session Token Behavior (Long Restores)
- Session token expiry now slides forward on activity to avoid long restore failures.
  - Extension window: `+6 hours`.
  - Minimum refresh threshold: only updates when expiry is within 30 minutes.
  - Activity endpoints that refresh:
    - `cloudbackup_recovery_update_run.php`
    - `cloudbackup_recovery_push_events.php`
    - `cloudbackup_recovery_get_run_status.php`
    - `cloudbackup_recovery_get_run_events.php`
    - `cloudbackup_recovery_poll_cancel.php`
- Dedicated refresh endpoint:
  - `cloudbackup_recovery_refresh_session.php`
  - Auth: `session_token` + `run_id`, validates run ownership, returns `session_expires_at`.
- Recovery UI behavior:
  - The WinPE recovery UI polls `/api/refresh-session` every 5 minutes while a restore is running.
  - This prevents “Session token expired” during multi‑hour or multi‑day restores.

---

## UI Changes
- `accounts/modules/addons/cloudstorage/templates/cloudbackup_jobs.tpl`
  - Adds “Disk Image” engine button.
  - Disk image fields: volume/device, image format (vhdx/raw), temp dir.
  - Posting fields: `disk_source_volume`, `disk_image_format`, `disk_temp_dir`. When engine is `disk_image`, `source_path` is set to the volume/device for server storage.
- `accounts/modules/addons/cloudstorage/templates/e3backup_disk_image_restore.tpl`
  - Uses `is_restorable`/`non_restorable_reason` from the restore-point list API.
  - Disables point selection and token generation for non-restorable points.
  - Displays explicit reason text to the user.
- `accounts/modules/addons/cloudstorage/templates/e3backup_restores.tpl`
  - Surfaces non-restorable reason text in the main restore-point grid.
  - Disables restore actions and replaces action labels with `Unavailable` where applicable.

---

## Database Fields
- Table `s3_cloudbackup_jobs` (must exist in schema):
  - `disk_source_volume` (VARCHAR): e.g., `C:` or `/dev/vg0/root`.
  - `disk_image_format` (VARCHAR, default `vhdx`).
  - `disk_temp_dir` (VARCHAR, optional).
- Runs reuse existing run table columns; no new run columns were added for disk image.

---

## How Disk Image Backup Works (flow) — Current Streaming Path
1) Server assigns a run with engine `disk_image` to the agent (payload includes disk fields).
2) Agent `runRun` routes to `runDiskImage`, normalizes options.
3) Snapshot + streaming:
   - Windows: VSS shadow copy → snapshot device (no trailing slash) streamed directly.
     - If previous snapshot exists and CBT is enabled, the agent uses NTFS USN + file extents to read only changed ranges.
     - If CBT is unavailable/invalid, falls back to NTFS volume bitmap and reads only allocated clusters (unused space becomes zeros).
     - Physical disk sources now prefer disk-layout extents (used partition extents + metadata extents) to avoid full-device reads.
     - If no usable extents are available, the agent falls back to full-device streaming and emits a fallback warning event.
   - Linux: LVM snapshot when available; otherwise the live device is streamed.
   - No temp image is written in streaming mode.
4) Kopia upload:
  - Agent constructs a virtual file entry pointing at the snapshot device and runs the Kopia snapshot pipeline directly against it.
  - Before `storage_init`, the agent runs a storage preflight probe (DNS/TCP/TLS and policy-block check) and emits explicit failure events when endpoint connectivity fails.
  - Progress denominator uses device size when available (Windows via IOCTL length; Linux via stat on the device path).
  - Events: `DISK_IMAGE_STREAM_START`, `KOPIA_*` stages, storage diagnostics events (`STORAGE_DNS_FAILED`, `STORAGE_TCP_REFUSED`, `STORAGE_TCP_TIMEOUT`, `STORAGE_TLS_FAILED`, `STORAGE_HTTP_BLOCKED`, `STORAGE_ENDPOINT_UNREACHABLE`), `DISK_IMAGE_READ_PLAN_FALLBACK`, `DISK_IMAGE_READ_RECOVERED`, `DISK_IMAGE_FINALIZING_SLOW`/`DISK_IMAGE_FINALIZING_STALLED` (tail end only), `DISK_IMAGE_STREAM_COMPLETED` (or `DISK_IMAGE_FAILED` on error).
  - Stall detection is suppressed for the final ~1% (or 128 MiB) of data; the agent logs finalizing events and waits for the upload to finish.
  - Windows physical read hardening:
    - Near-tail `ERROR_SECTOR_NOT_FOUND` is treated as EOF (tail-tolerant, not strict size equality).
    - Mid-stream `ERROR_SECTOR_NOT_FOUND` in best-effort mode is zero-filled for the affected read span and logged as a warning event.
    - Strict mode disables recovery and fails fast on read errors.
5) Cleanup: snapshots removed (VSS Remove / LVM lvremove). No temp image exists to prune in streaming mode.

---

## Disk-Image Restore Readiness Verification (Feb 2026)

This feature ensures a disk-image backup can be *started for restore* before the restore point is considered fully restorable.  
The backup upload itself can still be successful, but restore-start operations are blocked when critical restore metadata is incomplete.

### Why this exists
- Some runs can finish data upload and produce a `manifest_id`, but still miss required restore metadata (for example, missing/invalid disk layout fields).
- Previously, these points could appear valid until restore start, causing confusing late failures.
- The readiness gate moves this check into backup finalization and marks the restore point as explicitly non-startable (`metadata_incomplete`) when metadata remains incomplete after retries.

### End-to-end behavior
1) Streaming upload completes and `manifest_id` is available.
2) Agent verifies restore metadata readiness.
3) If verification fails, agent retries **metadata capture only** (`CollectDiskLayout(...)`) with backoff:
   - attempt 2: `+2s`
   - attempt 3: `+5s`
   - attempt 4: `+10s`
4) Retry path does **not** restart snapshot creation, upload, or dedup pipeline; it only recollects/reposts metadata.
5) Agent writes readiness outcome into `stats_json.restore_readiness`.
6) Server restore-point recorder reads readiness status:
   - readiness pass: restore point keeps run terminal status (`success` or `warning`)
   - readiness fail: restore point status becomes `metadata_incomplete`
7) All restore start/token entry points block `metadata_incomplete`.
8) UI renders non-restorable state and disables restore actions.

### Agent verification rules
Implemented in `verifyDiskImageRestoreReadiness(...)` within `internal/agent/disk_image.go`.

Required conditions:
- `manifest_id` present and non-empty.
- `disk_layout` present.
- `disk_layout.partitions` contains at least one partition.
- `partition_style` is one of `gpt|mbr`.
- `boot_mode` is one of `uefi|bios|unknown`.
- `disk_total_bytes > 0`.
- For shrink-capable/block-map-backed layouts: when `block_map_source` is present, at least one partition must include non-empty `used_extents`.

### Agent observability events
Added in `internal/agent/disk_image.go`:
- `DISK_IMAGE_RESTORE_READINESS_VERIFYING`
- `DISK_IMAGE_RESTORE_READINESS_RETRY`
- `DISK_IMAGE_RESTORE_READINESS_FAILED`
- `DISK_IMAGE_RESTORE_READY`

### Agent `stats_json` payload shape
Readiness details are persisted via `UpdateRun(... StatsJSON ...)`:

```json
{
  "manifest_id": "kopia-manifest-id",
  "disk_layout": { "...": "..." },
  "restore_readiness": {
    "status": "ready | metadata_incomplete",
    "reason_code": "ok | manifest_missing | disk_layout_missing | disk_layout_no_partitions | partition_style_invalid | boot_mode_invalid | disk_total_bytes_invalid | used_extents_missing",
    "reason_detail": "human-readable explanation",
    "attempts": 1,
    "partitions": 3,
    "has_used_extents": true,
    "checked_at": "RFC3339 UTC timestamp"
  }
}
```

Notes:
- `agent_update_run.php` merges `stats_json` recursively, so incremental progress updates do not erase readiness metadata.
- Existing stream/read-plan fields (mode, ranges, bytes, CBT stats, previous manifest) remain intact.

### Server-side restore-point classification
`CloudBackupController::recordRestorePointsForRun(...)` now:
- decodes `run.stats_json` once,
- extracts `disk_layout` fields into restore-point disk columns,
- checks `restore_readiness.status`,
- sets restore point `status = metadata_incomplete` when:
  - readiness status is `metadata_incomplete`, or
  - disk-image layout metadata is missing.

No schema migration is required for this status value because restore-point status is already string-based.

### Restore-start API enforcement
The following endpoints now explicitly block `metadata_incomplete`:
- `cloudbackup_disk_image_start_restore.php`
- `cloudbackup_recovery_start_restore.php`
- `cloudbackup_recovery_token_create.php`
- `cloudbackup_recovery_token_exchange.php`

Response intent:
- return a clear error that restore metadata is incomplete,
- instruct user/operator to run a fresh disk-image backup.

### Restore-point list API contract
`e3backup_restore_points_list.php` appends:
- `is_restorable` (boolean)
- `non_restorable_reason` (string)

Classification currently considers:
- `status === metadata_incomplete` -> non-restorable.
- non-terminal statuses (anything outside `success|warning`) -> non-restorable.
- disk-image point with missing `disk_layout_json` -> non-restorable.

### UI behavior
`e3backup_disk_image_restore.tpl`:
- disabled `Select` and `Generate Token` on non-restorable points,
- shows reason text next to status/selected point.

`e3backup_restores.tpl`:
- shows non-restorable reason text in the restore-point table,
- disables relevant action buttons and shows `Unavailable`,
- blocks modal open when point is non-restorable.

### Files created/modified for this feature
No new permanent production files were added for readiness.  
Feature was implemented by modifying these files:

- Agent:
  - `e3-backup-agent/internal/agent/disk_image.go`
- Server/API:
  - `accounts/modules/addons/cloudstorage/lib/Client/CloudBackupController.php`
  - `accounts/modules/addons/cloudstorage/api/cloudbackup_disk_image_start_restore.php`
  - `accounts/modules/addons/cloudstorage/api/cloudbackup_recovery_start_restore.php`
  - `accounts/modules/addons/cloudstorage/api/cloudbackup_recovery_token_create.php`
  - `accounts/modules/addons/cloudstorage/api/cloudbackup_recovery_token_exchange.php`
  - `accounts/modules/addons/cloudstorage/api/e3backup_restore_points_list.php`
- UI:
  - `accounts/modules/addons/cloudstorage/templates/e3backup_disk_image_restore.tpl`
  - `accounts/modules/addons/cloudstorage/templates/e3backup_restores.tpl`

### Quick verification checklist (developer)
- Readiness pass scenario:
  - complete disk-image run with valid metadata,
  - confirm `restore_readiness.status = ready`,
  - restore point remains `success` or `warning`,
  - restore/token APIs and UI actions remain enabled.
- Readiness failure scenario:
  - force layout metadata failure (for example, missing used extents with `block_map_source` present),
  - confirm retry cadence `2s -> 5s -> 10s`,
  - confirm no re-upload/re-stream occurs,
  - confirm `restore_readiness.status = metadata_incomplete`,
  - confirm restore point status `metadata_incomplete`,
  - confirm all restore start/token APIs return explicit metadata-incomplete errors,
  - confirm UI shows reason and disables restore actions.

---

## Disk Image Restore Performance Updates (Feb 2026)
- Raw disk restores now support parallel segment downloads/writes for higher throughput.
  - Uses per-worker readers and per-worker block device handles.
  - Segment size and worker count are policy-controlled (see Policy JSON flags).
- Restore uses extents whenever the disk layout provides used extents.
  - Extents are merged and normalized; metadata ranges are always included to preserve boot data.
  - GPT backup headers (last 34 sectors) are included when partition style is GPT.
- Kopia restore parallelism is now configurable via policy.
- Recovery and agent restore contexts now carry `policy_json` so restore settings are applied consistently.

---

## Current Limitations / TODOs
- VHDX writer is still a stub (no BAT/metadata). VHDX behaves like raw sparse writes.
- CBT is user‑mode (USN journal + extents) and only applies to NTFS volumes.
- Physical-disk extent planning depends on disk layout/partition block maps; if unavailable, the agent falls back to full-device reads.
- Best-effort mode may zero-fill unreadable physical-disk spans on `ERROR_SECTOR_NOT_FOUND`; enable strict mode to fail fast instead.
- If USN state is invalid (journal reset/wrap, volume change), the agent falls back to the NTFS bitmap full.
- Mount-based recovery and synthetic fulls are placeholders (not implemented).
- LVM snapshot creation is best-effort; if `lvcreate` unavailable, it reads the live device.
- Kopia S3 storage does not expose HTTP transport tuning (MaxIdleConnsPerHost/MaxConnsPerHost); higher parallelism may require an upstream change or fork.

---

## Build / Runtime Notes
- Go modules: `github.com/mxk/go-vss v1.2.0` (Windows VSS), `github.com/billziss-gh/cgofuse` was removed from direct deps.
- Build Windows: `GOOS=windows GOARCH=amd64 go build -o bin/e3-backup-agent.exe ./cmd/agent`
- Run (Windows): `.\bin\e3-backup-agent.exe --config "C:\ProgramData\E3Backup\agent.conf"` (Administrator for VSS).
- Run (Linux): ensure `lvcreate` available for snapshots; otherwise runs directly on device.
- Policy JSON flags (optional):
  - `disk_image_cbt` (default true): enable USN+extent change tracking for Windows disk image backups.
  - `disk_image_change_tracking` (alias for `disk_image_cbt`).
  - `disk_image_bitmap` (default true): allow NTFS bitmap fulls when CBT is unavailable.
  - `disk_image_strict_read_errors` (default false): fail fast on disk read errors instead of best-effort recovery.
  - `parallel_disk_reads` (optional): per-run override for parallel disk readers.
  - `restore_parallel_workers` (default 8-16): number of parallel disk-restore workers.
  - `restore_segment_size_mb` (default 32): per-worker segment size in MiB.
  - `restore_kopia_parallel` (default workers, capped 1-32): Kopia restore parallelism.
- Environment flags:
  - `AGENT_DISK_IMAGE_STRICT_READ_ERRORS` (default false): global strict read-error fallback when policy is unset.
- Volume selection (disk image job wizard): the server waits up to 30 s for the agent’s disk list (`agent_list_disks.php`); the client retries on timeout (up to 3 attempts with delay) for robustness on slow or just-started agents (e.g. Windows 11).

---

## Hand-off Pointers
- For real VHDX support: implement dynamic disk header, BAT, metadata region, block allocation, and sector bitmaps in `internal/agent/vhdx`.
- For CBT: consider a kernel‑mode driver for true block‑level change tracking (avoids USN/extent enumeration limits).
- For cleanup: add temp-dir pruning after successful upload.
- For mounts: wire `kopia_mount.go` to Kopia FUSE on Linux and WinFsp on Windows. Add new command type `mount`/`unmount` to run commands table.
- For synthetic fulls: implement `createSyntheticFull` to restore from Kopia into a new image, then re-upload as a consolidated snapshot.

---

## Driver Bundle Behavior (Recovery Media)
- After each successful disk-image backup, the agent checks destination object storage for:
  - `dest_prefix/driver-bundles/<agentid>/essential.zip`
- If present, bundle capture/upload is skipped.
- If missing, the agent captures the essential bundle and uploads it to the same destination bucket.
- Recovery media manifest APIs return 12-hour pre-signed URLs for source bundle downloads.

