package agent

import (
	"context"
	"fmt"
	"io"
	"log"
	"math"
	"os"
	"path/filepath"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/snapshotfs"
	"github.com/kopia/kopia/snapshot/restore"
)

// kopiaRestoreDiskImageToDevice restores a disk image snapshot directly to a block device.
func (r *Runner) kopiaRestoreDiskImageToDevice(ctx context.Context, run *NextRunResponse, manifestID, targetDevice string, extents []DiskExtent, runID int64) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	password := opts.password()

	log.Printf("agent: disk restore opening repo at %s for job %d, manifest %s", repoPath, run.JobID, manifestID)

	if _, statErr := os.Stat(repoPath); os.IsNotExist(statErr) {
		st, stErr := opts.storage(ctx)
		if stErr != nil {
			return fmt.Errorf("kopia: storage init for disk restore: %w", stErr)
		}
		if connErr := repo.Connect(ctx, repoPath, st, password, nil); connErr != nil {
			return fmt.Errorf("kopia: connect to repo for disk restore failed: %w", connErr)
		}
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return fmt.Errorf("kopia: open repo for disk restore: %w", err)
	}
	defer rep.Close(ctx)

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return fmt.Errorf("kopia: load snapshot: %w", err)
	}

	rootEntry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	if rootEntry == nil {
		return fmt.Errorf("kopia: failed to create root entry from snapshot")
	}

	totalBytes := man.RootEntry.FileSize
	if totalBytes <= 0 {
		if entrySize := rootEntry.Size(); entrySize > 0 {
			totalBytes = entrySize
		}
	}
	if len(extents) > 0 {
		var sum int64
		for _, e := range extents {
			sum += e.LengthBytes
		}
		if sum > 0 {
			totalBytes = sum
		}
	}

	progressCounter := &restoreProgressCounter{
		runner: r,
		runID:  runID,
	}

	serverProgressFn := func(bytesWritten, bytesTotal int64, speedBps float64) {
		_ = r.client.UpdateRun(RunUpdate{
			RunID:            runID,
			BytesProcessed:   Int64Ptr(bytesWritten),
			BytesTransferred: Int64Ptr(bytesWritten),
			BytesTotal:       Int64Ptr(bytesTotal),
			ProgressPct:      computeProgressPct(bytesWritten, bytesTotal),
			SpeedBytesPerSec: int64(speedBps),
			CurrentItem:      fmt.Sprintf("Restoring disk: %.1f%%", computeProgressPct(bytesWritten, bytesTotal)),
		})
	}

	out := &blockDeviceRestoreOutput{
		targetPath:       targetDevice,
		knownFileSize:    totalBytes,
		extents:          extents,
		progressCallback: progressCounter.onProgress,
		serverProgressFn: serverProgressFn,
	}

	log.Printf("agent: disk restore starting to %s (extents=%d)", targetDevice, len(extents))
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "DISK_RESTORE_STARTED",
		ParamsJSON: map[string]any{
			"target_disk": targetDevice,
		},
	})

	_, err = restore.Entry(ctx, rep, out, rootEntry, restore.Options{
		ProgressCallback: progressCounter.onProgress,
		Parallel:         2,
		RestoreDirEntryAtDepth: math.MaxInt32,
	})
	if err != nil {
		return fmt.Errorf("kopia: disk restore: %w", err)
	}
	return nil
}

// blockDeviceRestoreOutput writes a single snapshot file directly to a block device.
type blockDeviceRestoreOutput struct {
	targetPath       string
	knownFileSize    int64
	extents          []DiskExtent
	progressCallback func(ctx context.Context, stats restore.Stats)
	serverProgressFn func(bytesWritten, bytesTotal int64, speedBps float64)
}

var _ restore.Output = (*blockDeviceRestoreOutput)(nil)

func (b *blockDeviceRestoreOutput) Parallelizable() bool {
	return true
}

func (b *blockDeviceRestoreOutput) BeginDirectory(ctx context.Context, relativePath string, e kopiafs.Directory) error {
	return nil
}

func (b *blockDeviceRestoreOutput) WriteDirEntry(ctx context.Context, relativePath string, de *snapshot.DirEntry, e kopiafs.Directory) error {
	return nil
}

