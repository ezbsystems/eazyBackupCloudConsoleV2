package jobs

import (
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
)

func TestReconcileActiveClaimsDropsGhosts(t *testing.T) {
	cfg := &config.Config{}
	cfg.Worker.MaxConcurrentRuns = 4
	cfg.Worker.RamBudgetMiB = 1024
	cfg.Worker.DiskBudgetMiB = 1024
	cfg.Worker.MaxCPUCores = 4

	s := NewScheduler(cfg, api.NewClient("http://example.test", "token", ""), "/tmp/config.yaml")
	s.running["ghost-run"] = struct{}{}
	s.activeBuckets[""] = 1

	s.reconcileActiveClaims([]string{})

	if s.currentLoad() != 0 {
		t.Fatalf("expected ghost load cleared, got %d", s.currentLoad())
	}
}

func TestReconcileActiveClaimsKeepsAuthorized(t *testing.T) {
	cfg := &config.Config{}
	cfg.Worker.MaxConcurrentRuns = 4
	cfg.Worker.RamBudgetMiB = 1024
	cfg.Worker.DiskBudgetMiB = 1024
	cfg.Worker.MaxCPUCores = 4

	s := NewScheduler(cfg, api.NewClient("http://example.test", "token", ""), "/tmp/config.yaml")
	s.running["live-run"] = struct{}{}

	s.reconcileActiveClaims([]string{"live-run"})

	if s.currentLoad() != 1 {
		t.Fatalf("expected live run kept, got %d", s.currentLoad())
	}
}
