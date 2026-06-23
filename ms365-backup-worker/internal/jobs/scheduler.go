package jobs

import (
	"context"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/configapply"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphsync"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
	"github.com/eazybackup/ms365-backup-worker/internal/telemetry"
	"github.com/eazybackup/ms365-backup-worker/internal/updater"
	"github.com/eazybackup/ms365-backup-worker/internal/version"
)

type Scheduler struct {
	cfg                  *config.Config
	client               *api.Client
	runner               *Runner
	repoPool             *kopia.Pool
	configPath           string
	telemetry            *telemetry.Collector
	runningMu            sync.Mutex
	running              map[string]struct{}
	runCancelMu          sync.Mutex
	runCancels           map[string]context.CancelFunc
	bucketMu             sync.Mutex
	activeBuckets        map[string]int
	reserved             resourceBudget
	draining             bool
	pendingUpdate        *api.UpdateOffer
	pendingConfig        *api.ConfigOffer
	appliedConfigVersion int
	deployError          string
	configError          string
	admitRejectMu        sync.Mutex
	admitRejects         int
	runProgress          sync.Map
}

type resourceBudget struct {
	mu       sync.Mutex
	ramMiB   int
	diskMiB  int
	cpuCores float64
}

func NewScheduler(cfg *config.Config, client *api.Client, configPath string) *Scheduler {
	repoPool := kopia.NewPool(kopia.RepoCacheSettings{
		RepoConfigDir:       cfg.Kopia.RepoConfigDir,
		ContentCacheSizeMiB: cfg.Kopia.ContentCacheSizeMiB,
	})
	graph.SetGlobalConcurrency(cfg.Graph.GlobalMaxConcurrency)
	s := &Scheduler{
		cfg:                  cfg,
		client:               client,
		configPath:           configPath,
		telemetry:            telemetry.NewCollector(cfg.Worker.RunDir, cfg.Worker.MaxCPUCores),
		repoPool:             repoPool,
		runner:               NewRunner(cfg, client, repoPool),
		running:              make(map[string]struct{}),
		runCancels:           make(map[string]context.CancelFunc),
		activeBuckets:        make(map[string]int),
		appliedConfigVersion: configapply.ReadAppliedVersion(configPath),
	}
	s.runner.SetProgressHook(s.recordRunProgress)
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
	admitRejects := s.resetAdmitRejects()
	hb, err := s.client.Heartbeat(ctx, s.heartbeatParams(load, admitRejects))
	if err != nil {
		log.Printf("heartbeat failed: %v", err)
		return
	}
	if hb != nil {
		s.reconcileActiveClaims(hb.ActiveClaims)
		load = s.currentLoad()
	}

	s.refreshPendingOffers(hb)

	standaloneDrain := hb != nil && hb.Drain
	if s.pendingUpdate != nil && !s.pendingUpdate.Drain {
		// Force deploy offers apply in place; do not treat stale draining status as standalone drain.
		standaloneDrain = false
	}
	awaitingDeploy := s.pendingUpdate != nil || (hb != nil && hb.AwaitingDeploy)
	awaitingConfig := s.pendingConfig != nil

	if standaloneDrain && !awaitingDeploy && !awaitingConfig {
		s.draining = true
		if load > 0 {
			s.repoPool.Drain(ctx)
			s.releaseAllActiveClaims(ctx, "drain")
		}
		return
	}

	if !awaitingDeploy && !awaitingConfig {
		s.pendingUpdate = nil
		s.pendingConfig = nil
		if !standaloneDrain {
			s.draining = false
		}
		s.deployError = ""
		s.configError = ""
		return
	}

	// Binary update takes precedence over config rollout.
	if awaitingDeploy && s.pendingUpdate != nil {
		s.applyBinaryUpdate(ctx, load)
		return
	}
	if awaitingConfig && s.pendingConfig != nil {
		s.applyConfigUpdate(ctx, load)
	}
}

func (s *Scheduler) heartbeatParams(load, admitRejects int) api.HeartbeatParams {
	snap := s.telemetry.Sample()
	return api.HeartbeatParams{
		CurrentLoad:       load,
		Version:           version.Version,
		DeployError:       s.deployError,
		ProxmoxVmid:       s.cfg.Worker.ProxmoxVmid,
		ClaimAdmitRejects: admitRejects,
		ConfigVersion:     s.appliedConfigVersion,
		ConfigError:       s.configError,
		Telemetry: &api.TelemetryReport{
			CPUPct:        snap.CPUPct,
			CPUCoresUsed:  snap.CPUCoresUsed,
			MemUsedMiB:    snap.MemUsedMiB,
			MemTotalMiB:   snap.MemTotalMiB,
			DiskFreeMiB:   snap.DiskFreeMiB,
			DiskTotalMiB:  snap.DiskTotalMiB,
			RunDirFreeMiB: snap.RunDirFreeMiB,
			Goroutines:    snap.Goroutines,
			SampledAt:     snap.SampledAt.UTC().Format(time.RFC3339),
		},
	}
}

