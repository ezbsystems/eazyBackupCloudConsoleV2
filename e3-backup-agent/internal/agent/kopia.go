package agent

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"io/fs"
	"log"
	"math"
	"net/url"
	"os"
	"path"
	"path/filepath"
	"runtime"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/fs/localfs"
	"github.com/kopia/kopia/fs/virtualfs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/blob"
	"github.com/kopia/kopia/repo/blob/filesystem"
	"github.com/kopia/kopia/repo/blob/s3"
	"github.com/kopia/kopia/repo/blob/throttling"
	"github.com/kopia/kopia/repo/compression"
	"github.com/kopia/kopia/repo/content"
	"github.com/kopia/kopia/repo/maintenance"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/policy"
	"github.com/kopia/kopia/snapshot/restore"
	snapshotfs "github.com/kopia/kopia/snapshot/snapshotfs"
)

// isCancellationError checks if an error is due to context cancellation
func isCancellationError(err error) bool {
	if err == nil {
		return false
	}
	// Check for context.Canceled directly
	if errors.Is(err, context.Canceled) {
		return true
	}
	if errors.Is(err, context.DeadlineExceeded) {
		return true
	}
	// Check error message for cancellation indicators
	errStr := strings.ToLower(err.Error())
	cancellationIndicators := []string{
		"context canceled",
		"context cancelled",
		"operation was canceled",
		"operation cancelled",
	}
	for _, indicator := range cancellationIndicators {
		if strings.Contains(errStr, indicator) {
			return true
		}
	}
	return false
}

// sanitizeErrorMessage removes internal implementation details from error messages
func sanitizeErrorMessage(err error) string {
	if err == nil {
		return ""
	}
	msg := err.Error()
	// Replace kopia references with generic terms
	msg = strings.ReplaceAll(msg, "kopia:", "backup engine:")
	msg = strings.ReplaceAll(msg, "Kopia", "eazyBackup")
	msg = strings.ReplaceAll(msg, "kopia", "eazyBackup")
	return msg
}

func displaySourceLabel(run *NextRunResponse) string {
	if run == nil {
		return ""
	}
	if len(run.SourcePaths) > 1 {
		return fmt.Sprintf("multiple sources (%d)", len(run.SourcePaths))
	}
	if len(run.SourcePaths) == 1 && strings.TrimSpace(run.SourcePaths[0]) != "" {
		return run.SourcePaths[0]
	}
	return run.SourcePath
}

type renamedEntry struct {
	entry kopiafs.Entry
	name  string
}

func (e *renamedEntry) Name() string {
	return e.name
}

func (e *renamedEntry) Size() int64 {
	return e.entry.Size()
}

func (e *renamedEntry) Mode() os.FileMode {
	return e.entry.Mode()
}

func (e *renamedEntry) ModTime() time.Time {
	return e.entry.ModTime()
}

func (e *renamedEntry) IsDir() bool {
	return e.entry.IsDir()
}

func (e *renamedEntry) Sys() interface{} {
	return e.entry.Sys()
}

func (e *renamedEntry) Owner() kopiafs.OwnerInfo {
	return e.entry.Owner()
}

func (e *renamedEntry) Device() kopiafs.DeviceInfo {
	return e.entry.Device()
}

func (e *renamedEntry) LocalFilesystemPath() string {
	return e.entry.LocalFilesystemPath()
}

type renamedDirectory struct {
	*renamedEntry
	dir kopiafs.Directory
}

func (d *renamedDirectory) Child(ctx context.Context, name string) (kopiafs.Entry, error) {
	return d.dir.Child(ctx, name)
}

func (d *renamedDirectory) Readdir(ctx context.Context) (kopiafs.Entries, error) {
	return d.dir.Readdir(ctx)
}

func buildMultiSourceEntry(paths []string) (kopiafs.Entry, error) {
	entries := make(kopiafs.Entries, 0, len(paths))
	labels := buildSourceLabels(paths)
	for idx, p := range paths {
		clean := sanitizeSourcePath(p)
		if clean == "" {
			return nil, fmt.Errorf("source path is empty")
		}
		entry, err := localfs.NewEntry(clean)
		if err != nil {
			return nil, fmt.Errorf("source path stat failed for %s: %w", clean, err)
		}
		name := labels[idx]
		wrapped := &renamedEntry{entry: entry, name: name}
		if dir, ok := entry.(kopiafs.Directory); ok {
			entries = append(entries, &renamedDirectory{renamedEntry: wrapped, dir: dir})
		} else {
			entries = append(entries, wrapped)
		}
	}
	return virtualfs.NewStaticDirectory("sources", entries), nil
}

// kopiaSnapshot runs a Kopia snapshot for the given run.
func (r *Runner) kopiaSnapshot(ctx context.Context, run *NextRunResponse) error {
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	if err := os.MkdirAll(filepath.Dir(repoPath), 0o755); err != nil {
		return fmt.Errorf("kopia: mkdir repo dir: %w", err)
	}

	return r.kopiaSnapshotWithEntry(ctx, run, nil, 0)
}

