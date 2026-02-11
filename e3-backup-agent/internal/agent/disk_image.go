package agent

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"runtime"
	"strconv"
	"strings"
	"sync/atomic"
	"time"

	vhdxwriter "github.com/your-org/e3-backup-agent/internal/agent/vhdx"
)

// #region agent log
func debugLog(runID int64, message string, data map[string]any, hypothesisId string) {
	entry := map[string]any{
		"id":           fmt.Sprintf("log_%d", time.Now().UnixNano()),
		"timestamp":    time.Now().UnixMilli(),
		"location":     "disk_image.go:debug",
		"message":      message,
		"data":         data,
		"runId":        fmt.Sprintf("run_%d", runID),
		"hypothesisId": hypothesisId,
	}
	b, err := json.Marshal(entry)
	if err != nil {
		return
	}
	f, err := os.OpenFile("/var/www/eazybackup.ca/.cursor/debug.log", os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o600)
	if err != nil {
		return
	}
	_, _ = f.Write(append(b, '\n'))
	_ = f.Close()
}

// #endregion

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

type diskImageStreamResult struct {
	ManifestID         string
	ReadMode           string
	ReadRanges         int
	ReadBytes          int64
	UsedBytes          int64
	PreviousManifestID string
	CBTState           *CBTState
	CBTStats           *CBTStats
}

