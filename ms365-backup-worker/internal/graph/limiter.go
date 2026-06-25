package graph

import (
	"context"
	"sync"
)

var (
	globalLimitMu sync.RWMutex
	globalSem     chan struct{}
)

// SetGlobalConcurrency caps in-flight Graph HTTP requests across all runs on this worker.
func SetGlobalConcurrency(max int) {
	globalLimitMu.Lock()
	defer globalLimitMu.Unlock()
	if max <= 0 {
		globalSem = nil
		return
	}
	globalSem = make(chan struct{}, max)
}

func acquireGlobal(ctx context.Context) error {
	globalLimitMu.RLock()
	sem := globalSem
	globalLimitMu.RUnlock()
	if sem == nil {
		return nil
	}
	select {
	case sem <- struct{}{}:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
}

func releaseGlobal() {
	globalLimitMu.RLock()
	sem := globalSem
	globalLimitMu.RUnlock()
	if sem == nil {
		return
	}
	<-sem
}

// #region agent log
// GlobalSemStats reports the global transport semaphore occupancy (debug only).
func GlobalSemStats() (inUse, capacity int) {
	globalLimitMu.RLock()
	sem := globalSem
	globalLimitMu.RUnlock()
	if sem == nil {
		return 0, 0
	}
	return len(sem), cap(sem)
}

// #endregion
