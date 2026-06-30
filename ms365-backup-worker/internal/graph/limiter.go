package graph

import (
	"context"
	"sync"
	"time"
)

var (
	globalLimitMu sync.RWMutex
	globalSem     *countingSem
)

type countingSem struct {
	mu    sync.Mutex
	cap   int
	inUse int
}

func newCountingSem(cap int) *countingSem {
	return &countingSem{cap: cap}
}

// SetGlobalConcurrency caps in-flight Graph HTTP requests across all runs on this worker.
func SetGlobalConcurrency(max int) {
	globalLimitMu.Lock()
	defer globalLimitMu.Unlock()
	if max <= 0 {
		globalSem = nil
		return
	}
	globalSem = newCountingSem(max)
}

func acquireGlobal(ctx context.Context) error {
	globalLimitMu.RLock()
	sem := globalSem
	globalLimitMu.RUnlock()
	if sem == nil {
		return nil
	}
	return sem.acquire(ctx)
}

func releaseGlobal() {
	globalLimitMu.RLock()
	sem := globalSem
	globalLimitMu.RUnlock()
	if sem == nil {
		return
	}
	sem.release()
}

const globalAcquirePoll = 50 * time.Millisecond

func (s *countingSem) acquire(ctx context.Context) error {
	for {
		if err := ctx.Err(); err != nil {
			return err
		}
		s.mu.Lock()
		if s.inUse < s.cap {
			s.inUse++
			s.mu.Unlock()
			return nil
		}
		s.mu.Unlock()
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(globalAcquirePoll):
		}
	}
}

func (s *countingSem) release() {
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.inUse > 0 {
		s.inUse--
	}
}

// GlobalSemStats reports global transport semaphore occupancy.
func GlobalSemStats() (inUse, capacity int) {
	globalLimitMu.RLock()
	sem := globalSem
	globalLimitMu.RUnlock()
	if sem == nil {
		return 0, 0
	}
	sem.mu.Lock()
	defer sem.mu.Unlock()
	return sem.inUse, sem.cap
}
