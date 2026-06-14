package jobs

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"os"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphrestore"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type RestoreRunner struct {
	cfg    *config.Config
	client *api.Client
}

func NewRestoreRunner(cfg *config.Config, client *api.Client) *RestoreRunner {
	return &RestoreRunner{cfg: cfg, client: client}
}

func (r *RestoreRunner) Run(ctx context.Context, job *api.RunJob) error {
	if job == nil || job.JobType != "restore" {
		return fmt.Errorf("not a restore job")
	}
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
	}

	gc := graph.NewClient(job.GraphToken, job.GraphRegion, graph.ClientOptions{
		MaxRetries:       r.cfg.Graph.MaxRetries,
		RetryBaseDelayMs: r.cfg.Graph.RetryBaseDelayMs,
		MaxConcurrency:   r.cfg.Worker.GraphParallelRequests,
		AdaptiveLimit:    r.cfg.Graph.AdaptiveConcurrency,
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
	repoConfig := storage.RepoConfigPath(r.cfg.Worker.RunDir, job.RunID)

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

	fetch := func(path string) ([]byte, error) {
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
		return kopia.Extract(ctx, kopia.ExtractRequest{
			Storage:    storage,
			RepoConfig: repoConfig,
			ManifestID: manifestID,
			Path:       path,
			SourcePath: "/ms365",
		})
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
			Phase:      "restore_graph",
			Percent:    pct,
			ItemsDone:  done,
			ItemsTotal: total,
			Message:    message,
		})
	})

	stats, err := runner.RestoreItems(ctx, primaryTarget, items, fetch)
	if err != nil {
		return r.fail(ctx, job.RunID, err.Error())
	}
	if stats.Restored == 0 && stats.Skipped == 0 && len(items) > 0 {
		if stats.Errors > 0 {
			msg := fmt.Sprintf("failed to restore %d item(s)", stats.Errors)
			if len(stats.ErrorMessages) > 0 {
				msg += ": " + strings.Join(stats.ErrorMessages, "; ")
			}
			return r.fail(ctx, job.RunID, msg)
		}
		return r.fail(ctx, job.RunID, "no items were restored")
	}

	doneCount := stats.Restored + stats.Skipped
	reportProgress(api.ProgressUpdate{
		Phase:      "restore_graph",
		Percent:    99,
		ItemsDone:  doneCount,
		ItemsTotal: len(items),
		Message:    "Restore complete, finalizing",
	})

	statsJSON, _ := json.Marshal(stats)
	_ = os.Remove(repoConfig)
	return r.client.Complete(ctx, api.CompleteUpdate{
		RunID:     job.RunID,
		StatsJSON: string(statsJSON),
	})
}

func (r *RestoreRunner) fail(ctx context.Context, runID, message string) error {
	_ = r.client.Fail(ctx, api.FailUpdate{RunID: runID, Message: message})
	return fmt.Errorf("%s", message)
}

func stringsHasPrefix(s, prefix string) bool {
	return len(s) >= len(prefix) && s[:len(prefix)] == prefix
}
