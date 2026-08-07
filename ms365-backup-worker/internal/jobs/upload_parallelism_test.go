package jobs

import (
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestResolveUploadParallelismOverlayHeavyListsWhale(t *testing.T) {
	got := ResolveUploadParallelism(UploadParallelismInput{
		BaseParallel: 6,
		OverlayMax:   64,
		SmallMax:     16,
		SmallAvgBytes: 262144,
		ItemCount:    85835,
		BytesTotal:   85835 * 348,
		Overlay: graphfs.EntryStats{
			StaticFiles: 85835,
			StaticBytes: 85835 * 348,
		},
	})
	if got.Mode != UploadParallelismOverlay {
		t.Fatalf("mode=%q want overlay", got.Mode)
	}
	if got.Parallel < 32 {
		t.Fatalf("parallel=%d want high overlay parallelism", got.Parallel)
	}
	if got.Parallel > 64 {
		t.Fatalf("parallel=%d exceeds overlay max", got.Parallel)
	}
}

func TestResolveUploadParallelismHealthyLargeFilesBaseline(t *testing.T) {
	got := ResolveUploadParallelism(UploadParallelismInput{
		BaseParallel: 8,
		OverlayMax:   64,
		SmallMax:     16,
		SmallAvgBytes: 262144,
		ItemCount:    1000,
		BytesTotal:   10 << 30,
		Overlay: graphfs.EntryStats{
			GraphFiles: 1000,
			GraphBytes: 10 << 30,
		},
		TenantHeadroom: 12,
		GlobalHeadroom: 24,
	})
	if got.Mode != UploadParallelismBaseline {
		t.Fatalf("mode=%q want baseline", got.Mode)
	}
	if got.Parallel != 8 {
		t.Fatalf("parallel=%d want 8", got.Parallel)
	}
}

func TestResolveUploadParallelismGraphSmallClampedByHeadroom(t *testing.T) {
	got := ResolveUploadParallelism(UploadParallelismInput{
		BaseParallel: 6,
		OverlayMax:   64,
		SmallMax:     16,
		SmallAvgBytes: 262144,
		ItemCount:    5000,
		BytesTotal:   5000 * 4096,
		Overlay: graphfs.EntryStats{
			GraphFiles: 5000,
			GraphBytes: 5000 * 4096,
		},
		TenantHeadroom:       4,
		GlobalHeadroom:       8,
		ActiveSiblingUploads: 2,
	})
	if got.Mode != UploadParallelismGraphSmall {
		t.Fatalf("mode=%q want graph_small", got.Mode)
	}
	if got.Parallel != 6 {
		t.Fatalf("parallel=%d want 6 (headroom 4/2 siblings, floored at base)", got.Parallel)
	}
}

func TestResolveUploadParallelismDisabledOverlayMax(t *testing.T) {
	got := ResolveUploadParallelism(UploadParallelismInput{
		BaseParallel: 6,
		OverlayMax:   0,
		SmallMax:     16,
		SmallAvgBytes: 262144,
		ItemCount:    85835,
		BytesTotal:   85835 * 348,
		Overlay: graphfs.EntryStats{
			StaticFiles: 85835,
			StaticBytes: 85835 * 348,
		},
	})
	if got.Mode != UploadParallelismBaseline {
		t.Fatalf("mode=%q want baseline when overlay max disabled", got.Mode)
	}
	if got.Parallel != 6 {
		t.Fatalf("parallel=%d want 6", got.Parallel)
	}
}

func TestResolveUploadParallelismGraphSmallRaisesTowardMax(t *testing.T) {
	got := ResolveUploadParallelism(UploadParallelismInput{
		BaseParallel: 6,
		OverlayMax:   64,
		SmallMax:     16,
		SmallAvgBytes: 262144,
		ItemCount:    2000,
		BytesTotal:   2000 * 8192,
		Overlay: graphfs.EntryStats{
			GraphFiles: 2000,
			GraphBytes: 2000 * 8192,
		},
		TenantHeadroom: 20,
		GlobalHeadroom: 20,
	})
	if got.Mode != UploadParallelismGraphSmall {
		t.Fatalf("mode=%q want graph_small", got.Mode)
	}
	if got.Parallel != 16 {
		t.Fatalf("parallel=%d want 16", got.Parallel)
	}
}
