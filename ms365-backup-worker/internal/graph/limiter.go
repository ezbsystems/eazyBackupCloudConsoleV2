package graph

import (
	"context"
	"strings"
	"sync"
	"time"
)

var (
	globalLimitMu sync.RWMutex
	globalSem     chan struct{}

	tenantLimitMu sync.RWMutex
	tenantSem     = make(map[string]chan struct{})
	tenantBudget  = make(map[string]int)

	tenantCooldownMu    sync.Mutex
	tenantCooldownUntil = make(map[string]time.Time)

	tenantAdaptiveMu      sync.RWMutex
	tenantAdaptiveLimit   = make(map[string]int)
	tenantAdaptiveCeiling = make(map[string]int)
)

// tenantCooldownMax bounds a single parked cooldown window. Graph 429 Retry-After is
// capped at retryAfter429Cap (600s) in client.go before ParkTenantThrottle is called.
// Worker job leases default to 7200s and renew on progress heartbeats (~30s), so
// throttle waits cannot outlast a healthy lease while the run is still active.
const tenantCooldownMax = 600 * time.Second

// ParkTenantThrottle briefly pauses new Graph transport acquisitions for a tenant
// after a 429 Retry-After so concurrent requests do not thundering-herd the retry.
func ParkTenantThrottle(tenantID string, delay time.Duration) {
	tenantID = normalizeTenantID(tenantID)
	if tenantID == "" || delay <= 0 {
		return
	}
	if delay > tenantCooldownMax {
		delay = tenantCooldownMax
	}
	until := time.Now().Add(delay)
	tenantCooldownMu.Lock()
	if cur, ok := tenantCooldownUntil[tenantID]; !ok || until.After(cur) {
		tenantCooldownUntil[tenantID] = until
	}
	tenantCooldownMu.Unlock()
}

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

// SetTenantBudget sets the per-Entra-tenant in-flight Graph request cap for this worker process.
func SetTenantBudget(tenantID string, budget int) {
	tenantID = normalizeTenantID(tenantID)
	if tenantID == "" || budget <= 0 {
		return
	}
	tenantLimitMu.Lock()
	if cur, ok := tenantBudget[tenantID]; ok && cur == budget {
		tenantLimitMu.Unlock()
		return
	}
	tenantBudget[tenantID] = budget
	tenantSem[tenantID] = make(chan struct{}, budget)
	tenantLimitMu.Unlock()

	tenantAdaptiveMu.Lock()
	tenantAdaptiveCeiling[tenantID] = budget
	if cur, ok := tenantAdaptiveLimit[tenantID]; ok && cur > budget {
		tenantAdaptiveLimit[tenantID] = budget
	}
	tenantAdaptiveMu.Unlock()
}

func tenantAdaptiveSeed(tenantID string) (learned, ceiling int, ok bool) {
	tenantID = normalizeTenantID(tenantID)
	if tenantID == "" {
		return 0, 0, false
	}
	tenantAdaptiveMu.RLock()
	defer tenantAdaptiveMu.RUnlock()
	learned, hasLearned := tenantAdaptiveLimit[tenantID]
	ceiling, hasCeiling := tenantAdaptiveCeiling[tenantID]
	if !hasLearned && !hasCeiling {
		return 0, 0, false
	}
	if !hasLearned {
		learned = 0
	}
	if !hasCeiling {
		ceiling = 0
	}
	return learned, ceiling, true
}

func persistTenantAdaptiveLimit(tenantID string, limit int) {
	tenantID = normalizeTenantID(tenantID)
	if tenantID == "" || limit <= 0 {
		return
	}
	tenantAdaptiveMu.Lock()
	tenantAdaptiveLimit[tenantID] = limit
	tenantAdaptiveMu.Unlock()
}

func normalizeTenantID(tenantID string) string {
	return strings.TrimSpace(tenantID)
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

func acquireTenant(ctx context.Context, tenantID string) error {
	tenantID = normalizeTenantID(tenantID)
	if tenantID == "" {
		return nil
	}
	for {
		tenantCooldownMu.Lock()
		until, cooling := tenantCooldownUntil[tenantID]
		tenantCooldownMu.Unlock()
		if !cooling || time.Now().After(until) {
			if cooling {
				tenantCooldownMu.Lock()
				delete(tenantCooldownUntil, tenantID)
				tenantCooldownMu.Unlock()
			}
			break
		}
		wait := time.Until(until)
		if wait > tenantCooldownMax {
			wait = tenantCooldownMax
		}
		timer := time.NewTimer(wait)
		select {
		case <-ctx.Done():
			timer.Stop()
			return ctx.Err()
		case <-timer.C:
		}
	}
	tenantLimitMu.RLock()
	sem := tenantSem[tenantID]
	tenantLimitMu.RUnlock()
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

func releaseTenant(tenantID string) {
	tenantID = normalizeTenantID(tenantID)
	if tenantID == "" {
		return
	}
	tenantLimitMu.RLock()
	sem := tenantSem[tenantID]
	tenantLimitMu.RUnlock()
	if sem == nil {
		return
	}
	<-sem
}