func (s *Scheduler) refreshPendingOffers(hb *api.HeartbeatResponse) {
	if hb == nil {
		return
	}
	if hb.Update != nil {
		s.pendingUpdate = hb.Update
	} else if !s.draining {
		s.pendingUpdate = nil
	}
	if hb.Config != nil && hb.Config.Version > s.appliedConfigVersion {
		s.pendingConfig = hb.Config
	} else if !s.draining {
		s.pendingConfig = nil
	}
}

func (s *Scheduler) applyBinaryUpdate(ctx context.Context, load int) {
	s.draining = true
	forceApply := s.pendingUpdate != nil && !s.pendingUpdate.Drain
	if load > 0 {
		if s.pendingUpdate != nil && s.pendingUpdate.Drain {
			log.Printf("update %s available; hand-off %d run(s) before apply", s.pendingUpdateVersion(), load)
			s.repoPool.Drain(ctx)
			s.releaseAllActiveClaims(ctx, "drain")
			return
		}
		if !forceApply {
			log.Printf("update %s available; draining %d run(s) before apply", s.pendingUpdateVersion(), load)
			return
		}
		log.Printf("update %s available; forcing apply with %d active run(s)", s.pendingUpdateVersion(), load)
	}
	if s.pendingUpdate == nil {
		return
	}
	s.repoPool.Drain(ctx)
	s.releaseAllActiveClaims(ctx, "")
	load = s.currentLoad()
	if load > 0 && !forceApply {
		return
	}
	log.Printf("applying update to version %s", s.pendingUpdate.Version)
	if err := updater.Apply(s.client.Token(), updater.OfferFromAPI(s.pendingUpdate), s.cfg.Worker.InstallPath); err != nil {
		log.Printf("update failed: %v", err)
		s.deployError = err.Error()
		_, _ = s.client.Heartbeat(ctx, s.heartbeatParams(0, 0))
		s.draining = false
		s.pendingUpdate = nil
		s.deployError = ""
	}
}

func (s *Scheduler) applyConfigUpdate(ctx context.Context, load int) {
	offer := s.pendingConfig
	if offer == nil {
		return
	}
	s.draining = true
	if load > 0 {
		log.Printf("config v%d available; hand-off %d run(s) before apply", offer.Version, load)
		s.repoPool.Drain(ctx)
		s.releaseAllActiveClaims(ctx, "drain")
		return
	}
	s.repoPool.Drain(ctx)
	s.releaseAllActiveClaims(ctx, "")
	load = s.currentLoad()
	if load > 0 {
		return
	}
	log.Printf("applying config version %d", offer.Version)
	raw, gotSHA, err := s.client.FetchConfig(ctx, offer.DownloadURL)
	if err != nil {
		s.reportConfigFailure(ctx, err)
		return
	}
	if !strings.EqualFold(gotSHA, offer.Sha256) {
		s.reportConfigFailure(ctx, fmt.Errorf("sha256 mismatch: got %s want %s", gotSHA, offer.Sha256))
		return
	}
	if err := configapply.Apply(s.configPath, offer.Version, offer.Sha256, raw); err != nil {
		s.reportConfigFailure(ctx, err)
		return
	}
	s.appliedConfigVersion = offer.Version
	s.configError = ""
	if err := updater.RestartSelf(s.cfg.Worker.InstallPath); err != nil {
		log.Printf("config applied but restart failed: %v", err)
		s.reportConfigFailure(ctx, err)
	}
}

func (s *Scheduler) reportConfigFailure(ctx context.Context, err error) {
	log.Printf("config apply failed: %v", err)
	s.configError = err.Error()
	s.draining = false
	s.pendingConfig = nil
	_, _ = s.client.Heartbeat(ctx, s.heartbeatParams(0, 0))
	s.configError = ""
}

func (s *Scheduler) pendingUpdateVersion() string {
	if s.pendingUpdate == nil {
		return "?"
	}
	return s.pendingUpdate.Version
}