// kopiaSnapshotWithEntry runs a Kopia snapshot using a provided entry override (for device streaming).
// When entryOverride is nil, it falls back to localfs.NewEntry(run.SourcePath).
func (r *Runner) kopiaSnapshotWithEntry(ctx context.Context, run *NextRunResponse, entryOverride kopiafs.Entry, declaredSize int64) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	if err := os.MkdirAll(filepath.Dir(repoPath), 0o755); err != nil {
		return fmt.Errorf("kopia: mkdir repo dir: %w", err)
	}

	// Basic validation of source path before doing heavy work (only when using filesystem path).
	sourceLabel := displaySourceLabel(run)
	if entryOverride == nil {
		run.SourcePath = sanitizeSourcePath(run.SourcePath)
		stat, err := os.Stat(run.SourcePath)
		if err != nil {
			return fmt.Errorf("kopia: source path stat failed: %w", err)
		}
		if stat.IsDir() {
			if hasFiles, err := hasAtLeastOneEntry(run.SourcePath); err == nil && !hasFiles {
				log.Printf("agent: source path appears empty (no files found): %s", run.SourcePath)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "warn",
					Level:     "warn",
					MessageID: "KOPIA_SOURCE_EMPTY",
					ParamsJSON: map[string]any{
						"source": sourceLabel,
					},
				})
			}
		}
	}

	log.Printf(
		"agent: run %d (kopia) storage init dest_type=%s bucket=%s prefix=%q endpoint=%q endpoint_len=%d region=%q source=%s",
		run.RunID, opts.destType, opts.bucket, opts.prefix, opts.endpoint, len(opts.endpoint), opts.region, sourceLabel,
	)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_STAGE",
		ParamsJSON: map[string]any{
			"stage":    "storage_init",
			"bucket":   opts.bucket,
			"prefix":   opts.prefix,
			"endpoint": opts.endpoint,
			"region":   opts.region,
			"source":   sourceLabel,
		},
	})

	st, err := opts.storage(ctx)
	if err != nil {
		return fmt.Errorf("kopia: storage init: %w", err)
	}

	password := opts.password()

	initAndConnect := func() error {
		log.Printf("agent: kopia repo initialize+connect for run %d", run.RunID)
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_STAGE",
			ParamsJSON: map[string]any{
				"stage": "repo_init_connect",
			},
		})
		initOpts := &repo.NewRepositoryOptions{
			BlockFormat: content.FormattingOptions{
				// Larger pack size improves throughput on large files; adjust if needed.
				MutableParameters: content.MutableParameters{
					MaxPackSize: 64 << 20, // 64 MiB
				},
			},
		}
		if err := repo.Initialize(ctx, st, initOpts, password); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("kopia: initialize repo: %w", err)
		}
		if err := repo.Connect(ctx, repoPath, st, password, nil); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("kopia: connect repo: %w", err)
		}
		return nil
	}

	if _, err := os.Stat(repoPath); err != nil {
		if os.IsNotExist(err) {
			if err := initAndConnect(); err != nil {
				return err
			}
		} else {
			return fmt.Errorf("kopia: stat repo config: %w", err)
		}
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "repository not initialized") {
			log.Printf("agent: kopia repo not initialized, attempting initialize+connect for run %d", run.RunID)
			if err := initAndConnect(); err != nil {
				return err
			}
			rep, err = repo.Open(ctx, repoPath, password, nil)
		}
		if err != nil {
			return fmt.Errorf("kopia: open repo: %w", err)
		}
	}
	defer rep.Close(ctx)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_STAGE",
		ParamsJSON: map[string]any{
			"stage": "repo_open",
		},
	})

	// Apply bandwidth throttling if set (KB/s -> bytes/s).
	if run.LocalBandwidthLimitKbps > 0 {
		if dr, ok := rep.(interface {
			Throttler() throttling.SettableThrottler
		}); ok {
			limits := dr.Throttler().Limits()
			limits.UploadBytesPerSecond = float64(run.LocalBandwidthLimitKbps) * 1024.0
			if err := dr.Throttler().SetLimits(limits); err != nil {
				log.Printf("agent: kopia set upload throttler failed: %v", err)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "info",
					Level:     "warn",
					MessageID: "KOPIA_THROTTLE_SET_FAILED",
					ParamsJSON: map[string]any{
						"error": err.Error(),
					},
				})
			} else {
				log.Printf("agent: kopia upload throttled to %.0f bytes/s", limits.UploadBytesPerSecond)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "info",
					Level:     "info",
					MessageID: "KOPIA_THROTTLE_SET",
					ParamsJSON: map[string]any{
						"upload_bytes_per_sec": limits.UploadBytesPerSecond,
						"bandwidth_kbps":       run.LocalBandwidthLimitKbps,
					},
				})
			}
		}
	}

	var (
		srcEntry   kopiafs.Entry
		totalBytes int64
		totalFiles int64
	)
	if entryOverride != nil {
		srcEntry = entryOverride
		if declaredSize > 0 {
			totalBytes = declaredSize
		} else if len(run.SourcePaths) > 0 {
			for _, p := range run.SourcePaths {
				if tb, tf := estimateSourceSize(p); tb > 0 {
					totalBytes += tb
					totalFiles += tf
				}
			}
		}
	} else {
		localEntry, err := localfs.NewEntry(run.SourcePath)
		if err != nil {
			return fmt.Errorf("kopia: source entry: %w", err)
		}
		srcEntry = localEntry

		if tb, tf := estimateSourceSize(run.SourcePath); tb > 0 {
			totalBytes = tb
			totalFiles = tf
		}
	}

	// Best-effort pre-scan to estimate total size for progress calculation.
	if totalBytes > 0 {
		log.Printf("agent: kopia source estimate run %d files=%d bytes=%d", run.RunID, totalFiles, totalBytes)
		_ = r.client.UpdateRun(RunUpdate{
			RunID:        run.RunID,
			BytesTotal:   Int64Ptr(totalBytes),
			ObjectsTotal: totalFiles,
		})
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_SOURCE_ESTIMATE",
			ParamsJSON: map[string]any{
				"files": totalFiles,
				"bytes": totalBytes,
			},
		})
	}

	srcInfo := snapshot.SourceInfo{
		Host:     opts.host,
		UserName: opts.username,
		Path:     filepath.Clean(run.SourcePath),
	}

	parallelUploads, compressor := parsePolicyOverrides(run.PolicyJSON, run.CompressionEnabled)

	pol, err := policy.TreeForSource(ctx, rep, srcInfo)
	if err != nil {
		return fmt.Errorf("kopia: load policy: %w", err)
	}

	ep := pol.EffectivePolicy()
	if compressor != "" {
		ep.CompressionPolicy.CompressorName = compression.Name(compressor)
	}

	manifestID := ""
	manifestMissing := false
	snapCount := 0
	var snapSample []string
	allSnapCount := 0
	var allSnapSample []string

	log.Printf("agent: kopia policy overrides run=%d compressor=%q parallel_uploads=%d bandwidth_kbps=%d",
		run.RunID, ep.CompressionPolicy.CompressorName, parallelUploads, run.LocalBandwidthLimitKbps)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_POLICY",
		ParamsJSON: map[string]any{
			"compression":      ep.CompressionPolicy.CompressorName,
			"parallel_uploads": parallelUploads,
			"bandwidth_kbps":   run.LocalBandwidthLimitKbps,
		},
	})
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_STAGE",
		ParamsJSON: map[string]any{
			"stage":            "policy_applied",
			"compression":      ep.CompressionPolicy.CompressorName,
			"parallel_uploads": parallelUploads,
			"bandwidth_kbps":   run.LocalBandwidthLimitKbps,
		},
	})

	progressCounter := newKopiaProgressCounter(r, run.RunID)

	// OnUpload callback is required to track bytes actually written to blob storage
	uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{
		Purpose:  "snapshot",
		OnUpload: progressCounter.UploadedBytes,
	}, func(wctx context.Context, w repo.RepositoryWriter) error {
		u := snapshotfs.NewUploader(w)
		u.Progress = progressCounter
		if parallelUploads > 0 {
			u.ParallelUploads = parallelUploads
		}
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_STAGE",
			ParamsJSON: map[string]any{
				"stage":            "upload_start",
				"source":           sourceLabel,
				"bucket":           opts.bucket,
				"prefix":           opts.prefix,
				"parallel_uploads": u.ParallelUploads,
				"compression":      ep.CompressionPolicy.CompressorName,
				"include_glob":     run.LocalIncludeGlob,
				"exclude_glob":     run.LocalExcludeGlob,
				"repo_path":        repoPath,
			},
		})
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_UPLOAD_START",
			ParamsJSON: map[string]any{
				"source":           sourceLabel,
				"bucket":           opts.bucket,
				"prefix":           opts.prefix,
				"parallel_uploads": u.ParallelUploads,
				"compression":      ep.CompressionPolicy.CompressorName,
				"bandwidth_kbps":   run.LocalBandwidthLimitKbps,
			},
		})
		man, err := u.Upload(wctx, srcEntry, pol, srcInfo)
		if err != nil {
			// Check if this is a cancellation error - don't report as failure
			if isCancellationError(err) {
				log.Printf("agent: upload cancelled for run %d", run.RunID)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "cancelled",
					Level:     "info",
					MessageID: "CANCELLED",
					ParamsJSON: map[string]any{
						"message": "Upload cancelled by user.",
					},
				})
				return err
			}
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_UPLOAD_FAILED",
				ParamsJSON: map[string]any{
					"error": sanitizeErrorMessage(err),
				},
			})
			return err
		}

		if man == nil {
			log.Printf("agent: kopia upload returned nil manifest for run %d", run.RunID)
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_UPLOAD_NIL_MANIFEST",
				ParamsJSON: map[string]any{
					"source": sourceLabel,
				},
			})
			return fmt.Errorf("kopia: upload returned nil manifest")
		}

		// Upload returns the manifest structure but does NOT persist it.
		// We must call SaveSnapshot to write the manifest to the repository and get an ID.
		log.Printf("agent: kopia saving snapshot manifest for run %d (rootEntry=%v)", run.RunID, man.RootEntry != nil)
		savedID, saveErr := snapshot.SaveSnapshot(wctx, w, man)
		if saveErr != nil {
			log.Printf("agent: kopia SaveSnapshot failed for run %d: %v", run.RunID, saveErr)
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_SAVE_SNAPSHOT_FAILED",
				ParamsJSON: map[string]any{
					"error": saveErr.Error(),
				},
			})
			return fmt.Errorf("kopia: save snapshot: %w", saveErr)
		}
		manifestID = string(savedID)
		log.Printf("agent: kopia snapshot saved for run %d: manifestID=%s", run.RunID, manifestID)

		// Always list snapshots after upload for diagnostics; if manifest is empty but snapshots exist, pick the newest.
		if snaps, serr := snapshot.ListSnapshots(wctx, rep, srcInfo); serr == nil {
			snapCount = len(snaps)
			for i, s := range snaps {
				if i >= 3 {
					break
				}
				snapSample = append(snapSample, fmt.Sprintf("%s (%s)", s.ID, s.Source.Path))
			}
			log.Printf("agent: snapshot listing after upload run %d (source-filtered): count=%d sample=%v", run.RunID, snapCount, snapSample)
			if manifestID == "" && len(snaps) > 0 {
				manifestID = string(snaps[len(snaps)-1].ID)
				manifestMissing = false
				log.Printf("agent: recovered manifest from filtered listing for run %d: %s", run.RunID, manifestID)
			}
		} else {
			log.Printf("agent: snapshot listing failed after upload run %d: %v", run.RunID, serr)
		}

		// Also list all snapshots (no filter) to detect path mismatches.
		if allSnaps, serr2 := snapshot.ListSnapshots(wctx, rep, snapshot.SourceInfo{}); serr2 == nil {
			allSnapCount = len(allSnaps)
			for i, s := range allSnaps {
				if i >= 3 {
					break
				}
				allSnapSample = append(allSnapSample, fmt.Sprintf("%s (%s)", s.ID, s.Source.Path))
			}
			log.Printf("agent: snapshot listing after upload run %d (all sources): count=%d sample=%v", run.RunID, allSnapCount, allSnapSample)
			if manifestID == "" && len(allSnaps) > 0 {
				manifestID = string(allSnaps[len(allSnaps)-1].ID)
				manifestMissing = false
				log.Printf("agent: recovered manifest from all-sources listing for run %d: %s", run.RunID, manifestID)
			}
		} else {
			log.Printf("agent: snapshot listing (all sources) failed after upload run %d: %v", run.RunID, serr2)
		}

		if manifestID == "" {
			// Mark missing and let outer logic attempt fallback before failing
			manifestMissing = true
			log.Printf("agent: kopia upload returned empty manifest for run %d", run.RunID)
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_MANIFEST_MISSING",
				ParamsJSON: map[string]any{
					"source":          sourceLabel,
					"bucket":          opts.bucket,
					"prefix":          opts.prefix,
					"include_glob":    run.LocalIncludeGlob,
					"exclude_glob":    run.LocalExcludeGlob,
					"repo_path":       repoPath,
					"snap_count":      snapCount,
					"snap_sample":     strings.Join(snapSample, "; "),
					"all_snap_count":  allSnapCount,
					"all_snap_sample": strings.Join(allSnapSample, "; "),
				},
			})
			return nil
		}

		log.Printf("agent: kopia manifest id for run %d: %s", run.RunID, manifestID)
		filesDone, filesTotal, foldersDone := progressCounter.GetCounts()
		if err := r.client.UpdateRun(RunUpdate{
			RunID:      run.RunID,
			ManifestID: manifestID,
			StatsJSON: map[string]any{
				"manifest_id": manifestID,
				"files_done":  filesDone,
				"files_total": filesTotal,
				"folders_done": foldersDone,
			},
		}); err != nil {
			log.Printf("agent: UpdateRun manifest failed run %d: %v", run.RunID, err)
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_MANIFEST_UPDATE_FAILED",
				ParamsJSON: map[string]any{
					"error": err.Error(),
				},
			})
			return err
		}

		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_MANIFEST_RECORDED",
			ParamsJSON: map[string]any{
				"manifest_id": manifestID,
				"source":      sourceLabel,
				"bucket":      opts.bucket,
				"prefix":      opts.prefix,
			},
		})
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_UPLOAD_COMPLETE",
		})
		return nil
	})

	if uploadErr != nil {
		return fmt.Errorf("kopia: upload: %w", uploadErr)
	}

	// Send final stats after upload completes (includes any bytes written during flush)
	finalBytesProcessed, finalBytesUploaded := progressCounter.GetFinalStats()
	log.Printf("agent: kopia upload complete run %d: bytes_processed=%d bytes_uploaded=%d", run.RunID, finalBytesProcessed, finalBytesUploaded)
	_ = r.client.UpdateRun(RunUpdate{
		RunID:            run.RunID,
		BytesTransferred: Int64Ptr(finalBytesUploaded),
		BytesProcessed:   Int64Ptr(finalBytesProcessed),
	})

	// Fallback: if upload completed but manifest missing, try to recover the latest snapshot ID
	if manifestMissing {
		fallbackID, err := latestSnapshotID(ctx, rep, srcInfo)
		if err != nil {
			return fmt.Errorf("kopia: upload completed but manifest missing; fallback failed: %w", err)
		}
		if fallbackID == "" {
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_MANIFEST_MISSING",
				ParamsJSON: map[string]any{
					"source":          sourceLabel,
					"bucket":          opts.bucket,
					"prefix":          opts.prefix,
					"include_glob":    run.LocalIncludeGlob,
					"exclude_glob":    run.LocalExcludeGlob,
					"repo_path":       repoPath,
					"fallback":        "no snapshots found for source",
					"snap_count":      snapCount,
					"snap_sample":     strings.Join(snapSample, "; "),
					"all_snap_count":  allSnapCount,
					"all_snap_sample": strings.Join(allSnapSample, "; "),
					"endpoint":        opts.endpoint,
				},
			})
			return fmt.Errorf("kopia: upload completed but manifest missing; no snapshots found for source (possible empty/filtered source)")
		}
		manifestID = fallbackID
		log.Printf("agent: kopia manifest recovered from latest snapshot for run %d: %s", run.RunID, manifestID)
		filesDone, filesTotal, foldersDone := progressCounter.GetCounts()
		if err := r.client.UpdateRun(RunUpdate{
			RunID:      run.RunID,
			ManifestID: manifestID,
			StatsJSON: map[string]any{
				"manifest_id": manifestID,
				"files_done":  filesDone,
				"files_total": filesTotal,
				"folders_done": foldersDone,
			},
		}); err != nil {
			log.Printf("agent: UpdateRun fallback manifest failed run %d: %v", run.RunID, err)
			return fmt.Errorf("kopia: fallback manifest update failed: %w", err)
		}
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_MANIFEST_FALLBACK",
			ParamsJSON: map[string]any{
				"manifest_id": manifestID,
				"source":      sourceLabel,
				"bucket":      opts.bucket,
				"prefix":      opts.prefix,
			},
		})
	}

	if manifestID == "" {
		return fmt.Errorf("kopia: manifest id not recorded")
	}

	return nil
}

