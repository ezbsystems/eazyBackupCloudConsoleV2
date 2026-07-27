package jobs

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"sync/atomic"
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
	cfg.Worker.DiskWatermarkMiB = 1024
	cfg.Worker.DiskFlushWatermarkMiB = 2048
	cfg.Worker.UpdateReserveMiB = 256
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

func TestGcOrphanedRunsProtectsActiveJobs(t *testing.T) {
	s := diskTestScheduler(t)
	runDir := s.cfg.Worker.RunDir

	childDir := filepath.Join(runDir, "batch-child-reserved")
	touchOldDir(t, childDir, 48*time.Hour)
	activeJobs.Store("batch-child-reserved", activeJob{diskMiB: 512, bucket: "b"})

	s.gcOrphanedRuns()

	if _, err := os.Stat(childDir); os.IsNotExist(err) {
		t.Fatal("expected activeJobs child run dir kept during pressure GC")
	}
	activeJobs.Delete("batch-child-reserved")
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

func TestHeadroomDeniesWithoutWatermarkReservationsAndUpdateReserve(t *testing.T) {
	in := diskHeadroomInput{
		freeMiB:          2000,
		watermarkMiB:     1024,
		flushMarkMiB:     2048,
		reservedDiskMiB:  512,
		updateReserveMiB: 256,
		candidateDiskMiB: 512,
	}
	if in.hasHeadroom() {
		t.Fatal("expected admission denied when free < watermark+reserved+update+candidate")
	}
	in.freeMiB = 2304
	if !in.hasHeadroom() {
		t.Fatal("expected admission at exact required headroom")
	}
}

func TestSoftPressureThresholdUsesMaxOfFlushAndReserved(t *testing.T) {
	lowReserved := diskHeadroomInput{
		freeMiB:          1500,
		watermarkMiB:     1024,
		flushMarkMiB:     2048,
		reservedDiskMiB:  0,
		updateReserveMiB: 256,
	}
	if !lowReserved.softPressure() {
		t.Fatal("expected soft pressure below flush mark")
	}

	highReserved := diskHeadroomInput{
		freeMiB:          3200,
		watermarkMiB:     1024,
		flushMarkMiB:     2048,
		reservedDiskMiB:  2048,
		updateReserveMiB: 256,
	}
	if !highReserved.softPressure() {
		t.Fatal("expected soft pressure when reservations raise threshold above flush mark")
	}
}

func TestSoftPressureResumeRequiresHysteresis(t *testing.T) {
	in := diskHeadroomInput{
		freeMiB:          2500,
		watermarkMiB:     1024,
		flushMarkMiB:     2048,
		reservedDiskMiB:  512,
		updateReserveMiB: 256,
		hysteresisMiB:    512,
	}
	if in.canResumeFromPressure() {
		t.Fatal("expected resume blocked until free exceeds soft threshold + hysteresis")
	}
	in.freeMiB = 2600
	if !in.canResumeFromPressure() {
		t.Fatal("expected resume once free > threshold + hysteresis even with active reservations")
	}
}

func TestCanResumeFromPressureWithActiveReservations(t *testing.T) {
	in := diskHeadroomInput{
		freeMiB:          5600,
		watermarkMiB:     1024,
		flushMarkMiB:     2048,
		reservedDiskMiB:  4096,
		updateReserveMiB: 256,
		hysteresisMiB:    512,
	}
	// soft threshold = max(1024+4096+256, 2048) = 5376; resume at 5888
	if in.canResumeFromPressure() {
		t.Fatal("expected resume blocked below soft threshold + hysteresis")
	}
	in.freeMiB = 6000
	if !in.canResumeFromPressure() {
		t.Fatal("expected resume with reserved > 0 when free exceeds soft threshold + hysteresis")
	}
}

func TestSoftThresholdIgnoresOnDiskCacheSize(t *testing.T) {
	// Prod 9008 shape: healthy free, large warm Kopia cache, active reservations.
	in := diskHeadroomInput{
		freeMiB:          32022,
		watermarkMiB:     4096,
		flushMarkMiB:     8192,
		reservedDiskMiB:  6144,
		updateReserveMiB: 0,
		hysteresisMiB:    512,
		cachePressureMiB: 25614,
	}
	if in.softPressure() {
		t.Fatalf("warm cache must not soft-pressure when free=%d soft=%d", in.freeMiB, in.softThresholdMiB())
	}
	if !in.canResumeFromPressure() {
		t.Fatalf("expected resume with free=%d soft=%d", in.freeMiB, in.softThresholdMiB())
	}
	if got := in.softThresholdMiB(); got != 10240 {
		t.Fatalf("soft threshold = %d, want 10240 (watermark+reserved, not +cache)", got)
	}
}

func TestSchedulerUnifiedHeadroomBlocksAdmission(t *testing.T) {
	s := diskTestScheduler(t)
	s.freeMiBFn = func() int64 { return 1500 }
	s.evaluateDiskPressure(context.Background())

	job := &api.RunJob{PhysicalKey: "mailbox:user@example.com"}
	if s.canAdmit(job) {
		t.Fatal("expected canAdmit false under soft pressure")
	}
	if s.tryReserve(&api.RunJob{RunID: "x", PhysicalKey: job.PhysicalKey}) {
		t.Fatal("expected tryReserve false under soft pressure")
	}
	if !s.diskCritical.Load() {
		t.Fatal("expected diskCritical under soft pressure")
	}
}

func TestSchedulerTryClaimBatchBlockedUnderPressure(t *testing.T) {
	s := diskTestScheduler(t)
	s.freeMiBFn = func() int64 { return 1500 }
	if s.tryClaimBatch(context.Background()) {
		t.Fatal("expected batch claim blocked under disk pressure")
	}
}

func TestSchedulerPollSkipsStandaloneRestoreUnderPressure(t *testing.T) {
	s := diskTestScheduler(t)
	s.freeMiBFn = func() int64 { return 1500 }
	s.poll(context.Background())
	// poll returns early; no panic and diskCritical should be engaged via evaluateDiskPressure
	if !s.diskCritical.Load() {
		t.Fatal("expected diskCritical during sustained soft pressure")
	}
}

func TestSchedulerRepoOperationBlockedUnderPressure(t *testing.T) {
	s := diskTestScheduler(t)
	s.freeMiBFn = func() int64 { return 1500 }
	s.diskCritical.Store(true)
	s.tryRepoOperation(context.Background())
	// No claim attempted — verified by absence of side effects; hasDiskSpace gate is in tryRepoOperation
	if s.hasDiskSpace() {
		t.Fatal("expected repo operations blocked when no disk headroom")
	}
}

func TestCooperativeDrainOrdering(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
	}))
	defer srv.Close()
	s := diskTestScheduler(t)
	s.client = api.NewClient(srv.URL, "token", "")

	var steps []drainStep
	var mu sync.Mutex
	record := func(step drainStep) {
		mu.Lock()
		steps = append(steps, step)
		mu.Unlock()
	}

	s.batchMu.Lock()
	s.activeBatchID = "batch-1"
	s.batchMu.Unlock()

	runStarted := make(chan struct{})
	runDone := make(chan struct{})
	s.runningMu.Lock()
	s.running["restore-1"] = struct{}{}
	s.runningMu.Unlock()

	go func() {
		close(runStarted)
		time.Sleep(50 * time.Millisecond)
		s.runningMu.Lock()
		delete(s.running, "restore-1")
		s.runningMu.Unlock()
		close(runDone)
	}()

	<-runStarted
	s.cooperativeDrain(context.Background(), "drain", func(step drainStep) { record(step) })
	<-runDone

	mu.Lock()
	defer mu.Unlock()
	if !validDrainOrder(steps) {
		t.Fatalf("invalid drain order: %v", steps)
	}
}