func (s *Scheduler) poll(ctx context.Context) {
	if !s.hasDiskSpace() {
		log.Printf("skipping claim: RunDir free space below watermark")
		return
	}
	for s.availableSlots() > 0 {
		// A free run slot (by count) does not imply free resource budget: heavy
		// jobs reserve RAM/disk/CPU, so the worker can have idle slots yet no budget
		// to admit even a light job. If we claimed anyway we would immediately
		// canAdmit-reject and release, then loop and re-claim the same head-of-queue
		// job, spinning claim/release as fast as the network allows. Each cycle is
		// several committed writes on the control plane (queue + assignment + run
		// rows), which previously generated tens of thousands of release writes and
		// stalled the database. Skip claiming until a running job frees budget.
		if !s.canAdmit(&api.RunJob{PhysicalKey: "mailbox:_admit_probe"}) {
			break
		}
		job, err := s.client.Claim(ctx, s.claimHints())
		if err != nil {
			log.Printf("claim failed: %v", err)
			return
		}
		if job == nil {
			break
		}
		if !s.canAdmit(job) {
			s.recordAdmitReject()
			_ = s.client.Release(ctx, job.RunID, "")
			// Stop this poll cycle instead of re-claiming the same job: re-claiming
			// would return the same head-of-queue row and reject again. The next
			// poll tick retries once budget frees, bounding claim/release churn.
			break
		}
		if !s.tryStart(job) {
			s.recordAdmitReject()
			_ = s.client.Release(ctx, job.RunID, "")
			break
		}
		go func(j *api.RunJob) {
			defer s.done(j.RunID)
			runCtx, cancel := s.runContext(ctx, j)
			defer cancel()
			s.registerRunCancel(j.RunID, cancel)
			defer s.unregisterRunCancel(j.RunID)
			if err := s.runner.RunSafe(runCtx, j, cancel); err != nil {
				if isCooperativeCancel(err, runCtx) {
					log.Printf("run %s cancelled cooperatively", j.RunID)
					logCtx, logCancel := context.WithTimeout(context.WithoutCancel(ctx), 30*time.Second)
					s.client.RunLogf(logCtx, j.RunID, "info", "run stopped after cancellation")
					_ = s.client.FlushRunLogs(logCtx, j.RunID)
					logCancel()
					return
				}
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
		cpuCores = s.cfg.Worker.HeavyJobCPUCores
		if cpuCores <= 0 {
			cpuCores = 1
		}
	}
	return ramMiB, diskMiB, cpuCores
}

func (s *Scheduler) claimHints() map[string]any {
	probe := &api.RunJob{PhysicalKey: "site:_admit_probe"}
	return map[string]any{
		"accept_heavy": s.canAdmit(probe),
	}
}

func (s *Scheduler) recordAdmitReject() {
	s.admitRejectMu.Lock()
	s.admitRejects++
	s.admitRejectMu.Unlock()
}

func (s *Scheduler) resetAdmitRejects() int {
	s.admitRejectMu.Lock()
	defer s.admitRejectMu.Unlock()
	n := s.admitRejects
	s.admitRejects = 0
	return n
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

func (s *Scheduler) recordRunProgress(runID string, upd api.ProgressUpdate) {
	s.runProgress.Store(runID, upd)
}

func (s *Scheduler) flushProgressCheckpoint(ctx context.Context, runID string) {
	v, ok := s.runProgress.Load(runID)
	if !ok {
		return
	}
	upd, ok := v.(api.ProgressUpdate)
	if !ok || upd.RunID == "" {
		return
	}
	upd.RunID = runID
	if upd.Message == "" {
		upd.Message = "drain hand-off checkpoint"
	}
	flushCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), 15*time.Second)
	_, _, _ = s.client.Progress(flushCtx, upd)
	cancel()
}

func (s *Scheduler) releaseAllActiveClaims(ctx context.Context, reason string) {
	s.runningMu.Lock()
	runIDs := make([]string, 0, len(s.running))
	for id := range s.running {
		runIDs = append(runIDs, id)
	}
	s.runningMu.Unlock()
	for _, runID := range runIDs {
		s.cancelRun(runID)
		if reason == "drain" {
			s.flushProgressCheckpoint(ctx, runID)
		}
		if err := s.client.Release(ctx, runID, reason); err != nil {
			log.Printf("release %s before update: %v", runID, err)
		}
		s.runProgress.Delete(runID)
	}
}

func (s *Scheduler) registerRunCancel(runID string, cancel context.CancelFunc) {
	if runID == "" || cancel == nil {
		return
	}
	s.runCancelMu.Lock()
	s.runCancels[runID] = cancel
	s.runCancelMu.Unlock()
}

func (s *Scheduler) unregisterRunCancel(runID string) {
	if runID == "" {
		return
	}
	s.runCancelMu.Lock()
	delete(s.runCancels, runID)
	s.runCancelMu.Unlock()
}

func (s *Scheduler) cancelRun(runID string) {
	if runID == "" {
		return
	}
	s.runCancelMu.Lock()
	cancel, ok := s.runCancels[runID]
	s.runCancelMu.Unlock()
	if ok && cancel != nil {
		cancel()
	}
}

// reconcileActiveClaims drops in-memory runs the control plane no longer considers claimed.
func (s *Scheduler) reconcileActiveClaims(authorized []string) {
	if authorized == nil {
		return
	}
	allowed := make(map[string]struct{}, len(authorized))
	for _, id := range authorized {
		if id != "" {
			allowed[id] = struct{}{}
		}
	}

	s.runningMu.Lock()
	ghosts := make([]string, 0)
	for id := range s.running {
		if _, ok := allowed[id]; !ok {
			ghosts = append(ghosts, id)
		}
	}
	s.runningMu.Unlock()

	for _, runID := range ghosts {
		log.Printf("reconciling ghost run %s (no active queue claim)", runID)
		s.runCancelMu.Lock()
		_, hadCancel := s.runCancels[runID]
		s.runCancelMu.Unlock()
		s.cancelRun(runID)
		if !hadCancel {
			s.runningMu.Lock()
			_, stillRunning := s.running[runID]
			s.runningMu.Unlock()
			if stillRunning {
				s.done(runID)
			}
		}
	}
}
