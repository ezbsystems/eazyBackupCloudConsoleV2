package graph

import (
	"context"
	"log"
	"math/rand"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

const (
	tenantControllerFloor          = 1
	tenantShrinkDebounceMax        = 2 * time.Second
	tenantCooldownMax              = 600 * time.Second
	tenantIdleDecayAfter           = 5 * time.Minute
	tenantCeilingGrowBlockedWindow = 2 * time.Minute
	tenantCooldownJitterMax        = 500 * time.Millisecond
	defaultTenantControllerID      = "__default__"
	defaultTenantCeiling           = 16
)

var (
	tenantControllersMu sync.RWMutex
	tenantControllers   = make(map[string]*tenantController)
)

type tenantController struct {
	mu sync.Mutex

	cond       *sync.Cond
	limit      int
	inFlight   int
	ceiling    int
	floor      int
	successStreak int

	shrinkAllowedAfter time.Time
	cooldownUntil      time.Time
	lastActivity       time.Time
	last429            time.Time

	throttleWaiters atomic.Int32
}

func normalizeControllerTenantID(tenantID string) string {
	tenantID = strings.TrimSpace(tenantID)
	if tenantID == "" {
		return defaultTenantControllerID
	}
	return tenantID
}

func getTenantController(tenantID string) *tenantController {
	tenantID = normalizeControllerTenantID(tenantID)
	tenantControllersMu.RLock()
	tc, ok := tenantControllers[tenantID]
	tenantControllersMu.RUnlock()
	if ok {
		return tc
	}
	tenantControllersMu.Lock()
	defer tenantControllersMu.Unlock()
	if tc, ok = tenantControllers[tenantID]; ok {
		return tc
	}
	tc = newTenantController(defaultTenantCeiling)
	tenantControllers[tenantID] = tc
	return tc
}

func newTenantController(ceiling int) *tenantController {
	if ceiling <= 0 {
		ceiling = defaultTenantCeiling
	}
	tc := &tenantController{
		ceiling:      ceiling,
		limit:        conservativeStartLimit(ceiling),
		floor:        tenantControllerFloor,
		lastActivity: time.Now(),
	}
	tc.cond = sync.NewCond(&tc.mu)
	return tc
}

func conservativeStartLimit(ceiling int) int {
	if ceiling <= 1 {
		return 1
	}
	start := ceiling / 2
	if start < 2 {
		return 2
	}
	return start
}

// SetTenantCeiling sets the fleet budget ceiling for a tenant on this worker process.
func SetTenantCeiling(tenantID string, budget int) {
	tenantID = normalizeControllerTenantID(tenantID)
	if budget <= 0 {
		return
	}
	getTenantController(tenantID).setCeiling(budget)
}

// ResetTenantControllerForTest removes a tenant controller (tests only).
func ResetTenantControllerForTest(tenantID string) {
	tenantID = normalizeControllerTenantID(tenantID)
	tenantControllersMu.Lock()
	delete(tenantControllers, tenantID)
	tenantControllersMu.Unlock()
}

func (tc *tenantController) ensureAdaptiveLimit(ceiling int) {
	if ceiling <= 0 {
		return
	}
	tc.mu.Lock()
	defer tc.mu.Unlock()
	tc.touchActivityLocked()
	if tc.ceiling != defaultTenantCeiling {
		if tc.limit > tc.ceiling {
			tc.limit = tc.ceiling
			tc.cond.Broadcast()
		}
		return
	}
	oldStart := conservativeStartLimit(tc.ceiling)
	if ceiling < tc.ceiling {
		tc.ceiling = ceiling
	}
	if tc.inFlight == 0 && (tc.limit == oldStart || tc.limit > tc.ceiling) {
		tc.limit = conservativeStartLimit(tc.ceiling)
		if tc.limit > tc.ceiling {
			tc.limit = tc.ceiling
		}
	}
}

func (tc *tenantController) setCeiling(budget int) {
	if budget <= 0 {
		return
	}
	tc.mu.Lock()
	defer tc.mu.Unlock()
	tc.touchActivityLocked()
	if budget < tc.ceiling {
		tc.ceiling = budget
		if tc.limit > budget {
			tc.limit = budget
			if tc.limit < tc.floor {
				tc.limit = tc.floor
			}
			tc.cond.Broadcast()
		}
		return
	}
	if budget > tc.ceiling {
		if tc.recentlyThrottledLocked(time.Now()) {
			return
		}
		tc.ceiling = budget
	}
}

func (tc *tenantController) recentlyThrottledLocked(now time.Time) bool {
	if tc.last429.IsZero() {
		return false
	}
	return now.Sub(tc.last429) < tenantCeilingGrowBlockedWindow
}

func (tc *tenantController) touchActivityLocked() {
	tc.lastActivity = time.Now()
}

func (tc *tenantController) maybeIdleDecayLocked(now time.Time) {
	if tc.lastActivity.IsZero() || now.Sub(tc.lastActivity) < tenantIdleDecayAfter {
		return
	}
	tc.limit = conservativeStartLimit(tc.ceiling)
	tc.successStreak = 0
	tc.shrinkAllowedAfter = time.Time{}
	tc.lastActivity = now
	tc.cond.Broadcast()
}

func (tc *tenantController) acquire(ctx context.Context) error {
	for {
		tc.mu.Lock()
		now := time.Now()
		tc.maybeIdleDecayLocked(now)
		tc.touchActivityLocked()

		for tc.inFlight >= tc.limit || now.Before(tc.cooldownUntil) {
			if ctx.Err() != nil {
				tc.mu.Unlock()
				return ctx.Err()
			}
			if now.Before(tc.cooldownUntil) {
				wait := time.Until(tc.cooldownUntil)
				if wait > tenantCooldownMax {
					wait = tenantCooldownMax
				}
				wait += time.Duration(rand.Int63n(int64(tenantCooldownJitterMax)))
				tc.mu.Unlock()
				timer := time.NewTimer(wait)
				select {
				case <-ctx.Done():
					timer.Stop()
					return ctx.Err()
				case <-timer.C:
				}
				tc.mu.Lock()
				now = time.Now()
				tc.touchActivityLocked()
				continue
			}
			if err := tc.waitLocked(ctx); err != nil {
				tc.mu.Unlock()
				return err
			}
			now = time.Now()
		}
		tc.inFlight++
		tc.mu.Unlock()
		return nil
	}
}

func (tc *tenantController) waitLocked(ctx context.Context) error {
	if ctx.Err() != nil {
		return ctx.Err()
	}
	done := make(chan struct{})
	go func() {
		select {
		case <-ctx.Done():
			tc.mu.Lock()
			tc.cond.Broadcast()
			tc.mu.Unlock()
		case <-done:
		}
	}()
	tc.cond.Wait()
	close(done)
	return ctx.Err()
}

func (tc *tenantController) release() {
	tc.mu.Lock()
	tc.inFlight--
	if tc.inFlight < 0 {
		tc.inFlight = 0
	}
	tc.touchActivityLocked()
	tc.cond.Signal()
	tc.mu.Unlock()
}

func (tc *tenantController) record429(retryAfter time.Duration) {
	if retryAfter <= 0 {
		retryAfter = time.Second
	}
	if retryAfter > tenantCooldownMax {
		retryAfter = tenantCooldownMax
	}
	debounce := retryAfter
	if debounce > tenantShrinkDebounceMax {
		debounce = tenantShrinkDebounceMax
	}

	tc.mu.Lock()
	defer tc.mu.Unlock()
	now := time.Now()
	tc.touchActivityLocked()
	tc.last429 = now

	cooldown := retryAfter
	if cooldown > tenantCooldownMax {
		cooldown = tenantCooldownMax
	}
	until := now.Add(cooldown)
	if until.After(tc.cooldownUntil) {
		tc.cooldownUntil = until
	}

	if now.Before(tc.shrinkAllowedAfter) {
		return
	}
	tc.successStreak = 0
	if tc.limit > tc.floor {
		newLimit := int(float64(tc.limit) * adaptiveDecreaseRatio)
		if newLimit < tc.floor {
			newLimit = tc.floor
		}
		if newLimit >= tc.limit {
			newLimit = tc.limit - 1
		}
		if newLimit < tc.floor {
			newLimit = tc.floor
		}
		tc.limit = newLimit
	}
	tc.shrinkAllowedAfter = now.Add(debounce)
	tc.cond.Broadcast()
}

func (tc *tenantController) recordSuccess() {
	tc.mu.Lock()
	defer tc.mu.Unlock()
	tc.touchActivityLocked()
	tc.successStreak++
	if tc.successStreak < adaptiveSuccessStreak {
		return
	}
	tc.successStreak = 0
	if tc.limit >= tc.ceiling {
		return
	}
	tc.limit++
	tc.cond.Broadcast()
}

func (tc *tenantController) limitValue() int {
	tc.mu.Lock()
	defer tc.mu.Unlock()
	return tc.limit
}

func (tc *tenantController) throttleWaiting() bool {
	return tc.throttleWaiters.Load() > 0
}

func (tc *tenantController) beginThrottleWait() {
	tc.throttleWaiters.Add(1)
}

func (tc *tenantController) endThrottleWait() {
	tc.throttleWaiters.Add(-1)
}

type tenantControllerSnapshot struct {
	Limit      int
	Ceiling    int
	InFlight   int
	Last429    time.Time
	Cooldown   time.Time
}

func (tc *tenantController) snapshot() tenantControllerSnapshot {
	tc.mu.Lock()
	defer tc.mu.Unlock()
	return tenantControllerSnapshot{
		Limit:    tc.limit,
		Ceiling:  tc.ceiling,
		InFlight: tc.inFlight,
		Last429:  tc.last429,
		Cooldown: tc.cooldownUntil,
	}
}

// LogTenantControllerState emits a structured heartbeat line for fleet observability.
func LogTenantControllerState(tenantID string, requestsTotal, throttle429 int64) {
	if tenantID == "" {
		tenantID = defaultTenantControllerID
	}
	snap := getTenantController(tenantID).snapshot()
	ratio := 0.0
	if requestsTotal > 0 {
		ratio = float64(throttle429) / float64(requestsTotal)
	}
	log.Printf(
		"graph tenant controller tenant=%s limit=%d ceiling=%d in_flight=%d requests_total=%d throttle_429=%d ratio=%.4f",
		tenantID, snap.Limit, snap.Ceiling, snap.InFlight, requestsTotal, throttle429, ratio,
	)
}
