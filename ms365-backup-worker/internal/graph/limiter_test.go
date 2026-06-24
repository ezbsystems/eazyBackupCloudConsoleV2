package graph

import (
	"context"
	"testing"
	"time"
)

func TestGlobalConcurrencyLimitsAcquire(t *testing.T) {
	SetGlobalConcurrency(2)
	defer SetGlobalConcurrency(0)

	ctx := context.Background()
	if err := acquireGlobal(ctx); err != nil {
		t.Fatalf("first acquire: %v", err)
	}
	if err := acquireGlobal(ctx); err != nil {
		t.Fatalf("second acquire: %v", err)
	}

	acquired := make(chan struct{}, 1)
	go func() {
		_ = acquireGlobal(ctx)
		close(acquired)
	}()

	select {
	case <-acquired:
		t.Fatal("third global acquire should block")
	case <-time.After(50 * time.Millisecond):
	}

	releaseGlobal()
	select {
	case <-acquired:
	case <-time.After(time.Second):
		t.Fatal("third global acquire should succeed after release")
	}
	releaseGlobal()
	releaseGlobal()
}
