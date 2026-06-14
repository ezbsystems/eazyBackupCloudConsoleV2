package jobs

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"path/filepath"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type Runner struct {
	cfg    *config.Config
	client *api.Client
}

func NewRunner(cfg *config.Config, client *api.Client) *Runner {
	return &Runner{cfg: cfg, client: client}
}

func (r *Runner) Run(ctx context.Context, job *api.RunJob) error {
	if job == nil {
		return nil
	}
	if job.JobType == "restore" {
		restoreRunner := NewRestoreRunner(r.cfg, r.client)
		return restoreRunner.Run(ctx, job)
	}
	log.Printf("starting run %s resource=%s", job.RunID, job.PhysicalKey)

	runDir := filepath.Join(r.cfg.Worker.RunDir, job.RunID)
	_ = os.MkdirAll(runDir, 0o755)
	defer os.RemoveAll(runDir)

	progressStop := r.client.StartProgressHeartbeat(ctx, job.RunID, r.cfg.ProgressHeartbeat(), func() api.ProgressUpdate {
		return api.ProgressUpdate{RunID: job.RunID, Phase: "graph_sync", Message: "heartbeat"}
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
	repoConfig := storage.RepoConfigPath(r.cfg.Kopia.RepoConfigDir, job.RunID)

	overlay := graphfs.NewOverlayBuilder()
	if job.IncrementalEnabled && job.PreviousManifest != "" {
		_ = r.client.Progress(ctx, api.ProgressUpdate{
			RunID:   job.RunID,
			Phase:   "prior_snapshot",
			Percent: 5,
			Message: "Loading prior snapshot metadata",
		})
		priorRoot, err := kopia.PriorSnapshotRoot(ctx, storage, repoConfig, job.PreviousManifest)
		if err != nil {
			log.Printf("run %s prior snapshot warning: %v", job.RunID, err)
		} else if err := overlay.MergePrior(ctx, priorRoot, ""); err != nil {
			log.Printf("run %s prior merge warning: %v", job.RunID, err)
		} else {
			log.Printf("run %s merged prior manifest %s (%d entries)", job.RunID, job.PreviousManifest, overlay.EntryCount())
		}
	}

	gc := graph.NewClient(job.GraphToken, job.GraphRegion, graph.ClientOptions{
		MaxRetries:       r.cfg.Graph.MaxRetries,
		RetryBaseDelayMs: r.cfg.Graph.RetryBaseDelayMs,
		MaxConcurrency:   r.cfg.Worker.GraphParallelRequests,
		AdaptiveLimit:    r.cfg.Graph.AdaptiveConcurrency,
	})

	var graphItemsDone, graphItemsTotal int
	var graphBytesTotal int64

	wl := &graphsync.WorkloadRunner{
		Client:           gc,
		Job:              job,
		Parallel:         r.cfg.Worker.GraphParallelRequests,
		FolderParallel:   r.cfg.Worker.GraphFolderParallel,
		Overlay:          overlay,
		UseBatchFallback: r.cfg.Graph.UseBatchFallback,
		OnProgress: func(phase string, done, total int, bytes int64) {
			graphItemsDone = done
			graphItemsTotal = total
			graphBytesTotal = bytes
			pct := 10.0
			if total > 0 {
				pct = 10 + (float64(done)/float64(total))*25
			}
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
	if err != nil {
		_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		return err
	}

	if wlRes.FileCount == 0 && job.PreviousManifest == "" {
		_ = r.client.Complete(ctx, api.CompleteUpdate{
			RunID:      job.RunID,
			ManifestID: "",
			StatsJSON:  `{"status":"no_changes"}`,
		})
		return nil
	}

	_ = r.client.Progress(ctx, api.ProgressUpdate{
		RunID:      job.RunID,
		Phase:      "kopia_upload",
		Percent:    40,
		ItemsTotal: wlRes.FileCount,
		ItemsDone:  int(wlRes.ItemsDone),
		BytesHashed: wlRes.BytesTotal,
		Message:    "Uploading snapshot to Kopia repository",
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

	uploadStop := r.client.StartProgressHeartbeat(ctx, job.RunID, r.cfg.ProgressHeartbeat(), func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:      job.RunID,
			Phase:      "kopia_upload",
			ItemsDone:  graphItemsDone,
			ItemsTotal: graphItemsTotal,
			BytesHashed: graphBytesTotal,
			Message:    "Upload in progress",
		}
	})
	defer uploadStop()

	snapRes, err := kopia.Snapshot(ctx, kopia.SnapshotRequest{
		Storage:            storage,
		RepoConfig:         repoConfig,
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
	if err != nil {
		_ = r.client.Fail(ctx, api.FailUpdate{RunID: job.RunID, Message: err.Error()})
		return err
	}

	stats, _ := json.Marshal(map[string]any{
		"manifest_id":    snapRes.ManifestID,
		"bytes_hashed":   snapRes.BytesHashed,
		"bytes_uploaded": snapRes.BytesUploaded,
		"files":          snapRes.FilesDone,
		"workloads":      wlRes.Stats,
		"delta_states":   wlRes.DeltaStates,
		"graph_429_hits": wlRes.Stats["graph_429_hits"],
		"physical_key":   job.PhysicalKey,
	})
	_ = os.Remove(repoConfig)

	return r.client.Complete(ctx, api.CompleteUpdate{
		RunID:      job.RunID,
		ManifestID: snapRes.ManifestID,
		StatsJSON:  string(stats),
	})
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
