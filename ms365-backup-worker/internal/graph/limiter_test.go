package graph

import (
	"context"
	"sync"
	"testing"
	"time"
)

func TestTenantBudgetLimitsConcurrency(t *testing.T) {
	SetTenantBudget("tenant-a", 2)
	defer func() {
		tenantLimitMu.Lock()
		delete(tenantSem, "tenant-a")
		delete(tenantBudget, "tenant-a")
		tenantLimitMu.Unlock()
	}()

	ctx := context.Background()
	if err := acquireTenant(ctx, "tenant-a"); err != nil {
		t.Fatalf("first acquire: %v", err)
	}
	if err := acquireTenant(ctx, "tenant-a"); err != nil {
		t.Fatalf("second acquire: %v", err)
	}

	acquired := make(chan struct{}, 1)
	go func() {
		_ = acquireTenant(ctx, "tenant-a")
		close(acquired)
	}()

	select {
	case <-acquired:
		t.Fatal("third acquire should block until release")
	case <-time.After(50 * time.Millisecond):
	}

	releaseTenant("tenant-a")
	select {
	case <-acquired:
	case <-time.After(time.Second):
		t.Fatal("third acquire should succeed after release")
	}
	releaseTenant("tenant-a")
	releaseTenant("tenant-a")
}

func TestSetTenantBudgetNoOpForInvalid(t *testing.T) {
	SetTenantBudget("", 10)
	SetTenantBudget("tenant-b", 0)
	tenantLimitMu.RLock()
	_, ok := tenantSem["tenant-b"]
	tenantLimitMu.RUnlock()
	if ok {
		t.Fatal("zero budget should not create tenant semaphore")
	}
}

func TestTenantCooldownDelaysAcquire(t *testing.T) {
	SetTenantBudget("tenant-cooldown", 2)
	defer func() {
		tenantLimitMu.Lock()
		delete(tenantSem, "tenant-cooldown")
		delete(tenantBudget, "tenant-cooldown")
		tenantLimitMu.Unlock()
		tenantCooldownMu.Lock()
		delete(tenantCooldownUntil, "tenant-cooldown")
		tenantCooldownMu.Unlock()
	}()

	ParkTenantThrottle("tenant-cooldown", 200*time.Millisecond)
	start := time.Now()
	ctx := context.Background()
	if err := acquireTenant(ctx, "tenant-cooldown"); err != nil {
		t.Fatalf("acquire: %v", err)
	}
	elapsed := time.Since(start)
	releaseTenant("tenant-cooldown")
	if elapsed < 150*time.Millisecond {
		t.Fatalf("expected cooldown wait, elapsed=%v", elapsed)
	}
}

func TestTenantCooldownCapped(t *testing.T) {
	tenantCooldownMu.Lock()
	delete(tenantCooldownUntil, "tenant-cap")
	tenantCooldownMu.Unlock()

	ParkTenantThrottle("tenant-cap", 2*time.Hour)
	tenantCooldownMu.Lock()
	until := tenantCooldownUntil["tenant-cap"]
	tenantCooldownMu.Unlock()

	remaining := time.Until(until)
	if remaining > tenantCooldownMax+time.Second {
		t.Fatalf("cooldown until too far in future: %v (max %v)", remaining, tenantCooldownMax)
	}
	if remaining < tenantCooldownMax-time.Second {
		t.Fatalf("cooldown until too soon: %v (want ~%v)", remaining, tenantCooldownMax)
	}

	tenantCooldownMu.Lock()
	delete(tenantCooldownUntil, "tenant-cap")
	tenantCooldownMu.Unlock()
}

func TestSetTenantBudgetConcurrentUpdate(t *testing.T) {
	var wg sync.WaitGroup
	for i := 0; i < 20; i++ {
		wg.Add(1)
		go func(n int) {
			defer wg.Done()
			SetTenantBudget("tenant-c", 4+(n%3))
		}(i)
	}
	wg.Wait()
	tenantLimitMu.RLock()
	budget := tenantBudget["tenant-c"]
	tenantLimitMu.RUnlock()
	if budget < 4 || budget > 6 {
		t.Fatalf("unexpected budget %d", budget)
	}
	tenantLimitMu.Lock()
	delete(tenantSem, "tenant-c")
	delete(tenantBudget, "tenant-c")
	tenantLimitMu.Unlock()
}