func sanitizeSourcePath(raw string) string {
	s := strings.TrimSpace(raw)
	s = strings.ReplaceAll(s, "&quot;", "\"")
	s = strings.ReplaceAll(s, "&#34;", "\"")
	return strings.Trim(s, "\"'")
}

// kopiaSnapshotDiskImage runs a Kopia snapshot for disk image backups.
// It uses a stable source path (e.g., "C:") for SourceInfo to enable proper deduplication
// across runs, while reading data from the provided entry (e.g., VSS snapshot device).
func (r *Runner) kopiaSnapshotDiskImage(ctx context.Context, run *NextRunResponse, entryOverride kopiafs.Entry, declaredSize int64, stableSourcePath string) (string, error) {
	return r.kopiaSnapshotDiskImageWithProgress(ctx, run, entryOverride, declaredSize, stableSourcePath, nil, false)
}

// kopiaSnapshotDiskImageWithProgress is like kopiaSnapshotDiskImage but with an optional progress callback.
// The callback is called with the cumulative bytes processed and uploaded during the snapshot.
// Returns the manifest ID of the saved snapshot.
func (r *Runner) kopiaSnapshotDiskImageWithProgress(ctx context.Context, run *NextRunResponse, entryOverride kopiafs.Entry, declaredSize int64, stableSourcePath string, progressCb func(bytesProcessed int64, bytesUploaded int64), skipRunUpdate bool) (string, error) {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	if err := os.MkdirAll(filepath.Dir(repoPath), 0o755); err != nil {
		return "", fmt.Errorf("kopia: mkdir repo dir: %w", err)
	}

	log.Printf(
		"agent: run %d (kopia disk image) storage init dest_type=%s bucket=%s prefix=%q source=%s (stable=%s)",
		run.RunID, opts.destType, opts.bucket, opts.prefix, entryOverride.Name(), stableSourcePath,
	)
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_STAGE",
		ParamsJSON: map[string]any{
			"stage":         "storage_init",
			"bucket":        opts.bucket,
			"prefix":        opts.prefix,
			"stable_source": stableSourcePath,
		},
	})

	st, err := opts.storage(ctx)
	if err != nil {
		return "", fmt.Errorf("kopia: storage init: %w", err)
	}

	password := opts.password()

	initAndConnect := func() error {
		log.Printf("agent: kopia repo initialize+connect for run %d", run.RunID)
		initOpts := &repo.NewRepositoryOptions{
			BlockFormat: content.FormattingOptions{
				MutableParameters: content.MutableParameters{
					MaxPackSize: 64 << 20, // 64 MiB
				},
			},
		}
		if err := repo.Initialize(ctx, st, initOpts, password); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("kopia: initialize repo: %w", err)
		}
		if err := repo.Connect(ctx, repoPath, st, password, nil); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("kopia: connect repo: %w", err)
		}
		return nil
	}

	if _, err := os.Stat(repoPath); err != nil {
		if os.IsNotExist(err) {
			if err := initAndConnect(); err != nil {
				return "", err
			}
		} else {
			return "", fmt.Errorf("kopia: stat repo config: %w", err)
		}
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "repository not initialized") {
			if err := initAndConnect(); err != nil {
				return "", err
			}
			rep, err = repo.Open(ctx, repoPath, password, nil)
		}
		if err != nil {
			return "", fmt.Errorf("kopia: open repo: %w", err)
		}
	}
	defer rep.Close(ctx)

	// Use stable source path for SourceInfo to enable deduplication across runs
	srcInfo := snapshot.SourceInfo{
		Host:     opts.host,
		UserName: opts.username,
		Path:     stableSourcePath,
	}

	// Fetch previous snapshots for this source to enable incremental behavior
	var previousManifests []*snapshot.Manifest
	if snaps, err := snapshot.ListSnapshots(ctx, rep, srcInfo); err == nil && len(snaps) > 0 {
		log.Printf("agent: kopia found %d previous snapshots for source %s", len(snaps), stableSourcePath)
		previousManifests = snaps
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_PREVIOUS_SNAPSHOTS",
			ParamsJSON: map[string]any{
				"count":         len(snaps),
				"stable_source": stableSourcePath,
			},
		})
	} else {
		log.Printf("agent: kopia no previous snapshots for source %s (first run or error: %v)", stableSourcePath, err)
	}

	if declaredSize > 0 {
		log.Printf("agent: kopia disk image size estimate run %d bytes=%d", run.RunID, declaredSize)
		_ = r.client.UpdateRun(RunUpdate{
			RunID:        run.RunID,
			BytesTotal:   Int64Ptr(declaredSize),
			ObjectsTotal: 1, // Single file (disk image)
		})
	}

	parallelUploads, compressor := parsePolicyOverrides(run.PolicyJSON, run.CompressionEnabled)
	pol, err := policy.TreeForSource(ctx, rep, srcInfo)
	if err != nil {
		return "", fmt.Errorf("kopia: load policy: %w", err)
	}

	ep := pol.EffectivePolicy()
	if compressor != "" {
		ep.CompressionPolicy.CompressorName = compression.Name(compressor)
	}

	log.Printf("agent: kopia disk image policy overrides run=%d compressor=%q parallel_uploads=%d",
		run.RunID, ep.CompressionPolicy.CompressorName, parallelUploads)

	progressCounter := newKopiaProgressCounterWithCallback(r, run.RunID, progressCb, skipRunUpdate)
	var manifestID string

	// OnUpload callback is required to track bytes actually written to blob storage
	uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{
		Purpose:  "snapshot",
		OnUpload: progressCounter.UploadedBytes,
	}, func(wctx context.Context, w repo.RepositoryWriter) error {
		u := snapshotfs.NewUploader(w)
		u.Progress = progressCounter
		if parallelUploads > 0 {
			u.ParallelUploads = parallelUploads
		}

		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_UPLOAD_START",
			ParamsJSON: map[string]any{
				"stable_source":      stableSourcePath,
				"bucket":             opts.bucket,
				"prefix":             opts.prefix,
				"parallel_uploads":   u.ParallelUploads,
				"compression":        ep.CompressionPolicy.CompressorName,
				"previous_snapshots": len(previousManifests),
			},
		})

		// Pass previous manifests to enable incremental deduplication
		man, err := u.Upload(wctx, entryOverride, pol, srcInfo, previousManifests...)
		if err != nil {
			// Check if this is a cancellation error - don't report as failure
			if isCancellationError(err) {
				log.Printf("agent: upload cancelled for run %d", run.RunID)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "cancelled",
					Level:     "info",
					MessageID: "CANCELLED",
					ParamsJSON: map[string]any{
						"message": "Upload cancelled by user.",
					},
				})
				return err
			}
			r.pushEvents(run.RunID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "KOPIA_UPLOAD_FAILED",
				ParamsJSON: map[string]any{
					"error": sanitizeErrorMessage(err),
				},
			})
			return err
		}

		if man == nil {
			return fmt.Errorf("kopia: upload returned nil manifest")
		}

		// Save the snapshot manifest
		savedID, saveErr := snapshot.SaveSnapshot(wctx, w, man)
		if saveErr != nil {
			return fmt.Errorf("kopia: save snapshot: %w", saveErr)
		}
		manifestID = string(savedID)
		log.Printf("agent: kopia disk image snapshot saved for run %d: manifestID=%s", run.RunID, manifestID)

		if err := r.client.UpdateRun(RunUpdate{
			RunID:      run.RunID,
			ManifestID: manifestID,
			StatsJSON: map[string]any{
				"manifest_id":   manifestID,
				"stable_source": stableSourcePath,
			},
		}); err != nil {
			log.Printf("agent: UpdateRun manifest failed run %d: %v", run.RunID, err)
		}

		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_UPLOAD_COMPLETE",
			ParamsJSON: map[string]any{
				"manifest_id":   manifestID,
				"stable_source": stableSourcePath,
			},
		})
		return nil
	})

	if uploadErr != nil {
		return "", fmt.Errorf("kopia: upload: %w", uploadErr)
	}

	// Send final stats after upload completes (includes any bytes written during flush)
	finalBytesProcessed, finalBytesUploaded := progressCounter.GetFinalStats()
	log.Printf("agent: kopia disk image upload complete run %d: bytes_processed=%d bytes_uploaded=%d", run.RunID, finalBytesProcessed, finalBytesUploaded)
	_ = r.client.UpdateRun(RunUpdate{
		RunID:            run.RunID,
		BytesTransferred: Int64Ptr(finalBytesUploaded),
		BytesProcessed:   Int64Ptr(finalBytesProcessed),
	})

	if manifestID == "" {
		return "", fmt.Errorf("kopia: manifest id not recorded")
	}

	return manifestID, nil
}

// kopiaProgressCounter implements snapshotfs.UploadProgress and reports upload metrics to the server.
type kopiaProgressCounter struct {
	runner *Runner
	runID  int64

	startTime       time.Time
	lastReportAt    time.Time
	lastUploaded    int64
	lastTransferred int64
	currentFile     string
	mu              sync.Mutex
	bytesHashed     int64
	bytesUploaded   int64
	filesHashed     int64
	filesCached     int64
	dirsFinished    int64
	totalBytes      int64
	totalFiles      int64
	
	// Optional external progress callback (for Hyper-V cumulative progress)
	externalProgressCb func(bytesProcessed int64, bytesUploaded int64)
	skipRunUpdate      bool
}

