//go:build linux
// +build linux

package agent

import (
	"context"
	"fmt"
	"io"
	"log"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

// createDiskImageStream for Linux: optional LVM snapshot, then stream device directly to Kopia (no temp file).
func (r *Runner) createDiskImageStream(ctx context.Context, run *NextRunResponse, opts diskImageOptions, _ *DiskLayout, progressCb func(bytesProcessed int64, bytesUploaded int64), setTotal func(int64)) (*diskImageStreamResult, error) {
	if err := ctx.Err(); err != nil {
		return nil, err
	}
	src := opts.SourceVolume
	if src == "" {
		return nil, fmt.Errorf("disk image: source volume is empty")
	}

	snapPath, cleanup, err := createLVSnapshotIfPossible(src)
	if err != nil {
		log.Printf("agent: lvm snapshot creation failed, falling back to direct device: %v", err)
	}
	if err := ctx.Err(); err != nil {
		return nil, err
	}
	if snapPath != "" {
		src = snapPath
	}
	if cleanup != nil {
		defer cleanup()
	}

	log.Printf("agent: disk image streaming from device %s", src)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "DISK_IMAGE_STREAM_START",
		ParamsJSON: map[string]any{
			"device": src,
		},
	})

	// Determine source device size. We need a non-zero size for both progress
	// tracking and the Kopia stream entry; if we cannot resolve it the upload
	// will stall with no bytes_total. Fail fast with an actionable error rather
	// than hanging until the stall watchdog fires.
	size, sizeErr := probeDiskImageSize(src)
	if size <= 0 {
		detail := "unknown"
		if sizeErr != nil {
			detail = sizeErr.Error()
		}
		err := fmt.Errorf("disk image size probe returned 0 for %s (%s)", src, detail)
		log.Printf("agent: %v", err)
		r.pushEvents(run.RunID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "DISK_IMAGE_SIZE_PROBE_FAILED",
			ParamsJSON: map[string]any{
				"device": src,
				"error":  detail,
			},
		})
		return nil, err
	}
	if err := ctx.Err(); err != nil {
		return nil, err
	}
	if setTotal != nil {
		setTotal(size)
	}
	_ = r.client.UpdateRun(RunUpdate{
		RunID:      run.RunID,
		BytesTotal: Int64Ptr(size),
	})

	// Use the original source volume as the stable source path for deduplication.
	// This ensures subsequent snapshots are recognized as the same source.
	stableSourcePath := opts.SourceVolume

	// Create a deviceEntry to wrap the block device for Kopia's uploader
	streamEntry := &deviceEntry{
		name: fmt.Sprintf("%s.img", filepath.Base(stableSourcePath)),
		path: src, // Read from snapshot device (or original if no snapshot)
		size: size,
	}

	originalEngine := run.Engine
	run.Engine = "kopia"

	// Pass the stable source path for SourceInfo to enable proper deduplication
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
			"device": src,
		},
	})

	return &diskImageStreamResult{
		ManifestID: manifestID,
		ReadMode:   "full",
	}, nil
}

// getDeviceSizeLinux returns the size of a block device in bytes. It returns 0
// if the size cannot be determined.
func getDeviceSizeLinux(path string) int64 {
	size, _ := probeDiskImageSize(path)
	return size
}

// probeDiskImageSize attempts every supported strategy for resolving the size
// of a backing block device. The returned error aggregates the failures so the
// caller can surface a useful message instead of silently returning 0 (which
// causes the upload pipeline to stall with no bytes_total).
func probeDiskImageSize(path string) (int64, error) {
	if strings.TrimSpace(path) == "" {
		return 0, fmt.Errorf("empty device path")
	}
	var attempts []string

	if _, statErr := os.Stat(path); statErr != nil {
		attempts = append(attempts, fmt.Sprintf("stat: %v", statErr))
		return 0, fmt.Errorf("device not accessible: %s", strings.Join(attempts, "; "))
	}

	f, err := os.Open(path)
	if err != nil {
		attempts = append(attempts, fmt.Sprintf("open: %v", err))
	} else {
		defer f.Close()
		const blkgetsize64 = 0x80081272
		var bytes uint64
		if _, _, errno := syscall.Syscall(syscall.SYS_IOCTL, f.Fd(), uintptr(blkgetsize64), uintptr(unsafe.Pointer(&bytes))); errno == 0 && bytes > 0 {
			return int64(bytes), nil
		} else if errno != 0 {
			attempts = append(attempts, fmt.Sprintf("ioctl BLKGETSIZE64: %v", errno))
		}

		if stat, statErr := f.Stat(); statErr == nil && stat.Size() > 0 && stat.Mode().IsRegular() {
			return stat.Size(), nil
		}
	}

	if out, err := exec.Command("blockdev", "--getsize64", path).Output(); err == nil {
		if size, parseErr := strconv.ParseInt(strings.TrimSpace(string(out)), 10, 64); parseErr == nil && size > 0 {
			return size, nil
		} else if parseErr != nil {
			attempts = append(attempts, fmt.Sprintf("blockdev parse: %v", parseErr))
		}
	} else {
		attempts = append(attempts, fmt.Sprintf("blockdev: %v", err))
	}

	if size := getDeviceSizeLinuxSysfs(path); size > 0 {
		return size, nil
	}
	attempts = append(attempts, "sysfs: no /sys/class/block/<name>/size entry")

	if f != nil {
		if size, seekErr := f.Seek(0, io.SeekEnd); seekErr == nil && size > 0 {
			_, _ = f.Seek(0, io.SeekStart)
			return size, nil
		} else if seekErr != nil {
			attempts = append(attempts, fmt.Sprintf("seek-end: %v", seekErr))
		}
	}

	if len(attempts) == 0 {
		return 0, fmt.Errorf("no size returned by any probe")
	}
	return 0, fmt.Errorf("%s", strings.Join(attempts, "; "))
}

