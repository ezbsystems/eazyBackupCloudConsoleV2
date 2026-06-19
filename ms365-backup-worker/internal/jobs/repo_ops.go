package jobs

import (
	"context"
	"fmt"
	"log"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

func (s *Scheduler) tryRepoOperation(ctx context.Context) {
	if s.draining {
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
		_ = s.client.CompleteRepoOperation(ctx, op.OperationID, "error", map[string]any{
			"error": "bucket busy with active backup run",
		})
		return
	}

	go func(op *api.RepoOperation) {
		runCtx, cancel := context.WithTimeout(context.Background(), 6*time.Hour)
		defer cancel()
		result, err := s.executeRepoOperation(runCtx, op)
		if err != nil {
			log.Printf("repo operation %d (%s) failed: %v", op.OperationID, op.OpType, err)
			_ = s.client.CompleteRepoOperation(runCtx, op.OperationID, "error", map[string]any{"error": err.Error()})
			return
		}
		if result == nil {
			result = map[string]any{"status": "success"}
		}
		_ = s.client.CompleteRepoOperation(runCtx, op.OperationID, "success", result)
	}(op)
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

func (s *Scheduler) executeRepoOperation(ctx context.Context, op *api.RepoOperation) (map[string]any, error) {
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
		if err := kopia.RunMaintenance(ctx, s.repoPool, storage, s.cfg.Kopia.MaxPackSizeMiB, true); err != nil {
			return nil, err
		}
		return map[string]any{"mode": "quick"}, nil
	case "maintenance_full":
		if err := kopia.RunMaintenance(ctx, s.repoPool, storage, s.cfg.Kopia.MaxPackSizeMiB, false); err != nil {
			return nil, err
		}
		return map[string]any{"mode": "full"}, nil
	default:
		return nil, fmt.Errorf("unsupported repo operation: %s", op.OpType)
	}
}