func newKopiaProgressCounter(r *Runner, runID int64) *kopiaProgressCounter {
	return newKopiaProgressCounterWithCallback(r, runID, nil, false)
}

func newKopiaProgressCounterWithCallback(r *Runner, runID int64, progressCb func(bytesProcessed int64, bytesUploaded int64), skipRunUpdate bool) *kopiaProgressCounter {
	now := time.Now()
	return &kopiaProgressCounter{
		runner:             r,
		runID:              runID,
		startTime:          now,
		lastReportAt:       now,
		externalProgressCb: progressCb,
		skipRunUpdate:      skipRunUpdate,
	}
}

func (p *kopiaProgressCounter) UploadStarted() {
	// Reset timing on start to avoid stale values.
	p.mu.Lock()
	defer p.mu.Unlock()
	p.startTime = time.Now()
	p.lastReportAt = p.startTime
	p.reportProgressLocked(true)
}

func (p *kopiaProgressCounter) UploadFinished() {
	p.mu.Lock()
	defer p.mu.Unlock()
	
	// Log final stats for debugging
	bytesHashed := atomic.LoadInt64(&p.bytesHashed)
	bytesUploaded := atomic.LoadInt64(&p.bytesUploaded)
	log.Printf("agent: kopia UploadFinished: bytesHashed=%d bytesUploaded=%d", bytesHashed, bytesUploaded)
	
	p.reportProgressLocked(true)
}

// GetFinalStats returns the final stats after upload completes
func (p *kopiaProgressCounter) GetFinalStats() (bytesHashed, bytesUploaded int64) {
	return atomic.LoadInt64(&p.bytesHashed), atomic.LoadInt64(&p.bytesUploaded)
}

func (p *kopiaProgressCounter) GetCounts() (filesDone, filesTotal, foldersDone int64) {
	filesHashed := atomic.LoadInt64(&p.filesHashed)
	filesCached := atomic.LoadInt64(&p.filesCached)
	filesDone = filesHashed + filesCached
	filesTotal = atomic.LoadInt64(&p.totalFiles)
	foldersDone = atomic.LoadInt64(&p.dirsFinished)
	return
}

func (p *kopiaProgressCounter) CachedFile(fname string, numBytes int64) {
	atomic.AddInt64(&p.filesCached, 1)
	// Treat cached bytes as processed for progress.
	atomic.AddInt64(&p.bytesHashed, numBytes)
	p.setCurrentFile(fname)
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) HashingFile(fname string) {
	p.setCurrentFile(fname)
}

func (p *kopiaProgressCounter) ExcludedFile(fname string, size int64) {
	// Exclusions are informational only; no progress update needed.
}

func (p *kopiaProgressCounter) ExcludedDir(dirname string) {
	// Exclusions are informational only; no progress update needed.
}

func (p *kopiaProgressCounter) FinishedHashingFile(fname string, numBytes int64) {
	atomic.AddInt64(&p.filesHashed, 1)
	p.setCurrentFile(fname)
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) HashedBytes(numBytes int64) {
	atomic.AddInt64(&p.bytesHashed, numBytes)
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) Error(path string, err error, isIgnored bool) {
	// On error, emit a progress tick so UI remains up-to-date.
	p.setCurrentFile(path)
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) UploadedBytes(numBytes int64) {
	newTotal := atomic.AddInt64(&p.bytesUploaded, numBytes)
	log.Printf("agent: kopia UploadedBytes callback: +%d bytes (total: %d)", numBytes, newTotal)
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) StartedDirectory(dirname string) {
	p.setCurrentFile(dirname)
}

func (p *kopiaProgressCounter) FinishedDirectory(dirname string) {
	atomic.AddInt64(&p.dirsFinished, 1)
	p.setCurrentFile(dirname)
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) EstimatedDataSize(fileCount int, totalBytes int64) {
	atomic.StoreInt64(&p.totalFiles, int64(fileCount))
	atomic.StoreInt64(&p.totalBytes, totalBytes)
	// Send an update when we learn the total size.
	p.reportProgress(false)
}

func (p *kopiaProgressCounter) setCurrentFile(path string) {
	p.mu.Lock()
	p.currentFile = path
	p.mu.Unlock()
}

func (p *kopiaProgressCounter) reportProgress(force bool) {
	p.mu.Lock()
	defer p.mu.Unlock()
	p.reportProgressLocked(force)
}

func (p *kopiaProgressCounter) reportProgressLocked(force bool) {
	now := time.Now()
	if !force && now.Sub(p.lastReportAt) < 2*time.Second {
		return
	}

	bytesHashed := atomic.LoadInt64(&p.bytesHashed)
	bytesUploaded := atomic.LoadInt64(&p.bytesUploaded)
	totalBytes := atomic.LoadInt64(&p.totalBytes)
	totalFiles := atomic.LoadInt64(&p.totalFiles)
	filesHashed := atomic.LoadInt64(&p.filesHashed)
	filesCached := atomic.LoadInt64(&p.filesCached)
	dirsFinished := atomic.LoadInt64(&p.dirsFinished)
	currentItem := p.currentFile

	// bytesHashed = bytes read from source and processed (determines overall job progress)
	// bytesUploaded = actual NEW bytes sent to storage (shows deduplication effectiveness)
	// For progress/speed/ETA, use bytesHashed since that's what determines job completion.

	elapsed := now.Sub(p.lastReportAt).Seconds()
	speed := int64(0)
	if elapsed > 0 {
		// Speed based on processing rate (hashing), not upload rate
		speed = int64(float64(bytesHashed-p.lastTransferred) / elapsed)
	}
	p.lastReportAt = now
	p.lastUploaded = bytesUploaded
	p.lastTransferred = bytesHashed

	progressPct := 0.0
	if totalBytes > 0 {
		progressPct = math.Min(100.0, (float64(bytesHashed)/float64(totalBytes))*100.0)
	}

	etaSeconds := int64(0)
	if speed > 0 && totalBytes > 0 {
		remaining := totalBytes - bytesHashed
		if remaining < 0 {
			remaining = 0
		}
		etaSeconds = int64(float64(remaining) / float64(speed))
	}

	filesDone := filesHashed + filesCached
	objectsTransferred := filesDone
	statsPayload := map[string]any{
		"files_done":   filesDone,
		"files_total":  totalFiles,
		"folders_done": dirsFinished,
	}

	// Call external progress callback if set (for Hyper-V cumulative progress)
	if p.externalProgressCb != nil {
		p.externalProgressCb(bytesHashed, bytesUploaded)
	}

	// Fire-and-forget; errors are logged upstream.
	// BytesTransferred = actual bytes uploaded to storage (shows deduplication savings)
	// BytesProcessed = bytes read/hashed from source (shows overall scan progress)
	// Note: When skipRunUpdate is true (Hyper-V), the external tracker handles run updates.
	if !p.skipRunUpdate {
		_ = p.runner.client.UpdateRun(RunUpdate{
			RunID:              p.runID,
			Status:             "running",
			ProgressPct:        progressPct,
			BytesTransferred:   Int64Ptr(bytesUploaded),
			BytesProcessed:     Int64Ptr(bytesHashed),
			BytesTotal:         Int64Ptr(totalBytes),
			ObjectsTransferred: objectsTransferred,
			ObjectsTotal:       totalFiles,
			SpeedBytesPerSec:   speed,
			EtaSeconds:         etaSeconds,
			CurrentItem:        currentItem,
			StatsJSON:          statsPayload,
		})
	}

	p.runner.pushEvents(p.runID, RunEvent{
		Type:      "progress",
		Level:     "info",
		MessageID: "KOPIA_PROGRESS_UPDATE",
		ParamsJSON: map[string]any{
			"pct":            progressPct,
			"bytes_processed": bytesHashed,   // Source bytes scanned
			"bytes_uploaded": bytesUploaded,  // Actual upload (with dedup)
			"bytes_total":    totalBytes,
			"files_done":     filesHashed + filesCached,
			"files_total":    totalFiles,
			"speed_bps":      speed,
			"eta_seconds":    etaSeconds,
			"current":        currentItem,
		},
	})
}

// kopiaRestore provides a restore entry point (not yet wired to server commands).
// This restores the specified manifest to targetPath.
func (r *Runner) kopiaRestore(ctx context.Context, run *NextRunResponse, manifestID, targetPath string) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	password := opts.password()

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return fmt.Errorf("kopia: open for restore: %w", err)
	}
	defer rep.Close(ctx)

	if err := os.MkdirAll(targetPath, 0o755); err != nil {
		return fmt.Errorf("kopia: mkdir target: %w", err)
	}

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return fmt.Errorf("kopia: load snapshot: %w", err)
	}

	rootEntry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	out := &restore.FilesystemOutput{
		TargetPath:           targetPath,
		OverwriteDirectories: true,
		OverwriteFiles:       true,
		OverwriteSymlinks:    true,
	}
	_, err = restore.Entry(ctx, rep, out, rootEntry, restore.Options{
		RestoreDirEntryAtDepth: math.MaxInt32,
	})
	return err
}

