package agent

import (
	"context"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"time"

	vhdxwriter "github.com/your-org/e3-backup-agent/internal/agent/vhdx"
)

// diskImageOptions captures the normalized inputs for creating a disk image.
type diskImageOptions struct {
	SourceVolume string
	ImageFormat  string // vhdx, raw
	TempDir      string
	BlockSize    int64
	Cache        *BlockCache
}

// diskImageResult holds metadata about the created image.
type diskImageResult struct {
	ImagePath     string
	BytesWritten  int64
	BytesSkipped  int64
	BlocksHashed  int64
	BlocksChanged int64
	BlockCache    *BlockCache
}

// runDiskImage orchestrates snapshot → image build → Kopia backup.
func (r *Runner) runDiskImage(run *NextRunResponse) error {
	startedAt := time.Now().UTC()
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     run.RunID,
		Status:    "running",
		StartedAt: startedAt.Format(time.RFC3339),
	})
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "DISK_IMAGE_STARTING",
		ParamsJSON: map[string]any{
			"engine":      "disk_image",
			"source":      run.DiskSourceVolume,
			"format":      run.DiskImageFormat,
			"temp_dir":    run.DiskTempDir,
			"source_path": run.SourcePath,
		},
	})

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Start cancel polling goroutine
	cancelPollDone := make(chan struct{})
	go func() {
		defer close(cancelPollDone)
		ticker := time.NewTicker(3 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				cancelReq, _, errCmd := r.pollCommands(run.RunID)
				if errCmd != nil {
					log.Printf("agent: disk image cancel poll error: %v", errCmd)
					continue
				}
				if cancelReq {
					log.Printf("agent: disk image cancel requested for run %d", run.RunID)
					r.pushEvents(run.RunID, RunEvent{
						Type:      "cancelled",
						Level:     "warn",
						MessageID: "CANCEL_REQUESTED",
						ParamsJSON: map[string]any{
							"message": "Backup cancellation requested by user",
						},
					})
					cancel()
					return
				}
			}
		}
	}()
	defer func() {
		cancel() // Ensure context is cancelled to stop polling goroutine
		<-cancelPollDone // Wait for polling goroutine to finish
	}()

	opts := normalizeDiskImageOptions(r, run)
	if opts.SourceVolume == "" {
		return fmt.Errorf("disk image: missing source volume")
	}

	// Streaming mode: read snapshot device directly into Kopia (no zero-skip, no temp image).
	err := r.createDiskImageStream(ctx, run, opts)
	
	// Determine final status
	finishedAt := time.Now().UTC().Format(time.RFC3339)
	
	// Check if cancelled
	if ctx.Err() != nil {
		log.Printf("agent: disk image backup cancelled for run %d", run.RunID)
		_ = r.client.UpdateRun(RunUpdate{
			RunID:      run.RunID,
			Status:     "cancelled",
			FinishedAt: finishedAt,
		})
		r.pushEvents(run.RunID, RunEvent{
			Type:      "cancelled",
			Level:     "warn",
			MessageID: "CANCELLED",
			ParamsJSON: map[string]any{
				"message": "Backup cancelled by user",
			},
		})
		return ctx.Err()
	}
	
	if err != nil {
		log.Printf("agent: disk image streaming failed: %v", err)
		r.pushEvents(run.RunID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "DISK_IMAGE_FAILED",
			ParamsJSON: map[string]any{
				"error": err.Error(),
			},
		})
		_ = r.client.UpdateRun(RunUpdate{
			RunID:        run.RunID,
			Status:       "failed",
			ErrorSummary: err.Error(),
			FinishedAt:   finishedAt,
		})
		return err
	}

	// Mark success and emit summary after streaming completes.
	_ = r.client.UpdateRun(RunUpdate{
		RunID:      run.RunID,
		Status:     "success",
		FinishedAt: finishedAt,
	})
	r.pushEvents(run.RunID, RunEvent{
		Type:      "summary",
		Level:     "info",
		MessageID: "DISK_IMAGE_COMPLETED",
		ParamsJSON: map[string]any{
			"source": opts.SourceVolume,
			"format": opts.ImageFormat,
		},
	})

	return nil
}

func normalizeDiskImageOptions(r *Runner, run *NextRunResponse) diskImageOptions {
	format := run.DiskImageFormat
	if format == "" {
		if runtime.GOOS == "windows" {
			format = "vhdx"
		} else {
			format = "raw"
		}
	}
	tempDir := run.DiskTempDir
	if tempDir == "" {
		tempDir = filepath.Join(r.cfg.RunDir, "disk_images", fmt.Sprintf("job_%d", run.JobID))
	}
	_ = os.MkdirAll(tempDir, 0o755)
	blockSize := int64(2 << 20) // 2 MiB default for image chunking
	return diskImageOptions{
		SourceVolume: firstNonEmpty(run.DiskSourceVolume, run.SourcePath),
		ImageFormat:  format,
		TempDir:      tempDir,
		BlockSize:    blockSize,
		Cache:        LoadBlockCache(r.cfg.RunDir, run.JobID, blockSize),
	}
}

type sparseWriter interface {
	WriteAt(p []byte, off int64) (int, error)
	Close() error
}

func openSparseWriter(path, format string) (sparseWriter, error) {
	switch strings.ToLower(strings.TrimSpace(format)) {
	case "vhdx":
		return vhdxwriter.New(path)
	default:
		f, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR|os.O_TRUNC, 0o600)
		if err != nil {
			return nil, err
		}
		return f, nil
	}
}

// allZero reports whether the byte slice is entirely zeros.
func allZero(b []byte) bool {
	for _, v := range b {
		if v != 0 {
			return false
		}
	}
	return true
}
