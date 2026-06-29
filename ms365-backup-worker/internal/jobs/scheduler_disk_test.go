package jobs

import (
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
)

func diskTestScheduler(t *testing.T) *Scheduler {
	t.Helper()
	cfg := &config.Config{}
	cfg.Worker.MaxConcurrentRuns = 4
	cfg.Worker.RamBudgetMiB = 16384
	cfg.Worker.DiskBudgetMiB = 16384
	cfg.Worker.MaxCPUCores = 4
	cfg.Worker.JobRamBudgetMiB = 512
	cfg.Worker.JobDiskBudgetMiB = 512
	cfg.Worker.HeavyJobDiskBudgetMiB = 512
	cfg.Worker.DiskWatermarkMiB = 1
	cfg.Worker.DiskFlushWatermarkMiB = 2
	cfg.Worker.RunDirGCTTLSeconds = 3600
	cfg.Worker.RunDir = t.TempDir()
	cfg.Kopia.RepoConfigDir = t.TempDir()
	return NewScheduler(cfg, api.NewClient("http://example.test", "token", ""), t.TempDir()+"/config.yaml")
}

func touchOldDir(t *testing.T, path string, age time.Duration) {
	t.Helper()
	if err := os.MkdirAll(path, 0o755); err != nil {
		t.Fatal(err)
	}
	past := time.Now().Add(-age)
	if err := os.Chtimes(path, past, past); err != nil {
		t.Fatal(err)
	}
}

func TestGcOrphanedRunsAgeTTL(t *testing.T) {
	s := diskTestScheduler(t)
	runDir := s.cfg.Worker.RunDir

	oldOrphan := filepath.Join(runDir, "orphan-old")
	touchOldDir(t, oldOrphan, 2*time.Hour)

	recentOrphan := filepath.Join(runDir, "orphan-recent")
	touchOldDir(t, recentOrphan, 5*time.Minute)

	oldStaging := filepath.Join(runDir, "run-staging-old")
	touchOldDir(t, oldStaging, 2*time.Hour)

	kopiaDir := filepath.Join(runDir, reservedRunDirKopia)
	touchOldDir(t, kopiaDir, 48*time.Hour)

	activeDir := filepath.Join(runDir, "live-run")
	touchOldDir(t, activeDir, 48*time.Hour)
	s.runningMu.Lock()
	s.running["live-run"] = struct{}{}
	s.runningMu.Unlock()

	s.gcOrphanedRuns()

	if _, err := os.Stat(oldOrphan); !os.IsNotExist(err) {
		t.Fatal("expected aged orphan removed")
	}
	if _, err := os.Stat(oldStaging); !os.IsNotExist(err) {
		t.Fatal("expected aged staging-named orphan removed")
	}
	if _, err := os.Stat(recentOrphan); os.IsNotExist(err) {
		t.Fatal("expected recent orphan kept")
	}
	if _, err := os.Stat(kopiaDir); os.IsNotExist(err) {
		t.Fatal("expected reserved kopia dir kept")
	}
	if _, err := os.Stat(activeDir); os.IsNotExist(err) {
		t.Fatal("expected active run dir kept")
	}
}

func TestTryReserveBlocksWhenDiskCritical(t *testing.T) {
	s := diskTestScheduler(t)
	job := &api.RunJob{
		RunID:       "child-1",
		PhysicalKey: "mailbox:user@example.com",
	}
	if !s.tryReserve(job) {
		t.Fatal("expected initial tryReserve to succeed")
	}
	s.releaseReserve(job.RunID)

	s.diskCritical.Store(true)
	if s.tryReserve(job) {
		t.Fatal("expected tryReserve to fail when diskCritical")
	}
}

func TestHasRealHeadroom(t *testing.T) {
	s := diskTestScheduler(t)

	s.cfg.Worker.DiskWatermarkMiB = 1
	if !s.hasRealHeadroom(0) {
		t.Fatal("expected headroom with low watermark")
	}
	if !s.hasRealHeadroom(512) {
		t.Fatal("expected headroom for modest job disk budget")
	}

	s.cfg.Worker.DiskWatermarkMiB = 1 << 29
	if s.hasRealHeadroom(0) {
		t.Fatal("expected no headroom with absurd watermark")
	}
}

func TestHasDiskSpaceUsesRealFree(t *testing.T) {
	s := diskTestScheduler(t)
	s.cfg.Worker.DiskWatermarkMiB = 1
	if !s.hasDiskSpace() {
		t.Fatal("expected hasDiskSpace with low watermark")
	}
	s.cfg.Worker.DiskWatermarkMiB = 1 << 29
	if s.hasDiskSpace() {
		t.Fatal("expected hasDiskSpace false with absurd watermark")
	}
}
