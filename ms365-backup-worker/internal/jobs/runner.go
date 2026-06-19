package jobs

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type Runner struct {
	cfg      *config.Config
	client   *api.Client
	repoPool *kopia.Pool
}

func NewRunner(cfg *config.Config, client *api.Client, repoPool *kopia.Pool) *Runner {
	return &Runner{cfg: cfg, client: client, repoPool: repoPool}
}

func (r *Runner) Run(ctx context.Context, job *api.RunJob) error {
	if job == nil {
		return nil
	}
	if job.JobType == "restore" {
		restoreRunner := NewRestoreRunner(r.cfg, r.client, r.repoPool)
		return restoreRunner.Run(ctx, job)
	}
	runStart := time.Now()
	defer func() { _ = r.client.FlushRunLogs(ctx, job.RunID) }()
	r.client.RunLogf(ctx, job.RunID, "info", "starting run %s resource=%s", job.RunID, job.PhysicalKey)
	log.Printf("starting run %s resource=%s", job.RunID, job.PhysicalKey)

	runDir := filepath.Join(r.cfg.Worker.RunDir, job.RunID)
	_ = os.MkdirAll(runDir, 0o755)
	defer os.RemoveAll(runDir)

	var graphItemsDone, graphItemsTotal int
	var graphBytesTotal int64
	var graphLastPercent float64 = 1

	progressStop := r.client.StartProgressHeartbeat(ctx, job.RunID, r.cfg.ProgressHeartbeat(), func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:       job.RunID,
			Phase:       "graph_sync",
			Percent:     graphLastPercent,
			ItemsDone:   graphItemsDone,
			ItemsTotal:  graphItemsTotal,
			BytesHashed: graphBytesTotal,
			Message:     "heartbeat",
		}
	})
	defer progressStop()

	_ = r.client.Progress(ctx, api.ProgressUpdate{
		RunID:   job.RunID,
		Phase:   "graph_sync",
		Percent: 1,
		Message: "Syncing from Microsoft Graph",
	})

	storage := kopia.StorageOptions{
		Endpoint:     job.DestEndpoint,
		Region:       job.DestRegion,
		Bucket:       job.DestBucket,
		Prefix:       job.DestPrefix,
		AccessKey:    job.DestAccessKey,
		SecretKey:    job.DestSecretKey,
		RepoPassword: job.RepoPassword,
	}

	overlay := graphfs.NewOverlayBuilder()
	if job.IncrementalEnabled && job.PreviousManifest != "" {
		priorStart := time.Now()
		_ = r.client.Progress(ctx, api.ProgressUpdate{
			RunID:   job.RunID,
			Phase:   "prior_snapshot",
			Percent: 5,
			Message: "Loading prior snapshot metadata",
		})
		graphLastPercent = 5
		priorRoot, err := kopia.PriorSnapshotRoot(ctx, r.repoPool, storage, job.PreviousManifest)
		if err != nil {
			log.Printf("run %s prior snapshot warning: %v", job.RunID, err)
		} else if err := overlay.MergePrior(ctx, priorRoot, ""); err != nil {
			log.Printf("run %s prior merge warning: %v", job.RunID, err)
		} else {
			log.Printf("run %s merged prior manifest %s (%d entries) in %dms", job.RunID, job.PreviousManifest, overlay.EntryCount(), time.Since(priorStart).Milliseconds())
		}
	}

	gc := graph.NewClient(job.GraphToken, job.GraphRegion, graph.ClientOptions{
		MaxRetries:       r.cfg.Graph.MaxRetries,
		RetryBaseDelayMs: r.cfg.Graph.RetryBaseDelayMs,
		MaxConcurrency:   r.cfg.Worker.GraphParallelRequests,
		AdaptiveLimit:    r.cfg.Graph.AdaptiveEnabled(),
	})
	stopTokenRefresh := bindGraphTokenRefresh(ctx, r.cfg, r.client, gc, job.RunID)
	defer stopTokenRefresh()

	graphStart := time.Now()
	wl := &graphsync.WorkloadRunner{
		Client:           gc,
		Job:              job,
		Parallel:         r.cfg.Worker.GraphParallelRequests,
		FolderParallel:   r.cfg.Worker.GraphFolderParallel,
		DriveParallel:    r.cfg.Worker.GraphSharePointDriveParallel,
		Overlay:          overlay,
		UseBatchFallback: r.cfg.Graph.UseBatchFallback,
		RunLog: func(level, message string) {
			r.client.RunLogf(ctx, job.RunID, level, "%s", message)
		},
		OnProgress: func(phase string, done, total int, bytes int64) {
			graphItemsDone = done
			graphItemsTotal = total
			graphBytesTotal = bytes
			pct := 10.0
			if total > 0 {
				pct = 10 + (float64(done)/float64(total))*25
			}
			graphLastPercent = pct
			_ = r.client.Progress(ctx, api.ProgressUpdate{
				RunID:         job.RunID,
				Phase:         "graph_sync",
				Percent:       pct,
				ItemsDone:     done,
				ItemsTotal:    total,
				BytesHashed:   bytes,
				Message:       fmt.Sprintf("Graph sync: %s", phase),
			})
		},
	}
	wlRes, err := wl.Run(ctx)
	graphElapsed := time.Since(graphStart)
	if err != nil {
		r.client.RunLogf(ctx, job.RunID, "error", "graph_sync failed: %v", err)
		_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		return err
	}
	r.client.RunLogf(ctx, job.RunID, "info", "run %s graph_sync completed in %dms items=%d graph_429=%v", job.RunID, graphElapsed.Milliseconds(), wlRes.FileCount, wlRes.Stats["graph_429_hits"])
	log.Printf("run %s graph_sync completed in %dms items=%d graph_429=%v", job.RunID, graphElapsed.Milliseconds(), wlRes.FileCount, wlRes.Stats["graph_429_hits"])
	if odStats, ok := wlRes.Stats["onedrive"].(map[string]int); ok {
		r.client.RunLogf(ctx, job.RunID, "info", "onedrive sync stats: %+v", odStats)
	}
	if err := r.ensureOneDriveRootFiles(ctx, gc, job, overlay); err != nil {
		r.client.RunLogf(ctx, job.RunID, "error", "onedrive root guard: %v", err)
		_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		return err
	}

	if wlRes.FileCount == 0 && job.PreviousManifest == "" {
		log.Printf("run %s completed no_changes (empty first run) total=%dms", job.RunID, time.Since(runStart).Milliseconds())
		_ = r.client.Complete(ctx, api.CompleteUpdate{
			RunID:      job.RunID,
			ManifestID: "",
			StatsJSON:  `{"status":"no_changes"}`,
		})
		return nil
	}

	if job.PreviousManifest != "" && !overlay.HasChanges() {
		stats, _ := json.Marshal(map[string]any{
			"status":         "no_changes",
			"manifest_id":    job.PreviousManifest,
			"workloads":      wlRes.Stats,
			"delta_states":   wlRes.DeltaStates,
			"graph_429_hits": wlRes.Stats["graph_429_hits"],
			"physical_key":   job.PhysicalKey,
			"graph_sync_ms":  graphElapsed.Milliseconds(),
		})
		log.Printf("run %s completed no_changes (incremental unchanged) total=%dms", job.RunID, time.Since(runStart).Milliseconds())
		return r.client.Complete(ctx, api.CompleteUpdate{
			RunID:      job.RunID,
			ManifestID: job.PreviousManifest,
			StatsJSON:  string(stats),
		})
	}

	_ = r.client.Progress(ctx, api.ProgressUpdate{
		RunID:       job.RunID,
		Phase:       "kopia_upload",
		Percent:     40,
		ItemsTotal:  wlRes.FileCount,
		ItemsDone:   int(wlRes.ItemsDone),
		BytesHashed: wlRes.BytesTotal,
		Message:     "Uploading snapshot to Kopia repository",
	})

	if err := kopia.EnsureRunDir(r.cfg.Worker.RunDir); err != nil {
		_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		return err
	}

	sourcePath := job.KopiaSourcePath
	if sourcePath == "" {
		sourcePath = filepath.Join("/ms365", job.PhysicalKey)
	}
	tree := overlay.Build("snapshot")
	if job.GraphID != "" && graphsync.JobIncludesOneDrive(job) {
		if err := graphsync.VerifyOneDriveOverlayTree(ctx, overlay, tree, job.AzureTenantID, job.GraphID); err != nil {
			r.client.RunLogf(ctx, job.RunID, "error", "onedrive tree verify: %v", err)
			_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
			return err
		}
	}

	uploadStop := r.client.StartProgressHeartbeat(ctx, job.RunID, r.cfg.ProgressHeartbeat(), func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:       job.RunID,
			Phase:       "kopia_upload",
			ItemsDone:   graphItemsDone,
			ItemsTotal:  graphItemsTotal,
			BytesHashed: graphBytesTotal,
			Message:     "Upload in progress",
		}
	})
	defer uploadStop()

	snapshotStart := time.Now()
	snapRes, err := r.repoPool.Snapshot(ctx, kopia.SnapshotRequest{
		Storage:            storage,
		SourcePath:         sourcePath,
		Host:               r.cfg.Worker.Hostname,
		Username:           "ms365",
		Entry:              tree,
		Parallel:           r.cfg.Kopia.ParallelUploads,
		Compressor:         r.cfg.Kopia.Compressor,
		MaxPackSizeMiB:     r.cfg.Kopia.MaxPackSizeMiB,
		CheckpointInterval: r.cfg.Kopia.CheckpointInterval(),
		OnProgress: func(p kopia.ProgressCounter) {
			done := int(p.FilesDone.Load())
			total := wlRes.FileCount
			if graphItemsTotal > total {
				total = graphItemsTotal
			}
			pct := 40.0
			if total > 0 {
				pct = 40 + (float64(done)/float64(total))*55
			}
			_ = r.client.Progress(ctx, api.ProgressUpdate{
				RunID:         job.RunID,
				Phase:         "kopia_upload",
				Percent:       pct,
				BytesHashed:   p.BytesHashed.Load(),
				BytesUploaded: p.BytesUploaded.Load(),
				ItemsDone:     done,
				ItemsTotal:    total,
			})
		},
	})
	snapshotElapsed := time.Since(snapshotStart)
	if err != nil {
		_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		return err
	}
	log.Printf("run %s kopia_snapshot completed in %dms manifest=%s", job.RunID, snapshotElapsed.Milliseconds(), snapRes.ManifestID)
	r.client.RunLogf(ctx, job.RunID, "info", "run %s kopia_snapshot completed in %dms manifest=%s", job.RunID, snapshotElapsed.Milliseconds(), snapRes.ManifestID)

	stats, _ := json.Marshal(map[string]any{
		"manifest_id":    snapRes.ManifestID,
		"bytes_hashed":   snapRes.BytesHashed,
		"bytes_uploaded": snapRes.BytesUploaded,
		"files":          snapRes.FilesDone,
		"workloads":      wlRes.Stats,
		"delta_states":   wlRes.DeltaStates,
		"graph_429_hits": wlRes.Stats["graph_429_hits"],
		"physical_key":   job.PhysicalKey,
		"graph_sync_ms":  graphElapsed.Milliseconds(),
		"kopia_snapshot_ms": snapshotElapsed.Milliseconds(),
	})
	log.Printf("run %s completed total=%dms", job.RunID, time.Since(runStart).Milliseconds())
	r.client.RunLogf(ctx, job.RunID, "info", "run %s completed total=%dms", job.RunID, time.Since(runStart).Milliseconds())

	return r.client.Complete(ctx, api.CompleteUpdate{
		RunID:      job.RunID,
		ManifestID: snapRes.ManifestID,
		StatsJSON:  string(stats),
	})
}

