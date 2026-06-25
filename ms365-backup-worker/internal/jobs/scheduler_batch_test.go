package jobs

import (
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
)

func TestReconcileActiveClaimsDropsGhostBatch(t *testing.T) {
	cfg := &config.Config{}
	cfg.Worker.MaxConcurrentRuns = 4
	cfg.Worker.RamBudgetMiB = 1024
	cfg.Worker.DiskBudgetMiB = 1024
	cfg.Worker.MaxCPUCores = 4
	cfg.Worker.RunDir = t.TempDir()

	s := NewScheduler(cfg, api.NewClient("http://example.test", "token", ""), t.TempDir()+"/config.yaml")
	s.batchMu.Lock()
	s.activeBatchID = "ghost-batch"
	s.batchMu.Unlock()

	s.reconcileActiveClaims([]string{})

	if s.ownsBatch() {
		t.Fatal("expected ghost batch cleared")
	}
}

func TestReconcileActiveClaimsKeepsAuthorizedBatch(t *testing.T) {
	cfg := &config.Config{}
	cfg.Worker.MaxConcurrentRuns = 4
	cfg.Worker.RamBudgetMiB = 1024
	cfg.Worker.DiskBudgetMiB = 1024
	cfg.Worker.MaxCPUCores = 4
	cfg.Worker.RunDir = t.TempDir()

	s := NewScheduler(cfg, api.NewClient("http://example.test", "token", ""), t.TempDir()+"/config.yaml")
	s.batchMu.Lock()
	s.activeBatchID = "live-batch"
	s.batchMu.Unlock()

	s.reconcileActiveClaims([]string{"live-batch"})

	if !s.ownsBatch() {
		t.Fatal("expected authorized batch kept")
	}
	if s.currentLoad() != 1 {
		t.Fatalf("expected load 1, got %d", s.currentLoad())
	}
}
