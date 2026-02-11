package agent

import (
	"context"
	"fmt"
	"io"
	"log"
	"math"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"sync/atomic"
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
			if isTimeSkewError(stErr) {
				log.Printf("agent: disk restore storage init failed due to possible clock skew, attempting clock sync from API host")
				apiTime, apiTimeOK := r.logRecoveryTimeDiagnostics(runID, run, stErr)
				r.pushEvents(runID, RunEvent{
					Type:      "warn",
					Level:     "warn",
					MessageID: "RECOVERY_TIME_SYNC_ATTEMPT",
					ParamsJSON: map[string]any{
						"api_base_url": r.cfg.APIBaseURL,
					},
				})
				var syncErr error
				var syncedAt time.Time
				if apiTimeOK {
					syncErr = setSystemTimeUTC(apiTime.UTC())
					syncedAt = apiTime.UTC()
				} else {
					syncedAt, syncErr = syncClockFromAPIBase(r.cfg.APIBaseURL)
				}
				if syncErr != nil {
					log.Printf("agent: recovery clock sync failed: %v", syncErr)
					r.pushEvents(runID, RunEvent{
						Type:      "error",
						Level:     "error",
						MessageID: "RECOVERY_TIME_SYNC_FAILED",
						ParamsJSON: map[string]any{
							"error": sanitizeErrorMessage(syncErr),
						},
					})
				} else {
					localAfter := time.Now()
					skew := 0.0
					if apiTimeOK {
						skew = roundSeconds(localAfter.UTC().Sub(apiTime).Seconds())
					}
					log.Printf("agent: recovery clock sync succeeded at %s, retrying storage init", syncedAt.UTC().Format(time.RFC3339))
					r.pushEvents(runID, RunEvent{
						Type:      "info",
						Level:     "info",
						MessageID: "RECOVERY_TIME_SYNC_OK",
						ParamsJSON: map[string]any{
							"synced_at_utc":  syncedAt.UTC().Format(time.RFC3339),
							"local_time_utc": localAfter.UTC().Format(time.RFC3339),
							"skew_seconds":   skew,
						},
					})
					st, stErr = opts.storage(ctx)
					if stErr != nil {
						r.pushEvents(runID, RunEvent{
							Type:      "error",
							Level:     "error",
							MessageID: "RECOVERY_STORAGE_INIT_FAILED",
							ParamsJSON: map[string]any{
								"error":       sanitizeErrorMessage(stErr),
								"error_type":  fmt.Sprintf("%T", stErr),
								"after_sync":  true,
								"skew_seconds": skew,
							},
						})
					}
				}
			}
		}
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

	restorePolicy := parseRestorePolicy(run.PolicyJSON)
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
		parallelWorkers:  restorePolicy.ParallelWorkers,
		segmentSizeBytes: restorePolicy.SegmentSizeBytes,
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
		Parallel:         restorePolicy.KopiaParallel,
		RestoreDirEntryAtDepth: math.MaxInt32,
	})
	if err != nil {
		return fmt.Errorf("kopia: disk restore: %w", err)
	}
	return nil
}

func isTimeSkewError(err error) bool {
	if err == nil {
		return false
	}
	msg := err.Error()
	if msg == "" {
		return false
	}
	lower := strings.ToLower(msg)
	if strings.Contains(lower, "requesttimetooskewed") {
		return true
	}
	return strings.Contains(lower, "request time") && strings.Contains(lower, "time is too large")
}