// runDiskImage orchestrates snapshot → image build → Kopia backup.
func (r *Runner) runDiskImage(run *NextRunResponse) error {
	startedAt := time.Now().UTC()
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     run.RunID,
		Status:    "running",
		StartedAt: startedAt.Format(time.RFC3339),
	})
	resetParallelReads := setParallelDiskReadsOverride(policyBool(run.PolicyJSON, "parallel_disk_reads"))
	resetStrictReadErrors := setStrictReadErrorsOverride(policyBool(run.PolicyJSON, "disk_image_strict_read_errors"))
	defer resetParallelReads()
	defer resetStrictReadErrors()

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
					// #region agent log
					debugLog(run.RunID, "disk_image_cancel_requested", map[string]any{
						"source_volume": run.DiskSourceVolume,
					}, "H3")
					// #endregion
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
	stallSeconds := diskImageStallSeconds()
	var lastProgressAt int64
	var lastProgressBytesProcessed int64
	var lastProgressBytesUploaded int64
	var lastProgressLogAt int64
	var lastBytesTotal int64
	atomic.StoreInt64(&lastProgressAt, time.Now().UnixNano())
	// #region agent log
	debugLog(run.RunID, "disk_image_start", map[string]any{
		"source_volume": run.DiskSourceVolume,
		"engine":        run.Engine,
		"stall_seconds": stallSeconds,
	}, "H4")
	// #endregion
	progressCb := func(bytesProcessed int64, bytesUploaded int64) {
		now := time.Now()
		atomic.StoreInt64(&lastProgressAt, now.UnixNano())
		atomic.StoreInt64(&lastProgressBytesProcessed, bytesProcessed)
		atomic.StoreInt64(&lastProgressBytesUploaded, bytesUploaded)
		lastLogAt := atomic.LoadInt64(&lastProgressLogAt)
		if lastLogAt == 0 || now.Sub(time.Unix(0, lastLogAt)) >= 60*time.Second {
			atomic.StoreInt64(&lastProgressLogAt, now.UnixNano())
			// #region agent log
			debugLog(run.RunID, "disk_image_progress", map[string]any{
				"bytes_processed": bytesProcessed,
				"bytes_uploaded":  bytesUploaded,
				"stall_seconds":   stallSeconds,
			}, "H1")
			// #endregion
		}
	}
	stallDone := make(chan struct{})
	if stallSeconds > 0 {
		var finalizeStart int64
		finalizeLogged := false
		finalizeStallLogged := false
		go func() {
			defer close(stallDone)
			ticker := time.NewTicker(15 * time.Second)
			defer ticker.Stop()
			timeout := time.Duration(stallSeconds) * time.Second
			finalizeTimeout := time.Duration(stallSeconds*5) * time.Second
			for {
				select {
				case <-ctx.Done():
					return
				case <-ticker.C:
					last := time.Unix(0, atomic.LoadInt64(&lastProgressAt))
					if time.Since(last) >= timeout {
						total := atomic.LoadInt64(&lastBytesTotal)
						processed := atomic.LoadInt64(&lastProgressBytesProcessed)
						remaining := int64(-1)
						if total > 0 {
							remaining = total - processed
						}
						threshold := int64(128 << 20) // 128 MiB
						if total > 0 {
							onePct := total / 100
							if onePct > threshold {
								threshold = onePct
							}
						}
						nearDone := total > 0 && processed > 0 && remaining >= 0 && remaining <= threshold
						if nearDone {
							if finalizeStart == 0 {
								finalizeStart = time.Now().UnixNano()
							}
							finalizeElapsed := time.Since(time.Unix(0, finalizeStart))
							if !finalizeLogged {
								finalizeLogged = true
								log.Printf(
									"agent: disk image finalizing (run=%d) remaining=%d total=%d timeout=%ds",
									run.RunID,
									remaining,
									total,
									int(finalizeTimeout.Seconds()),
								)
								r.pushEvents(run.RunID, RunEvent{
									Type:      "warn",
									Level:     "warn",
									MessageID: "DISK_IMAGE_FINALIZING_SLOW",
									ParamsJSON: map[string]any{
										"message":                  "Disk image is finalizing; suppressing stall cancel.",
										"stall_seconds":            stallSeconds,
										"finalize_timeout_seconds": int(finalizeTimeout.Seconds()),
										"bytes_total":              total,
										"bytes_processed":          processed,
										"bytes_remaining":          remaining,
									},
								})
							}
							if finalizeTimeout > 0 && finalizeElapsed >= finalizeTimeout && !finalizeStallLogged {
								finalizeStallLogged = true
								log.Printf(
									"agent: disk image finalization slow (run=%d) elapsed=%.0fs remaining=%d total=%d",
									run.RunID,
									finalizeElapsed.Seconds(),
									remaining,
									total,
								)
								r.pushEvents(run.RunID, RunEvent{
									Type:      "warn",
									Level:     "warn",
									MessageID: "DISK_IMAGE_FINALIZING_STALLED",
									ParamsJSON: map[string]any{
										"message":                  "Disk image finalization exceeded expected time; continuing to wait.",
										"finalize_timeout_seconds": int(finalizeTimeout.Seconds()),
										"bytes_total":              total,
										"bytes_processed":          processed,
										"bytes_remaining":          remaining,
									},
								})
							}
							// Keep the stall timer alive while finalizing.
							atomic.StoreInt64(&lastProgressAt, time.Now().UnixNano())
							continue
						}
						// #region agent log
						debugLog(run.RunID, "disk_image_stall_detected", map[string]any{
							"stall_seconds":    stallSeconds,
							"since_seconds":    time.Since(last).Seconds(),
							"last_progress_at": last.Format(time.RFC3339Nano),
							"bytes_processed":  atomic.LoadInt64(&lastProgressBytesProcessed),
							"bytes_uploaded":   atomic.LoadInt64(&lastProgressBytesUploaded),
							"bytes_total":      total,
							"bytes_remaining":  remaining,
							"finalize_elapsed": func() float64 {
								if finalizeStart == 0 {
									return 0
								}
								return time.Since(time.Unix(0, finalizeStart)).Seconds()
							}(),
						}, "H1")
						// #endregion
						log.Printf("agent: disk image stalled for %ds, cancelling run %d", stallSeconds, run.RunID)
						r.pushEvents(run.RunID, RunEvent{
							Type:      "error",
							Level:     "error",
							MessageID: "DISK_IMAGE_STALLED",
							ParamsJSON: map[string]any{
								"message":                  "Disk image backup stalled; cancelling run.",
								"stall_seconds":            stallSeconds,
								"finalize_timeout_seconds": int(finalizeTimeout.Seconds()),
								"last_progress_at":         last.Format(time.RFC3339Nano),
								"last_bytes_processed":     atomic.LoadInt64(&lastProgressBytesProcessed),
								"last_bytes_uploaded":      atomic.LoadInt64(&lastProgressBytesUploaded),
								"bytes_total":              total,
								"bytes_remaining":          remaining,
							},
						})
						cancel()
						return
					}
				}
			}
		}()
	} else {
		close(stallDone)
	}
	defer func() {
		cancel() // Ensure context is cancelled to stop polling goroutines
		<-cancelPollDone
		<-stallDone
	}()

	opts := normalizeDiskImageOptions(r, run)
	// #region agent log
	debugLog(run.RunID, "disk_image_read_flags", map[string]any{
		"parallel_reader": useParallelReader(),
	}, "H4")
	// #endregion
	if opts.SourceVolume == "" {
		err := fmt.Errorf("disk image: missing source volume")
		log.Printf("agent: disk image failed before start: %v", err)
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
			FinishedAt:   time.Now().UTC().Format(time.RFC3339),
		})
		return err
	}

	layout, layoutErr := CollectDiskLayout(opts.SourceVolume)
	if layoutErr != nil {
		log.Printf("agent: disk image layout capture failed: %v", layoutErr)
	}

	// Streaming mode: read snapshot device directly into Kopia.
	setTotal := func(total int64) {
		if total <= 0 {
			return
		}
		atomic.StoreInt64(&lastBytesTotal, total)
		// #region agent log
		debugLog(run.RunID, "disk_image_total", map[string]any{
			"bytes_total": total,
		}, "H4")
		// #endregion
	}
	streamResult, err := r.createDiskImageStream(ctx, run, opts, layout, progressCb, setTotal)
	manifestID := ""
	if streamResult != nil {
		manifestID = streamResult.ManifestID
	}

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
		endpoint := normalizeEndpoint(firstNonEmpty(run.DestEndpoint, r.cfg.DestEndpoint))
		classified := classifyStorageInitError(endpoint, err)
		if strings.Contains(strings.ToLower(err.Error()), "storage init") || classified.ReasonCode != "endpoint_unreachable" {
			params := storageFailureParams(classified)
			params["stage"] = "storage_init"
			r.pushEvents(run.RunID, RunEvent{
				Type:       "error",
				Level:      "error",
				Code:       classified.ReasonCode,
				MessageID:  classified.MessageID,
				ParamsJSON: params,
			})
		}
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

	// Persist disk layout metadata on the run (used for restore points).
	if manifestID != "" {
		statsPayload := map[string]any{
			"manifest_id": manifestID,
		}
		if layout != nil {
			statsPayload["disk_layout"] = layout
		}
		if streamResult != nil {
			if streamResult.ReadMode != "" {
				statsPayload["disk_image_mode"] = streamResult.ReadMode
			}
			if streamResult.ReadRanges > 0 {
				statsPayload["disk_image_read_ranges"] = streamResult.ReadRanges
			}
			if streamResult.ReadBytes > 0 {
				statsPayload["disk_image_read_bytes"] = streamResult.ReadBytes
			}
			if streamResult.UsedBytes > 0 {
				statsPayload["disk_image_used_bytes"] = streamResult.UsedBytes
			}
			if streamResult.PreviousManifestID != "" {
				statsPayload["disk_image_previous_manifest"] = streamResult.PreviousManifestID
			}
			if streamResult.CBTStats != nil {
				statsPayload["disk_image_cbt"] = streamResult.CBTStats
			}
		}
		_ = r.client.UpdateRun(RunUpdate{
			RunID:     run.RunID,
			StatsJSON: statsPayload,
		})
	}

	if streamResult != nil && streamResult.CBTState != nil {
		streamResult.CBTState.LastManifestID = manifestID
		if err := streamResult.CBTState.Save(); err != nil {
			log.Printf("agent: disk image cbt state save failed: %v", err)
		}
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

	sourceVolume := strings.TrimSpace(run.DiskSourceVolume)
	if sourceVolume == "" {
		// Disk image jobs on local_agent can store the path in source_paths or source_path.
		paths := normalizeSourcePaths(run.SourcePaths, run.SourcePath)
		if len(paths) > 0 {
			sourceVolume = strings.TrimSpace(paths[0])
		}
	}
	return diskImageOptions{
		SourceVolume: sourceVolume,
		ImageFormat:  format,
		TempDir:      tempDir,
		BlockSize:    blockSize,
		Cache:        LoadBlockCache(r.cfg.RunDir, run.JobID, blockSize),
	}
}

func diskImageStallSeconds() int {
	val := strings.TrimSpace(os.Getenv("AGENT_DISK_IMAGE_STALL_SECONDS"))
	if val != "" {
		if secs, err := strconv.Atoi(val); err == nil && secs > 0 {
			return secs
		}
	}
	return 60
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
