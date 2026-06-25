package graph

import (
	"context"
	"sync"
	"testing"
	"time"
)

func TestTenantControllerLimitsConcurrency(t *testing.T) {
	const tenantID = "tenant-a"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 2)

	ctx := context.Background()
	tc := getTenantController(tenantID)
	if err := tc.acquire(ctx); err != nil {
		t.Fatalf("first acquire: %v", err)
	}
	if err := tc.acquire(ctx); err != nil {
		t.Fatalf("second acquire: %v", err)
	}

	acquired := make(chan struct{}, 1)
	go func() {
		_ = tc.acquire(ctx)
		close(acquired)
	}()

	select {
	case <-acquired:
		t.Fatal("third acquire should block until release")
	case <-time.After(50 * time.Millisecond):
	}

	tc.release()
	select {
	case <-acquired:
	case <-time.After(time.Second):
		t.Fatal("third acquire should succeed after release")
	}
	tc.release()
	tc.release()
}

// TestTenantControllerSuccessStreakStarvedBySporadic429 documents the root cause
// of the live "limit pinned at 1" crawl: when a 429 lands before the shared
// success streak reaches its threshold, success-based additive increase never
// fires, so the limit cannot climb off the floor under a steady trickle of 429s.
func TestTenantControllerSuccessStreakStarvedBySporadic429(t *testing.T) {
	tc := newTenantController(16)
	tc.mu.Lock()
	tc.limit = 1
	tc.mu.Unlock()

	for round := 0; round < 30; round++ {
		for i := 0; i < adaptiveSuccessStreak-1; i++ {
			tc.recordSuccess()
		}
		// A 429 arrives just before the streak would have triggered a grow.
		tc.mu.Lock()
		tc.shrinkAllowedAfter = time.Time{}
		tc.mu.Unlock()
		tc.record429(time.Second)
	}

	if got := tc.limitValue(); got != 1 {
		t.Fatalf("success-streak recovery should be starved and stay at floor, got %d", got)
	}
}

// TestTenantControllerTimeBasedRecoveryFromFloor verifies the fix: throttle-free
// elapsed time grows the limit back toward the ceiling even when the success
// streak is unavailable.
func TestTenantControllerTimeBasedRecoveryFromFloor(t *testing.T) {
	prev := tenantGrowInterval
	tenantGrowInterval = 5 * time.Second
	defer func() { tenantGrowInterval = prev }()

	tc := newTenantController(16)
	tc.mu.Lock()
	defer tc.mu.Unlock()
	tc.limit = 1
	tc.last429 = time.Time{}
	tc.cooldownUntil = time.Time{}
	tc.nextGrowAt = time.Time{}

	base := time.Unix(1_700_000_000, 0)
	tc.maybeGrowLocked(base) // first observation arms the recovery clock, no grow
	if tc.limit != 1 {
		t.Fatalf("first observation must not grow, got %d", tc.limit)
	}
	for i := 1; i <= 8; i++ {
		tc.maybeGrowLocked(base.Add(time.Duration(i) * tenantGrowInterval))
	}
	if tc.limit < 8 {
		t.Fatalf("expected time-based recovery toward ceiling, got %d", tc.limit)
	}
}

// TestTenantControllerGrowBlockedDuringThrottleWindow ensures recovery does not
// fight active throttling: no growth within the quiet window after a 429.
func TestTenantControllerGrowBlockedDuringThrottleWindow(t *testing.T) {
	prev := tenantGrowInterval
	tenantGrowInterval = 5 * time.Second
	defer func() { tenantGrowInterval = prev }()

	tc := newTenantController(16)
	tc.mu.Lock()
	defer tc.mu.Unlock()
	tc.limit = 1
	tc.cooldownUntil = time.Time{}
	base := time.Unix(1_700_000_000, 0)
	tc.last429 = base

	tc.maybeGrowLocked(base.Add(1 * time.Second))
	tc.maybeGrowLocked(base.Add(2 * time.Second))
	if tc.limit != 1 {
		t.Fatalf("limit must not grow within throttle quiet window, got %d", tc.limit)
	}
}