func getDeviceSizeLinuxSysfs(path string) int64 {
	resolved, err := filepath.EvalSymlinks(path)
	if err != nil {
		resolved = path
	}
	name := filepath.Base(resolved)
	if name == "." || name == string(filepath.Separator) || name == "" {
		return 0
	}
	b, err := os.ReadFile(filepath.Join("/sys/class/block", name, "size"))
	if err != nil {
		return 0
	}
	sectors, err := strconv.ParseInt(strings.TrimSpace(string(b)), 10, 64)
	if err != nil || sectors <= 0 {
		return 0
	}
	return sectors * 512
}

// createDiskImage for Linux: optional LVM snapshot -> sparse raw image.
func (r *Runner) createDiskImage(ctx context.Context, run *NextRunResponse, opts diskImageOptions) (*diskImageResult, error) {
	src := opts.SourceVolume
	if src == "" {
		return nil, fmt.Errorf("disk image: source volume is empty")
	}

	snapPath, cleanup, err := createLVSnapshotIfPossible(src)
	if err != nil {
		log.Printf("agent: lvm snapshot creation failed, falling back to direct device: %v", err)
	}
	if snapPath != "" {
		src = snapPath
	}
	if cleanup != nil {
		defer cleanup()
	}

	if err := os.MkdirAll(opts.TempDir, 0o755); err != nil {
		return nil, fmt.Errorf("disk image: mkdir temp dir: %w", err)
	}
	imageName := fmt.Sprintf("job_%s_%d.%s", run.JobID, time.Now().Unix(), opts.ImageFormat)
	imagePath := filepath.Join(opts.TempDir, imageName)

	bytesWritten, bytesSkipped, err := writeSparseImageLinux(src, imagePath, opts.BlockSize, opts.ImageFormat)
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

// createLVSnapshotIfPossible tries to create an LVM snapshot if the source is
// an LV. The source must be an LVM device path (typically /dev/<vg>/<lv> or
// /dev/mapper/<vg>-<lv>). If the source is not an LV, or LVM tooling is
// unavailable, the function returns an error and the caller falls back to
// reading the device directly.
func createLVSnapshotIfPossible(src string) (string, func(), error) {
	if !strings.HasPrefix(src, "/dev/") {
		return "", nil, fmt.Errorf("not an LVM device")
	}
	if _, err := exec.LookPath("lvcreate"); err != nil {
		return "", nil, fmt.Errorf("lvcreate not available")
	}

	vg, lv, ok := resolveVGLV(src)
	if !ok {
		return "", nil, fmt.Errorf("unable to parse LV path: %s", src)
	}
	snapName := fmt.Sprintf("e3snap_%d", time.Now().Unix())
	snapPath := filepath.Join("/dev", vg, snapName)

	// Use --extents (-l) for percentage-based sizing. lvcreate's --size (-L)
	// only accepts absolute byte/MB/GB values; --extents accepts percentages
	// such as 20%ORIGIN (snapshot CoW pool sized at 20% of the origin LV).
	cmd := exec.Command(
		"lvcreate",
		"--snapshot",
		"--name", snapName,
		"--extents", "20%ORIGIN",
		filepath.Join("/dev", vg, lv),
	)
	if out, err := cmd.CombinedOutput(); err != nil {
		return "", nil, fmt.Errorf("lvcreate snapshot failed: %v output=%s", err, strings.TrimSpace(string(out)))
	}

	cleanup := func() {
		exec.Command("lvremove", "-f", snapPath).Run()
	}
	return snapPath, cleanup, nil
}

// resolveVGLV extracts the volume group and logical volume names from a device
// path. Supports both /dev/<vg>/<lv> and /dev/mapper/<vg>-<lv> styles.
func resolveVGLV(src string) (string, string, bool) {
	cleaned := strings.TrimPrefix(src, "/dev/")
	if strings.HasPrefix(cleaned, "mapper/") {
		name := strings.TrimPrefix(cleaned, "mapper/")
		// dm naming escapes literal '-' in vg/lv names as '--'; split on the
		// first single '-' that isn't part of a doubled escape.
		for i := 0; i < len(name); i++ {
			if name[i] != '-' {
				continue
			}
			if i+1 < len(name) && name[i+1] == '-' {
				i++ // skip escaped dash
				continue
			}
			vg := strings.ReplaceAll(name[:i], "--", "-")
			lv := strings.ReplaceAll(name[i+1:], "--", "-")
			if vg == "" || lv == "" {
				return "", "", false
			}
			return vg, lv, true
		}
		return "", "", false
	}
	parts := strings.SplitN(cleaned, "/", 2)
	if len(parts) != 2 || parts[0] == "" || parts[1] == "" {
		return "", "", false
	}
	return parts[0], parts[1], true
}

func writeSparseImageLinux(srcPath, dstPath string, blockSize int64, format string) (int64, int64, error) {
	src, err := os.Open(srcPath)
	if err != nil {
		return 0, 0, fmt.Errorf("open source: %w", err)
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
