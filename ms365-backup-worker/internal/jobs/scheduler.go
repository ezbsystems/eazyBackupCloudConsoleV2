package jobs

import (
	"context"
	"log"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
	"github.com/eazybackup/ms365-backup-worker/internal/updater"
	"github.com/eazybackup/ms365-backup-worker/internal/version"
)

type Scheduler struct {
	cfg       *config.Config
	client    *api.Client
	runner    *Runner
	repoPool  *kopia.Pool
	runningMu sync.Mutex
	running   map[string]struct{}
	bucketMu  sync.Mutex
	activeBuckets map[string]int
	reserved  resourceBudget
	draining  bool
}

type resourceBudget struct {
	mu        sync.Mutex
	ramMiB    int
	diskMiB   int
	cpuCores  float64
}

func NewScheduler(cfg *config.Config, client *api.Client) *Scheduler {
	repoPool := kopia.NewPool(kopia.RepoCacheSettings{
		RepoConfigDir:       cfg.Kopia.RepoConfigDir,
		ContentCacheSizeMiB: cfg.Kopia.ContentCacheSizeMiB,
	})
	graph.SetGlobalConcurrency(cfg.Graph.GlobalMaxConcurrency)
	s := &Scheduler{
		cfg:      cfg,
		client:   client,
		repoPool: repoPool,
		runner:   NewRunner(cfg, client, repoPool),
		running:  make(map[string]struct{}),
		activeBuckets: make(map[string]int),
	}
	s.gcOrphanedRuns()
	go s.periodicGC()
	return s
}

func (s *Scheduler) Run(ctx context.Context) error {
	reg, err := s.client.Register(ctx, s.cfg.Worker.Hostname, s.cfg.Worker.MaxConcurrentRuns, version.Version, s.cfg.Worker.ProxmoxVmid)
	if err != nil {
		return err
	}
	if reg != nil && reg.NodeID != "" {
		s.cfg.Worker.NodeID = reg.NodeID
		s.client.SetNodeID(reg.NodeID)
		log.Printf("registered worker node %s version=%s", reg.NodeID, version.Version)
	}

	pollTicker := time.NewTicker(s.cfg.PollInterval())
	defer pollTicker.Stop()
	hbTicker := time.NewTicker(s.cfg.HeartbeatInterval())
	defer hbTicker.Stop()

	for {
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-hbTicker.C:
			s.heartbeat(ctx)
		case <-pollTicker.C:
			if s.draining {
				continue
			}
			s.poll(ctx)
		}
	}
}

func (s *Scheduler) heartbeat(ctx context.Context) {
	load := s.currentLoad()
	hb, err := s.client.Heartbeat(ctx, load, version.Version, "", s.cfg.Worker.ProxmoxVmid)
	if err != nil {
		log.Printf("heartbeat failed: %v", err)
		return
	}
	if hb == nil || hb.Update == nil {
		return
	}
	if load > 0 {
		s.draining = true
		log.Printf("update %s available; draining %d run(s) before apply", hb.Update.Version, load)
		return
	}
	s.draining = true
	s.repoPool.Drain(ctx)
	s.releaseAllActiveClaims(ctx)
	log.Printf("applying update to version %s", hb.Update.Version)
	if err := updater.Apply(s.client.Token(), updater.OfferFromAPI(hb.Update), s.cfg.Worker.InstallPath); err != nil {
		log.Printf("update failed: %v", err)
		_, _ = s.client.Heartbeat(ctx, 0, version.Version, err.Error(), s.cfg.Worker.ProxmoxVmid)
		s.draining = false
	}
}

func (s *Scheduler) poll(ctx context.Context) {
	if !s.hasDiskSpace() {
		log.Printf("skipping claim: RunDir free space below watermark")
		return
	}
	for s.availableSlots() > 0 {
		job, err := s.client.Claim(ctx)
		if err != nil {
			log.Printf("claim failed: %v", err)
			return
		}
		if job == nil {
			break
		}
		if !s.canAdmit(job) {
			_ = s.client.Release(ctx, job.RunID)
			continue
		}
		if !s.tryStart(job) {
			_ = s.client.Release(ctx, job.RunID)
			continue
		}
		go func(j *api.RunJob) {
			defer s.done(j.RunID)
			runCtx, cancel := s.runContext(ctx, j)
			defer cancel()
			if err := s.runner.RunSafe(runCtx, j); err != nil {
				log.Printf("run %s failed: %v", j.RunID, err)
				// Use the live scheduler context (not runCtx, which may be cancelled/expired)
				// so the final failure line is still delivered to the control plane.
				logCtx, logCancel := context.WithTimeout(context.WithoutCancel(ctx), 30*time.Second)
				s.client.RunLogf(logCtx, j.RunID, "error", "run %s failed: %v", j.RunID, err)
				_ = s.client.FlushRunLogs(logCtx, j.RunID)
				logCancel()
			}
		}(job)
	}
	if !s.draining {
		s.tryRepoOperation(ctx)
	}
}

// runContext builds the working context for a run. It is intentionally NOT bound to
// the claim-time lease (job.LeaseExpiresAt): the control plane keeps a live run's lease
// fresh via worker heartbeat (renewForNode) and progress (renewForRun), so binding the
// worker's own context to the initial lease caused long whale-scale snapshots to
// self-cancel mid-write ("error writing pack file: context deadline exceeded"), which the
// control plane then mistook for worker loss and re-queued until max attempts. Instead we
// apply a generous safety ceiling that only bounds genuinely stuck runs.
func (s *Scheduler) runContext(parent context.Context, job *api.RunJob) (context.Context, context.CancelFunc) {
	maxRun := s.cfg.MaxRunDuration()
	if maxRun <= 0 {
		return context.WithCancel(parent)
	}
	return context.WithTimeout(parent, maxRun)
}