func (r *Runner) logRecoveryTimeDiagnostics(runID int64, run *NextRunResponse, storageErr error) (time.Time, bool) {
	localNow := time.Now()
	apiBase := strings.TrimSpace(r.cfg.APIBaseURL)
	apiTime, apiAttempts, apiErr := probeServerDate(apiBase)

	destEndpoint := normalizeEndpoint(firstNonEmpty(run.DestEndpoint, r.cfg.DestEndpoint))
	var s3Time time.Time
	var s3Attempts []TimeSyncAttempt
	var s3Err error
	if destEndpoint != "" {
		s3Time, s3Attempts, s3Err = probeServerDate(destEndpoint)
	}

	params := map[string]any{
		"local_time_utc":        localNow.UTC().Format(time.RFC3339),
		"local_time_local":      localNow.Format(time.RFC3339),
		"api_endpoint":          apiBase,
		"api_time_utc":          "-",
		"api_skew_seconds":      "-",
		"api_error":             "",
		"api_attempts":          apiAttempts,
		"s3_endpoint":           destEndpoint,
		"s3_time_utc":           "-",
		"s3_skew_seconds":       "-",
		"s3_error":              "",
		"s3_attempts":           s3Attempts,
		"api_s3_delta_seconds":  "-",
		"storage_init_error":    sanitizeErrorMessage(storageErr),
		"storage_init_error_type": fmt.Sprintf("%T", storageErr),
	}

	if apiErr == nil {
		apiSkew := roundSeconds(localNow.UTC().Sub(apiTime).Seconds())
		params["api_time_utc"] = apiTime.UTC().Format(time.RFC3339)
		params["api_skew_seconds"] = apiSkew
	} else if apiErr != nil {
		params["api_error"] = apiErr.Error()
	}

	if destEndpoint != "" {
		if s3Err == nil {
			s3Skew := roundSeconds(localNow.UTC().Sub(s3Time).Seconds())
			params["s3_time_utc"] = s3Time.UTC().Format(time.RFC3339)
			params["s3_skew_seconds"] = s3Skew
			if apiErr == nil {
				params["api_s3_delta_seconds"] = roundSeconds(apiTime.Sub(s3Time).Seconds())
			}
		} else {
			params["s3_error"] = s3Err.Error()
		}
	}

	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "RECOVERY_TIME_DIAGNOSTICS",
		ParamsJSON: params,
	})

	msg := fmt.Sprintf(
		"Time sync diagnostics: local_utc=%s api_utc=%s api_skew_s=%v s3_utc=%s api_s3_delta_s=%v",
		params["local_time_utc"],
		params["api_time_utc"],
		params["api_skew_seconds"],
		params["s3_time_utc"],
		params["api_s3_delta_seconds"],
	)
	r.pushRecoveryLogs(runID, RunLogEntry{
		Level:       "info",
		Code:        "RECOVERY_TIME_DIAGNOSTICS",
		Message:     msg,
		DetailsJSON: params,
	})

	if strings.TrimSpace(os.Getenv("E3_RECOVERY_DEBUG_LOG")) == "1" {
		_ = r.client.PushRecoveryDebugLog(runID, "info", "RECOVERY_TIME_DIAGNOSTICS", msg, params)
	}

	if apiErr == nil {
		return apiTime, true
	}
	return time.Time{}, false
}

func roundSeconds(val float64) float64 {
	return math.Round(val*1000) / 1000
}