// kopiaRestoreWithProgress provides a restore with progress tracking.
func (r *Runner) kopiaRestoreWithProgress(ctx context.Context, run *NextRunResponse, manifestID, targetPath string, runID int64) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	password := opts.password()

	log.Printf("agent: kopia restore opening repo at %s for job %d", repoPath, run.JobID)

	// Check if repo config exists
	if _, statErr := os.Stat(repoPath); os.IsNotExist(statErr) {
		// Repo config doesn't exist - need to connect to existing repo in S3
		log.Printf("agent: kopia repo config not found at %s, connecting to remote repo", repoPath)
		r.pushEvents(runID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_RESTORE_CONNECTING",
			ParamsJSON: map[string]any{
				"bucket": opts.bucket,
				"prefix": opts.prefix,
			},
		})

		st, stErr := opts.storage(ctx)
		if stErr != nil {
			return fmt.Errorf("kopia: storage init for restore: %w", stErr)
		}

		// Connect to existing repo (don't initialize - it should already exist from backup)
		if connErr := repo.Connect(ctx, repoPath, st, password, nil); connErr != nil {
			// If connect fails, the repo might not exist or credentials are wrong
			return fmt.Errorf("kopia: connect to repo for restore failed (check if backup was successful and credentials match): %w", connErr)
		}
		log.Printf("agent: kopia connected to remote repo, config saved at %s", repoPath)
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return fmt.Errorf("kopia: open repo for restore: %w", err)
	}
	defer rep.Close(ctx)

	if err := os.MkdirAll(targetPath, 0o755); err != nil {
		return fmt.Errorf("kopia: mkdir target: %w", err)
	}

	log.Printf("agent: kopia restore loading snapshot %s", manifestID)
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_RESTORE_LOADING",
		ParamsJSON: map[string]any{
			"manifest_id": manifestID,
		},
	})

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return fmt.Errorf("kopia: load snapshot: %w", err)
	}

	log.Printf("agent: kopia restore snapshot loaded, source=%s start_time=%v", man.Source.Path, man.StartTime)

	// Log root entry details for debugging
	if man.RootEntry != nil {
		log.Printf("agent: kopia restore root entry: type=%s name=%s size=%d objectID=%v",
			man.RootEntry.Type, man.RootEntry.Name, man.RootEntry.FileSize, man.RootEntry.ObjectID)
	} else {
		log.Printf("agent: kopia restore root entry is nil!")
	}

	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_RESTORE_SNAPSHOT_LOADED",
		ParamsJSON: map[string]any{
			"manifest_id": manifestID,
			"source_path": man.Source.Path,
			"start_time":  man.StartTime.String(),
		},
	})

	rootEntry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	if rootEntry == nil {
		return fmt.Errorf("kopia: failed to create root entry from snapshot")
	}

	// Log more details about the entry we're about to restore
	log.Printf("agent: kopia restore entry: name=%s isDir=%v mode=%v",
		rootEntry.Name(), rootEntry.IsDir(), rootEntry.Mode())

	// Create progress counter for tracking
	progressCounter := &restoreProgressCounter{
		runner: r,
		runID:  runID,
	}

	// Use a wrapper that forces full restore (no placeholders)
	// FilesystemOutput implements ShallowEntryWriter which allows Kopia to write
	// placeholder .kopia-entry files instead of actual content. We wrap it to
	// only expose the basic Output interface, forcing actual content writes.
	fsOut := &restore.FilesystemOutput{
		TargetPath:           targetPath,
		OverwriteDirectories: true,
		OverwriteFiles:       true,
		OverwriteSymlinks:    true,
		// Windows-specific settings:
		WriteFilesAtomically: false, // Atomic rename fails on Windows with "Access denied"
		SkipOwners:           true,  // Windows ownership model differs from Unix
		SkipPermissions:      true,  // Windows permissions differ from Unix
	}
	out := &fullRestoreOutput{fsOut}

	log.Printf("agent: kopia restore starting to %s", targetPath)
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_RESTORE_STARTED",
		ParamsJSON: map[string]any{
			"manifest_id": manifestID,
			"target_path": targetPath,
		},
	})

	stats, err := restore.Entry(ctx, rep, out, rootEntry, restore.Options{
		ProgressCallback: progressCounter.onProgress,
		Parallel:         4, // Use parallel restore for better performance
		RestoreDirEntryAtDepth: math.MaxInt32,
	})

	if err != nil {
		return err
	}

	log.Printf("agent: kopia restore completed: files=%d dirs=%d bytes=%d skipped=%d errors=%d",
		stats.RestoredFileCount, stats.RestoredDirCount, stats.RestoredTotalFileSize,
		stats.SkippedCount, stats.IgnoredErrorCount)

	// Verify restoration by checking target directory
	if entries, readErr := os.ReadDir(targetPath); readErr == nil {
		log.Printf("agent: kopia restore target dir %s contains %d entries:", targetPath, len(entries))
		for i, entry := range entries {
			if i >= 10 { // Only log first 10
				log.Printf("agent:   ... and %d more entries", len(entries)-10)
				break
			}
			info, _ := entry.Info()
			if info != nil {
				log.Printf("agent:   - %s (dir=%v size=%d)", entry.Name(), entry.IsDir(), info.Size())
			} else {
				log.Printf("agent:   - %s (dir=%v)", entry.Name(), entry.IsDir())
			}
		}
	} else {
		log.Printf("agent: kopia restore could not read target dir: %v", readErr)
	}
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_RESTORE_STATS",
		ParamsJSON: map[string]any{
			"files_restored": stats.RestoredFileCount,
			"dirs_restored":  stats.RestoredDirCount,
			"bytes_restored": stats.RestoredTotalFileSize,
			"files_skipped":  stats.SkippedCount,
			"errors":         stats.IgnoredErrorCount,
		},
	})

	return nil
}

// kopiaRestoreSelectedPaths restores a subset of snapshot paths to the target path.
func (r *Runner) kopiaRestoreSelectedPaths(ctx context.Context, run *NextRunResponse, manifestID, targetPath string, selectedPaths []string, runID int64) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	password := opts.password()

	if _, statErr := os.Stat(repoPath); os.IsNotExist(statErr) {
		log.Printf("agent: kopia repo config not found at %s, connecting to remote repo", repoPath)
		st, stErr := opts.storage(ctx)
		if stErr != nil {
			return fmt.Errorf("kopia: storage init for restore: %w", stErr)
		}
		if connErr := repo.Connect(ctx, repoPath, st, password, nil); connErr != nil {
			return fmt.Errorf("kopia: connect to repo for restore failed: %w", connErr)
		}
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return fmt.Errorf("kopia: open repo for restore: %w", err)
	}
	defer rep.Close(ctx)

	if err := os.MkdirAll(targetPath, 0o755); err != nil {
		return fmt.Errorf("kopia: mkdir target: %w", err)
	}

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return fmt.Errorf("kopia: load snapshot: %w", err)
	}

	rootEntry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	if rootEntry == nil {
		return fmt.Errorf("kopia: failed to create root entry from snapshot")
	}

	progressCounter := &restoreProgressCounter{
		runner: r,
		runID:  runID,
	}

	unique := map[string]struct{}{}
	paths := make([]string, 0, len(selectedPaths))
	for _, raw := range selectedPaths {
		p := normalizeSnapshotPath(raw)
		if p == "" {
			continue
		}
		if _, exists := unique[p]; exists {
			continue
		}
		unique[p] = struct{}{}
		paths = append(paths, p)
	}
	if len(paths) == 0 {
		return fmt.Errorf("kopia: no valid paths selected")
	}

	for _, rel := range paths {
		entry, err := findSnapshotEntry(ctx, rootEntry, rel)
		if err != nil {
			return fmt.Errorf("kopia: selected path not found (%s): %w", rel, err)
		}

		parentRel := path.Dir(rel)
		if parentRel == "." || parentRel == "/" {
			parentRel = ""
		}
		destBase := targetPath
		if parentRel != "" {
			destBase = filepath.Join(targetPath, filepath.FromSlash(parentRel))
		}
		if err := os.MkdirAll(destBase, 0o755); err != nil {
			return fmt.Errorf("kopia: mkdir target for %s: %w", rel, err)
		}

		fsOut := &restore.FilesystemOutput{
			TargetPath:           destBase,
			OverwriteDirectories: true,
			OverwriteFiles:       true,
			OverwriteSymlinks:    true,
			WriteFilesAtomically: false,
			SkipOwners:           true,
			SkipPermissions:      true,
		}
		out := &fullRestoreOutput{fsOut}

		if _, err := restore.Entry(ctx, rep, out, entry, restore.Options{
			ProgressCallback: progressCounter.onProgress,
			Parallel:         4,
			RestoreDirEntryAtDepth: math.MaxInt32,
		}); err != nil {
			return err
		}
	}

	return nil
}

// restoreProgressCounter tracks restore progress and reports to server.
type restoreProgressCounter struct {
	runner       *Runner
	runID        int64
	lastReportAt int64
}

func (p *restoreProgressCounter) onProgress(ctx context.Context, stats restore.Stats) {
	// Throttle progress updates to avoid flooding
	now := unixMillis()
	if now-p.lastReportAt < 2000 { // Report at most every 2 seconds
		return
	}
	p.lastReportAt = now

	// Update run progress
	_ = p.runner.client.UpdateRun(RunUpdate{
		RunID:              p.runID,
		BytesTransferred:   Int64Ptr(stats.RestoredTotalFileSize),
		ObjectsTransferred: int64(stats.RestoredFileCount) + int64(stats.RestoredDirCount),
		CurrentItem:        fmt.Sprintf("Restored %d files, %d dirs", stats.RestoredFileCount, stats.RestoredDirCount),
	})

	// Push progress event
	p.runner.pushEvents(p.runID, RunEvent{
		Type:      "progress",
		Level:     "info",
		MessageID: "RESTORE_PROGRESS",
		ParamsJSON: map[string]any{
			"files_restored": stats.RestoredFileCount,
			"dirs_restored":  stats.RestoredDirCount,
			"bytes_restored": stats.RestoredTotalFileSize,
			"files_skipped":  stats.SkippedCount,
		},
	})
}

func unixMillis() int64 {
	return time.Now().UnixNano() / int64(time.Millisecond)
}

// fullRestoreOutput wraps FilesystemOutput but only exposes the basic restore.Output
// interface, NOT the ShallowEntryWriter interface. This prevents Kopia from writing
// placeholder .kopia-entry files and forces it to download and write actual content.
type fullRestoreOutput struct {
	*restore.FilesystemOutput
}

// Ensure fullRestoreOutput implements restore.Output but NOT ShallowEntryWriter
var _ restore.Output = (*fullRestoreOutput)(nil)

// BeginDirectory delegates to underlying FilesystemOutput
func (f *fullRestoreOutput) BeginDirectory(ctx context.Context, relativePath string, e kopiafs.Directory) error {
	return f.FilesystemOutput.BeginDirectory(ctx, relativePath, e)
}

// FinishDirectory delegates to underlying FilesystemOutput
func (f *fullRestoreOutput) FinishDirectory(ctx context.Context, relativePath string, e kopiafs.Directory) error {
	return f.FilesystemOutput.FinishDirectory(ctx, relativePath, e)
}

// WriteFile delegates to underlying FilesystemOutput - writes ACTUAL content
func (f *fullRestoreOutput) WriteFile(ctx context.Context, relativePath string, e kopiafs.File) error {
	return f.FilesystemOutput.WriteFile(ctx, relativePath, e)
}

// CreateSymlink delegates to underlying FilesystemOutput
func (f *fullRestoreOutput) CreateSymlink(ctx context.Context, relativePath string, e kopiafs.Symlink) error {
	return f.FilesystemOutput.CreateSymlink(ctx, relativePath, e)
}

