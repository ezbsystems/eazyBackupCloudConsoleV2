package jobs

import (
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func graphClientOptions(cfg *config.Config, maxConcurrency int, adaptive bool) graph.ClientOptions {
	g := cfg.Graph
	return graph.ClientOptions{
		MaxRetries:                   g.MaxRetries,
		RetryBaseDelayMs:             g.RetryBaseDelayMs,
		MaxConcurrency:               maxConcurrency,
		AdaptiveLimit:                adaptive,
		ContentReadIdleSeconds:       g.ResolvedContentReadIdleSeconds(),
		ContentReadRetries:           g.ResolvedContentReadRetries(),
		StreamResponseHeaderSeconds:  g.ResolvedStreamResponseHeaderSeconds(),
		ContentReadMinBytesPerSecond: g.ResolvedContentReadMinBytesPerSecond(),
		ContentReadMinWindowSeconds:  g.ResolvedContentReadMinWindowSeconds(),
		ContentReadMinFileSizeBytes:  int64(g.ResolvedContentReadMinFileSizeMiB()) << 20,
	}
}
