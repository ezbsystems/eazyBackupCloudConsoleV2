package jobs

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphrestore"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type RestoreRunner struct {
	cfg      *config.Config
	client   *api.Client
	repoPool *kopia.Pool
}

func NewRestoreRunner(cfg *config.Config, client *api.Client, repoPool *kopia.Pool) *RestoreRunner {
	return &RestoreRunner{cfg: cfg, client: client, repoPool: repoPool}
}

func (r *RestoreRunner) Run(ctx context.Context, job *api.RunJob) error {
	if job == nil || job.JobType != "restore" {
		return fmt.Errorf("not a restore job")
	}
	defer func() { r.flushLogs(ctx, job.RunID) }()
	r.client.RunLogf(r.logCtx(ctx), job.RunID, "info", "starting restore %s", job.RunID)
	log.Printf("starting restore %s", job.RunID)

	selection := job.RestoreSelection
	if len(selection.Items) == 0 {
		return r.fail(ctx, job.RunID, "no items selected")
	}
	if len(selection.Targets) == 0 {
		return r.fail(ctx, job.RunID, "no restore target")
	}

	_ = r.client.Progress(ctx, api.ProgressUpdate{
		RunID:      job.RunID,
		Phase:      "restore_extract",
		Percent:    5,
		ItemsTotal: len(selection.Items),
		Message:    "Reading snapshot items",
	})

	primaryTarget := graphrestore.Target{
		ResourceID:   selection.Targets[0].ResourceID,
		GraphID:      selection.Targets[0].GraphID,
		ResourceType: selection.Targets[0].ResourceType,
		DriveID:      strings.TrimSpace(job.DriveID),
	}

	gc := graph.NewClient(job.GraphToken, job.GraphRegion, graph.ClientOptions{
		MaxRetries:       r.cfg.Graph.MaxRetries,
		RetryBaseDelayMs: r.cfg.Graph.RetryBaseDelayMs,
		MaxConcurrency:   r.cfg.Worker.GraphParallelRequests,
		AdaptiveLimit:    r.cfg.Graph.AdaptiveEnabled(),
	})
	stopTokenRefresh := bindGraphTokenRefresh(ctx, r.cfg, r.client, gc, job.RunID)
	defer stopTokenRefresh()

	storage := kopia.StorageOptions{
		Endpoint:     job.DestEndpoint,
		Region:       job.DestRegion,
		Bucket:       job.DestBucket,
		Prefix:       job.DestPrefix,
		AccessKey:    job.DestAccessKey,
		SecretKey:    job.DestSecretKey,
		RepoPassword: job.RepoPassword,
	}

	manifestByItem := map[string]string{}
	for _, item := range selection.Items {
		if item.ManifestID != "" {
			if item.Path != "" {
				manifestByItem[item.Path] = item.ManifestID
			}
			if item.PathPrefix != "" {
				manifestByItem[item.PathPrefix] = item.ManifestID
			}
		}
	}

	fetch := graphrestore.ContentFetcher{
		Bytes: func(path string) ([]byte, error) {
			manifestID := job.SourceManifestID
			for prefix, mid := range manifestByItem {
				if path == prefix || stringsHasPrefix(path, prefix) {
					manifestID = mid
					break
				}
			}
			for _, item := range selection.Items {
				if item.ManifestID != "" {
					manifestID = item.ManifestID
					break
				}
			}
			return r.repoPool.Extract(ctx, kopia.ExtractRequest{
				Storage:    storage,
				ManifestID: manifestID,
				Path:       path,
				SourcePath: "/ms365",
			})
		},
		Stream: func(path string) (io.ReadCloser, int64, error) {
			manifestID := job.SourceManifestID
			for prefix, mid := range manifestByItem {
				if path == prefix || stringsHasPrefix(path, prefix) {
					manifestID = mid
					break
				}
			}
			for _, item := range selection.Items {
				if item.ManifestID != "" {
					manifestID = item.ManifestID
					break
				}
			}
			reader, size, err := r.repoPool.ExtractReader(ctx, kopia.ExtractRequest{
				Storage:    storage,
				ManifestID: manifestID,
				Path:       path,
				SourcePath: "/ms365",
			})
			if err != nil {
				return nil, 0, err
			}
			return reader, size, nil
		},
	}

	items := make([]graphrestore.SelectionItem, len(selection.Items))
	for i, item := range selection.Items {
		items[i] = graphrestore.SelectionItem{
			ChildRunID: item.ChildRunID,
			ManifestID: item.ManifestID,
			Path:       item.Path,
			PathPrefix: item.PathPrefix,
			Type:       item.Type,
		}
	}

	var lastProgress api.ProgressUpdate
	lastProgress = api.ProgressUpdate{
		RunID:      job.RunID,
		Phase:      "restore_graph",
		Percent:    10,
		ItemsTotal: len(selection.Items),
		Message:    "Restoring to Microsoft 365",
	}
	reportProgress := func(upd api.ProgressUpdate) {
		upd.RunID = job.RunID
		if upd.Phase == "" {
			upd.Phase = "restore_graph"
		}
		lastProgress = upd
		if err := r.client.Progress(ctx, upd); err != nil {
			log.Printf("restore %s progress warning: %v", job.RunID, err)
		}
	}
	progressStop := r.client.StartProgressHeartbeat(ctx, job.RunID, 45*time.Second, func() api.ProgressUpdate {
		return lastProgress
	})
	defer progressStop()

	runner := graphrestore.NewRunner(gc, selection.ConflictPolicy, func(done, skipped, total int, message string) {
		pct := 10.0
		if total > 0 {
			pct = 10 + (float64(done)/float64(total))*85
		}
		reportProgress(api.ProgressUpdate{
			Phase:        "restore_graph",
			Percent:      pct,
			ItemsDone:    done,
			ItemsTotal:   total,
			ItemsSkipped: skipped,
			Message:      message,
		})
	})
	runner.OnItemError = func(message string) {
		r.client.RunLogf(r.logCtx(ctx), job.RunID, "error", "restore item error: %s", message)
	}

	stats, err := runner.RestoreItems(ctx, primaryTarget, items, fetch)
	if err != nil {
		r.client.RunLogf(r.logCtx(ctx), job.RunID, "error", "restore failed: %s", err.Error())
		return r.failTerminal(ctx, job.RunID, err.Error())
	}
	doneCount := stats.Restored + stats.Skipped
	if doneCount == 0 && len(items) > 0 {
		msg := "no items were restored"
		if stats.Errors > 0 {
			msg = fmt.Sprintf("failed to restore %d item(s)", stats.Errors)
			if len(stats.ErrorMessages) > 0 {
				msg += ": " + strings.Join(stats.ErrorMessages, "; ")
			}
		}
		r.client.RunLogf(r.logCtx(ctx), job.RunID, "error", "restore %s failed: %s", job.RunID, msg)
		reportProgress(api.ProgressUpdate{
			Phase:      "restore_graph",
			Percent:    95,
			ItemsDone:  0,
			ItemsTotal: len(items),
			Message:    msg,
		})
		return r.failTerminal(ctx, job.RunID, msg)
	}

	reportProgress(api.ProgressUpdate{
		Phase:      "restore_graph",
		Percent:    99,
		ItemsDone:  doneCount,
		ItemsTotal: len(items),
		Message:    "Restore complete, finalizing",
	})

	statsJSON, _ := json.Marshal(stats)
	r.client.RunLogf(r.logCtx(ctx), job.RunID, "info", "restore %s completed restored=%d skipped=%d errors=%d", job.RunID, stats.Restored, stats.Skipped, stats.Errors)
	return r.completeTerminal(ctx, job.RunID, string(statsJSON))
}

