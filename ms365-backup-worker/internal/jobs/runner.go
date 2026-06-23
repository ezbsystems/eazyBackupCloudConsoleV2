package jobs

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"sync/atomic"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type Runner struct {
	cfg           *config.Config
	client        *api.Client
	repoPool      *kopia.Pool
	progressHook  func(string, api.ProgressUpdate)
}

func NewRunner(cfg *config.Config, client *api.Client, repoPool *kopia.Pool) *Runner {
	return &Runner{cfg: cfg, client: client, repoPool: repoPool}
}

func (r *Runner) SetProgressHook(fn func(string, api.ProgressUpdate)) {
	r.progressHook = fn
}

func (r *Runner) noteProgress(upd api.ProgressUpdate) {
	if r.progressHook != nil && upd.RunID != "" {
		r.progressHook(upd.RunID, upd)
	}
}

// terminalContext returns a short-lived context detached from the run context so that
// terminal status reports (fail/complete) and final log flushes are delivered to the
// control plane even if the run context was cancelled or hit its deadline. Without this,
// a cancelled run could not report its own failure, leaving the run "running" until the
// lease lapsed and the reconciler re-queued it (burning attempts).
func terminalContext(ctx context.Context) (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.WithoutCancel(ctx), 2*time.Minute)
}

// reportFail delivers a failure status (and flushes buffered logs) on a detached context.
func (r *Runner) reportFail(ctx context.Context, runID, message string) {
	tctx, cancel := terminalContext(ctx)
	defer cancel()
	_ = r.client.Fail(tctx, api.FailUpdate{RunID: runID, Message: message})
	_ = r.client.FlushRunLogs(tctx, runID)
}

// reportComplete delivers a completion status on a detached context.
func (r *Runner) reportComplete(ctx context.Context, upd api.CompleteUpdate) error {
	tctx, cancel := terminalContext(ctx)
	defer cancel()
	return r.client.Complete(tctx, upd)
}

