# Dual-mode small-object upload parallelism

**Date:** 2026-08-07  
**Worker:** 0.4.38  
**PHP:** 1.52.55

## Problem

Production batch `895844b3-6975-4826-a728-003afec2aeff` finished Graph enumeration quickly but crawled at ~3.4 KB/s during Kopia upload. The remaining child was SharePoint site **Common** with `lists:true` / `files:false`: **~85,835 list-item JSON blobs (~348 B each)** hashed from the overlay. The 0.4.37 glacial-stream recovery guard does not apply (no Graph `/content` bodies; files are far below the 16 MiB threshold).

## Solution

After `graph_sync`, classify overlay entries (`staticFile` vs `GraphFile`) and average size, then choose `SnapshotRequest.Parallel`:

| Mode | Detection | Policy |
|------|-----------|--------|
| `overlay` | No Graph files or ≥95% static; avg &lt; 64 KiB | `min(overlay_max, max(base, items/1500))` — ignores Graph AIMD |
| `graph_small` | Graph files present; avg &lt; `parallel_uploads_small_avg_bytes` | Raise toward `parallel_uploads_small_max`, clamped by tenant/global headroom ÷ active upload siblings |
| `baseline` | Large/mixed workloads | Configured `parallel_uploads` |

Defaults: `parallel_uploads_overlay_max: 64`, `parallel_uploads_small_max: 16`, `parallel_uploads_small_avg_bytes: 262144`. Explicit `0` on max knobs disables that boost path.

## Telemetry

Worker logs `upload_parallelism mode=… parallel=…` at snapshot start. Progress payloads and `stats_json` carry `upload_parallelism` and `upload_parallelism_mode` (scalar only).

## Non-goals

- No list JSON packing/tar layer
- No raise of Graph `graph_parallel_requests` or `max_concurrent_runs`
- Mid-snapshot runs do not pick up new parallelism until restart/reclaim
