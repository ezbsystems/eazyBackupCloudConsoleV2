package jobs

import (
	"context"
	"sync/atomic"
	"testing"
	"time"
)

func TestGraphStallWatchCancelsOnNoProgress(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var items int32
	var hits int64
	stop := StartGraphStallWatch(ctx, cancel, GraphProgressSnapshot{
		ItemsDone:    func() int { return int(atomic.LoadInt32(&items)) },
		BytesTotal:   func() int64 { return 0 },
		ThrottleHits: func() int64 { return atomic.LoadInt64(&hits) },
	}, GraphStallWatchConfig{
		StallSeconds:         2,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
		RunID:                "test-run",
	})
	defer stop()

	select {
	case <-ctx.Done():
	case <-time.After(5 * time.Second):
		t.Fatal("expected graph stall watchdog to cancel")
	}
}

func TestGraphStallWatchIgnores429Activity(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var items int32
	var hits int64
	stop := StartGraphStallWatch(ctx, cancel, GraphProgressSnapshot{
		ItemsDone:    func() int { return int(atomic.LoadInt32(&items)) },
		BytesTotal:   func() int64 { return 0 },
		ThrottleHits: func() int64 { return atomic.LoadInt64(&hits) },
	}, GraphStallWatchConfig{
		StallSeconds:         3,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
		RunID:                "test-run",
	})
	defer stop()

	for i := 0; i < 4; i++ {
		atomic.AddInt64(&hits, 1)
		time.Sleep(1100 * time.Millisecond)
	}
	if ctx.Err() != nil {
		t.Fatalf("429 activity should count as progress, got %v", ctx.Err())
	}
}

func TestGraphStallWatchCancelsOnThrottleCeilingDespite429s(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var hits int64
	stop := StartGraphStallWatch(ctx, cancel, GraphProgressSnapshot{
		ItemsDone:    func() int { return 0 },
		BytesTotal:   func() int64 { return 0 },
		ThrottleHits: func() int64 { return atomic.LoadInt64(&hits) },
	}, GraphStallWatchConfig{
		StallSeconds:                30,
		ThrottleStallCeilingSeconds: 2,
		CheckIntervalSeconds:        1,
		GraceSeconds:                0,
		RunID:                       "test-run",
	})
	defer stop()

	for i := 0; i < 4; i++ {
		atomic.AddInt64(&hits, 1)
		time.Sleep(1100 * time.Millisecond)
	}

	select {
	case <-ctx.Done():
	case <-time.After(6 * time.Second):
		t.Fatal("expected throttle ceiling to cancel despite 429 activity")
	}
}
