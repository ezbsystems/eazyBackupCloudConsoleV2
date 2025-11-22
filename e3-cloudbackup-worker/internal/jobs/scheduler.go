package jobs

import (
	"context"
	"log"
	"sync"
	"time"

	"github.com/your-org/e3-cloudbackup-worker/internal/config"
	"github.com/your-org/e3-cloudbackup-worker/internal/db"
)

type Scheduler struct {
	db            *db.Database
	cfg           *config.Config
	runningMu     sync.Mutex
	running       map[int64]struct{}
	maxConcurrent int
	pollInterval  time.Duration
}

func NewScheduler(database *db.Database, cfg *config.Config) *Scheduler {
	return &Scheduler{
		db:            database,
		cfg:           cfg,
		running:       make(map[int64]struct{}),
		maxConcurrent: cfg.Worker.MaxConcurrentJobs,
		pollInterval:  cfg.PollInterval(),
	}
}

func (s *Scheduler) Run(ctx context.Context) error {
	ticker := time.NewTicker(s.pollInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-ticker.C:
			s.tick(ctx)
		}
	}
}

func (s *Scheduler) tick(ctx context.Context) {
	// Refresh global settings
	settings, err := s.db.GetAddonSettings(ctx)
	if err != nil {
		log.Printf("warning: failed to load addon settings: %v", err)
	}
	perWorker := s.availableSlots()
	globalAvail := perWorker
	if settings != nil && settings.GlobalMaxConcurrentJobs > 0 {
		runningCount, err := s.db.CountGlobalRunningRuns(ctx)
		if err != nil {
			log.Printf("warning: failed to count global running runs: %v", err)
		} else {
			ga := settings.GlobalMaxConcurrentJobs - runningCount
			if ga < 0 {
				ga = 0
			}
			if ga < globalAvail {
				globalAvail = ga
			}
		}
	}
	available := globalAvail
	if available <= 0 {
		return
	}
	runs, err := s.db.GetNextQueuedRuns(ctx, available)
	if err != nil {
		log.Printf("failed to fetch queued runs: %v", err)
		return
	}
	for _, r := range runs {
		if s.tryStart(r.ID) {
			go s.startRun(ctx, r)
		}
	}
}

func (s *Scheduler) availableSlots() int {
	s.runningMu.Lock()
	defer s.runningMu.Unlock()
	return s.maxConcurrent - len(s.running)
}

func (s *Scheduler) tryStart(runID int64) bool {
	s.runningMu.Lock()
	defer s.runningMu.Unlock()
	if _, exists := s.running[runID]; exists {
		return false
	}
	if len(s.running) >= s.maxConcurrent {
		return false
	}
	s.running[runID] = struct{}{}
	return true
}

func (s *Scheduler) done(runID int64) {
	s.runningMu.Lock()
	defer s.runningMu.Unlock()
	delete(s.running, runID)
}

func (s *Scheduler) startRun(ctx context.Context, r db.Run) {
	defer s.done(r.ID)
	runner := NewRunner(s.db, s.cfg)
	if err := runner.Run(ctx, r); err != nil {
		log.Printf("run %d failed: %v", r.ID, err)
	}
}
