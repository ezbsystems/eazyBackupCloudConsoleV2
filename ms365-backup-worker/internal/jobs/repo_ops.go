package jobs

import (
	"context"
	"fmt"
	"log"
	"strings"
	"sync"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

const (
	repoOpProgressInterval = 150 * time.Second
	repoOpCompleteTimeout  = 2 * time.Minute
)

func (s *Scheduler) tryRepoOperation(ctx context.Context) {
	if s.draining || !s.hasDiskSpace() || s.diskCritical.Load() {
		return
	}
	op, err := s.client.ClaimRepoOperation(ctx)
	if err != nil {
		log.Printf("repo operation claim failed: %v", err)
		return
	}
	if op == nil {
		return
	}
	if op.OperationID <= 0 || strings.TrimSpace(op.OpType) == "" {
		return
	}
	if s.isBucketBusy(op.DestBucket) {
		completeCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), repoOpCompleteTimeout)
		defer cancel()
		_ = s.client.CompleteRepoOperation(completeCtx, op.OperationID, "error", map[string]any{
			"error": "bucket busy with active backup run",
		})
		return
	}

	go s.runRepoOperation(op)
}

func (s *Scheduler) runRepoOperation(op *api.RepoOperation) {
	runCtx, cancel := context.WithTimeout(context.Background(), 6*time.Hour)
	defer cancel()

	s.trackRepoOp(1)
	defer s.trackRepoOp(-1)

	log.Printf("repo_op_start op=%d type=%s bucket=%s node=%s timeout=6h",
		op.OperationID, op.OpType, op.DestBucket, s.client.NodeID())

	progressCtx, progressCancel := context.WithCancel(context.Background())
	defer progressCancel()

	var progressMu sync.Mutex
	lastFields := map[string]any{"phase": "start"}
	reportProgress := func(phase string, fields map[string]any) {
		merged := map[string]any{"phase": phase}
		for k, v := range fields {
			merged[k] = v
		}
		progressMu.Lock()
		for k, v := range merged {
			lastFields[k] = v
		}
		snapshot := copyStringAnyMap(lastFields)
		progressMu.Unlock()
		_ = s.client.ProgressRepoOperation(progressCtx, op.OperationID, phase, snapshot)
	}

	reportProgress("start", map[string]any{
		"op_type":       op.OpType,
		"repository_id": op.RepositoryID,
		"bucket":        op.DestBucket,
	})

	go s.repoOpProgressLoop(progressCtx, op, &progressMu, &lastFields)

	result, err := s.executeRepoOperation(runCtx, op, reportProgress)
	progressCancel()

	completeCtx, completeCancel := context.WithTimeout(context.WithoutCancel(runCtx), repoOpCompleteTimeout)
	defer completeCancel()
	if err != nil {
		log.Printf("repo operation %d (%s) failed: %v", op.OperationID, op.OpType, err)
		_ = s.client.CompleteRepoOperation(completeCtx, op.OperationID, "error", map[string]any{"error": err.Error()})
		return
	}
	if result == nil {
		result = map[string]any{"status": "success"}
	}
	_ = s.client.CompleteRepoOperation(completeCtx, op.OperationID, "success", result)
}

func (s *Scheduler) repoOpProgressLoop(
	ctx context.Context,
	op *api.RepoOperation,
	progressMu *sync.Mutex,
	lastFields *map[string]any,
) {
	ticker := time.NewTicker(repoOpProgressInterval)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			progressMu.Lock()
			snapshot := copyStringAnyMap(*lastFields)
			progressMu.Unlock()
			phase := "heartbeat"
			if p, ok := snapshot["phase"].(string); ok && strings.TrimSpace(p) != "" {
				phase = p
			}
			_ = s.client.ProgressRepoOperation(ctx, op.OperationID, phase, snapshot)
		}
	}
}

func (s *Scheduler) trackRepoOp(delta int) {
	s.activeRepoOpsMu.Lock()
	defer s.activeRepoOpsMu.Unlock()
	s.activeRepoOps += delta
	if s.activeRepoOps < 0 {
		s.activeRepoOps = 0
	}
}

