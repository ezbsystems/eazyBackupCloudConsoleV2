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
	"strings"
	"time"
)

// createDiskImageStream for Linux: optional LVM snapshot, then stream device directly to Kopia (no temp file).
func (r *Runner) createDiskImageStream(ctx context.Context, run *NextRunResponse, opts diskImageOptions) error {
	src := opts.SourceVolume
	if src == "" {
		return fmt.Errorf("disk image: source volume is empty")
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

	log.Printf("agent: disk image streaming from device %s", src)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "DISK_IMAGE_STREAM_START",
		ParamsJSON: map[string]any{
			"device": src,
		},
	})

	// Get device size for progress tracking
	size := getDeviceSizeLinux(src)
	if size > 0 {
		_ = r.client.UpdateRun(RunUpdate{
			RunID:      run.RunID,
			BytesTotal: Int64Ptr(size),
		})
	}

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
	_, runErr := r.kopiaSnapshotDiskImage(ctx, run, streamEntry, size, stableSourcePath)

	// Restore
	run.Engine = originalEngine

	if runErr != nil {
		return runErr
	}

	r.pushEvents(run.RunID, RunEvent{
		Type:      "summary",
		Level:     "info",
		MessageID: "DISK_IMAGE_STREAM_COMPLETED",
		ParamsJSON: map[string]any{
			"device": src,
		},
	})

	return nil
}

// getDeviceSizeLinux returns the size of a block device in bytes.
func getDeviceSizeLinux(path string) int64 {
	f, err := os.Open(path)
	if err != nil {
		return 0
	}
	defer f.Close()

	// Seek to end to get size
	size, err := f.Seek(0, io.SeekEnd)
	if err != nil {
		return 0
	}
	// Seek back to start
	f.Seek(0, io.SeekStart)
	return size
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
	imageName := fmt.Sprintf("job_%d_%d.%s", run.JobID, time.Now().Unix(), opts.ImageFormat)
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

// createLVSnapshotIfPossible tries to create an LVM snapshot if the source is an LV.
func createLVSnapshotIfPossible(src string) (string, func(), error) {
	if !strings.HasPrefix(src, "/dev/") {
		return "", nil, fmt.Errorf("not an LVM device")
	}
	if _, err := exec.LookPath("lvcreate"); err != nil {
		return "", nil, fmt.Errorf("lvcreate not available")
	}

	parts := strings.Split(strings.TrimPrefix(src, "/dev/"), "/")
	if len(parts) < 2 {
		return "", nil, fmt.Errorf("unable to parse LV path")
	}
	vg := parts[0]
	lv := strings.Join(parts[1:], "/")
	snapName := fmt.Sprintf("snap_%d", time.Now().Unix())
	snapPath := filepath.Join("/dev", vg, snapName)

	// Create snapshot with 5% size as a starting point
	cmd := exec.Command("lvcreate", "-s", "-n", snapName, "-L", "5%ORIGIN", filepath.Join("/dev", vg, lv))
	if out, err := cmd.CombinedOutput(); err != nil {
		return "", nil, fmt.Errorf("lvcreate snapshot failed: %v output=%s", err, string(out))
	}

	cleanup := func() {
		exec.Command("lvremove", "-f", snapPath).Run()
	}
	return snapPath, cleanup, nil
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

