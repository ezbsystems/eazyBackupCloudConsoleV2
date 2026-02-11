//go:build windows
// +build windows

package agent

import (
	"context"
	"crypto/sha256"
	"fmt"
	"io"
	"log"
	"os"
	"path/filepath"
	"strings"
	"syscall"
	"time"
	"unsafe"

	kopiafs "github.com/kopia/kopia/fs"
	vss "github.com/mxk/go-vss"
)

// createDiskImageStream for Windows: VSS snapshot -> stream device directly to Kopia (no temp file).
func (r *Runner) createDiskImageStream(ctx context.Context, run *NextRunResponse, opts diskImageOptions, layout *DiskLayout, progressCb func(bytesProcessed int64, bytesUploaded int64), setTotal func(int64)) (*diskImageStreamResult, error) {
	if err := ctx.Err(); err != nil {
		return nil, err
	}
	srcVolume := opts.SourceVolume
	if srcVolume == "" {
		return nil, fmt.Errorf("disk image: source volume is empty")
	}

	devicePath := ""
	cleanup := func() {}

	if isWindowsPhysicalDiskPath(srcVolume) {
		devicePath = normalizeWindowsPhysicalDiskPath(srcVolume)
		log.Printf("agent: disk image streaming from physical disk %s", devicePath)
	} else {
		// Ensure Windows volume format (e.g., C: or C:\) for VSS Create
		volForVSS := srcVolume
		if len(volForVSS) == 2 && strings.HasSuffix(volForVSS, ":") {
			volForVSS = volForVSS + "\\"
		}

		log.Printf("agent: creating VSS snapshot for volume %s (streaming)", volForVSS)
		id, err := vss.Create(volForVSS)
		if err != nil {
			return nil, fmt.Errorf("vss create: %w", err)
		}
		if err := ctx.Err(); err != nil {
			_ = vss.Remove(id)
			return nil, err
		}
		// VSS requires cleanup to release snapshot
		cleanup = func() {
			_ = vss.Remove(id)
		}
		defer cleanup()

		sc, err := vss.Get(id)
		if err != nil {
			return nil, fmt.Errorf("vss get: %w", err)
		}
		devicePath = strings.TrimSuffix(sc.DeviceObject, `\`)
		if devicePath == "" {
			return nil, fmt.Errorf("vss snapshot returned empty device path")
		}
	}

	log.Printf("agent: disk image streaming from snapshot device %s", devicePath)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "DISK_IMAGE_STREAM_START",
		ParamsJSON: map[string]any{
			"device": devicePath,
		},
	})

	size, _ := getDeviceSizeWindows(devicePath)
	if err := ctx.Err(); err != nil {
		return nil, err
	}
	if size > 0 {
		if setTotal != nil {
			setTotal(size)
		}
		_ = r.client.UpdateRun(RunUpdate{
			RunID:      run.RunID,
			BytesTotal: Int64Ptr(size),
		})
	}

	// Use the original volume (e.g., "C:") as the stable source identifier for Kopia.
	// This ensures subsequent snapshots are recognized as the same source for deduplication.
	stableSourcePath := srcVolume
	stableSourcePath = strings.TrimSuffix(stableSourcePath, "\\")
	stableSourcePath = strings.TrimSpace(stableSourcePath)

	previousSnapshot, err := r.openPreviousDiskImageSnapshot(ctx, run, stableSourcePath)
	if err != nil {
		log.Printf("agent: disk image previous snapshot lookup failed: %v", err)
	}
	if previousSnapshot != nil {
		if _, ok := previousSnapshot.Entry.(kopiafs.File); !ok {
			log.Printf("agent: disk image previous snapshot entry not file; disabling CBT fallback")
			previousSnapshot.Close()
			previousSnapshot = nil
		}
	}
	if previousSnapshot != nil {
		defer previousSnapshot.Close()
	}

	readPlan := r.buildDiskImageReadPlanWindows(ctx, run, stableSourcePath, previousSnapshot != nil, layout, size)
	if isWindowsPhysicalDiskPath(srcVolume) && (readPlan == nil || readPlan.Mode == "full") {
		reason := "physical_disk_no_extents"
		if readPlan != nil && strings.TrimSpace(readPlan.Reason) != "" {
			reason = readPlan.Reason
		}
		r.pushEvents(run.RunID, RunEvent{
			Type:      "warn",
			Level:     "warn",
			MessageID: "DISK_IMAGE_READ_PLAN_FALLBACK",
			ParamsJSON: map[string]any{
				"source": srcVolume,
				"reason": reason,
				"mode":   "full",
			},
		})
	}
	physicalSource := isWindowsPhysicalDiskPath(srcVolume)
	warnReadRecovery := func(details diskReadWarning) {
		r.pushEvents(run.RunID, RunEvent{
			Type:      "warn",
			Level:     "warn",
			MessageID: "DISK_IMAGE_READ_RECOVERED",
			ParamsJSON: map[string]any{
				"source":            srcVolume,
				"device":            devicePath,
				"path":              details.Path,
				"reader":            details.Reader,
				"offset_bytes":      details.OffsetBytes,
				"requested_bytes":   details.RequestedBytes,
				"read_bytes":        details.ReadBytes,
				"zero_filled_bytes": details.ZeroFilledBytes,
				"near_tail":         details.NearTail,
				"error":             details.Error,
				"mode":              "best_effort",
			},
		})
	}
	readMode := "full"
	readRanges := 0
	usedBytes := int64(0)
	if readPlan != nil {
		readMode = readPlan.Mode
		readRanges = len(readPlan.Extents)
		usedBytes = readPlan.UsedBytes
	}
	// #region agent log
	debugLog(run.RunID, "disk_image_stream_setup", map[string]any{
		"device":        devicePath,
		"size_bytes":    size,
		"read_mode":     readMode,
		"read_ranges":   readRanges,
		"used_bytes":    usedBytes,
		"stable_source": stableSourcePath,
	}, "H2")
	// #endregion

	var streamEntry kopiafs.Entry
	if readPlan != nil && readPlan.Mode != "full" {
		var fallback kopiafs.Entry
		if readPlan.Mode == "cbt" && previousSnapshot != nil {
			fallback = previousSnapshot.Entry
		}
		streamEntry = &rangeAwareDeviceEntry{
			name:            fmt.Sprintf("%s.img", strings.TrimSuffix(stableSourcePath, ":")),
			path:            devicePath,
			size:            size,
			readRanges:      readPlan.Extents,
			fallbackEntry:   fallback,
			physicalSource:  physicalSource,
			warningCallback: warnReadRecovery,
		}
	} else {
		streamEntry = &deviceEntry{
			name:            fmt.Sprintf("%s.img", strings.TrimSuffix(stableSourcePath, ":")),
			path:            devicePath, // Read from VSS snapshot device
			size:            size,
			physicalSource:  physicalSource,
			warningCallback: warnReadRecovery,
			// PhysicalDrive reads have shown tail-end hangs with parallel chunking.
			// Force sequential reads for this path to avoid deadlocks.
			forceSequential: physicalSource,
		}
	}

	if readPlan != nil && readPlan.CBTStats != nil {
		params := map[string]any{
			"mode":        readPlan.CBTStats.Mode,
			"reason":      readPlan.CBTStats.Reason,
			"read_ranges": readPlan.CBTStats.ReadRanges,
			"read_bytes":  readPlan.CBTStats.ReadBytes,
		}
		if previousSnapshot != nil && previousSnapshot.ManifestID != "" {
			params["previous_manifest"] = previousSnapshot.ManifestID
		}
		r.pushEvents(run.RunID, RunEvent{
			Type:       "info",
			Level:      "info",
			MessageID:  "DISK_IMAGE_CHANGE_MAP",
			ParamsJSON: params,
		})
	}

	originalEngine := run.Engine
	run.Engine = "kopia"

	// Pass the stable source path (volume) for SourceInfo, not the VSS device path.
	// This allows Kopia to find previous snapshots and deduplicate properly.
	manifestID, runErr := r.kopiaSnapshotDiskImageWithProgress(ctx, run, streamEntry, size, stableSourcePath, progressCb, false)

	// Restore
	run.Engine = originalEngine

	if runErr != nil {
		return nil, runErr
	}

	r.pushEvents(run.RunID, RunEvent{
		Type:      "summary",
		Level:     "info",
		MessageID: "DISK_IMAGE_STREAM_COMPLETED",
		ParamsJSON: map[string]any{
			"device": devicePath,
		},
	})
	result := &diskImageStreamResult{
		ManifestID: manifestID,
	}
	if readPlan != nil {
		result.ReadMode = readPlan.Mode
		result.ReadRanges = len(readPlan.Extents)
		result.ReadBytes = readPlan.ReadBytes
		result.UsedBytes = readPlan.UsedBytes
		result.CBTState = readPlan.CBTState
		result.CBTStats = readPlan.CBTStats
	}
	if previousSnapshot != nil {
		result.PreviousManifestID = previousSnapshot.ManifestID
	}
	return result, nil
}

func isWindowsPhysicalDiskPath(value string) bool {
	trimmed := strings.TrimSpace(strings.ToLower(value))
	return strings.HasPrefix(trimmed, `\\.\physicaldrive`) || strings.HasPrefix(trimmed, `physicaldrive`)
}

func normalizeWindowsPhysicalDiskPath(value string) string {
	trimmed := strings.TrimSpace(value)
	if strings.HasPrefix(strings.ToLower(trimmed), `\\.\physicaldrive`) {
		return trimmed
	}
	if strings.HasPrefix(strings.ToLower(trimmed), `physicaldrive`) {
		return `\\.\` + trimmed
	}
	return trimmed
}

// getDeviceSizeWindows returns the length of a volume/device using IOCTL_DISK_GET_LENGTH_INFO.
func getDeviceSizeWindows(path string) (int64, error) {
	h, err := os.OpenFile(path, os.O_RDONLY, 0)
	if err != nil {
		return 0, err
	}
	defer h.Close()

	const ioctlDiskGetLengthInfo = 0x7405c
	type lengthInfo struct {
		Length int64
	}
	var out lengthInfo
	// Use DeviceIoControl for IOCTL_DISK_GET_LENGTH_INFO
	if err := syscall.DeviceIoControl(
		syscall.Handle(h.Fd()),
		ioctlDiskGetLengthInfo,
		nil,
		0,
		(*byte)(unsafe.Pointer(&out)),
		uint32(unsafe.Sizeof(out)),
		nil,
		nil,
	); err != nil {
		return 0, fmt.Errorf("device ioctl failed: %w", err)
	}
	return out.Length, nil
}

// createDiskImage for Windows: VSS snapshot -> sparse image copy.
func (r *Runner) createDiskImage(ctx context.Context, run *NextRunResponse, opts diskImageOptions) (*diskImageResult, error) {
	if err := ctx.Err(); err != nil {
		return nil, err
	}
	srcVolume := opts.SourceVolume
	if srcVolume == "" {
		return nil, fmt.Errorf("disk image: source volume is empty")
	}

	// Ensure Windows volume format (e.g., C: or C:\)
	if len(srcVolume) == 2 && strings.HasSuffix(srcVolume, ":") {
		srcVolume = srcVolume + "\\"
	}

	log.Printf("agent: creating VSS snapshot for volume %s", srcVolume)
	id, err := vss.Create(srcVolume)
	if err != nil {
		return nil, fmt.Errorf("vss create: %w", err)
	}
	// VSS requires cleanup to release snapshot
	cleanup := func() {
		_ = vss.Remove(id)
	}
	defer cleanup()

	sc, err := vss.Get(id)
	if err != nil {
		return nil, fmt.Errorf("vss get: %w", err)
	}
	// Use the snapshot device path without a trailing backslash; trailing slash on CreateFile against a volume can trigger ERROR_INVALID_FUNCTION.
	devicePath := strings.TrimSuffix(sc.DeviceObject, `\`)
	if devicePath == "" {
		return nil, fmt.Errorf("vss snapshot returned empty device path")
	}

	imageName := fmt.Sprintf("job_%d_%d.%s", run.JobID, time.Now().Unix(), opts.ImageFormat)
	imagePath := filepath.Join(opts.TempDir, imageName)

	if err := os.MkdirAll(opts.TempDir, 0o755); err != nil {
		return nil, fmt.Errorf("disk image: mkdir temp dir: %w", err)
	}

	log.Printf("agent: disk image reading from snapshot device %s (block_size=%d)", devicePath, opts.BlockSize)
	bytesWritten, bytesSkipped, err := writeSparseImage(devicePath, imagePath, opts.BlockSize, opts.ImageFormat, opts.Cache)
	if err != nil {
		return nil, err
	}

	return &diskImageResult{
		ImagePath:    imagePath,
		BytesWritten: bytesWritten,
		BytesSkipped: bytesSkipped,
		BlockCache:   opts.Cache,
	}, nil
}

// writeSparseImage reads from srcPath and writes a sparse file to dstPath, skipping zero blocks.
func writeSparseImage(srcPath, dstPath string, blockSize int64, format string, cache *BlockCache) (int64, int64, error) {
	src, err := os.Open(srcPath)
	if err != nil {
		return 0, 0, fmt.Errorf("open source volume: %w", err)
	}
	defer src.Close()

	dst, err := openSparseWriter(dstPath, format)
	if err != nil {
		return 0, 0, fmt.Errorf("create image: %w", err)
	}
	defer dst.Close()

	buf := make([]byte, blockSize)
	var written int64
	var skipped int64
	var offset int64
	for {
		n, readErr := io.ReadFull(src, buf)
		if readErr != nil && readErr != io.ErrUnexpectedEOF && readErr != io.EOF {
			return written, skipped, fmt.Errorf("read source: %w", readErr)
		}
		if n == 0 {
			break
		}
		chunk := buf[:n]
		if allZero(chunk) {
			skipped += int64(n)
			offset += int64(n)
		} else {
			if cache != nil {
				if cache.BlockHashes == nil {
					cache.BlockHashes = map[int64][]byte{}
				}
				sum := sha256.Sum256(chunk)
				cache.BlockHashes[offset/blockSize] = sum[:]
			}
			if _, err := dst.WriteAt(chunk, offset); err != nil {
				return written, skipped, fmt.Errorf("write image: %w", err)
			}
			written += int64(n)
			offset += int64(n)
		}
		if readErr == io.ErrUnexpectedEOF || readErr == io.EOF {
			break
		}
	}
	return written, skipped, nil
}