func copyStringAnyMap(in map[string]any) map[string]any {
	out := make(map[string]any, len(in))
	for k, v := range in {
		out[k] = v
	}
	return out
}

func (s *Scheduler) isBucketBusy(bucket string) bool {
	bucket = strings.TrimSpace(bucket)
	if bucket == "" {
		return false
	}
	s.bucketMu.Lock()
	defer s.bucketMu.Unlock()
	return s.activeBuckets[bucket] > 0
}

func (s *Scheduler) trackBucket(bucket string, delta int) {
	bucket = strings.TrimSpace(bucket)
	if bucket == "" {
		return
	}
	s.bucketMu.Lock()
	defer s.bucketMu.Unlock()
	s.activeBuckets[bucket] += delta
	if s.activeBuckets[bucket] <= 0 {
		delete(s.activeBuckets, bucket)
	}
}

func (s *Scheduler) executeRepoOperation(
	ctx context.Context,
	op *api.RepoOperation,
	reportProgress func(phase string, fields map[string]any),
) (map[string]any, error) {
	if s.testHooks != nil && s.testHooks.repoOpExecute != nil {
		return s.testHooks.repoOpExecute(ctx, op, reportProgress)
	}

	storage := kopia.StorageOptions{
		Endpoint:     op.DestEndpoint,
		Region:       op.DestRegion,
		Bucket:       op.DestBucket,
		Prefix:       op.DestPrefix,
		AccessKey:    op.DestAccessKey,
		SecretKey:    op.DestSecretKey,
		RepoPassword: op.RepoPassword,
	}

	switch strings.ToLower(strings.TrimSpace(op.OpType)) {
	case "retention_apply":
		reportProgress("retention_apply", nil)
		ret, err := kopia.ApplyRetention(ctx, s.repoPool, storage, s.cfg.Kopia.MaxPackSizeMiB, op.EffectivePolicy)
		if err != nil {
			return nil, err
		}
		log.Printf("retention_apply op=%d deleted=%d sources=%d", op.OperationID, ret.DeletedCount, ret.SourcesCount)
		return map[string]any{
			"deleted_count": ret.DeletedCount,
			"sources_count": ret.SourcesCount,
		}, nil
	case "maintenance_quick":
		outcome, err := kopia.RunManagedMaintenance(
			ctx, s.repoPool, storage, s.cfg.Kopia.MaxPackSizeMiB, true, s.cfg.Kopia.IndexMaintenanceThreshold, reportProgress,
		)
		if err != nil {
			return nil, err
		}
		log.Printf("maintenance_quick op=%d requested=%s effective=%s escalated=%v index_blobs_before=%d index_blobs_after=%d",
			op.OperationID, outcome.RequestedMode, outcome.EffectiveMode, outcome.Escalated, outcome.IndexBlobsBefore, outcome.IndexBlobsAfter)
		return maintenanceOutcomeMap(outcome), nil
	case "maintenance_full":
		outcome, err := kopia.RunManagedMaintenance(
			ctx, s.repoPool, storage, s.cfg.Kopia.MaxPackSizeMiB, false, s.cfg.Kopia.IndexMaintenanceThreshold, reportProgress,
		)
		if err != nil {
			return nil, err
		}
		log.Printf("maintenance_full op=%d requested=%s effective=%s skipped=%v index_blobs_before=%d index_blobs_after=%d",
			op.OperationID, outcome.RequestedMode, outcome.EffectiveMode, outcome.Skipped, outcome.IndexBlobsBefore, outcome.IndexBlobsAfter)
		return maintenanceOutcomeMap(outcome), nil
	default:
		return nil, fmt.Errorf("unsupported repo operation: %s", op.OpType)
	}
}

func maintenanceOutcomeMap(outcome kopia.MaintenanceOutcome) map[string]any {
	result := map[string]any{
		"requested_mode":     outcome.RequestedMode,
		"effective_mode":     outcome.EffectiveMode,
		"index_blobs_before": outcome.IndexBlobsBefore,
		"index_blobs_after":  outcome.IndexBlobsAfter,
	}
	if outcome.Escalated {
		result["escalated"] = true
	}
	if outcome.Skipped {
		result["skipped"] = true
	}
	return result
}
