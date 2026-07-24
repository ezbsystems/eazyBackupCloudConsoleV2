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

func TestProgressCounterUpdatesLastUploadAt(t *testing.T) {
	counter := NewProgressCounter(nil)
	before := counter.SecondsSinceLastUpload()
	if before < 0 {
		t.Fatalf("expected initial upload timestamp, got %d", before)
	}
	time.Sleep(20 * time.Millisecond)
	counter.UploadedBytes(2048)
	after := counter.SecondsSinceLastUpload()
	if after < 0 || after > 2 {
		t.Fatalf("expected recent upload timestamp, got %d", after)
	}
	if counter.BytesUploaded.Load() != 2048 {
		t.Fatalf("bytes uploaded = %d", counter.BytesUploaded.Load())
	}
	snap := counter.DebugSnapshot()
	if snap["seconds_since_last_upload"] == nil {
		t.Fatal("expected seconds_since_last_upload in debug snapshot")
	}
}

func TestStallWatchCancelsOnIdle(t *testing.T) {
	counter := NewProgressCounter(nil)
	stale := time.Now().Add(-10 * time.Second).UnixNano()
	counter.lastHashAt.Store(stale)
	counter.lastUploadAt.Store(stale)
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

func TestStallWatchCancelsOnPostHashUploadStall(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.FinishedHashingFile("done.txt", 4096)
	stale := time.Now().Add(-10 * time.Second).UnixNano()
	counter.lastHashAt.Store(stale)
	counter.lastUploadAt.Store(stale)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stalled := false
	stop := StartStallWatch(ctx, cancel, counter, StallWatchConfig{
		StallSeconds:         1,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
		OnStall: func(snapshot map[string]any) {
			stalled = true
			if snapshot["bytes_uploaded"].(int64) != 0 {
				t.Fatalf("expected zero bytes uploaded in wedge, got %v", snapshot["bytes_uploaded"])
			}
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
		t.Fatal("expected stall watch to cancel on post-hash upload wedge")
	}
	if !stalled {
		t.Fatal("expected OnStall callback for upload wedge")
	}
}

func TestStallWatchTailPhaseCancelsOnUploadStallOnly(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.FilesTotal.Store(10)
	counter.FilesDone.Store(10)
	counter.lastHashAt.Store(time.Now().UnixNano())
	stale := time.Now().Add(-10 * time.Second).UnixNano()
	counter.lastUploadAt.Store(stale)

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
		t.Fatal("expected tail-phase OR stall when upload flat")
	}
	if !stalled {
		t.Fatal("expected OnStall callback for tail upload stall")
	}
}

func TestStallWatchMidUploadDoesNotCancelOnSingleSideStall(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.FilesTotal.Store(100)
	counter.FilesDone.Store(50)
	counter.lastHashAt.Store(time.Now().UnixNano())
	counter.lastUploadAt.Store(time.Now().Add(-10 * time.Second).UnixNano())

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stop := StartStallWatch(ctx, cancel, counter, StallWatchConfig{
		StallSeconds:         5,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
	})
	defer stop()

	time.Sleep(3 * time.Second)
	if ctx.Err() != nil {
		t.Fatal("mid-upload should require both hash and upload stalls")
	}
}

func TestStallWatchDoesNotCancelWhenUploadActive(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.lastHashAt.Store(time.Now().Add(-10 * time.Second).UnixNano())
	counter.FinishedHashingFile("done.txt", 4096)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stop := StartStallWatch(ctx, cancel, counter, StallWatchConfig{
		StallSeconds:         1,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
	})
	defer stop()

	go func() {
		ticker := time.NewTicker(200 * time.Millisecond)
		defer ticker.Stop()
		for range ticker.C {
			if ctx.Err() != nil {
				return
			}
			counter.UploadedBytes(512)
		}
	}()

	time.Sleep(3 * time.Second)
	if ctx.Err() != nil {
		t.Fatal("expected stall watch to stay alive while upload bytes move")
	}
}

func TestStallWatchTailPhaseUsesReportedItemsTotal(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.FilesTotal.Store(0)
	counter.FilesDone.Store(10)
	counter.lastHashAt.Store(time.Now().UnixNano())
	stale := time.Now().Add(-10 * time.Second).UnixNano()
	counter.lastUploadAt.Store(stale)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stalled := false
	stop := StartStallWatch(ctx, cancel, counter, StallWatchConfig{
		StallSeconds:         1,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
		ReportedItemsTotal:   10,
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
		t.Fatal("expected tail-phase OR stall when FilesTotal=0 but ReportedItemsTotal reached")
	}
	if !stalled {
		t.Fatal("expected OnStall callback for reported-items tail phase")
	}
}

func TestStallWatchMidUploadIgnoresHighReportedItemsTotal(t *testing.T) {
	counter := NewProgressCounter(nil)
	counter.FilesTotal.Store(100)
	counter.FilesDone.Store(50)
	counter.lastHashAt.Store(time.Now().UnixNano())
	counter.lastUploadAt.Store(time.Now().Add(-10 * time.Second).UnixNano())

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	stop := StartStallWatch(ctx, cancel, counter, StallWatchConfig{
		StallSeconds:         5,
		CheckIntervalSeconds: 1,
		GraceSeconds:         0,
		ReportedItemsTotal:   200,
	})
	defer stop()

	time.Sleep(3 * time.Second)
	if ctx.Err() != nil {
		t.Fatal("mid-upload should require both hash and upload stalls even when ReportedItemsTotal is high")
	}
}