// Close delegates to underlying FilesystemOutput
func (f *fullRestoreOutput) Close(ctx context.Context) error {
	return f.FilesystemOutput.Close(ctx)
}

// NOTE: We deliberately do NOT implement WritePlaceholder/ShallowEntryWriter
// This forces Kopia to use WriteFile for all files, downloading actual content

// singleFileRestoreOutput writes a single file to a specific target path
// Used for VHDX restores where we want to restore a single file (not a directory)
type singleFileRestoreOutput struct {
	targetPath       string
	knownFileSize    int64 // Pre-known file size from snapshot metadata (needed since Size() returns 0 for streams)
	progressCallback func(ctx context.Context, stats restore.Stats)
	// Server progress callback - called periodically with bytes written
	serverProgressFn func(bytesWritten, bytesTotal int64, speedBps float64)
}

// Ensure singleFileRestoreOutput implements restore.Output
var _ restore.Output = (*singleFileRestoreOutput)(nil)

// Parallelizable returns true - we support parallel restores
func (s *singleFileRestoreOutput) Parallelizable() bool {
	return true
}

// BeginDirectory is a no-op for single file restores
func (s *singleFileRestoreOutput) BeginDirectory(ctx context.Context, relativePath string, e kopiafs.Directory) error {
	return nil
}

// WriteDirEntry is a no-op for single file restores
func (s *singleFileRestoreOutput) WriteDirEntry(ctx context.Context, relativePath string, de *snapshot.DirEntry, e kopiafs.Directory) error {
	return nil
}

// FinishDirectory is a no-op for single file restores
func (s *singleFileRestoreOutput) FinishDirectory(ctx context.Context, relativePath string, e kopiafs.Directory) error {
	return nil
}

// WriteFile writes the file to the target path using parallel segment downloads
func (s *singleFileRestoreOutput) WriteFile(ctx context.Context, relativePath string, e kopiafs.File) error {
	// Ensure target directory exists
	targetDir := filepath.Dir(s.targetPath)
	if err := os.MkdirAll(targetDir, 0755); err != nil {
		return fmt.Errorf("create target directory: %w", err)
	}

	// Get file size - for streaming backups, both e.Size() and snapshot metadata return 0
	// We need to open the file and call Length() on the reader to get the true size
	fileSize := s.knownFileSize
	if fileSize <= 0 {
		fileSize = e.Size()
	}
	
	// If size is still 0, try to get it by opening the file reader
	if fileSize <= 0 {
		testReader, err := e.Open(ctx)
		if err == nil {
			// The reader interface has Length() - check if we can access it
			if lr, ok := testReader.(interface{ Length() int64 }); ok {
				fileSize = lr.Length()
				log.Printf("agent: kopia VHDX got size from reader.Length(): %d bytes (%.2f GB)", 
					fileSize, float64(fileSize)/(1024*1024*1024))
			}
			testReader.Close()
		}
	}
	
	log.Printf("agent: kopia VHDX WriteFile: path=%s knownSize=%d entrySize=%d actualSize=%d", 
		s.targetPath, s.knownFileSize, e.Size(), fileSize)
	
	// For small files (< 64MB) or unknown size, use simple sequential copy
	if fileSize < 64*1024*1024 {
		log.Printf("agent: kopia VHDX using sequential restore (fileSize=%d < 64MB threshold)", fileSize)
		return s.writeFileSequential(ctx, e)
	}

	// For large files, use parallel segment restore
	log.Printf("agent: kopia VHDX using parallel segment restore (fileSize=%d = %.2f GB)", 
		fileSize, float64(fileSize)/(1024*1024*1024))
	return s.writeFileParallel(ctx, e, fileSize)
}

// writeFileSequential performs a simple sequential copy for small files
func (s *singleFileRestoreOutput) writeFileSequential(ctx context.Context, e kopiafs.File) error {
	reader, err := e.Open(ctx)
	if err != nil {
		return fmt.Errorf("open source file: %w", err)
	}
	defer reader.Close()

	outFile, err := os.Create(s.targetPath)
	if err != nil {
		return fmt.Errorf("create target file: %w", err)
	}
	defer outFile.Close()

	buf := make([]byte, 4*1024*1024) // 4MB buffer
	var totalWritten int64
	for {
		n, readErr := reader.Read(buf)
		if n > 0 {
			written, writeErr := outFile.Write(buf[:n])
			if writeErr != nil {
				return fmt.Errorf("write target file: %w", writeErr)
			}
			totalWritten += int64(written)
		}
		if readErr == io.EOF {
			break
		}
		if readErr != nil {
			return fmt.Errorf("read source file: %w", readErr)
		}
	}

	log.Printf("agent: kopia VHDX restore sequential complete: %d bytes to %s", totalWritten, s.targetPath)
	return nil
}

// writeFileParallel performs parallel segment download for large files
func (s *singleFileRestoreOutput) writeFileParallel(ctx context.Context, e kopiafs.File, fileSize int64) error {
	
	// Determine number of parallel workers based on file size and CPU count
	// Use more workers for very large files, but cap at reasonable limits
	numWorkers := runtime.NumCPU()
	if numWorkers < 4 {
		numWorkers = 4
	}
	if numWorkers > 32 {
		numWorkers = 32
	}
	
	// For really large files, use more workers
	if fileSize > 10*1024*1024*1024 { // > 10GB
		numWorkers = numWorkers * 2
		if numWorkers > 64 {
			numWorkers = 64
		}
	}

	// Minimum segment size of 32MB to avoid too many small segments
	minSegmentSize := int64(32 * 1024 * 1024)
	segmentSize := fileSize / int64(numWorkers)
	if segmentSize < minSegmentSize {
		segmentSize = minSegmentSize
		numWorkers = int(fileSize / segmentSize)
		if numWorkers < 1 {
			numWorkers = 1
		}
	}

	log.Printf("agent: kopia VHDX parallel restore starting: size=%d (%.2f GB) workers=%d segment_size=%d (%.2f MB)",
		fileSize, float64(fileSize)/(1024*1024*1024), numWorkers, segmentSize, float64(segmentSize)/(1024*1024))

	startTime := time.Now()

	// Pre-allocate the target file to the correct size for efficient parallel writes
	outFile, err := os.Create(s.targetPath)
	if err != nil {
		return fmt.Errorf("create target file: %w", err)
	}
	
	// Truncate/extend file to final size for sparse file optimization
	if err := outFile.Truncate(fileSize); err != nil {
		outFile.Close()
		return fmt.Errorf("preallocate target file: %w", err)
	}
	outFile.Close()

	// Progress tracking
	var bytesWritten int64
	var lastProgressLog int64
	progressMu := sync.Mutex{}

	var lastServerReport time.Time
	reportProgress := func(n int64) {
		progressMu.Lock()
		bytesWritten += n
		current := bytesWritten
		progressMu.Unlock()
		
		now := time.Now()
		
		// Log progress every 256MB
		if current-lastProgressLog >= 256*1024*1024 {
			lastProgressLog = current
			pct := float64(current) / float64(fileSize) * 100
			log.Printf("agent: kopia VHDX parallel restore progress: %d / %d bytes (%.1f%%)",
				current, fileSize, pct)
		}
		
		// Report to server every 2 seconds
		if s.serverProgressFn != nil && now.Sub(lastServerReport) >= 2*time.Second {
			lastServerReport = now
			elapsed := now.Sub(startTime).Seconds()
			var speedBps float64
			if elapsed > 0 {
				speedBps = float64(current) / elapsed
			}
			s.serverProgressFn(current, fileSize, speedBps)
		}
	}

	// Error collection
	var errMu sync.Mutex
	var firstErr error

	// Create worker goroutines
	var wg sync.WaitGroup
	
	for i := 0; i < numWorkers; i++ {
		startOffset := int64(i) * segmentSize
		endOffset := startOffset + segmentSize
		if i == numWorkers-1 {
			endOffset = fileSize // Last worker takes any remainder
		}
		if startOffset >= fileSize {
			break
		}
		
		wg.Add(1)
		go func(workerID int, start, end int64) {
			defer wg.Done()
			workerStart := time.Now()
			segmentBytes := end - start
			log.Printf("agent: kopia parallel worker %d starting: offset=%d-%d size=%d (%.1f MB)", 
				workerID, start, end, segmentBytes, float64(segmentBytes)/(1024*1024))
			
			// Check for cancellation or prior error
			select {
			case <-ctx.Done():
				return
			default:
			}
			
			errMu.Lock()
			if firstErr != nil {
				errMu.Unlock()
				return
			}
			errMu.Unlock()

			// Open a new reader for this segment (each reader is independent)
			reader, err := e.Open(ctx)
			if err != nil {
				errMu.Lock()
				if firstErr == nil {
					firstErr = fmt.Errorf("worker %d: open source: %w", workerID, err)
				}
				errMu.Unlock()
				return
			}
			defer reader.Close()

			// Seek to start position
			if _, err := reader.Seek(start, io.SeekStart); err != nil {
				errMu.Lock()
				if firstErr == nil {
					firstErr = fmt.Errorf("worker %d: seek to %d: %w", workerID, start, err)
				}
				errMu.Unlock()
				return
			}

			// Open target file for this segment
			f, err := os.OpenFile(s.targetPath, os.O_WRONLY, 0644)
			if err != nil {
				errMu.Lock()
				if firstErr == nil {
					firstErr = fmt.Errorf("worker %d: open target: %w", workerID, err)
				}
				errMu.Unlock()
				return
			}
			defer f.Close()

			// Seek to write position
			if _, err := f.Seek(start, io.SeekStart); err != nil {
				errMu.Lock()
				if firstErr == nil {
					firstErr = fmt.Errorf("worker %d: seek target to %d: %w", workerID, start, err)
				}
				errMu.Unlock()
				return
			}

			// Read and write this segment
			buf := make([]byte, 4*1024*1024) // 4MB buffer per worker
			bytesToRead := end - start
			var segmentWritten int64

			for segmentWritten < bytesToRead {
				// Check for cancellation
				select {
				case <-ctx.Done():
					return
				default:
				}
				
				// Check for error from other workers
				errMu.Lock()
				if firstErr != nil {
					errMu.Unlock()
					return
				}
				errMu.Unlock()

				toRead := int64(len(buf))
				remaining := bytesToRead - segmentWritten
				if toRead > remaining {
					toRead = remaining
				}

				n, readErr := reader.Read(buf[:toRead])
				if n > 0 {
					written, writeErr := f.Write(buf[:n])
					if writeErr != nil {
						errMu.Lock()
						if firstErr == nil {
							firstErr = fmt.Errorf("worker %d: write at %d: %w", workerID, start+segmentWritten, writeErr)
						}
						errMu.Unlock()
						return
					}
					segmentWritten += int64(written)
					reportProgress(int64(written))
				}
				if readErr == io.EOF {
					break
				}
				if readErr != nil {
					errMu.Lock()
					if firstErr == nil {
						firstErr = fmt.Errorf("worker %d: read at %d: %w", workerID, start+segmentWritten, readErr)
					}
					errMu.Unlock()
					return
				}
			}
			
			// Worker completion logging
			workerDuration := time.Since(workerStart)
			workerSpeed := float64(segmentWritten) / workerDuration.Seconds() / (1024 * 1024)
			log.Printf("agent: kopia parallel worker %d complete: %d bytes in %.1fs (%.1f MB/s)", 
				workerID, segmentWritten, workerDuration.Seconds(), workerSpeed)
		}(i, startOffset, endOffset)
	}

	// Wait for all workers to complete
	wg.Wait()

	if firstErr != nil {
		return fmt.Errorf("parallel restore failed: %w", firstErr)
	}

	elapsed := time.Since(startTime)
	speedMBps := float64(bytesWritten) / elapsed.Seconds() / (1024 * 1024)
	speedGbps := speedMBps * 8 / 1000 // Convert MB/s to Gbps
	
	log.Printf("agent: kopia VHDX parallel restore complete: %d bytes (%.2f GB) to %s using %d workers in %.1fs (%.1f MB/s = %.2f Gbps)",
		bytesWritten, float64(bytesWritten)/(1024*1024*1024), s.targetPath, numWorkers, elapsed.Seconds(), speedMBps, speedGbps)
	return nil
}