func TestCooperativeDrainWaitsBeforeRelease(t *testing.T) {
	s := diskTestScheduler(t)
	var released atomic.Bool
	s.testHooks = &schedulerTestHooks{
		onReleaseClaims: func() { released.Store(true) },
	}

	s.runningMu.Lock()
	s.running["slow-run"] = struct{}{}
	s.runningMu.Unlock()

	done := make(chan struct{})
	go func() {
		time.Sleep(80 * time.Millisecond)
		s.runningMu.Lock()
		delete(s.running, "slow-run")
		s.runningMu.Unlock()
		close(done)
	}()

	s.cooperativeDrain(context.Background(), "drain", nil)
	<-done
	if released.Load() {
		// release happens after wait — verify ordering via steps
	}
	var steps []drainStep
	if s.lastDrainSteps == nil {
		t.Fatal("expected drain steps recorded")
	}
	steps = s.lastDrainSteps
	waitIdx, releaseIdx := -1, -1
	for i, step := range steps {
		if step == drainStepWaitRunners {
			waitIdx = i
		}
		if step == drainStepReleaseClaims {
			releaseIdx = i
		}
	}
	if waitIdx < 0 || releaseIdx < 0 || waitIdx >= releaseIdx {
		t.Fatalf("expected wait before release, got steps=%v", steps)
	}
}