func (s *Scheduler) hasDiskSpace() bool {
	var st syscall.Statfs_t
	if err := syscall.Statfs(s.cfg.Worker.RunDir, &st); err != nil {
		return true
	}
	free := int64(st.Bavail) * int64(st.Bsize)
	return free >= s.cfg.Worker.DiskWatermarkBytes()
}

func (s *Scheduler) periodicGC() {
	ticker := time.NewTicker(10 * time.Minute)
	defer ticker.Stop()
	for range ticker.C {
		s.gcOrphanedRuns()
	}
}

func (s *Scheduler) gcOrphanedRuns() {
	entries, err := os.ReadDir(s.cfg.Worker.RunDir)
	if err != nil {
		return
	}
	s.runningMu.Lock()
	running := make(map[string]struct{}, len(s.running))
	for id := range s.running {
		running[id] = struct{}{}
	}
	s.runningMu.Unlock()

	for _, e := range entries {
		if !e.IsDir() {
			continue
		}
		if _, ok := running[e.Name()]; ok {
			continue
		}
		path := filepath.Join(s.cfg.Worker.RunDir, e.Name())
		if strings.Contains(e.Name(), "staging") {
			continue
		}
		_ = os.RemoveAll(path)
	}
}

func (s *Scheduler) jobBudget(job *api.RunJob) (ramMiB, diskMiB int, cpuCores float64) {
	baseKey, _ := graphsync.ParsePhysicalKey(job.PhysicalKey)
	kind, _, _ := strings.Cut(baseKey, ":")
	ramMiB = s.cfg.Worker.JobRamBudgetMiB
	diskMiB = s.cfg.Worker.JobDiskBudgetMiB
	cpuCores = 1
	switch kind {
	case "drive", "site", "onedrive":
		ramMiB = s.cfg.Worker.HeavyJobRamBudgetMiB
		diskMiB = s.cfg.Worker.HeavyJobDiskBudgetMiB
		cpuCores = 2
	}
	return ramMiB, diskMiB, cpuCores
}

func (s *Scheduler) canAdmit(job *api.RunJob) bool {
	if !s.hasDiskSpace() {
		return false
	}
	ramMiB, diskMiB, cpuCores := s.jobBudget(job)
	s.reserved.mu.Lock()
	defer s.reserved.mu.Unlock()
	if s.reserved.ramMiB+ramMiB > s.cfg.Worker.RamBudgetMiB {
		return false
	}
	if s.reserved.diskMiB+diskMiB > s.cfg.Worker.DiskBudgetMiB {
		return false
	}
	if s.reserved.cpuCores+cpuCores > s.cfg.Worker.MaxCPUCores {
		return false
	}
	return true
}

func (s *Scheduler) availableSlots() int {
	s.runningMu.Lock()
	defer s.runningMu.Unlock()
	return s.cfg.Worker.MaxConcurrentRuns - len(s.running)
}

func (s *Scheduler) currentLoad() int {
	s.runningMu.Lock()
	defer s.runningMu.Unlock()
	return len(s.running)
}

type activeJob struct {
	ramMiB   int
	diskMiB  int
	cpuCores float64
	bucket   string
}

var activeJobs sync.Map

func (s *Scheduler) tryStart(job *api.RunJob) bool {
	s.runningMu.Lock()
	defer s.runningMu.Unlock()
	if s.draining {
		return false
	}
	if _, ok := s.running[job.RunID]; ok {
		return false
	}
	if len(s.running) >= s.cfg.Worker.MaxConcurrentRuns {
		return false
	}
	ramMiB, diskMiB, cpuCores := s.jobBudget(job)
	s.reserved.mu.Lock()
	if s.reserved.ramMiB+ramMiB > s.cfg.Worker.RamBudgetMiB ||
		s.reserved.diskMiB+diskMiB > s.cfg.Worker.DiskBudgetMiB ||
		s.reserved.cpuCores+cpuCores > s.cfg.Worker.MaxCPUCores {
		s.reserved.mu.Unlock()
		return false
	}
	s.reserved.ramMiB += ramMiB
	s.reserved.diskMiB += diskMiB
	s.reserved.cpuCores += cpuCores
	s.reserved.mu.Unlock()
	activeJobs.Store(job.RunID, activeJob{ramMiB: ramMiB, diskMiB: diskMiB, cpuCores: cpuCores, bucket: job.DestBucket})
	s.running[job.RunID] = struct{}{}
	s.trackBucket(job.DestBucket, 1)
	return true
}

func (s *Scheduler) done(runID string) {
	s.runningMu.Lock()
	delete(s.running, runID)
	s.runningMu.Unlock()
	if v, ok := activeJobs.LoadAndDelete(runID); ok {
		if aj, ok := v.(activeJob); ok {
			s.trackBucket(aj.bucket, -1)
			s.reserved.mu.Lock()
			s.reserved.ramMiB -= aj.ramMiB
			s.reserved.diskMiB -= aj.diskMiB
			s.reserved.cpuCores -= aj.cpuCores
			s.reserved.mu.Unlock()
		}
	}
}

func (s *Scheduler) releaseAllActiveClaims(ctx context.Context) {
	s.runningMu.Lock()
	runIDs := make([]string, 0, len(s.running))
	for id := range s.running {
		runIDs = append(runIDs, id)
	}
	s.runningMu.Unlock()
	for _, runID := range runIDs {
		if err := s.client.Release(ctx, runID); err != nil {
			log.Printf("release %s before update: %v", runID, err)
		}
	}
}
