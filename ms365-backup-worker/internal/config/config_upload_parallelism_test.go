package config

import "testing"

func TestResolvedParallelUploadsDefaults(t *testing.T) {
	k := KopiaConfig{}
	if got := k.ResolvedParallelUploadsOverlayMax(); got != 64 {
		t.Fatalf("overlay max = %d want 64", got)
	}
	if got := k.ResolvedParallelUploadsSmallMax(); got != 16 {
		t.Fatalf("small max = %d want 16", got)
	}
	if got := k.ResolvedParallelUploadsSmallAvgBytes(); got != 262144 {
		t.Fatalf("small avg = %d want 262144", got)
	}
}

func TestResolvedParallelUploadsExplicitZeroDisables(t *testing.T) {
	zero := 0
	k := KopiaConfig{
		ParallelUploadsOverlayMax:    &zero,
		ParallelUploadsSmallMax:        &zero,
		ParallelUploadsSmallAvgBytes:   &zero,
	}
	if got := k.ResolvedParallelUploadsOverlayMax(); got != 0 {
		t.Fatalf("overlay max = %d want 0", got)
	}
	if got := k.ResolvedParallelUploadsSmallMax(); got != 0 {
		t.Fatalf("small max = %d want 0", got)
	}
	if got := k.ResolvedParallelUploadsSmallAvgBytes(); got != 0 {
		t.Fatalf("small avg = %d want 0", got)
	}
}
