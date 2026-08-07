package config

import "testing"

func TestResolvedContentReadThroughputDefaults(t *testing.T) {
	g := GraphConfig{}
	if got := g.ResolvedContentReadMinBytesPerSecond(); got != 65536 {
		t.Fatalf("default min bps = %d want 65536", got)
	}
	if got := g.ResolvedContentReadMinWindowSeconds(); got != 300 {
		t.Fatalf("default window = %d want 300", got)
	}
	if got := g.ResolvedContentReadMinFileSizeMiB(); got != 16 {
		t.Fatalf("default min file mib = %d want 16", got)
	}
}

func TestResolvedContentReadThroughputExplicitZeroDisables(t *testing.T) {
	zero := 0
	g := GraphConfig{
		ContentReadMinBytesPerSecond: &zero,
		ContentReadMinWindowSeconds:  &zero,
		ContentReadMinFileSizeMiB:    &zero,
	}
	if got := g.ResolvedContentReadMinBytesPerSecond(); got != 0 {
		t.Fatalf("explicit zero min bps = %d want 0", got)
	}
	if got := g.ResolvedContentReadMinWindowSeconds(); got != 0 {
		t.Fatalf("explicit zero window = %d want 0", got)
	}
	if got := g.ResolvedContentReadMinFileSizeMiB(); got != 0 {
		t.Fatalf("explicit zero min file mib = %d want 0", got)
	}
}

func TestResolvedContentReadThroughputCustomValues(t *testing.T) {
	bps := 32768
	window := 120
	mib := 8
	g := GraphConfig{
		ContentReadMinBytesPerSecond: &bps,
		ContentReadMinWindowSeconds:  &window,
		ContentReadMinFileSizeMiB:    &mib,
	}
	if got := g.ResolvedContentReadMinBytesPerSecond(); got != 32768 {
		t.Fatalf("custom min bps = %d want 32768", got)
	}
	if got := g.ResolvedContentReadMinWindowSeconds(); got != 120 {
		t.Fatalf("custom window = %d want 120", got)
	}
	if got := g.ResolvedContentReadMinFileSizeMiB(); got != 8 {
		t.Fatalf("custom min file mib = %d want 8", got)
	}
}