func (r *Runner) ensureOneDriveRootFiles(ctx context.Context, gc *graph.Client, job *api.RunJob, overlay *graphfs.OverlayBuilder) error {
	if job == nil || overlay == nil || gc == nil {
		return nil
	}
	baseKey, _ := graphsync.ParsePhysicalKey(job.PhysicalKey)
	kind, _, _ := strings.Cut(baseKey, ":")
	if kind != "user" && kind != "mailbox" {
		return nil
	}
	onedrive := false
	if job.Workloads != nil {
		onedrive = job.Workloads["onedrive"]
	}
	if !onedrive && job.Scope != nil {
		onedrive = job.Scope["onedrive"] || job.Scope["files"]
	}
	if !onedrive {
		return nil
	}
	userID := strings.TrimSpace(job.GraphID)
	if userID == "" {
		return fmt.Errorf("onedrive root guard: missing graph user id")
	}
	driveID := strings.TrimSpace(job.DriveID)
	opts := graphsync.OneDriveSyncOptions{
		AzureTenantID: job.AzureTenantID,
		UserID:        userID,
		DriveID:       driveID,
		Overlay:       overlay,
	}
	resolved, err := graphsync.ResolveOneDriveID(ctx, gc, opts)
	if err != nil {
		return err
	}
	healed, err := graphsync.HealOneDriveRootFiles(ctx, gc, opts, resolved)
	if err != nil {
		return err
	}
	missing, err := graphsync.CountMissingOneDriveRootFiles(ctx, gc, opts, resolved)
	if err != nil {
		return err
	}
	if healed > 0 || missing > 0 {
		r.client.RunLogf(ctx, job.RunID, "info", "onedrive root guard: healed=%d missing=%d overlay_entries=%d", healed, missing, overlay.EntryCount())
	}
	if missing > 0 {
		return fmt.Errorf("onedrive root guard: %d root file(s) still missing after heal", missing)
	}
	return nil
}

func (r *Runner) RunSafe(ctx context.Context, job *api.RunJob) (err error) {
	defer func() {
		if rec := recover(); rec != nil {
			err = fmt.Errorf("panic: %v", rec)
			_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		}
	}()
	return r.Run(ctx, job)
}