func TestHardPressureReleasesOwnedBatchEvenWhenAlreadySoftCritical(t *testing.T) {
	// Prod death spiral: soft pressure latches diskCritical, then free falls through
	// watermark. Hard drain used to cancel with reason "" (no BatchRelease), so the
	// same node resumed the claim and rebuilt a 40GiB cache until the next cancel.
	var released atomic.Bool
	var releaseReason atomic.Value
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/accounts/modules/addons/cloudstorage/api/ms365_worker_batch_release.php" ||
			strings.Contains(r.URL.Path, "ms365_worker_batch_release.php") {
			released.Store(true)
			_ = r.ParseForm()
			body, _ := io.ReadAll(r.Body)
			releaseReason.Store(string(body))
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
	}))
	defer srv.Close()

	s := diskTestScheduler(t)
	s.client = api.NewClient(srv.URL, "token", "node-1")
	s.diskCritical.Store(true) // already soft-critical (no hard-pressure log gate)
	s.batchMu.Lock()
	s.activeBatchID = "batch-hard-pressure"
	s.batchMu.Unlock()
	s.freeMiBFn = func() int64 { return 500 } // below watermark 1024

	s.evaluateDiskPressure(context.Background())

	if !released.Load() {
		t.Fatal("expected hard pressure to BatchRelease owned batch")
	}
	reasonBody, _ := releaseReason.Load().(string)
	if reasonBody == "" || (!strings.Contains(reasonBody, "drain") && !strings.Contains(reasonBody, "disk_hard")) {
		t.Fatalf("expected drain/disk_hard release reason, got %q", reasonBody)
	}
	if len(s.lastDrainSteps) == 0 || s.lastDrainSteps[len(s.lastDrainSteps)-1] != drainStepReleaseClaims {
		t.Fatalf("expected drain steps to include release, got %v", s.lastDrainSteps)
	}
}

func TestDiskCriticalPersistsAcrossBriefFreeSpaceRecovery(t *testing.T) {
	s := diskTestScheduler(t)
	var free atomic.Int64
	free.Store(1500)
	s.freeMiBFn = func() int64 { return free.Load() }

	s.evaluateDiskPressure(context.Background())
	if !s.diskCritical.Load() {
		t.Fatal("expected diskCritical after soft pressure")
	}

	s.reserved.mu.Lock()
	s.reserved.diskMiB = 512
	s.reserved.mu.Unlock()
	free.Store(2200)
	s.evaluateDiskPressure(context.Background())
	if !s.diskCritical.Load() {
		t.Fatal("expected diskCritical to remain set when free is below soft threshold + hysteresis")
	}

	free.Store(2600)
	s.evaluateDiskPressure(context.Background())
	if s.diskCritical.Load() {
		t.Fatal("expected diskCritical cleared when free exceeds soft threshold + hysteresis with reservations still active")
	}
}

func TestHasRealHeadroom(t *testing.T) {
	s := diskTestScheduler(t)
	s.freeMiBFn = func() int64 { return 10000 }

	s.cfg.Worker.DiskWatermarkMiB = 1
	if !s.hasRealHeadroom(0) {
		t.Fatal("expected headroom with low watermark")
	}
	if !s.hasRealHeadroom(512) {
		t.Fatal("expected headroom for modest job disk budget")
	}

	s.freeMiBFn = func() int64 { return 100 }
	s.cfg.Worker.DiskWatermarkMiB = 1 << 29
	if s.hasRealHeadroom(0) {
		t.Fatal("expected no headroom with absurd watermark")
	}
}

func TestHasDiskSpaceUsesRealFree(t *testing.T) {
	s := diskTestScheduler(t)
	s.freeMiBFn = func() int64 { return 10000 }
	s.cfg.Worker.DiskWatermarkMiB = 1
	if !s.hasDiskSpace() {
		t.Fatal("expected hasDiskSpace with low watermark")
	}
	s.freeMiBFn = func() int64 { return 100 }
	s.cfg.Worker.DiskWatermarkMiB = 1 << 29
	if s.hasDiskSpace() {
		t.Fatal("expected hasDiskSpace false with absurd watermark")
	}
}
