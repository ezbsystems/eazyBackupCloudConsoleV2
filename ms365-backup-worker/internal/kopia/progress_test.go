package kopia

import (
	"context"
	"testing"
	"time"
)

func TestProgressCounterUpdatesLastHashAt(t *testing.T) {
	counter := NewProgressCounter(nil)
	before := counter.SecondsSinceLastHash()
	if before < 0 {
		t.Fatalf("expected initial hash timestamp, got %d", before)
	}
	time.Sleep(20 * time.Millisecond)
	counter.FinishedHashingFile("test.txt", 1024)
	after := counter.SecondsSinceLastHash()
	if after < 0 || after > 2 {
		t.Fatalf("expected recent hash timestamp, got %d", after)
	}
	if counter.BytesHashed.Load() != 1024 {
		t.Fatalf("bytes hashed = %d", counter.BytesHashed.Load())
	}
}

func TestStallWatchCancelsOnIdle(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.lastHashAt.Store(time.Now().Add(-10 * time.Second).UnixNano())
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stalled := false
	stop := StartStallWatch(ctx, cancel, counter, StallWatchConfig{
		StallSeconds:         1,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
		OnStall: func(snapshot map[string]any) {
			stalled = true
		},
	})
	defer stop()

	deadline := time.Now().Add(4 * time.Second)
	for time.Now().Before(deadline) {
		if ctx.Err() != nil {
			break
		}
		time.Sleep(50 * time.Millisecond)
	}
	if ctx.Err() == nil {
		t.Fatal("expected stall watch to cancel context")
	}
	if !stalled {
		t.Fatal("expected OnStall callback")
	}
}