func (r *RestoreRunner) logCtx(ctx context.Context) context.Context {
	return context.WithoutCancel(ctx)
}

func (r *RestoreRunner) flushLogs(ctx context.Context, runID string) {
	_ = r.client.FlushRunLogs(r.logCtx(ctx), runID)
}

func (r *RestoreRunner) terminalCtx(ctx context.Context) (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.WithoutCancel(ctx), 2*time.Minute)
}

func (r *RestoreRunner) completeTerminal(ctx context.Context, runID, statsJSON string) error {
	r.flushLogs(ctx, runID)
	tctx, cancel := r.terminalCtx(ctx)
	defer cancel()
	if err := r.client.Complete(tctx, api.CompleteUpdate{
		RunID:     runID,
		StatsJSON: statsJSON,
	}); err != nil {
		log.Printf("restore %s complete failed: %v", runID, err)
		return err
	}
	return nil
}

func (r *RestoreRunner) fail(ctx context.Context, runID, message string) error {
	return r.failTerminal(ctx, runID, message)
}

func (r *RestoreRunner) failTerminal(ctx context.Context, runID, message string) error {
	r.flushLogs(ctx, runID)
	tctx, cancel := r.terminalCtx(ctx)
	defer cancel()
	if len(message) > 4000 {
		message = message[:4000] + "…"
	}
	if err := r.client.Fail(tctx, api.FailUpdate{RunID: runID, Message: message}); err != nil {
		log.Printf("restore %s fail callback failed: %v (message: %s)", runID, err, message)
	}
	return fmt.Errorf("%s", message)
}

func stringsHasPrefix(s, prefix string) bool {
	return len(s) >= len(prefix) && s[:len(prefix)] == prefix
}