// FileExists checks if the target file already exists
func (s *singleFileRestoreOutput) FileExists(ctx context.Context, relativePath string, e kopiafs.File) bool {
	_, err := os.Stat(s.targetPath)
	return err == nil
}

// CreateSymlink is a no-op for single file restores
func (s *singleFileRestoreOutput) CreateSymlink(ctx context.Context, relativePath string, e kopiafs.Symlink) error {
	return nil
}

// SymlinkExists returns false - we don't have symlinks in single file restore
func (s *singleFileRestoreOutput) SymlinkExists(ctx context.Context, relativePath string, e kopiafs.Symlink) bool {
	return false
}

// Close is a no-op for single file restores
func (s *singleFileRestoreOutput) Close(ctx context.Context) error {
	return nil
}

// bufferedWriter wraps an io.Writer with a larger buffer for better sequential write performance
type bufferedWriter struct {
	w      io.Writer
	buf    []byte
	n      int
	bufLen int
}

func newBufferedWriter(w io.Writer, bufSize int) *bufferedWriter {
	return &bufferedWriter{
		w:      w,
		buf:    make([]byte, bufSize),
		bufLen: bufSize,
	}
}

func (b *bufferedWriter) Write(p []byte) (int, error) {
	totalWritten := 0
	for len(p) > 0 {
		// How much can fit in the buffer?
		canFit := b.bufLen - b.n
		if canFit == 0 {
			// Buffer full, flush it
			if err := b.Flush(); err != nil {
				return totalWritten, err
			}
			canFit = b.bufLen
		}

		// Copy to buffer
		toCopy := len(p)
		if toCopy > canFit {
			toCopy = canFit
		}
		copy(b.buf[b.n:], p[:toCopy])
		b.n += toCopy
		p = p[toCopy:]
		totalWritten += toCopy
	}
	return totalWritten, nil
}

func (b *bufferedWriter) Flush() error {
	if b.n == 0 {
		return nil
	}
	_, err := b.w.Write(b.buf[:b.n])
	b.n = 0
	return err
}

// kopiaMaintenance runs maintenance in the requested mode (quick/full).
func (r *Runner) kopiaMaintenance(ctx context.Context, run *NextRunResponse, mode string) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	password := opts.password()
	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return fmt.Errorf("kopia: open for maintenance: %w", err)
	}
	defer rep.Close(ctx)

	dr, ok := rep.(repo.DirectRepository)
	if !ok {
		return fmt.Errorf("kopia: repo does not support direct maintenance")
	}

	maintMode := maintenance.ModeQuick
	if strings.EqualFold(mode, "full") {
		maintMode = maintenance.ModeFull
	}

	return repo.DirectWriteSession(ctx, dr, repo.WriteSessionOptions{Purpose: "maintenance"}, func(wctx context.Context, dw repo.DirectRepositoryWriter) error {
		return maintenance.RunExclusive(wctx, dw, maintMode, true, func(ctx context.Context, rp maintenance.RunParameters) error {
			return maintenance.Run(ctx, rp, maintenance.SafetyParameters{})
		})
	})
}

// kopiaRestoreVHDX restores a single VHDX disk from its manifest to a local file.
// Unlike kopiaRestoreWithProgress which restores directory trees, this handles
// single-file stream entries (VHDXs) that were backed up during Hyper-V backup.
func (r *Runner) kopiaRestoreVHDX(ctx context.Context, run *NextRunResponse, manifestID string, targetFilePath string, diskName string, runID int64) error {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := filepath.Join(r.cfg.RunDir, "kopia", fmt.Sprintf("job_%d.config", run.JobID))
	password := opts.password()

	log.Printf("agent: kopia VHDX restore opening repo at %s for job %d, manifest %s", repoPath, run.JobID, manifestID)

	// Check if repo config exists
	if _, statErr := os.Stat(repoPath); os.IsNotExist(statErr) {
		// Repo config doesn't exist - need to connect to existing repo in S3
		log.Printf("agent: kopia repo config not found at %s, connecting to remote repo", repoPath)
		r.pushEvents(runID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_RESTORE_CONNECTING",
			ParamsJSON: map[string]any{
				"bucket": opts.bucket,
				"prefix": opts.prefix,
			},
		})

		st, stErr := opts.storage(ctx)
		if stErr != nil {
			return fmt.Errorf("kopia: storage init for VHDX restore: %w", stErr)
		}

		// Connect to existing repo
		if connErr := repo.Connect(ctx, repoPath, st, password, nil); connErr != nil {
			return fmt.Errorf("kopia: connect to repo for VHDX restore failed: %w", connErr)
		}
		log.Printf("agent: kopia connected to remote repo, config saved at %s", repoPath)
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return fmt.Errorf("kopia: open repo for VHDX restore: %w", err)
	}
	defer rep.Close(ctx)

	// Create target directory if needed
	targetDir := filepath.Dir(targetFilePath)
	if err := os.MkdirAll(targetDir, 0755); err != nil {
		return fmt.Errorf("kopia: mkdir target for VHDX: %w", err)
	}

	log.Printf("agent: kopia VHDX restore loading snapshot %s", manifestID)
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_RESTORE_LOADING",
		ParamsJSON: map[string]any{
			"manifest_id": manifestID,
			"disk_name":   diskName,
		},
	})

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return fmt.Errorf("kopia: load VHDX snapshot: %w", err)
	}

	log.Printf("agent: kopia VHDX restore snapshot loaded, source=%s start_time=%v", man.Source.Path, man.StartTime)

	// For VHDX backups, the snapshot contains the disk as the root entry
	// We need to restore it as a single file
	rootEntry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	if rootEntry == nil {
		return fmt.Errorf("kopia: failed to create root entry from VHDX snapshot")
	}

	log.Printf("agent: kopia VHDX restore entry: name=%s isDir=%v mode=%v size=%d",
		rootEntry.Name(), rootEntry.IsDir(), rootEntry.Mode(), man.RootEntry.FileSize)

	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "HYPERV_RESTORE_DISK_PROGRESS",
		ParamsJSON: map[string]any{
			"disk_name":   diskName,
			"bytes_total": man.RootEntry.FileSize,
			"status":      "starting",
		},
	})

	log.Printf("agent: kopia VHDX restore starting to %s", targetFilePath)
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "KOPIA_RESTORE_STARTED",
		ParamsJSON: map[string]any{
			"manifest_id": manifestID,
			"target_path": targetFilePath,
			"disk_name":   diskName,
		},
	})

	// Use Kopia's parallel restore for both single files and directories
	// This provides much better performance for large files by fetching content blocks in parallel
	{
		progressCounter := &restoreProgressCounter{
			runner: r,
			runID:  runID,
		}

		// Determine parallel factor based on available CPUs (min 4, max 16)
		parallelFactor := runtime.NumCPU()
		if parallelFactor < 4 {
			parallelFactor = 4
		}
		if parallelFactor > 16 {
			parallelFactor = 16
		}

		if !rootEntry.IsDir() {
			// For single-file entries, we need to wrap in a virtual directory
			// so that restore.Entry works correctly
			knownSize := man.RootEntry.FileSize
			log.Printf("agent: kopia VHDX restore using parallel restore with %d workers, file size=%d bytes (%.2f GB)", 
				parallelFactor, knownSize, float64(knownSize)/(1024*1024*1024))

			// Create a single-file output that writes directly to targetFilePath
			// Include server progress callback to report to the UI
			restoreStartTime := time.Now()
			singleFileOut := &singleFileRestoreOutput{
				targetPath:       targetFilePath,
				knownFileSize:    knownSize,
				progressCallback: progressCounter.onProgress,
				serverProgressFn: func(bytesWritten, bytesTotal int64, speedBps float64) {
					var progressPct float64
					if bytesTotal > 0 {
						progressPct = float64(bytesWritten) / float64(bytesTotal) * 100.0
						if progressPct > 99.9 {
							progressPct = 99.9
						}
					}
					
					// Calculate ETA
					var etaSeconds int64
					if speedBps > 0 && bytesTotal > bytesWritten {
						remaining := bytesTotal - bytesWritten
						etaSeconds = int64(float64(remaining) / speedBps)
					}
					
					elapsed := time.Since(restoreStartTime)
					speedMBps := float64(bytesWritten) / elapsed.Seconds() / (1024 * 1024)
					
					_ = r.client.UpdateRun(RunUpdate{
						RunID:            runID,
						Status:           "running",
						ProgressPct:      progressPct,
						BytesTransferred: Int64Ptr(bytesWritten),
						BytesTotal:       Int64Ptr(bytesTotal),
						SpeedBytesPerSec: int64(speedBps),
						EtaSeconds:       etaSeconds,
						CurrentItem:      fmt.Sprintf("Restoring %s (%.1f MB/s)", diskName, speedMBps),
					})
				},
			}

			stats, err := restore.Entry(ctx, rep, singleFileOut, rootEntry, restore.Options{
				ProgressCallback: progressCounter.onProgress,
				Parallel:         parallelFactor,
				RestoreDirEntryAtDepth: math.MaxInt32,
			})

			if err != nil {
				log.Printf("agent: kopia VHDX parallel restore failed: %v", err)
				return fmt.Errorf("kopia: parallel VHDX restore: %w", err)
			}

			log.Printf("agent: kopia VHDX restore completed: files=%d bytes=%d skipped=%d errors=%d parallel=%d",
				stats.RestoredFileCount, stats.RestoredTotalFileSize,
				stats.SkippedCount, stats.IgnoredErrorCount, parallelFactor)

			r.pushEvents(runID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "KOPIA_RESTORE_STATS",
				ParamsJSON: map[string]any{
					"disk_name":      diskName,
					"bytes_restored": stats.RestoredTotalFileSize,
					"files_restored": stats.RestoredFileCount,
				},
			})
		} else {
			// For directory entries, use the standard restore path with FilesystemOutput
			log.Printf("agent: kopia VHDX restore (directory) using parallel restore with %d workers", parallelFactor)

			fsOut := &restore.FilesystemOutput{
				TargetPath:           targetDir,
				OverwriteDirectories: true,
				OverwriteFiles:       true,
				OverwriteSymlinks:    true,
				WriteFilesAtomically: false,
				SkipOwners:           true,
				SkipPermissions:      true,
			}
			out := &fullRestoreOutput{fsOut}

			stats, err := restore.Entry(ctx, rep, out, rootEntry, restore.Options{
				ProgressCallback: progressCounter.onProgress,
				Parallel:         parallelFactor,
				RestoreDirEntryAtDepth: math.MaxInt32,
			})

			if err != nil {
				return err
			}

			log.Printf("agent: kopia VHDX restore completed: files=%d dirs=%d bytes=%d skipped=%d errors=%d parallel=%d",
				stats.RestoredFileCount, stats.RestoredDirCount, stats.RestoredTotalFileSize,
				stats.SkippedCount, stats.IgnoredErrorCount, parallelFactor)

			// If the restored file has a different name, rename it to the expected name
			restoredName := filepath.Base(man.Source.Path)
			expectedName := filepath.Base(targetFilePath)
			if restoredName != expectedName {
				actualPath := filepath.Join(targetDir, restoredName)
				if _, statErr := os.Stat(actualPath); statErr == nil {
					log.Printf("agent: kopia VHDX renaming %s to %s", actualPath, targetFilePath)
					if renameErr := os.Rename(actualPath, targetFilePath); renameErr != nil {
						log.Printf("agent: kopia VHDX rename failed: %v", renameErr)
					}
				}
			}

			r.pushEvents(runID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "KOPIA_RESTORE_STATS",
				ParamsJSON: map[string]any{
					"disk_name":      diskName,
					"bytes_restored": stats.RestoredTotalFileSize,
					"files_restored": stats.RestoredFileCount,
				},
			})
		}
	}

	return nil
}

