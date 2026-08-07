package graphfs

import (
	"testing"
	"time"
)

func TestContentThroughputPolicyEnabled(t *testing.T) {
	policy := ContentThroughputPolicy{
		MinBytesPerSecond: 65536,
		WindowSeconds:     300,
		MinFileSizeBytes:  16 << 20,
	}
	if !policy.Enabled(16 << 20) {
		t.Fatal("expected guard enabled at threshold size")
	}
	if policy.Enabled((16 << 20) - 1) {
		t.Fatal("expected guard disabled below threshold size")
	}
	disabled := ContentThroughputPolicy{MinBytesPerSecond: 0, WindowSeconds: 300, MinFileSizeBytes: 16 << 20}
	if disabled.Enabled(32 << 20) {
		t.Fatal("expected zero min bps to disable guard")
	}
}

func TestThroughputWindowDetectsSlowStream(t *testing.T) {
	now := time.Unix(0, 0)
	clock := func() time.Time { return now }
	policy := ContentThroughputPolicy{
		MinBytesPerSecond: 65536,
		WindowSeconds:     5,
		MinFileSizeBytes:  1,
	}
	w := newThroughputWindow(policy, clock)

	// Advance through a 5s window with only 1 KiB total (< 64 KiB/s).
	for i := 0; i < 5; i++ {
		now = now.Add(time.Second)
		if w.add(200) {
			t.Fatalf("unexpected stall at second %d", i+1)
		}
	}
	now = now.Add(time.Second)
	if !w.add(200) {
		t.Fatal("expected stall after slow window")
	}
}

func TestThroughputWindowHealthyStream(t *testing.T) {
	now := time.Unix(0, 0)
	clock := func() time.Time { return now }
	policy := ContentThroughputPolicy{
		MinBytesPerSecond: 65536,
		WindowSeconds:     5,
		MinFileSizeBytes:  1,
	}
	w := newThroughputWindow(policy, clock)

	for i := 0; i < 6; i++ {
		now = now.Add(time.Second)
		if w.add(128 << 10) {
			t.Fatalf("unexpected stall at second %d", i+1)
		}
	}
}