func TestTenantControllerProportionalShrinkAcrossWaves(t *testing.T) {
	const tenantID = "tenant-shrink"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 16)
	tc := getTenantController(tenantID)
	tc.ensureAdaptiveLimit(16)

	if got := tc.limitValue(); got != 8 {
		t.Fatalf("initial limit=%d want 8", got)
	}
	tc.record429(500 * time.Millisecond)
	if got := tc.limitValue(); got != 4 {
		t.Fatalf("after first wave limit=%d want 4", got)
	}
	tc.record429(500 * time.Millisecond)
	if got := tc.limitValue(); got != 4 {
		t.Fatalf("concurrent burst should debounce shrink, got %d", got)
	}
	time.Sleep(600 * time.Millisecond)
	tc.record429(500 * time.Millisecond)
	if got := tc.limitValue(); got != 2 {
		t.Fatalf("after second wave limit=%d want 2", got)
	}
}

func TestTenantControllerSlotHeldDuring429Sleep(t *testing.T) {
	const tenantID = "tenant-slot"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 1)
	tc := getTenantController(tenantID)

	ctx := context.Background()
	if err := tc.acquire(ctx); err != nil {
		t.Fatalf("acquire: %v", err)
	}

	sleeping := make(chan struct{})
	go func() {
		tc.beginThrottleWait()
		time.Sleep(300 * time.Millisecond)
		tc.endThrottleWait()
		close(sleeping)
	}()

	acquired := make(chan error, 1)
	go func() {
		acquired <- tc.acquire(ctx)
	}()

	select {
	case err := <-acquired:
		if err != nil {
			t.Fatalf("unexpected acquire during sleep: %v", err)
		}
		t.Fatal("second acquire should block while slot held during 429 sleep")
	case <-time.After(100 * time.Millisecond):
	}

	<-sleeping
	tc.release()

	select {
	case err := <-acquired:
		if err != nil {
			t.Fatalf("acquire after release: %v", err)
		}
	case <-time.After(time.Second):
		t.Fatal("second acquire should succeed after first slot released")
	}
	tc.release()
}

func TestTenantControllerJitteredRecovery(t *testing.T) {
	const tenantID = "tenant-jitter"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 4)
	tc := getTenantController(tenantID)

	tc.mu.Lock()
	tc.cooldownUntil = time.Now().Add(200 * time.Millisecond)
	tc.mu.Unlock()

	ctx := context.Background()
	const waiters = 6
	var wg sync.WaitGroup
	wakeTimes := make([]time.Time, waiters)
	start := time.Now()
	for i := 0; i < waiters; i++ {
		wg.Add(1)
		go func(idx int) {
			defer wg.Done()
			_ = tc.acquire(ctx)
			wakeTimes[idx] = time.Now()
			tc.release()
		}(i)
	}
	wg.Wait()
	elapsed := time.Since(start)
	if elapsed < 150*time.Millisecond {
		t.Fatalf("expected cooldown wait, elapsed=%v", elapsed)
	}
	minWake, maxWake := wakeTimes[0], wakeTimes[0]
	for _, w := range wakeTimes {
		if w.Before(minWake) {
			minWake = w
		}
		if w.After(maxWake) {
			maxWake = w
		}
	}
	spread := maxWake.Sub(minWake)
	if spread < 10*time.Millisecond {
		t.Fatalf("expected staggered recovery, spread=%v", spread)
	}
}

func TestTenantControllerIdleDecay(t *testing.T) {
	const tenantID = "tenant-idle"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 8)
	tc := getTenantController(tenantID)

	tc.mu.Lock()
	tc.limit = 6
	tc.successStreak = 5
	tc.lastActivity = time.Now().Add(-tenantIdleDecayAfter - time.Second)
	tc.mu.Unlock()

	ctx := context.Background()
	if err := tc.acquire(ctx); err != nil {
		t.Fatalf("acquire: %v", err)
	}
	tc.release()

	if got := tc.limitValue(); got != 4 {
		t.Fatalf("after idle decay limit=%d want 4 (ceiling/2)", got)
	}
}