// kopiaMount is a placeholder for mounting snapshots; not implemented yet.
func (r *Runner) kopiaMount(ctx context.Context, run *NextRunResponse, manifestID, targetPath string) error {
	return fmt.Errorf("mount not implemented")
}

type kopiaOptions struct {
	destType string
	endpoint string
	region   string
	bucket   string
	prefix   string
	localDir string
	access   string
	secret   string
	host     string
	username string
}

func kopiaOptionsFromRun(cfg *AgentConfig, run *NextRunResponse) kopiaOptions {
	rawEndpoint := firstNonEmpty(run.DestEndpoint, cfg.DestEndpoint)
	endpoint := normalizeEndpoint(rawEndpoint) // keep scheme if present
	endpointHost := kopiaEndpointHost(endpoint)
	region := firstNonEmpty(run.DestRegion, cfg.DestRegion)
	prefix := normalizeKopiaPrefix(run.DestPrefix)
	log.Printf("agent: kopia opts endpoint raw=%q cfg_default=%q normalized=%q host_only=%q len=%d region=%q repo_dir=%s", run.DestEndpoint, cfg.DestEndpoint, endpoint, endpointHost, len(endpointHost), region, cfg.RunDir)
	return kopiaOptions{
		destType: run.DestType,
		endpoint: endpoint, // pass through full normalized endpoint (with scheme) to Kopia s3
		region:   region,
		bucket:   run.DestBucketName,
		prefix:   prefix,
		localDir: run.DestLocalPath,
		access:   run.DestAccessKey,
		secret:   run.DestSecretKey,
		host:     getHostname(),
		username: "agent",
	}
}

func (o kopiaOptions) storage(ctx context.Context) (blob.Storage, error) {
	// MinIO SDK expects host-only endpoint (no scheme). Extract host and determine TLS from scheme.
	endpointHost := o.endpoint
	doNotUseTLS := false // default to HTTPS
	if o.endpoint != "" {
		if u, err := url.Parse(o.endpoint); err == nil {
			log.Printf("agent: kopia endpoint parse scheme=%q host=%q path=%q rawPath=%q", u.Scheme, u.Host, u.Path, u.RawPath)
			if u.Host != "" {
				endpointHost = u.Host              // extract host-only (e.g., "s3.example.com" or "s3.example.com:9000")
				doNotUseTLS = (u.Scheme == "http") // only disable TLS if explicitly http://
			}
		} else {
			log.Printf("agent: kopia endpoint parse error: %v", err)
		}
	}
	log.Printf("agent: kopia s3 init endpoint_host=%q doNotUseTLS=%v bucket=%q prefix=%q", endpointHost, doNotUseTLS, o.bucket, o.prefix)
	switch strings.ToLower(strings.TrimSpace(o.destType)) {
	case "local":
		dir := o.localDir
		if dir == "" {
			dir = filepath.Join(os.TempDir(), "kopia-local-repo")
		}
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return nil, err
		}
		return filesystem.New(ctx, &filesystem.Options{
			Path: dir,
		}, true)
	default:
		return s3.New(ctx, &s3.Options{
			BucketName:      o.bucket,
			Prefix:          o.prefix,
			Endpoint:        endpointHost, // MinIO SDK requires host-only (no scheme)
			AccessKeyID:     o.access,
			SecretAccessKey: o.secret,
			Region:          o.region,
			DoNotUseTLS:     doNotUseTLS, // false = use HTTPS, true = use HTTP
			DoNotVerifyTLS:  true,
		})
	}
}

func (o kopiaOptions) password() string {
	// Derive a deterministic repo password per destination; keeps config headless.
	return fmt.Sprintf("%s:%s:%s", o.bucket, o.access, o.secret)
}

// kopiaEndpointHost ensures Kopia receives a host-only endpoint (no scheme/path).
func kopiaEndpointHost(raw string) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return ""
	}
	if u, err := url.Parse(raw); err == nil {
		if u.Host != "" {
			return u.Host
		}
	}
	if parts := strings.SplitN(raw, "/", 2); len(parts) > 0 && parts[0] != "" {
		return parts[0]
	}
	return raw
}

func getHostname() string {
	h, err := os.Hostname()
	if err != nil || h == "" {
		return runtime.GOOS
	}
	return h
}

// parsePolicyOverrides extracts parallel uploads and compression from policy_json and compression_enabled flag.
// If compressionEnabled is true and no explicit compressor is set in policyJSON, defaults to "zstd-default".
func parsePolicyOverrides(policyJSON map[string]any, compressionEnabled bool) (parallel int, compressor string) {
	if policyJSON == nil {
		policyJSON = make(map[string]any)
	}
	if v, ok := policyJSON["parallel_uploads"]; ok {
		switch t := v.(type) {
		case float64:
			parallel = int(t)
		case int:
			parallel = t
		case json.Number:
			if i, err := t.Int64(); err == nil {
				parallel = int(i)
			}
		}
	}
	if parallel <= 0 {
		parallel = 16
	}
	if v, ok := policyJSON["compression"]; ok {
		if s, ok := v.(string); ok {
			compressor = strings.TrimSpace(s)
		}
	}
	// If compression_enabled flag is set but no explicit compressor, use Kopia default
	if compressionEnabled && compressor == "" {
		compressor = "zstd-default"
	}
	// If compressor is explicitly "none", treat as no compression
	if strings.ToLower(compressor) == "none" {
		compressor = ""
	}
	return parallel, compressor
}

// normalizeKopiaPrefix ensures S3 prefixes behave as folder-like keys (trailing slash).
func normalizeKopiaPrefix(p string) string {
	p = strings.TrimSpace(p)
	p = strings.TrimPrefix(p, "/")
	if p == "" {
		return ""
	}
	if !strings.HasSuffix(p, "/") {
		p = p + "/"
	}
	return p
}

// isDebug returns true if policy_json requested detailed Kopia logging events.
func isDebug(policyJSON map[string]any) bool {
	if policyJSON == nil {
		return false
	}
	if v, ok := policyJSON["debug_logs"]; ok {
		if b, ok := v.(bool); ok && b {
			return true
		}
	}
	return false
}

// latestSnapshotID returns the newest snapshot manifest ID for the given source.
func latestSnapshotID(ctx context.Context, rep repo.Repository, src snapshot.SourceInfo) (string, error) {
	snaps, err := snapshot.ListSnapshots(ctx, rep, src)
	if err != nil {
		return "", err
	}
	var newest *snapshot.Manifest
	for _, s := range snaps {
		if newest == nil || s.StartTime.After(newest.StartTime) {
			newest = s
		}
	}
	if newest == nil {
		return "", nil
	}
	return string(newest.ID), nil
}

// estimateSourceSize walks the source path to estimate total bytes/files for progress denominators.
func estimateSourceSize(root string) (int64, int64) {
	var totalBytes int64
	var totalFiles int64

	filepath.WalkDir(root, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			// Ignore individual errors to keep estimation lightweight.
			return nil
		}
		if d.IsDir() {
			return nil
		}
		totalFiles++
		if info, ierr := d.Info(); ierr == nil && info != nil {
			totalBytes += info.Size()
		}
		return nil
	})

	return totalBytes, totalFiles
}

// hasAtLeastOneEntry returns true if the path contains at least one file or directory.
func hasAtLeastOneEntry(root string) (bool, error) {
	found := false
	err := filepath.WalkDir(root, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		if path == root {
			return nil
		}
		found = true
		return fs.SkipDir // we only need to know something exists
	})
	return found, err
}