// blockDeviceRestoreOutput writes a single snapshot file directly to a block device.
type blockDeviceRestoreOutput struct {
	targetPath       string
	knownFileSize    int64
	extents          []DiskExtent
	parallelWorkers  int
	segmentSizeBytes int64
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
	totalBytes := b.knownFileSize
	if totalBytes <= 0 {
		totalBytes = e.Size()
	}
	if totalBytes <= 0 {
		testReader, err := e.Open(ctx)
		if err == nil {
			if lr, ok := testReader.(interface{ Length() int64 }); ok {
				totalBytes = lr.Length()
			}
			_ = testReader.Close()
		}
	}

	if len(b.extents) == 0 {
		if b.shouldParallel(totalBytes) {
			return b.copyParallelSegments(ctx, e, totalBytes, nil)
		}
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

		return b.copySequential(ctx, reader, device, totalBytes)
	}
	if b.shouldParallel(totalBytes) {
		return b.copyParallelSegments(ctx, e, totalBytes, b.extents)
	}
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

	return b.copyExtentsSequential(ctx, reader, device, totalBytes)
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

func (b *blockDeviceRestoreOutput) copyExtentsSequential(ctx context.Context, reader io.ReadSeeker, device *os.File, totalBytes int64) error {
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

const minParallelRestoreBytes = int64(64 * 1024 * 1024)
const minParallelSegmentBytes = int64(8 * 1024 * 1024)

func (b *blockDeviceRestoreOutput) shouldParallel(totalBytes int64) bool {
	if b.parallelWorkers < 2 {
		return false
	}
	if totalBytes <= 0 {
		return false
	}
	return totalBytes >= minParallelRestoreBytes
}

func (b *blockDeviceRestoreOutput) copyParallelSegments(ctx context.Context, e kopiafs.File, totalBytes int64, extents []DiskExtent) error {
	if totalBytes <= 0 {
		return fmt.Errorf("disk restore requires a known size for parallel restore")
	}
	if err := prepareBlockDeviceForWrite(b.targetPath); err != nil {
		return err
	}

	segmentSize := b.segmentSizeBytes
	if segmentSize < minParallelSegmentBytes {
		segmentSize = minParallelSegmentBytes
	}

	segments := extents
	if len(segments) == 0 {
		segments = make([]DiskExtent, 0, int(totalBytes/segmentSize)+1)
		for offset := int64(0); offset < totalBytes; offset += segmentSize {
			length := segmentSize
			if offset+length > totalBytes {
				length = totalBytes - offset
			}
			if length <= 0 {
				break
			}
			segments = append(segments, DiskExtent{OffsetBytes: offset, LengthBytes: length})
		}
	} else {
		segments = splitDiskExtents(segments, segmentSize)
	}
	if len(segments) == 0 {
		return nil
	}

	workers := b.parallelWorkers
	if workers > len(segments) {
		workers = len(segments)
	}
	if workers < 1 {
		workers = 1
	}

	ctx, cancel := context.WithCancel(ctx)
	defer cancel()

	taskCh := make(chan DiskExtent, workers)
	var wg sync.WaitGroup
	var errMu sync.Mutex
	var firstErr error

	var bytesWritten int64
	start := time.Now()
	var reportMu sync.Mutex
	var lastReport time.Time

	reportProgress := func(delta int64) {
		if b.serverProgressFn == nil {
			return
		}
		current := atomic.AddInt64(&bytesWritten, delta)
		now := time.Now()
		reportMu.Lock()
		defer reportMu.Unlock()
		if !lastReport.IsZero() && now.Sub(lastReport) < 2*time.Second {
			return
		}
		lastReport = now
		elapsed := now.Sub(start).Seconds()
		var speedBps float64
		if elapsed > 0 {
			speedBps = float64(current) / elapsed
		}
		b.serverProgressFn(current, totalBytes, speedBps)
	}

	workerFn := func() {
		defer wg.Done()

		reader, err := e.Open(ctx)
		if err != nil {
			errMu.Lock()
			if firstErr == nil {
				firstErr = fmt.Errorf("open snapshot file: %w", err)
				cancel()
			}
			errMu.Unlock()
			return
		}
		defer reader.Close()

		device, err := openBlockDeviceForWriteNoPreflight(b.targetPath)
		if err != nil {
			errMu.Lock()
			if firstErr == nil {
				firstErr = err
				cancel()
			}
			errMu.Unlock()
			return
		}
		defer device.Close()

		buf := make([]byte, 4*1024*1024)
		for seg := range taskCh {
			if ctx.Err() != nil {
				return
			}
			if _, err := reader.Seek(seg.OffsetBytes, io.SeekStart); err != nil {
				errMu.Lock()
				if firstErr == nil {
					firstErr = fmt.Errorf("seek snapshot: %w", err)
					cancel()
				}
				errMu.Unlock()
				return
			}
			if _, err := device.Seek(seg.OffsetBytes, io.SeekStart); err != nil {
				errMu.Lock()
				if firstErr == nil {
					firstErr = fmt.Errorf("seek device: %w", err)
					cancel()
				}
				errMu.Unlock()
				return
			}
			remaining := seg.LengthBytes
			for remaining > 0 {
				if ctx.Err() != nil {
					return
				}
				toRead := int64(len(buf))
				if remaining < toRead {
					toRead = remaining
				}
				n, readErr := reader.Read(buf[:toRead])
				if n > 0 {
					if _, werr := device.Write(buf[:n]); werr != nil {
						errMu.Lock()
						if firstErr == nil {
							firstErr = fmt.Errorf("write device: %w", werr)
							cancel()
						}
						errMu.Unlock()
						return
					}
					remaining -= int64(n)
					reportProgress(int64(n))
				}
				if readErr == io.EOF {
					break
				}
				if readErr != nil {
					errMu.Lock()
					if firstErr == nil {
						firstErr = fmt.Errorf("read snapshot: %w", readErr)
						cancel()
					}
					errMu.Unlock()
					return
				}
			}
		}
	}

	wg.Add(workers)
	for i := 0; i < workers; i++ {
		go workerFn()
	}
	for _, seg := range segments {
		if ctx.Err() != nil {
			break
		}
		taskCh <- seg
	}
	close(taskCh)
	wg.Wait()

	if b.serverProgressFn != nil {
		current := atomic.LoadInt64(&bytesWritten)
		elapsed := time.Since(start).Seconds()
		var speedBps float64
		if elapsed > 0 {
			speedBps = float64(current) / elapsed
		}
		b.serverProgressFn(current, totalBytes, speedBps)
	}

	if firstErr != nil {
		return firstErr
	}
	return ctx.Err()
}

func splitDiskExtents(extents []DiskExtent, segmentSize int64) []DiskExtent {
	if segmentSize <= 0 {
		return extents
	}
	segments := make([]DiskExtent, 0, len(extents))
	for _, e := range extents {
		if e.LengthBytes <= 0 {
			continue
		}
		remaining := e.LengthBytes
		offset := e.OffsetBytes
		for remaining > 0 {
			length := segmentSize
			if remaining < length {
				length = remaining
			}
			segments = append(segments, DiskExtent{
				OffsetBytes: offset,
				LengthBytes: length,
			})
			offset += length
			remaining -= length
		}
	}
	return segments
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