func TestTenantControllerCeilingLowerImmediately(t *testing.T) {
	const tenantID = "tenant-ceiling"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 8)
	tc := getTenantController(tenantID)
	tc.mu.Lock()
	tc.limit = 6
	tc.mu.Unlock()

	SetTenantCeiling(tenantID, 3)
	if got := tc.limitValue(); got != 3 {
		t.Fatalf("limit=%d want immediate lower to 3", got)
	}
}

func TestTenantControllerNoGrowCeilingWhileThrottled(t *testing.T) {
	const tenantID = "tenant-no-grow"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 4)
	tc := getTenantController(tenantID)
	tc.record429(time.Second)

	SetTenantCeiling(tenantID, 8)
	snap := tc.snapshot()
	if snap.Ceiling != 4 {
		t.Fatalf("ceiling should not grow while recently throttled, got %d", snap.Ceiling)
	}

	tc.mu.Lock()
	tc.last429 = time.Now().Add(-tenantCeilingGrowBlockedWindow - time.Second)
	tc.mu.Unlock()
	SetTenantCeiling(tenantID, 8)
	snap = tc.snapshot()
	if snap.Ceiling != 8 {
		t.Fatalf("ceiling=%d want 8 after throttle window", snap.Ceiling)
	}
}

func TestTenantControllerSharedAcrossClients(t *testing.T) {
	const tenantID = "tenant-shared"
	ResetTenantControllerForTest(tenantID)
	SetTenantCeiling(tenantID, 2)

	c1 := NewClient("token", "", ClientOptions{MaxConcurrency: 8, AdaptiveLimit: true})
	c1.SetAzureTenantID(tenantID)
	c2 := NewClient("token", "", ClientOptions{MaxConcurrency: 8, AdaptiveLimit: true})
	c2.SetAzureTenantID(tenantID)

	ctx := context.Background()
	if err := c1.acquireWorkload(ctx); err != nil {
		t.Fatalf("c1 acquire: %v", err)
	}
	if err := c2.acquireWorkload(ctx); err != nil {
		t.Fatalf("c2 acquire: %v", err)
	}

	acquired := make(chan struct{}, 1)
	go func() {
		_ = c1.acquireWorkload(ctx)
		close(acquired)
	}()
	select {
	case <-acquired:
		t.Fatal("shared window should block third acquire")
	case <-time.After(50 * time.Millisecond):
	}

	c1.releaseWorkload()
	select {
	case <-acquired:
	case <-time.After(time.Second):
		t.Fatal("third acquire should succeed after release")
	}
	c1.releaseWorkload()
	c2.releaseWorkload()
}

func TestSetTenantCeilingNoOpForInvalid(t *testing.T) {
	SetTenantCeiling("", 10)
	SetTenantCeiling("tenant-b", 0)
	tc := getTenantController("tenant-b")
	if tc.limitValue() != conservativeStartLimit(defaultTenantCeiling) {
		t.Fatalf("zero budget should not change default controller start")
	}
	ResetTenantControllerForTest("tenant-b")
}

func TestSetTenantCeilingConcurrentUpdate(t *testing.T) {
	const tenantID = "tenant-c"
	ResetTenantControllerForTest(tenantID)
	var wg sync.WaitGroup
	for i := 0; i < 20; i++ {
		wg.Add(1)
		go func(n int) {
			defer wg.Done()
			SetTenantCeiling(tenantID, 4+(n%3))
		}(i)
	}
	wg.Wait()
	snap := getTenantController(tenantID).snapshot()
	if snap.Ceiling < 4 || snap.Ceiling > 6 {
		t.Fatalf("unexpected ceiling %d", snap.Ceiling)
	}
	ResetTenantControllerForTest(tenantID)
}
