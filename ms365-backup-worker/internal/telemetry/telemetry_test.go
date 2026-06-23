package telemetry

import (
	"testing"
	"time"
)

func TestParseMeminfoKB(t *testing.T) {
	got := parseMeminfoKB("MemTotal:       16384000 kB")
	if got != 16384000 {
		t.Fatalf("parseMeminfoKB = %d, want 16384000", got)
	}
}

func TestReadProcMeminfo(t *testing.T) {
	used, total := readProcMeminfo()
	if total <= 0 {
		t.Fatalf("expected positive MemTotal from /proc/meminfo, got used=%d total=%d", used, total)
	}
	if used < 0 || used > total {
		t.Fatalf("used MiB out of range: used=%d total=%d", used, total)
	}
}

func TestStatfsMiBRoot(t *testing.T) {
	free, total := statfsMiB("/")
	if total <= 0 {
		t.Fatalf("expected positive root disk total, got free=%d total=%d", free, total)
	}
	if free < 0 || free > total {
		t.Fatalf("free MiB out of range: free=%d total=%d", free, total)
	}
}

func TestCollectorFirstSampleZeroCPU(t *testing.T) {
	c := NewCollector("/tmp", 4)
	snap := c.Sample()
	if snap.CPUCoresUsed != 0 || snap.CPUPct != 0 {
		t.Fatalf("first CPU sample should be zero, got pct=%v cores=%v", snap.CPUPct, snap.CPUCoresUsed)
	}
	if snap.Goroutines <= 0 {
		t.Fatalf("expected positive goroutine count, got %d", snap.Goroutines)
	}
	if snap.SampledAt.IsZero() {
		t.Fatal("expected sampled_at to be set")
	}
}

func TestCollectorSecondSampleNonNegativeCPU(t *testing.T) {
	c := NewCollector("/tmp", 4)
	_ = c.Sample()
	time.Sleep(10 * time.Millisecond)
	snap := c.Sample()
	if snap.CPUCoresUsed < 0 || snap.CPUPct < 0 {
		t.Fatalf("negative CPU metrics: pct=%v cores=%v", snap.CPUPct, snap.CPUCoresUsed)
	}
}