func (r *Runner) Run(ctx context.Context, job *api.RunJob, onAbort context.CancelFunc) error {
	if job == nil {
		return nil
	}
	if job.JobType == "restore" {
		restoreRunner := NewRestoreRunner(r.cfg, r.client, r.repoPool)
		restoreRunner.SetProgressHook(r.progressHook)
		return restoreRunner.Run(ctx, job, onAbort)
	}
	runStart := time.Now()
	defer func() {
		// Flush on a detached context so the last lines land even if ctx is cancelled.
		fctx, cancel := terminalContext(ctx)
		_ = r.client.FlushRunLogs(fctx, job.RunID)
		cancel()
	}()
	r.client.RunLogf(ctx, job.RunID, "info", "starting run %s resource=%s", job.RunID, job.PhysicalKey)
	log.Printf("starting run %s resource=%s", job.RunID, job.PhysicalKey)

	runDir := filepath.Join(r.cfg.Worker.RunDir, job.RunID)
	_ = os.MkdirAll(runDir, 0o755)
	defer os.RemoveAll(runDir)

	var graphItemsDone, graphItemsTotal int
	var graphBytesTotal int64
	var graphLastPercent float64 = 1
	var gc *graph.Client

	onTenantBudget := func(budget int) {
		if job.AzureTenantID == "" || budget <= 0 {
			return
		}
		graph.SetTenantBudget(job.AzureTenantID, budget)
		if gc != nil {
			gc.ClampAdaptiveCeiling(budget)
		}
	}

	progressStop := r.client.StartProgressHeartbeat(ctx, job.RunID, r.cfg.ProgressHeartbeat(), stallAwareProgressFn(r.cfg.Worker.ProgressStallSeconds, func() api.ProgressUpdate {
		return r.graphProgressUpdate(job.RunID, graphLastPercent, graphItemsDone, graphItemsTotal, graphBytesTotal, gc)
	}), onAbort, onTenantBudget)
	defer progressStop()

	sendProgressForTenant(ctx, r.client, onAbort, api.ProgressUpdate{
		RunID:   job.RunID,
		Phase:   "graph_sync",
		Percent: 1,
		Message: "Syncing from Microsoft Graph",
	}, job.AzureTenantID)

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
		sendProgressForTenant(ctx, r.client, onAbort, api.ProgressUpdate{
			RunID:   job.RunID,
			Phase:   "prior_snapshot",
			Percent: 5,
			Message: "Loading prior snapshot metadata",
		}, job.AzureTenantID)
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

	gc = graph.NewClient(job.GraphToken, job.GraphRegion, graph.ClientOptions{
		MaxRetries:       r.cfg.Graph.MaxRetries,
		RetryBaseDelayMs: r.cfg.Graph.RetryBaseDelayMs,
		MaxConcurrency:   effectiveGraphParallel(r.cfg, job),
		AdaptiveLimit:    r.cfg.Graph.AdaptiveEnabled(),
	})
	stopTokenRefresh := bindGraphTokenRefresh(ctx, r.cfg, r.client, gc, job.RunID)
	defer stopTokenRefresh()
	if job.AzureTenantID != "" {
		gc.SetAzureTenantID(job.AzureTenantID)
		if job.GraphTenantBudget > 0 {
			graph.SetTenantBudget(job.AzureTenantID, job.GraphTenantBudget)
		}
	}

	graphCtx, graphCancel := context.WithCancel(ctx)
	defer graphCancel()
	var graphStalled atomic.Bool
	if r.cfg.Kopia.StallSeconds > 0 {
		stopGraphWatch := StartGraphStallWatch(graphCtx, graphCancel, GraphProgressSnapshot{
			ItemsDone:    func() int { return graphItemsDone },
			BytesTotal:   func() int64 { return graphBytesTotal },
			ThrottleHits: gc.ThrottleHits,
		}, GraphStallWatchConfig{
			StallSeconds:                r.cfg.Kopia.StallSeconds,
			ThrottleStallCeilingSeconds: r.cfg.Graph.ThrottleStallCeilingSeconds,
			CheckIntervalSeconds:        r.cfg.Kopia.StallCheckIntervalSeconds,
			GraceSeconds:                r.cfg.Kopia.StallGraceSeconds,
			RunID:                       job.RunID,
			OnStall: func(snapshot map[string]any) {
				graphStalled.Store(true)
				r.client.RunLogf(ctx, job.RunID, "error", "graph_sync stalled: %v", snapshot)
			},
		})
		defer stopGraphWatch()
	}

	graphStart := time.Now()
	emitGraphProgress := newThrottledProgressSender(ctx, r.client, onAbort, job.AzureTenantID, r.cfg.ProgressMinInterval())
	emitAndRecordGraph := func(upd api.ProgressUpdate) {
		r.noteProgress(upd)
		emitGraphProgress(upd)
	}
	wl := &graphsync.WorkloadRunner{
		Client:           gc,
		Job:              job,
		Parallel:         effectiveGraphParallel(r.cfg, job),
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
			upd := r.graphProgressUpdate(job.RunID, pct, done, total, bytes, gc)
			upd.Message = fmt.Sprintf("Graph sync: %s", phase)
			emitAndRecordGraph(upd)
		},
		OnCheckpoint: func(states map[string]map[string]string, done int, bytes int64) {
			upd := r.graphProgressUpdate(job.RunID, graphLastPercent, done, graphItemsTotal, bytes, gc)
			upd.Message = "graph_sync checkpoint"
			upd.CheckpointDeltaStates = states
			r.noteProgress(upd)
			sendProgressForTenant(ctx, r.client, onAbort, upd, job.AzureTenantID)
		},
	}
	wlRes, err := wl.Run(graphCtx)
	graphElapsed := time.Since(graphStart)
	if err != nil {
		if graphStalled.Load() {
			msg := fmt.Sprintf("graph_sync stalled: no enumeration progress for %ds", r.cfg.Kopia.StallSeconds)
			r.client.RunLogf(ctx, job.RunID, "error", "%s", msg)
			r.reportFail(ctx, job.RunID, msg)
			return err
		}
		if isCooperativeCancel(err, ctx) {
			r.client.RunLogf(ctx, job.RunID, "info", "run cancelled during graph sync")
			return err
		}
		r.client.RunLogf(ctx, job.RunID, "error", "graph_sync failed: %v", err)
		r.reportFail(ctx, job.RunID, err.Error())
		return err
	}
	r.client.RunLogf(ctx, job.RunID, "info", "run %s graph_sync completed in %dms items=%d graph_429=%v", job.RunID, graphElapsed.Milliseconds(), wlRes.FileCount, wlRes.Stats["graph_429_hits"])
	log.Printf("run %s graph_sync completed in %dms items=%d graph_429=%v", job.RunID, graphElapsed.Milliseconds(), wlRes.FileCount, wlRes.Stats["graph_429_hits"])
	if odStats, ok := wlRes.Stats["onedrive"].(map[string]int); ok {
		r.client.RunLogf(ctx, job.RunID, "info", "onedrive sync stats: %+v", odStats)
	}
	if err := r.ensureOneDriveRootFiles(ctx, gc, job, overlay); err != nil {
		if isCooperativeCancel(err, ctx) {
			r.client.RunLogf(ctx, job.RunID, "info", "run cancelled during onedrive root guard")
			return err
		}
		r.client.RunLogf(ctx, job.RunID, "error", "onedrive root guard: %v", err)
		r.reportFail(ctx, job.RunID, err.Error())
		return err
	}

	if wlRes.FileCount == 0 && job.PreviousManifest == "" {
		log.Printf("run %s completed no_changes (empty first run) total=%dms", job.RunID, time.Since(runStart).Milliseconds())
		done, total := completionItemCounts(0)
		_ = r.reportComplete(ctx, api.CompleteUpdate{
			RunID:      job.RunID,
			ManifestID: "",
			ItemsDone:  done,
			ItemsTotal: total,
			StatsJSON:  `{"status":"no_changes"}`,
		})
		return nil
	}

	if job.PreviousManifest != "" && !overlay.HasChanges() {
		done, total := completionItemCounts(wlRes.FileCount)
		stats, _ := json.Marshal(mergeCompletionStats(map[string]any{
			"status":         "no_changes",
			"manifest_id":    job.PreviousManifest,
			"workloads":      wlRes.Stats,
			"delta_states":   wlRes.DeltaStates,
			"graph_429_hits": wlRes.Stats["graph_429_hits"],
			"physical_key":   job.PhysicalKey,
			"graph_sync_ms":  graphElapsed.Milliseconds(),
		}, wlRes.Stats))
		log.Printf("run %s completed no_changes (incremental unchanged) total=%dms", job.RunID, time.Since(runStart).Milliseconds())
		return r.reportComplete(ctx, api.CompleteUpdate{
			RunID:      job.RunID,
			ManifestID: job.PreviousManifest,
			ItemsDone:  done,
			ItemsTotal: total,
			StatsJSON:  string(stats),
		})
	}

	sendProgressForTenant(ctx, r.client, onAbort, api.ProgressUpdate{
		RunID:       job.RunID,
		Phase:       "kopia_upload",
		Percent:     40,
		ItemsTotal:  wlRes.FileCount,
		ItemsDone:   int(wlRes.ItemsDone),
		BytesHashed: wlRes.BytesTotal,
		Message:     "Uploading snapshot to Kopia repository",
	}, job.AzureTenantID)

	if err := kopia.EnsureRunDir(r.cfg.Worker.RunDir); err != nil {
		r.reportFail(ctx, job.RunID, err.Error())
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
			r.reportFail(ctx, job.RunID, err.Error())
			return err
		}
	}

	uploadStop := r.client.StartProgressHeartbeat(ctx, job.RunID, r.cfg.ProgressHeartbeat(), stallAwareProgressFn(r.cfg.Worker.ProgressStallSeconds, func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:       job.RunID,
			Phase:       "kopia_upload",
			ItemsDone:   graphItemsDone,
			ItemsTotal:  graphItemsTotal,
			BytesHashed: graphBytesTotal,
			Message:     "Upload in progress",
		}
	}), onAbort, onTenantBudget)
	defer uploadStop()

	snapshotStart := time.Now()
	emitUploadProgress := newThrottledProgressSender(ctx, r.client, onAbort, job.AzureTenantID, r.cfg.ProgressMinInterval())
	emitAndRecordUpload := func(upd api.ProgressUpdate) {
		r.noteProgress(upd)
		emitUploadProgress(upd)
	}
	progressCounter := kopia.NewProgressCounter(func(p kopia.ProgressCounter) {
		done := int(p.FilesDone.Load())
		total := wlRes.FileCount
		if graphItemsTotal > total {
			total = graphItemsTotal
		}
		pct := 40.0
		if total > 0 {
			pct = 40 + (float64(done)/float64(total))*55
		}
		emitAndRecordUpload(api.ProgressUpdate{
			RunID:         job.RunID,
			Phase:         "kopia_upload",
			Percent:       pct,
			BytesHashed:   p.BytesHashed.Load(),
			BytesUploaded: p.BytesUploaded.Load(),
			ItemsDone:     done,
			ItemsTotal:    total,
		})
	})

	snapCtx, cancelSnap := context.WithCancel(ctx)
	defer cancelSnap()
	var stalled atomic.Bool
	if r.cfg.Kopia.StallSeconds > 0 {
		stallSeconds := r.cfg.Kopia.StallSeconds
		runDir := filepath.Join(r.cfg.Worker.RunDir, job.RunID)
		_ = os.MkdirAll(runDir, 0o755)
		stopWatch := kopia.StartStallWatch(snapCtx, cancelSnap, progressCounter, kopia.StallWatchConfig{
			StallSeconds:         stallSeconds,
			CheckIntervalSeconds: r.cfg.Kopia.StallCheckIntervalSeconds,
			GraceSeconds:         r.cfg.Kopia.StallGraceSeconds,
			RunID:                job.RunID,
			RunDir:               runDir,
			OnStall: func(snapshot map[string]any) {
				stalled.Store(true)
				r.client.RunLogf(ctx, job.RunID, "error", "kopia upload stalled: %v", snapshot)
			},
		})
		defer stopWatch()
	}

	snapRes, err := r.repoPool.Snapshot(snapCtx, kopia.SnapshotRequest{
		Storage:            storage,
		SourcePath:         sourcePath,
		Host:               r.cfg.Worker.Hostname,
		Username:           "ms365",
		Entry:              tree,
		Parallel:           r.cfg.Kopia.ParallelUploads,
		Compressor:         r.cfg.Kopia.Compressor,
		MaxPackSizeMiB:     r.cfg.Kopia.MaxPackSizeMiB,
		CheckpointInterval: r.cfg.Kopia.CheckpointInterval(),
		Counter:            progressCounter,
	})
	snapshotElapsed := time.Since(snapshotStart)
	if err != nil {
		if isCooperativeCancel(err, ctx) || isCooperativeCancel(snapCtx.Err(), ctx) {
			r.client.RunLogf(ctx, job.RunID, "info", "run cancelled during kopia snapshot")
			return err
		}
		if stalled.Load() {
			msg := fmt.Sprintf("kopia upload stalled: no hashing progress for %ds", r.cfg.Kopia.StallSeconds)
			r.client.RunLogf(ctx, job.RunID, "error", "%s", msg)
			r.reportFail(ctx, job.RunID, msg)
			return err
		}
		r.client.RunLogf(ctx, job.RunID, "error", "kopia_snapshot failed after %dms: %v", snapshotElapsed.Milliseconds(), err)
		r.reportFail(ctx, job.RunID, err.Error())
		return err
	}
	log.Printf("run %s kopia_snapshot completed in %dms manifest=%s", job.RunID, snapshotElapsed.Milliseconds(), snapRes.ManifestID)
	r.client.RunLogf(ctx, job.RunID, "info", "run %s kopia_snapshot completed in %dms manifest=%s", job.RunID, snapshotElapsed.Milliseconds(), snapRes.ManifestID)

	stats, _ := json.Marshal(mergeCompletionStats(map[string]any{
		"manifest_id":       snapRes.ManifestID,
		"bytes_hashed":      snapRes.BytesHashed,
		"bytes_uploaded":    snapRes.BytesUploaded,
		"files":             snapRes.FilesDone,
		"workloads":         wlRes.Stats,
		"delta_states":      wlRes.DeltaStates,
		"graph_429_hits":    wlRes.Stats["graph_429_hits"],
		"physical_key":      job.PhysicalKey,
		"graph_sync_ms":     graphElapsed.Milliseconds(),
		"kopia_snapshot_ms": snapshotElapsed.Milliseconds(),
	}, wlRes.Stats))
	log.Printf("run %s completed total=%dms", job.RunID, time.Since(runStart).Milliseconds())
	r.client.RunLogf(ctx, job.RunID, "info", "run %s completed total=%dms", job.RunID, time.Since(runStart).Milliseconds())

	done, total := completionItemCounts(wlRes.FileCount)
	return r.reportComplete(ctx, api.CompleteUpdate{
		RunID:      job.RunID,
		ManifestID: snapRes.ManifestID,
		ItemsDone:  done,
		ItemsTotal: total,
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

func effectiveGraphParallel(cfg *config.Config, job *api.RunJob) int {
	parallel := cfg.Worker.GraphParallelRequests
	if job != nil && job.GraphTenantBudget > 0 && job.GraphTenantBudget < parallel {
		parallel = job.GraphTenantBudget
	}
	return parallel
}

func (r *Runner) graphProgressUpdate(runID string, pct float64, itemsDone, itemsTotal int, bytes int64, gc *graph.Client) api.ProgressUpdate {
	upd := api.ProgressUpdate{
		RunID:       runID,
		Phase:       "graph_sync",
		Percent:     pct,
		ItemsDone:   itemsDone,
		ItemsTotal:  itemsTotal,
		BytesHashed: bytes,
		Message:     "heartbeat",
	}
	if gc != nil {
		upd.Graph429Hits = gc.ThrottleHits()
		upd.GraphAdaptiveLimit = gc.AdaptiveConcurrency()
		if gc.ThrottleWaiting() {
			upd.ThrottleWaiting = true
			upd.Message = "Throttled by Microsoft — waiting"
		}
	}
	return upd
}

func (r *Runner) RunSafe(ctx context.Context, job *api.RunJob, onAbort context.CancelFunc) (err error) {
	defer func() {
		if rec := recover(); rec != nil {
			err = fmt.Errorf("panic: %v", rec)
			if job != nil && !isCooperativeCancel(err, ctx) {
				r.reportFail(ctx, job.RunID, err.Error())
			}
		}
	}()
	return r.Run(ctx, job, onAbort)
}