func (b *blockDeviceRestoreOutput) FinishDirectory(ctx context.Context, relativePath string, e kopiafs.Directory) error {
	return nil
}

func (b *blockDeviceRestoreOutput) WriteFile(ctx context.Context, relativePath string, e kopiafs.File) error {
	device, err := openBlockDeviceForWrite(b.targetPath)
	if err != nil {
		return err
	}
	defer device.Close()

	reader, err := e.Open(ctx)
	if err != nil {
		return fmt.Errorf("open snapshot file: %w", err)
	}
	defer reader.Close()

	totalBytes := b.knownFileSize
	if totalBytes <= 0 {
		if lr, ok := reader.(interface{ Length() int64 }); ok {
			totalBytes = lr.Length()
		}
	}
	if totalBytes <= 0 {
		totalBytes = e.Size()
	}

	if len(b.extents) == 0 {
		return b.copySequential(ctx, reader, device, totalBytes)
	}
	return b.copyExtents(ctx, reader, device, totalBytes)
}

func (b *blockDeviceRestoreOutput) copySequential(ctx context.Context, reader io.Reader, device *os.File, totalBytes int64) error {
	buf := make([]byte, 4*1024*1024)
	var written int64
	start := time.Now()
	for {
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}
		n, err := reader.Read(buf)
		if n > 0 {
			if _, werr := device.Write(buf[:n]); werr != nil {
				return fmt.Errorf("write device: %w", werr)
			}
			written += int64(n)
			b.reportProgress(written, totalBytes, start)
		}
		if err == io.EOF {
			break
		}
		if err != nil {
			return fmt.Errorf("read snapshot: %w", err)
		}
	}
	return nil
}

func (b *blockDeviceRestoreOutput) copyExtents(ctx context.Context, reader io.ReadSeeker, device *os.File, totalBytes int64) error {
	var written int64
	start := time.Now()
	for _, ext := range b.extents {
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}
		if _, err := reader.Seek(ext.OffsetBytes, io.SeekStart); err != nil {
			return fmt.Errorf("seek snapshot: %w", err)
		}
		if _, err := device.Seek(ext.OffsetBytes, io.SeekStart); err != nil {
			return fmt.Errorf("seek device: %w", err)
		}
		remaining := ext.LengthBytes
		buf := make([]byte, 4*1024*1024)
		for remaining > 0 {
			select {
			case <-ctx.Done():
				return ctx.Err()
			default:
			}
			toRead := int64(len(buf))
			if remaining < toRead {
				toRead = remaining
			}
			n, err := reader.Read(buf[:toRead])
			if n > 0 {
				if _, werr := device.Write(buf[:n]); werr != nil {
					return fmt.Errorf("write device: %w", werr)
				}
				remaining -= int64(n)
				written += int64(n)
				b.reportProgress(written, totalBytes, start)
			}
			if err == io.EOF {
				break
			}
			if err != nil {
				return fmt.Errorf("read snapshot: %w", err)
			}
		}
	}
	return nil
}

func (b *blockDeviceRestoreOutput) reportProgress(bytesWritten, totalBytes int64, start time.Time) {
	if b.serverProgressFn == nil {
		return
	}
	elapsed := time.Since(start).Seconds()
	var speed float64
	if elapsed > 0 {
		speed = float64(bytesWritten) / elapsed
	}
	b.serverProgressFn(bytesWritten, totalBytes, speed)
}

func (b *blockDeviceRestoreOutput) FileExists(ctx context.Context, relativePath string, e kopiafs.File) bool {
	return false
}

func (b *blockDeviceRestoreOutput) CreateSymlink(ctx context.Context, relativePath string, e kopiafs.Symlink) error {
	return nil
}

func (b *blockDeviceRestoreOutput) SymlinkExists(ctx context.Context, relativePath string, e kopiafs.Symlink) bool {
	return false
}

func (b *blockDeviceRestoreOutput) Close(ctx context.Context) error {
	return nil
}

func computeProgressPct(done, total int64) float64 {
	if total <= 0 {
		return 0
	}
	return float64(done) / float64(total) * 100
}
