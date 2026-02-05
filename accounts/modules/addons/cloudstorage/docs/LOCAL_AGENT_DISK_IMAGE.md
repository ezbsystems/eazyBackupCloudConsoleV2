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
  - Uses per-OS `createDiskImage` to produce the image, then reuses `runKopia` to upload it.
  - Sparse writer helper (`openSparseWriter`) with VHDX stub + raw writer.
  - Block cache scaffold (`BlockCache`) for future change-detection.
- `internal/agent/disk_image_windows.go`
  - Uses VSS: `vss.Create(volume)` → shadow copy ID; fetches device path via `vss.Get`.
  - Copies snapshot device into sparse image, skipping zero blocks; records block hashes (sha256) into cache.
- `internal/agent/disk_image_linux.go`
  - Tries to create LVM snapshot (`lvcreate -s -L 5%ORIGIN`) when the source is an LVM device; falls back to direct device.
  - Copies snapshot/device into sparse image, skipping zero blocks; records block hashes.
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

---

## UI Changes
- `accounts/modules/addons/cloudstorage/templates/cloudbackup_jobs.tpl`
  - Adds “Disk Image” engine button.
  - Disk image fields: volume/device, image format (vhdx/raw), temp dir.
  - Posting fields: `disk_source_volume`, `disk_image_format`, `disk_temp_dir`. When engine is `disk_image`, `source_path` is set to the volume/device for server storage.

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
     - Physical disk sources fall back to full-device reads.
   - Linux: LVM snapshot when available; otherwise the live device is streamed.
   - No temp image is written in streaming mode.
4) Kopia upload:
   - Agent constructs a virtual file entry pointing at the snapshot device and runs the Kopia snapshot pipeline directly against it.
   - Progress denominator uses device size when available (Windows via IOCTL length; Linux via stat on the device path).
   - Events: `DISK_IMAGE_STREAM_START`, `KOPIA_*` stages, `DISK_IMAGE_STREAM_COMPLETED` (or `DISK_IMAGE_FAILED` on error).
5) Cleanup: snapshots removed (VSS Remove / LVM lvremove). No temp image exists to prune in streaming mode.

---

## Current Limitations / TODOs
- VHDX writer is still a stub (no BAT/metadata). VHDX behaves like raw sparse writes.
- CBT is user‑mode (USN journal + extents) and only applies to NTFS volumes; physical disks fall back to full reads.
- If USN state is invalid (journal reset/wrap, volume change), the agent falls back to the NTFS bitmap full.
- Mount-based recovery and synthetic fulls are placeholders (not implemented).
- LVM snapshot creation is best-effort; if `lvcreate` unavailable, it reads the live device.

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

---

## Hand-off Pointers
- For real VHDX support: implement dynamic disk header, BAT, metadata region, block allocation, and sector bitmaps in `internal/agent/vhdx`.
- For CBT: consider a kernel‑mode driver for true block‑level change tracking (avoids USN/extent enumeration limits).
- For cleanup: add temp-dir pruning after successful upload.
- For mounts: wire `kopia_mount.go` to Kopia FUSE on Linux and WinFsp on Windows. Add new command type `mount`/`unmount` to run commands table.
- For synthetic fulls: implement `createSyntheticFull` to restore from Kopia into a new image, then re-upload as a consolidated snapshot.

