package jobs

import (
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

const (
	uploadParallelismOverlayAvgBytes = 65536 // 64 KiB
	uploadParallelismStaticDominance = 0.95
)

// UploadParallelismMode names the scaler decision path.
type UploadParallelismMode string

const (
	UploadParallelismBaseline   UploadParallelismMode = "baseline"
	UploadParallelismOverlay    UploadParallelismMode = "overlay"
	UploadParallelismGraphSmall UploadParallelismMode = "graph_small"
)

// UploadParallelismInput carries workload shape and limiter headroom for scaling.
type UploadParallelismInput struct {
	BaseParallel         int
	OverlayMax           int
	SmallMax             int
	SmallAvgBytes        int64
	ItemCount            int
	BytesTotal           int64
	Overlay              graphfs.EntryStats
	TenantHeadroom       int
	GlobalHeadroom       int
	ActiveSiblingUploads int
}

// UploadParallelismResult is the chosen Kopia ParallelUploads value and diagnostics.
type UploadParallelismResult struct {
	Parallel  int
	Mode      UploadParallelismMode
	AvgBytes  int64
	Reason    string
}

// ResolveUploadParallelism selects Kopia upload parallelism from overlay shape and Graph headroom.
func ResolveUploadParallelism(in UploadParallelismInput) UploadParallelismResult {
	base := in.BaseParallel
	if base <= 0 {
		base = 4
	}
	avgBytes := averageBytes(in.BytesTotal, in.ItemCount)
	if overlayFiles := in.Overlay.TotalFiles(); overlayFiles > 0 {
		avgBytes = averageBytes(in.Overlay.TotalBytes(), overlayFiles)
	}
	result := UploadParallelismResult{
		Parallel: base,
		Mode:     UploadParallelismBaseline,
		AvgBytes: avgBytes,
		Reason:   "baseline",
	}

	staticRatio := in.Overlay.StaticRatio()
	overlayHeavy := in.Overlay.GraphFiles == 0 ||
		(staticRatio >= uploadParallelismStaticDominance && avgBytes < uploadParallelismOverlayAvgBytes)
	if overlayHeavy && avgBytes < uploadParallelismOverlayAvgBytes && in.OverlayMax > 0 {
		parallel := overlayParallelFromItemCount(base, in.OverlayMax, in.ItemCount)
		result.Parallel = parallel
		result.Mode = UploadParallelismOverlay
		result.Reason = fmt.Sprintf(
			"overlay-heavy static_ratio=%.2f avg_bytes=%d items=%d",
			staticRatio, avgBytes, in.ItemCount,
		)
		return result
	}

	if in.Overlay.GraphFiles > 0 && avgBytes < in.SmallAvgBytes && in.SmallMax > 0 {
		headroom := in.TenantHeadroom
		if in.GlobalHeadroom > 0 && in.GlobalHeadroom < headroom {
			headroom = in.GlobalHeadroom
		}
		siblings := in.ActiveSiblingUploads
		if siblings < 1 {
			siblings = 1
		}
		if headroom > 0 {
			headroom = headroom / siblings
		}
		if headroom < 1 {
			headroom = 1
		}
		parallel := in.SmallMax
		if headroom < parallel {
			parallel = headroom
		}
		if parallel < base {
			parallel = base
		}
		result.Parallel = parallel
		result.Mode = UploadParallelismGraphSmall
		result.Reason = fmt.Sprintf(
			"graph-small avg_bytes=%d graph_files=%d headroom=%d siblings=%d",
			avgBytes, in.Overlay.GraphFiles, headroom, siblings,
		)
		return result
	}

	return result
}

func averageBytes(total int64, count int) int64 {
	if count <= 0 {
		return 0
	}
	return total / int64(count)
}

func overlayParallelFromItemCount(base, overlayMax, itemCount int) int {
	if overlayMax <= 0 {
		return base
	}
	scaled := base
	if itemCount > 0 {
		scaled = itemCount / 1500
		if scaled < base {
			scaled = base
		}
	}
	if scaled > overlayMax {
		scaled = overlayMax
	}
	return scaled
}
